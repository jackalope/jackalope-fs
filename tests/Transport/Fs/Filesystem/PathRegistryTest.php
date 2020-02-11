<?php

namespace Jackalope\Transport\Fs\Filesystem;

use PHPUnit\Framework\TestCase;

class PathRegistryTest extends TestCase
{
    public function setUp()
    {
        $this->pathRegistry = new PathRegistry();
    }

    public function testGetUuid()
    {
        $this->pathRegistry->registerUuid('/path/to/node', '1234-1234');
        $res = $this->pathRegistry->getUuid('/path/to/node');
        $this->assertEquals('1234-1234', $res);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetUuidNonExisting()
    {
        $this->pathRegistry->getUuid('/path/to/node');
    }
}
