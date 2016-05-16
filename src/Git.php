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
        return $this->revParse();
    }

    public function revParse($rev = 'HEAD'):string
    {
        $revision = $this->exec("rev-parse $rev 2> /dev/null", $returnCode);
        if ($returnCode == 0) {
            return $revision[0];
        }
        throw new Exception("Unknown revision '$rev'.");
    }

    public function stash()
    {
        $this->exec('stash -u');
    }

    public function stashPop()
    {
        $this->exec('stash pop');
    }

    public function getCurrentBranch()
    {
        return $this->exec('rev-parse --abbrev-ref HEAD')[0];
    }

    public function checkout(string $arg)
    {
        $out = $this->exec("checkout $arg 2>&1", $returnCode);
        if ($returnCode != 0) {
            throw new Exception(implode("\n",$out));
        }
    }

    public function getLog():array
    {
        return $this->exec('log --format="%H"');
    }
    

    public function getUncommitedFiles():array
    {
        $lines = $this->exec("diff --name-status");
        return $this->parseDiff($lines);
    }

    public function getModifiedFilesBetweenRevisions(string $revision = '', string $newRevision = ''):array
    {
        try {
            // check if already uploaded revision found localy
            $revision = $this->revParse($revision);
        } catch (Exception $e) {
            throw new Exception("Unknown revision $revision. Try merging remote branch locally.");
        }

        $lines = $this->exec("diff --name-status $revision $newRevision");

        return $this->parseDiff($lines);
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
    public function parseDiff(array $lines):array
    {
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

}
