<?php

namespace Gut;

use Symfony\Component\Yaml\Yaml;

class Gut
{
    const VERSION = '0.9.0';
    const CONFIG_FILENAME = '.gut.yml';
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

    public function __construct($config = self::CONFIG_FILENAME)
    {
        if (is_string($config)) {
            if (is_file($config)) {
                $config = Yaml::parse(file_get_contents($config));
            } else {
                $this->term->error(self::CONFIG_FILENAME . ' is missing.');
            }
        }
        $this->config = array_merge($this->config, ($config ?? []));
        $this->config['revision_file_dirty'] = $this->config['revision_file'] . '-dirty';

        if (empty($this->config['locations'])) {
            throw new Exception('no locations set.');
        }

        $this->git = new Git();

        foreach ($this->config['locations'] as $locationName => $location) {
            $this->locations[$locationName] = new Location(AdapterFactory::create($location), $this->config['revision_file'], $this->git);
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
            $this->stashPop();
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


    public function rollback(string $revision = '')
    {
        $this->uploadCommit('HEAD^');
    }

    public function uploadFolder()
    {
//        $this->term->out('folder');
    }

    public function dirty()
    {
//        $this->term->out('dirty');
    }

    public function cleanDirty()
    {
//        $this->term->out('clean');
    }



}
