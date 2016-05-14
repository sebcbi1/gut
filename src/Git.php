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

    public function exec(string $command, &$returnCode = 0):array
    {
        $command = "git -C {$this->path} $command";
        exec($command, $output, $returnCode);
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
        if (!$this->checkIfCommitExistsInLog($revision)) {
            throw new Exception("Unknown revision $revision. Try merging remote branch locally.");
        }

        $lines = $this->exec("diff --name-status $revision $lastCommit");
        $return = [
            'added' => [],
            'modified' => [],
            'deleted' => [],
        ];
        
        foreach ($lines as $line) {
            list($status, $fileName) = preg_split('/\s+/', $line, 2);
            if (in_array($status, ['A'])) {
                $return['added'][] = $fileName;
            } elseif (in_array($status, ['C', 'M', 'T'])) {
                $return['modified'][] = $fileName;
            } elseif ($status == 'D') {
                $return['deleted'][] = $fileName;
            } else {
                throw new Exception("Unsupported git-diff status: {$status}");
            }
        }
        return $return;
    }

    public function checkIfCommitExistsInLog($revision)
    {
        $this->exec("show -s $revision >/dev/null 2>&1", $returnCode);
        return $returnCode == 0;
    }

}
