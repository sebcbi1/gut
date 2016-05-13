<?php

use Gut\AdapterFactory;
use Gut\Location;

class LocationTest extends \PHPUnit_Framework_TestCase
{
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

        $this->location = new Location(AdapterFactory::create(['type' =>'memory']), '.revision', $this->repoPath );
        $this->git = new \Gut\Git($this->repoPath);

    }

    public function tearDown()
    {
        exec("rm -Rf {$this->repoPath}");
    }
    
    public function testGetRevision()
    {
        $this->setExpectedException('League\Flysystem\FileNotFoundException');
        $this->assertEmpty($this->location->getRevision());
    }

    public function testSetRevision()
    {
        $this->location->setRevision('fakeRevision');
        $this->assertEquals('fakeRevision', $this->location->getRevision());
    }

    public function testGetModifiedFiles() 
    {
        $this->location->setRevision($this->git->getLastCommit());
        exec('echo blablabla > test.php; git add .; git commit -m "modified test"');

        $diff = $this->sort($this->location->getModifiedFiles());
        $expected = [
            'added' => [],
            'modified' => ["test.php"],
            'deleted' => [],
        ];
        $this->assertEquals($diff, $expected);
    }

    public function testUploadRevision()
    {
        $fs = $this->location->getFileSystem();

        $oldCommit = $this->git->getLastCommit();
        $this->location->setRevision($oldCommit);
        exec('echo blablabla > test.php; git add .; git commit -m "modified test"');

        $newCommit = $this->git->getLastCommit();
        $this->location->uploadRevision();
        $this->assertTrue($fs->has('remote://test.php'));
        $this->assertEquals($newCommit, $fs->read('remote://.revision'));

        exec('rm test.php; touch test3.php; git add .; git commit -m "modified test"');
        $this->location->uploadRevision();
        $this->assertTrue($fs->has('remote://test3.php'));
        $this->assertFalse($fs->has('remote://test.php'));
    }

    
    private function sort($array)
    {
        foreach (['added', 'modified', 'deleted'] as $k) {
            sort($array[$k]);
        }
        return $array;
    }

}
