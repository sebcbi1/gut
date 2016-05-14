<?php

class GitTest extends \PHPUnit_Framework_TestCase
{
    private $git;
    private $repoPath;

    public function setUp()
    {
        $this->repoPath = sys_get_temp_dir() . '/gutTestRepo';
        mkdir($this->repoPath);
        chdir($this->repoPath);
        // init repo
        exec('git init');
        // add a file
        exec('touch test.php; git add .; git commit -m "add test"');

        $this->git = new \Gut\Git($this->repoPath);
    }

    public function tearDown()
    {
        exec("rm -Rf {$this->repoPath}");
    }

    public function sort($array)
    {
        foreach (['added', 'modified', 'deleted'] as $k) {
            sort($array[$k]);
        }
        return $array;
    }

    public function testCheckIfCommitExistsInLog()
    {
        $this->assertTrue($this->git->checkIfCommitExistsInLog($this->git->getLastCommit()));
        $this->assertFalse($this->git->checkIfCommitExistsInLog('blablabla'));
    }

    public function testGetLastCommit()
    {
        $commit = $this->git->getLastCommit();
        $this->assertNotEmpty($commit);
        $this->assertEquals(40, strlen($commit));
    }

    public function testGetModifiedFilesBetweenRevisions()
    {
        // add another file
        exec('touch test2.php; git add .; git commit -m "add test2"');

        $lastCommit = $this->git->getLastCommit();
        $previousCommit = $this->git->getLog()[1];
        $diff = $this->sort($this->git->getModifiedFilesBetweenRevisions($previousCommit, $lastCommit));
        $expected = [
            'added' => ["test2.php"],
            'modified' => [],
            'deleted' => [],
        ];
        $this->assertEquals($diff, $expected);

        // modify a file
        exec('echo blablabla > test.php; git add .; git commit -m "modify test"');
        $lastCommit = $this->git->getLastCommit();
        $previousCommit = $this->git->getLog()[1];
        $diff = $this->sort($this->git->getModifiedFilesBetweenRevisions($previousCommit, $lastCommit));
        $expected = [
            'added' => [],
            'modified' => ["test.php"],
            'deleted' => [],
        ];
        $this->assertEquals($diff, $expected);

        // delete  a file
        exec('rm test.php; git add .; git commit -m "delete test"');
        $lastCommit = $this->git->getLastCommit();
        $previousCommit = $this->git->getLog()[1];
        $diff = $this->sort($this->git->getModifiedFilesBetweenRevisions($previousCommit, $lastCommit));
        $expected = [
            'added' => [],
            'modified' => [],
            'deleted' => ["test.php"],
        ];
        $this->assertEquals($diff, $expected);

        // multiple commits
        $lastCommit = $this->git->getLastCommit();
        $previousCommit = $this->git->getLog()[3];
        $diff = $this->sort($this->git->getModifiedFilesBetweenRevisions($previousCommit, $lastCommit));
        $expected = [
            'added' => ["test2.php"],
            'modified' => [],
            'deleted' => ["test.php"],
        ];
        $this->assertEquals($diff, $expected);

        // rename a file
        exec('mv test2.php test3.php; git add .; git commit -m "rename test2"');
        $lastCommit = $this->git->getLastCommit();
        $previousCommit = $this->git->getLog()[1];
        $diff = $this->sort($this->git->getModifiedFilesBetweenRevisions($previousCommit, $lastCommit));
        $expected = [
            'added' => ["test3.php"],
            'modified' => [],
            'deleted' => ["test2.php"],
        ];
        $this->assertEquals($diff, $expected);

    }
}
