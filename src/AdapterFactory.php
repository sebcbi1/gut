<?php

namespace Gut;

use Exception;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Adapter\NullAdapter;
use League\Flysystem\Memory\MemoryAdapter;

class AdapterFactory
{
    public static function create($adapter)
    {
        switch($adapter['type']) {
            case 'memory':
                return new MemoryAdapter();
                break;
            case 'local':
                if (false === realpath($adapter['path'])) { 
                    throw new Exception('Directory ' . $adapter['path'] .' doesn\'t exists.');
                }
                return new Local(realpath($adapter['path']));
                break;
            case 'ftp':
                return new Ftp($adapter);
                break;
            default:
                return new NullAdapter();
        }
    }
}
