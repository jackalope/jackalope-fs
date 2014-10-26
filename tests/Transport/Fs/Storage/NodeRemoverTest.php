<?php

namespace Transport\Fs\Storage;

use Prophecy\PhpUnit\ProphecyTestCase;
use Jackalope\Transport\Fs\Filesystem\Storage\NodeRemover;
use Jackalope\Transport\Fs\Filesystem\Storage;

class NodeRemoverTest extends ProphecyTestCase
{
    public function setUp()
    {
        $this->nodeReader = $this->prophesize('Jackalope\Transport\Fs\Filesystem\Storage\NodeReader');
        $this->filesystem = $this->prophesize('Jackalope\Transport\Fs\Filesystem\Filesystem');
        $this->index = $this->prophesize('Jackalope\Transport\Fs\Filesystem\Storage\Index');
        $this->helper = $this->prophesize('Jackalope\Transport\Fs\Filesystem\Storage\StorageHelper');

        $this->node = $this->prophesize('Jackalope\Transport\Fs\Model\Node');

        $this->nodeRemover = new NodeRemover(
            $this->nodeReader->reveal(),
            $this->filesystem->reveal(),
            $this->index->reveal(),
            $this->helper->reveal()
        );
    }

    public function testRemove()
    {
        $this->node->hasProperty(Storage::JCR_UUID)->willReturn(true);
        $this->node->getPropertyValue(Storage::JCR_UUID)->willReturn(1234);
        $this->index->deindexUuid('1234', false)->shouldBeCalled();
        $this->node->getChildrenNames()->willReturn(array());
        $this->helper->getNodePath('foo_workspace', 'foo', false)->willReturn('/asd');
        $this->filesystem->remove('/asd', true)->shouldBeCalled();

        $this->nodeReader->readNode('foo_workspace', 'foo')->willReturn(
            $this->node
        );

        $this->nodeRemover->removeNode('foo_workspace', 'foo');

        // hmph
        $this->assertTrue(true);
    }
}
