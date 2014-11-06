<?php

namespace Jackalope\Transport\Fs\Storage;

use Prophecy\PhpUnit\ProphecyTestCase;
use Jackalope\Transport\Fs\Filesystem\Storage\Index;
use Jackalope\Transport\Fs\Filesystem\Adapter\ArrayAdapter;
use Jackalope\Transport\Fs\Filesystem\Filesystem;

class IndexTest extends ProphecyTestCase
{
    private $index;
    private $filesystem;

    public function setUp()
    {
        $this->filesystem = new Filesystem(new ArrayAdapter());
        $this->index = new Index($this->filesystem);
    }

    public function provideGetReferringProperties()
    {
        return array(
            array('1234123412341234', null, true, 2),
            array('1234123412341234', null, false, 2),
            array('1234123412341234', 'referrer1', false, 2),
            array('1234123412341234', 'referrer1', true, 2),
            array('1234123412341234', 'referrer3', true, 1),
            array('1234123412341234', 'referrer3', false, 1),
            array('1234123412341234', 'foobar', true, 0),
        );
    }

    /**
     * @dataProvider provideGetReferringProperties
     */
    public function testGetReferringProperties($uuid, $name, $weak, $expectedNodeCount)
    {
        $this->index->indexReferrer('11111111', 'referrer1', $uuid, $weak);
        $this->index->indexReferrer('22222222', 'referrer1', $uuid, $weak);
        $this->index->indexReferrer('22222222', 'referrer2', $uuid, $weak);
        $this->index->indexReferrer('22222222', 'referrer3', $uuid, $weak);
        $this->index->indexReferrer('22222222', 'referrer3', $uuid, $weak);
        $this->index->indexReferrer('22222222', 'referrer3', $uuid, $weak);

        $res = $this->index->getReferringProperties($uuid, $name, $weak);
        $this->assertCount($expectedNodeCount, $res);
    }

    public function provideGetNodeLocationForUuid()
    {
        return array(
            array('1234123412341234', 'workspace', '/path/to/one', false),
            array('1234123412341234', 'workspace', '/path/to/one', true),
        );
    }

    /**
     * @dataProvider provideGetNodeLocationForUuid
     */
    public function testGetNodeLocationForUuid($uuid, $expectedWorkspace, $expectedPath, $internal)
    {
        $expectedFsPath = Index::INDEX_DIR . '/' . ($internal ? Index::IDX_INTERNAL_UUID : Index::IDX_JCR_UUID) . '/' . $uuid;

        $this->filesystem->write($expectedFsPath, <<<EOT
workspace:/path/to/one
EOT
);
        $res = $this->index->getNodeLocationForUuid($uuid, $internal);
        $this->assertEquals($expectedWorkspace, $res->getWorkspace());
        $this->assertEquals($expectedPath, $res->getPath());
    }

    public function provideDeindexReferrer()
    {
        return array(
            array('11111111', 'referrer1', false),
        );
    }

    /**
     * @dataProvider provideDeindexReferrer
     */
    public function testDeindexReferrer($internalUuid, $propertyName, $weak)
    {
        $this->index->indexReferrer($internalUuid, $propertyName, 'uuid', $weak);

        $referrers = $this->index->getReferringProperties('uuid', $propertyName);
        $this->assertCount(1, $referrers);

        $this->index->deindexReferrer($internalUuid, $propertyName, $weak);

        $referrers = $this->index->getReferringProperties('uuid', $propertyName);
        $this->assertCount(0, $referrers);
    }
}
