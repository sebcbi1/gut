<?php

namespace Gut;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;

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

    public function __construct(AdapterInterface $adapter, string $revisionFile)
    {
        $this->filesystem = new Filesystem($adapter);
        $this->revisionFile = $revisionFile;
        $this->git = new Git();
    }

    /**
     * @return string
     */
    public function getRevision():string
    {
        if (empty($this->revision)) {
            $this->revision = $this->filesystem->read($this->revisionFile);
        }
        return $this->revision;
    }

    /**
     * @param string $revision
     */
    public function setRevision(string $revision)
    {
        $this->filesystem->write($this->revisionFile, $revision);
        $this->revision = $revision;
    }

    public function getModifiedFiles()
    {
        $lastCommit = $this->git->getLastCommit();
        $diff = $this->git->getModifiedFilesBetweenRevisions($this->getRevision(), $lastCommit);
        return $diff;
    }


}