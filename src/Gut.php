<?php

namespace Gut;

use League\CLImate\CLImate;
use League\Flysystem\FileNotFoundException;
use Symfony\Component\Yaml\Yaml;

class Gut
{
    const VERSION = '0.9.0';
    const CONFIG_FILENAME = './gut.yml';
    const REVISION_FILE = '.revision';

    private $config = [
        'revision_file' => self::REVISION_FILE,
    ];

    private $locations = [];


    public function __construct($config = self::CONFIG_FILENAME)
    {
        $this->term = new CLImate();
        $this->git = new Git();
        $this->configure($config);
    }

    private function configure($config)
    {
        if (is_string($config) && is_file($config)) {
            $config = Yaml::parse(file_get_contents($config));
        }
        $this->config = array_merge($this->config, $config);
        $this->config['revision_file_dirty'] = $this->config['revision_file'] . '-dirty';

        if (empty($this->config['locations'])) {
            $this->term->error('no locations set.');
            die(-1);
        }

        foreach ($this->config['locations'] as $locationName => $location) {
            $adapter = AdapterFactory::create($location);
            if ($adapter) {
                $this->locations[$locationName] = new Location($adapter, $this->config['revision_file']);
            }
        }

    }

    public function parseCommandLineOptions()
    {
        global $argv;
        $options = array_slice($argv, 1);
        $command = null;
        if (count($options) == 0) {
            $command = '';
        } else {
            $command = $options[0];
            if (in_array($command, ['rollback', 'folder', 'init', 'dirty', 'clean', 'help'])) {
                $arg = '';
                if (count($options) > 1) {
                    $arg = $options[1];
                }
            } else {
                $arg = $options[0];
                $command = 'unknown';
            }
        }
        switch ($command) {
            case '':
                $this->uploadLastCommit();
                break;
            case 'init':
                $this->init($arg);
                break;
            case 'rollback':
                $this->rollback($arg);
                break;
            case 'folder':
                $this->uploadFolder($arg);
                break;
            case 'dirty':
                $this->dirty();
                break;
            case 'clean':
                $this->cleanDirty();
                break;
            case 'unknown':
                $this->unknownCommand($arg);
                break;
            default:
                $this->showHelp();
                break;
        }
    }

    public function showHelp()
    {
        $this->term->addArt(dirname(__FILE__) . '/art');
        $this->term->green()->draw('gut');
        $this->term->br();
        $this->term->green()->inline('gut');
        $this->term->inline(' version ');
        $this->term->yellow()->inline(self::VERSION);
        $this->term->br()->br();
        $this->term->yellow('Usage:');
        $this->term->out(' gut [location] [command] [<option>]');
        $this->term->br();
        $this->term->yellow('Location:');
        $this->term->out(' (optional) Location to upload to. (default: all).');
        $this->term->br();
        $this->term->yellow('Available commands:');
        $padding = $this->term->padding(10, ' ');

        $padding->label(' commit')->result('(default) Upload revision. Option: [<commit>|rollback] (default: HEAD).');
        $padding->label(' init')->result('Initialize location to specified revision. Option: [<commit>] (default: HEAD).');
        $padding->label(' dirty')->result('Upload not commited files.');
        $padding->label(' clean')->result('Restore files uploaded with \'dirty\' to their clean repository state.');
        $padding->label(' folder')->result('Upload specified folder. Option: <folder>');
        $padding->label(' help')->result('Show this help message.');
        $this->term->br();  
    }

    public function uploadLastCommit()
    {
        foreach ($this->locations as $locationName => $location) {
            try {
                $revision = $location->getRevision();
            } catch (FileNotFoundException $e) {
                $this->term->error("$locationName: Revision file ({$this->config['revision_file']}) not found.");
                continue;
            }
            var_dump($revision);
        }
    }

    public function init(string $revision = '')
    {
        if (empty($revision)) {
            // get last commit
        } else {
            // check commit exist
        }
        foreach ($this->locations as $locationName => $location) {
            $location->setRevision($revision);
        }
    }

    private function unknownCommand(string $command)
    {
        $this->term->error('unknown command: '. $command);
        $this->showHelp();
    }

    public function rollback(string $revision = '')
    {
        $this->term->out('rollback');
    }

    public function uploadFolder()
    {
        $this->term->out('folder');
    }

    public function dirty()
    {
        $this->term->out('dirty');
    }

    public function cleanDirty()
    {
        $this->term->out('clean');
    }

    public function getLocation(string $locationName):Location
    {
        if (isset($this->locations[$locationName])) {
            return $this->locations[$locationName];
        }
        throw new Exception("Location '$locationName' not found");
    }

}