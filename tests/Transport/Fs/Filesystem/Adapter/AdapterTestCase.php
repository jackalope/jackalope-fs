<?php

namespace Jackalope\Tests\Transport\Fs\Filesystem\Adapter;

use Symfony\Component\Filesystem\Filesystem;
use Jackalope\Transport\Fs\Filesystem\Adapter\LocalAdapter;

abstract class AdapterTestCase extends \PHPUnit_Framework_TestCase
{
    protected $testDir;

    abstract public function getAdapter();

    public function testReadWrite()
    {
        $filePath = 'foobar.txt';
        $fileContents = 'This is some content';

        $this->getAdapter()->write($filePath, 'This is some content');
        $res = $this->getAdapter()->read($filePath);

        $this->assertEquals($fileContents, $res);
    }

    public function provideMkdir()
    {
        return array(
            array(
                'foobar',
            ),
            array(
                'foobar/foobar',
            ),
            array(
                'bar/foo/bar'
            )
        );
    }

    /**
     * @dataProvider provideMkdir
     */
    public function testMkdir($path)
    {
        $this->getAdapter()->mkdir($path);
        $this->assertTrue($this->getAdapter()->exists($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testReadNonExisting()
    {
        $this->getAdapter()->read('/test_file_non_exist');
    }

    /**
     * @depends testReadWrite
     */
    public function testExists()
    {
        $this->getAdapter()->write('/test_file', 'This is some content');
        $this->assertTrue($this->getAdapter()->exists('/test_file'));
    }

    public function testNotExists()
    {
        $this->assertFalse($this->getAdapter()->exists('/asdasd'));
    }

    /**
     * @depends testExists
     */
    public function testRemove()
    {
        $this->getAdapter()->write('/level1', 'foo');
        $this->getAdapter()->remove('/level1', true);

        $this->assertFalse($this->getAdapter()->exists('/level1'));
    }
}
