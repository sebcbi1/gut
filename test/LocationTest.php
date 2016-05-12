<?php

use Gut\Gut;

class LocationTest extends \PHPUnit_Framework_TestCase
{
    private $gut;
    public function setUp()
    {
        $config = [
            'locations' => [
                'test' => [
                    'adapter' => 'memory'
                ]
            ]
        ];
        $this->gut = new Gut($config);
    }

    public function testSetRevision()
    {
        $this->gut->init('test');
        $this->assertEquals('test', $this->gut->getLocation('test')->getRevision());
    }

    public function testModifiedFiles()
    {
//        $this->gut->init('test');
        $this->gut->init('8b427d2d9985ba7cb0869ea3ad4fc0104702c6ff');
        $this->gut->getLocation('test')->getModifiedFiles();
    }
}