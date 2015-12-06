<?php

namespace Gut;

use League\CLImate\CLImate;

class Git
{
    public function __construct()
    {
        $this->term = new CLImate;
    }

    public function test(int $test):string
    {
        return 'test';
    }

}