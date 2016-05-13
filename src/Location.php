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
            $this->revision = $this->filesystem->read('remote://' . $this->revisionFile);
        }
        return $this->revision;
    }

    /**
     * @param string $revision
     */
    public function setRevision(string $revision)
    {
        $revFile = 'remote://' . $this->revisionFile;
        if ($this->filesystem->has($revFile)) {
            $this->filesystem->update($revFile, $revision);
        } else {
            $this->filesystem->write($revFile, $revision);
        }
        $this->revision = $revision;
    }

    /**
     * @return array
     */
    public function getModifiedFiles($revision = null):array
    {
        $revision = $revision ?? $this->git->getLastCommit();
        $diff = $this->git->getModifiedFilesBetweenRevisions($this->getRevision(), $revision);
        return $diff;
    }

    public function uploadRevision($revision = null)
    {
        $revision = $revision ?? $this->git->getLastCommit();
        $diff = $this->getModifiedFiles($revision);

        foreach ($diff['added'] as $file) {
            $this->filesystem->copy('local://' . $file, 'remote://' . $file);
        }
        foreach ($diff['modified'] as $file) {
            $this->filesystem->copy('local://' . $file, 'remote://' . $file);
        }
        foreach ($diff['deleted'] as $file) {
            $this->filesystem->delete('remote://' . $file);
        }
        $this->setRevision($revision);
    }

}
