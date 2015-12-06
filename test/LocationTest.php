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
}