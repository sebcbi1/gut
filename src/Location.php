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

    /**
     * @var array
     */
    private $purgeFolders;

    public function __construct(AdapterInterface $adapter, array $config, Git $git)
    {
        $this->filesystem = new MountManager([
            'local'  => new Filesystem(new Local('.')),
            'remote' => new Filesystem($adapter)
        ]);
        $this->revisionFile = $config['revision_file'];
        $this->dirtyFile = $this->revisionFile . '-dirty';

        $this->skipFilePatterns = $config['skip'] ?? [];
        if (is_string($this->skipFilePatterns)) {
            $this->skipFilePatterns = [$this->skipFilePatterns];
        }

        $this->purgeFolders = $config['purge'] ?? [];
        if (is_string($this->purgeFolders)) {
            $this->purgeFolders = [$this->purgeFolders];
        }

        $this->purgeFoldersConditions = $config['purge_condition'] ?? [];
        if (is_string($this->purgeFoldersConditions)) {
            $this->purgeFoldersConditions = [$this->purgeFoldersConditions];
        }

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
        return $this->filter($diff);
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

    public function uploadRevision(string $revision = 'HEAD') {
        foreach ($this->uploadFiles($revision) as $file) {
            //do nothing
        }
    }

    public function uploadFile(string $local, string $remote)
    {
        if ($this->filesystem->has($remote)) {
            $content = $this->filesystem->read($local);
            $this->filesystem->update($remote, $content);
        } else {
            $this->filesystem->copy($local, $remote);
        }
    }

    public function uploadFolder(string $folder)
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

    public function uploadNotCommitedFiles(array $files)
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

    private function filter(array $diff):array
    {
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

    private function skip(string $file):bool
    {
        foreach ($this->skipFilePatterns as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }
        return false;
    }

    public function purge()
    {
        foreach ($this->purgeFolders as $folder) {
            $this->purgeFolder($folder);
        }
    }

    private function purgeFolder($folder)
    {
        if ($this->filesystem->has('remote://' . $folder)) {
            $contents = $this->filesystem->listContents('remote://' . $folder, true);
            foreach ($contents as $content) {
                if ($content['type'] == 'file') {
                    $this->filesystem->delete('remote://' . $content['path']);
                } elseif ($content['type'] == 'dir') {
                    $this->filesystem->deleteDir('remote://' . $content['path']);
                }
            }
        }
    }

    public function conditionalPurge($modifiedFiles = [])
    {
        foreach ($this->purgeFoldersConditions as $pattern) {
            foreach ($modifiedFiles as $file) {
                if (fnmatch($pattern, $file)) {
                    return true;
                }
            }
        }
        return false;
    }

}
