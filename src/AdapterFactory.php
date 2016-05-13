<?php

namespace Gut;

use League\Flysystem\Adapter\NullAdapter;
use League\Flysystem\Memory\MemoryAdapter;

class AdapterFactory
{
    public static function create($adapter)
    {
        switch($adapter) {
            case 'memory':
                return new MemoryAdapter();
                break;
            default:
                return new NullAdapter();
        }
    }
}
