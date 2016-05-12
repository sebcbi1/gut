<?php

class GitTest extends \PHPUnit_Framework_TestCase
{
    private $git;

    public function setUp()
    {
        $this->git = new \Gut\Git(realpath(__DIR__.'/repo'));
    }

    public function testGetLastCommit()
    {
        $commit = $this->git->getLastCommit();
        $this->assertNotEmpty($commit);
        $this->assertEquals(40, strlen($commit));
    }

    public function testGetModifiedFilesBetweenRevisions()
    {
        $lastCommit = $this->git->getLastCommit();
        $previousCommit = $this->git->getLog()[1];
        var_dump($this->git->getModifiedFilesBetweenRevisions($previousCommit, $lastCommit));
    }
}