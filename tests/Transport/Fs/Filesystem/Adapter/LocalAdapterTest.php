<?php

namespace Jackalope\Tests\Transport\Fs\Filesystem\Adapter;

use Symfony\Component\Filesystem\Filesystem;
use Jackalope\Transport\Fs\Filesystem\Adapter\LocalAdapter;
use Jackalope\Tests\Transport\Fs\Filesystem\Adapter\AdapterTestCase;

class LocalAdapterTest extends AdapterTestCase
{
    protected $testDir;

    public function setUp()
    {
        $fs = new Filesystem();
        $this->testDir = __DIR__ . '/_data';
        if (file_exists($this->testDir)) {
            $fs->remove($this->testDir);
        }
        mkdir($this->testDir, 0777, true);

        $this->adapter = new LocalAdapter($this->testDir);
    }

    public function getAdapter()
    {
        return $this->adapter;
    }
}
