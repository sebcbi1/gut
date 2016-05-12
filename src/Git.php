<?php

namespace Gut;

class Git
{
    private $path = '.';

    public function __construct($path = '')
    {
        if (!empty($path)) {
            $this->path = $path;
        }
    }

    private function exec(string $command):array
    {
        $command = "git -C {$this->path} $command";
        exec($command, $output);
        return $output;
    }

    public function getLastCommit():string
    {
        return $this->exec('rev-parse HEAD')[0];
    }

    public function getLog():array
    {
        return $this->exec('log --format="%H"');
    }
    /**
     * Git Status Codes
     *
     * A: addition of a file
     * C: copy of a file into a new one
     * D: deletion of a file
     * M: modification of the contents or mode of a file
     * R: renaming of a file
     * T: change in the type of the file
     * U: file is unmerged (you must complete the merge before it can be committed)
     * X: "unknown" change type (most probably a bug, please report it)
     */
    public function getModifiedFilesBetweenRevisions(string $revision, string $lastCommit):array
    {
        $lines = $this->exec("diff --name-status $revision $lastCommit");
        $return = [
            'filesToUpload' => [],
            'filesToDelete' => [],
        ];
        foreach ($lines as $line) {
            list($status, $fileName) = preg_split('/\s+/', $line, 2);
            if (in_array($status, ['A', 'C', 'M', 'T'])) {
                $return['filesToUpload'][] = $fileName;
            } elseif ($status == 'D') {
                $return['filesToDelete'][] = $fileName;
            } else {
                throw new Exception("Unsupported git-diff status: {$status}");
            }
        }
        return $return;
    }

}