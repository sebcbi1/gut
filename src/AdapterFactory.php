<?php

namespace Gut;

use League\Flysystem\Adapter\NullAdapter;
use League\Flysystem\Memory\MemoryAdapter;

class AdapterFactory
{
    public static function create($location)
    {
        switch($location['adapter']) {
            case 'memory':
                return new MemoryAdapter();
                break;
            default:
                return new NullAdapter();
        }
    }
}