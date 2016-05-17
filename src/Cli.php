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

    public static function execute($config = self::CONFIG_FILENAME)
    {
        $cli = new self($config);
        $cli->parseCommandLineOptions();
    }


    public function parseCommandLineOptions()
    {
        $this->term->arguments->add([
            'location' => [
                'prefix'       => 'l',
                'longPrefix'   => 'location',
                'description'  => 'Execute command only for the specified location'
            ]
        ]);

        $this->term->arguments->parse();
        $location = $this->term->arguments->get('location');
        if (!empty($location)) {
            try {
                $this->gut->setLocation($location);
            } catch (Exception $e) {
                $this->term->error($e->getMessage());
            }
        }

        global $argv;
        $options = array_slice($argv, 1);
        $command = null;
        if (count($options) == 0) {
            $command = 'commit';
            $arg = null;
        } else {
            $command = $options[0];
            if (in_array($command, ['commit','rollback', 'folder', 'dir', 'init', 'dirty', 'clean', 'help'])) {
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
            case 'commit':
                $rev = $arg ?? 'HEAD';
                $this->uploadCommit($rev);
                break;
            case 'init':
                $this->gut->init($arg);
                break;
            case 'rollback':
                $this->uploadCommit('HEAD^');
                break;
            case 'folder':
            case 'dir':
                $this->uploadFolder($arg);
                break;
            case 'dirty':
                $this->dirty();
                break;
            case 'clean':
                $this->cleanDirty();
                break;
            case 'unknown':
                $this->term->error('unknown command: '. $command);
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
        $this->term->out(' gut [command] [<option>]');
        $this->term->br();
        $this->term->yellow('Available commands:');
        $padding = $this->term->padding(10, ' ');

        $padding->label(' commit')->result('(default) Upload revision. Option: [<commit>] (default: HEAD).');
        $padding->label(' rollback')->result('shortcut command for "gut commit HEAD^"');
        $padding->label(' init')->result('Initialize location to specified revision. Option: [<commit>] (default: HEAD).');
        $padding->label(' dirty')->result('Upload not commited files.');
        $padding->label(' clean')->result('Restore files uploaded with \'dirty\' to their clean repository state.');
        $padding->label(' folder')->result('Upload specified folder. Option: <folder>');
        $padding->label(' help')->result('Show this help message.');
        $this->term->br();  
    }

    public function uploadCommit($rev)
    {

        $branch = $this->gut->checkoutCommit($rev);

        foreach ($this->gut->getLocations() as $locationName => $location) {
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

        $this->gut->checkoutHead($branch);
    }

    private function dirty()
    {
        $files = $this->gut->getNotCommitedFiles();
        $choices = [];
        foreach (['added', 'modified','deleted'] as $status) {
            $statusLabel = '['. strtoupper(substr($status, 0, 1)) . '] ';
            foreach ($files[$status] as $file) {
                $choices[$file] = $statusLabel . $file;
            }
        }
        $response = $this->term->checkboxes("\nUpload the following uncommited files: ", $choices)->prompt();
        if (!empty($response)) {
            $this->gut->uploadNotCommitedFiles($response);
        }
        $this->term->br();
    }
    
    private function cleanDirty()
    {
        $this->term->inline("\nCleaning uncommited files $arg... ");
        $this->gut->cleanNotCommitedFiles();
        $this->term->out("done.\n");
    }

    private function uploadFolder($arg)
    {
        $this->term->inline("\nUploading $arg... ");
        $this->gut->uploadFolder($arg);
        $this->term->out("done.\n");
    }

}
