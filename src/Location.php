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


    public function __construct(AdapterInterface $adapter, string $revisionFile, string $repoPath = null)
    {
        $this->filesystem = new MountManager([
            'local'  => new Filesystem(new Local('.')),
            'remote' => new Filesystem($adapter)
        ]);
        $this->revisionFile = $revisionFile;
        $this->git = new Git($repoPath);
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
    public function setRevision(string $revision = null)
    {
        $revision = $revision ?? $this->git->getLastCommit();

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
    public function getModifiedFiles(string $revision = null):array
    {
        $revision = $revision ?? $this->git->getLastCommit();
        $diff = $this->git->getModifiedFilesBetweenRevisions($this->getRevision(), $revision);
        return $diff;
    }

    /**
     * @param string|null $revision
     */
    public function uploadRevision(string $revision = null)
    {
        $revision = $revision ?? $this->git->getLastCommit();
        $diff = $this->getModifiedFiles($revision);

        foreach ($diff['added'] as $file) {
            $this->uploadFile('local://' . $file, 'remote://' . $file);
        }
        foreach ($diff['modified'] as $file) {
            $this->uploadFile('local://' . $file, 'remote://' . $file);
        }
        foreach ($diff['deleted'] as $file) {
            $this->filesystem->delete('remote://' . $file);
        }
        $this->setRevision($revision);
    }

    private function uploadFile($local, $remote)
    {
        if ($this->filesystem->has($remote)) {
            $content = $this->filesystem->read($local);
            $this->filesystem->update($remote, $content);
        } else {
            $this->filesystem->copy($local, $remote);
        }
    }

}
