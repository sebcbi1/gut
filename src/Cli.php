<?php

namespace Gut;

use League\CLImate\CLImate;
use Symfony\Component\Yaml\Yaml;

class Cli
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
     * @var Gut
     */
    private $gut;

    public function __construct($config = self::CONFIG_FILENAME)
    {
        $this->term = new CLImate();
        $this->term->extend('Gut\Cli\ReplaceableText');
        $this->gut = new Gut($config);
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
                $arg = null;
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
                $this->gut->uploadCommit('HEAD');
                break;
            case 'init':
                $this->gut->init($arg);
                break;
            case 'rollback':
                $rev = $arg ?? 'HEAD^';
                $this->gut->uploadCommit($rev);
                break;
            case 'folder':
                $this->gut->uploadFolder($arg);
                break;
            case 'dirty':
                $this->gut->dirty();
                break;
            case 'clean':
                $this->gut->cleanDirty();
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

    public function uploadCommit($rev)
    {
        foreach ($this->locations as $locationName => $location) {
            try {

                $files = $location->getModifiedFiles($rev);
                if (empty($files['added']) && empty($files['modified']) && empty($files['deleted'])) {
                    $this->term->out("\n$locationName: no files to upload.\n");
                } else {
                    $this->term->out("\n$locationName - files to be uploaded/deleted :\n");

                    if (!empty($files['added'])) {
                        foreach ($files['added'] as $file) {
                            $this->term->green("  [A] $file");
                        }
                    }
                    if (!empty($files['modified'])) {
                        foreach ($files['modified'] as $file) {
                            $this->term->yellow("  [M] $file");
                        }
                    }
                    if (!empty($files['deleted'])) {
                        foreach ($files['deleted'] as $file) {
                            $this->term->red("  [D] $file");
                        }
                    }
                    $filesCount = count($files['added']) + count($files['modified']) + count($files['deleted']);
                    $input = $this->term->input("\nUpload changes to $locationName ? [y/N]");
                    $input->accept(['y', 'n'])->defaultTo('N');
                    if (strtolower($input->prompt()) == 'y') {

                        $this->term->inline("\nUploading ... ");
                        $text = $this->term->replaceableText();
                        $i = 1;
                        foreach ($location->uploadFiles($rev) as $file) {
                            $text->set("[$i/$filesCount] $file");
                            $i++;
                            usleep(200000);
                        }
                        $text->set("done.");
                        $this->term->br();
                        
                    }
                }
            } catch (Exception $e) {
                $this->term->error("$locationName: error - " . $e->getMessage());
                continue;
            }
        }
    }
    
    private function unknownCommand(string $command)
    {
        $this->term->error('unknown command: '. $command);
        $this->showHelp();
    }

}
