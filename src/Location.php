<?php

namespace Gut;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use League\Flysystem\Adapter\Local;


class Location
{
    /**
     * @var string
     */
    private $revision;

    /**
     * @var string
     */
    private $revisionFile;

    /**
     * @var MountManager
     */
    private $filesystem;

    /**
     * @var Git
     */
    private $git;

    /**
     * @var array
     */
    private $skipFilePatterns;


    public function __construct(AdapterInterface $adapter, array $config, Git $git)
    {
        $this->filesystem = new MountManager([
            'local'  => new Filesystem(new Local('.')),
            'remote' => new Filesystem($adapter)
        ]);
        $this->revisionFile = $config['revision_file'];
        $this->dirtyFile = $this->revisionFile . '-dirty';
        $this->skipFilePatterns = $config['skip'] ?? [];
        $this->git = $git;
    }

    public function getFileSystem():MountManager
    {
        return $this->filesystem;
    }

    /**
     * @return string
     */
    public function getRevision():string
    {
        if (empty($this->revision)) {
            $this->revision = trim($this->filesystem->read('remote://' . $this->revisionFile));
        }
        return $this->revision;
    }

    /**
     * @param string|null $revision
     */
    public function setRevision(string $revision = 'HEAD')
    {
        $revision = $this->git->revParse($revision);

        $revFile = 'remote://' . $this->revisionFile;
        if ($this->filesystem->has($revFile)) {
            $this->filesystem->update($revFile, $revision);
        } else {
            $this->filesystem->write($revFile, $revision);
        }
        $this->revision = $revision;
    }

    /**
     * @param string|null $revision
     * @return array
     * @throws Exception
     */
    public function getModifiedFiles($revision = 'HEAD'):array
    {
        $diff = $this->git->getModifiedFilesBetweenRevisions($this->getRevision(), $this->git->revParse($revision));
        foreach ($diff as $status => $files) {
            foreach ($files as $k => $file) {
                if ($this->skip($file)) {
                    unset($files[$k]);
                }
            }
            $diff[$status] = array_filter($files);
        }
        return $diff;
    }

    /**
     * @param string|null $revision
     * @return \Generator
     * @throws Exception
     */
    public function uploadFiles($revision = 'HEAD')
    {
        $revision =  $this->git->revParse($revision);
        $diff = $this->getModifiedFiles($revision);
        foreach ($diff['added'] as $file) {
            $this->uploadFile('local://' . $file, 'remote://' . $file);
            yield $file;
        }
        foreach ($diff['modified'] as $file) {
            $this->uploadFile('local://' . $file, 'remote://' . $file);
            yield $file;
        }
        foreach ($diff['deleted'] as $file) {
            if ($this->filesystem->has('remote://' . $file)) {
                $this->filesystem->delete('remote://' . $file);
            }
            yield $file;
        }
        $this->setRevision($revision);
    }

    public function uploadRevision($revision = 'HEAD') {
        foreach ($this->uploadFiles($revision) as $file) {
            //do nothing
        }
    }

    public function uploadFile($local, $remote)
    {
        if ($this->filesystem->has($remote)) {
            $content = $this->filesystem->read($local);
            $this->filesystem->update($remote, $content);
        } else {
            $this->filesystem->copy($local, $remote);
        }
    }

    public function uploadFolder($folder)
    {
        $new = '-' . substr(md5(microtime().rand(0,10000)), 0, 7);
        if ($this->filesystem->copy('local://' . $folder, 'remote://' . $folder.$new)) {
            $old = '-bak-' . date('YmdHis');
            if ($this->filesystem->has('remote://' . $folder)) {
                $this->filesystem->move('remote://' . $folder, 'remote://' . $folder.$old);
            }
            $this->filesystem->move('remote://' . $folder.$new, 'remote://' . $folder);
        }
    }

    public function uploadNotCommitedFiles($files)
    {
        foreach ($files as $file) {
            $this->uploadFile('local://'.$file, 'remote://'.$file);
        }

        $dirty = 'remote://' . $this->dirtyFile;
        if ($this->filesystem->has($dirty)) {
            $oldNotCommitedFiles = $this->filesystem->read($dirty);
            $oldNotCommitedFiles = explode("\n", $oldNotCommitedFiles);
            $files = array_unique(array_merge($oldNotCommitedFiles, $files));
        }

        $content = implode("\n", $files);

        if ($this->filesystem->has($dirty)) {
            $this->filesystem->update($dirty, $content);
        } else {
            $this->filesystem->write($dirty, $content);
        }

    }

    public function cleanNotCommitedFiles()
    {
        $dirty = 'remote://' . $this->dirtyFile;
        if ($this->filesystem->has($dirty)) {
            $dirtyFiles = $this->filesystem->read($dirty);
            $dirtyFiles = explode("\n", $dirtyFiles);
            foreach ($dirtyFiles as $file) {
                if ($this->filesystem->has('local://'.$file)) {
                    $this->uploadFile('local://'.$file, 'remote://'.$file);
                } else {
                    $this->filesystem->delete('remote://'.$file);
                }
            }
            $this->filesystem->delete($dirty);
        }
    }

    private function skip($file)
    {
        foreach ($this->skipFilePatterns as $pattern) {
            if (preg_match('#'.$pattern.'#', $file)) {
                return true;
            }
        }
        return false;
    }


}
