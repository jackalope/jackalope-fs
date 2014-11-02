<?php

namespace Jackalope\Tests\Transport\Fs\Filesystem\Adapter;

use Jackalope\Tests\Transport\Fs\Filesystem\Adapter\AdapterTestCase;
use Jackalope\Transport\Fs\Filesystem\Adapter\ArrayAdapter;

class ArrayAdapterTest extends AdapterTestCase
{
    private $adapter;

    public function setUp()
    {
        $this->adapter = new ArrayAdapter();
    }

    public function getAdapter()
    {
        return $this->adapter;
    }
}
