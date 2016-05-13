<?php

use Gut\Gut;

class GutTest extends \PHPUnit_Framework_TestCase
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

    public function test()
    {

    }
}
