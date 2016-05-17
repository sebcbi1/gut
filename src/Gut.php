<?php

namespace Gut;

class Gut
{
    const REVISION_FILE = '.revision';

    private $config = [
        'revision_file' => self::REVISION_FILE,
    ];

    /**
     * @var Location[]
     */
    private $locations = [];
    
    /**
     * @var Git
     */
    private $git;

    public function __construct($config = [])
    {

        $this->config = array_merge($this->config, ($config ?? []));

        if (empty($this->config['locations'])) {
            throw new Exception('no locations set.');
        }

        $this->git = new Git();

        foreach ($this->config['locations'] as $locationName => $location) {
            $this->locations[$locationName] = new Location(AdapterFactory::create($location), $this->config, $this->git);
        }

    }

    public function git()
    {
        return $this->git;
    }

    public function getLocations(): array 
    {
        return $this->locations;
    }

    public function setLocation($locationName)
    {
        if ($location = $this->getLocation($locationName)) {
            $this->locations = [[$locationName => $location]];
        }
    }

    public function getLocation(string $locationName):Location
    {
        if (isset($this->locations[$locationName])) {
            return $this->locations[$locationName];
        }
        throw new Exception("Location '$locationName' not found");
    }

    public function uploadCommit($rev = 'HEAD')
    {
        $branch = $this->checkoutCommit($rev);

        foreach ($this->locations as $locationName => $location) {
            try {
                $location->uploadRevision($rev);
            } catch (Exception $e) {
                $this->term->error("$locationName: error - " . $e->getMessage());
                continue;
            }
        }
        $this->checkoutHead($branch);

    }

    public function checkoutCommit($rev)
    {
        $this->git->stash();
        $branch = $this->git->getCurrentBranch();
        try {
            $this->git->checkout($rev);
        } catch (Exception $e) {
            $this->git->stashPop();
            throw new Exception($e->getMessage());
        }
        return $branch;
    }

    public function checkoutHead($branch)
    {
        $this->git->checkout($branch);
        $this->git->stashPop();
    }

    public function init(string $revision = null)
    {
        foreach ($this->locations as $locationName => $location) {
            $location->setRevision($revision);
        }
    }

    public function uploadFolder($folder)
    {
        foreach ($this->locations as $locationName => $location) {
            $location->uploadFolder($folder);
        }
    }

    public function uploadNotCommitedFiles($files = [])
    {
        foreach ($this->locations as $locationName => $location) {
            $location->uploadNotCommitedFiles($files);
        }
    }

    public function cleanNotCommitedFiles()
    {
        $this->git->stash();
        foreach ($this->locations as $locationName => $location) {
            $location->cleanNotCommitedFiles();
        }
        $this->git->stashPop();

    }

    public function getNotCommitedFiles()
    {
        return $this->git->getUncommitedFiles();
    }



}
