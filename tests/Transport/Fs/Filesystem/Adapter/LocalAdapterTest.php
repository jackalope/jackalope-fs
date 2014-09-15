<?php

namespace ackalope\Tests\Transport\Fs\Filesystem\Adapter;

use Symfony\Component\Filesystem\Filesystem;
use Jackalope\Transport\Fs\Filesystem\Adapter\LocalAdapter;

class LocalAdapterTest extends \PHPUnit_Framework_TestCase
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

    public function testWrite()
    {
        $filePath = 'foobar.txt';
        $fileContents = 'This is some content';
        $absFilePath = $this->testDir . '/' . $filePath;

        $this->adapter->write($filePath, 'This is some content');

        $this->assertTrue(file_exists($absFilePath));
        $this->assertEquals($fileContents, file_get_contents($absFilePath));
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
        $this->adapter->mkdir($path);
        $this->assertTrue(file_exists($this->testDir . '/' . $path));
    }

    public function testRead()
    {
        file_put_contents($this->testDir . '/test_file', 'Test');

        $res = $this->adapter->read('/test_file');
        $this->assertEquals('Test', $res);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testReadNonExisting()
    {
        $res = $this->adapter->read('/test_file_non_exist');
    }

    public function testExists()
    {
        file_put_contents($this->testDir . '/test_file', 'Test');
        $this->assertTrue($this->adapter->exists('/test_file'));
    }

    public function testNotExists()
    {
        $this->assertFalse($this->adapter->exists('/asdasd'));
    }
}
