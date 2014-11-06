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
        $this->node->getPropertyValue(Storage::JCR_UUID)->willReturn('jcr-uuid');
        $this->node->hasProperty(Storage::INTERNAL_UUID)->willReturn(true);
        $this->node->getPropertyValue(Storage::INTERNAL_UUID)->willReturn('internal-uuid');
        $this->index->getReferringProperties('jcr-uuid')->willReturn(array());

        $this->node->getChildrenNames()->willReturn(array());
        $this->node->getProperties()->willReturn(array(
            'prop1' => array(
                'type' => 'Reference',
                'value' => 'reference-1',
            ),
            'prop2' => array(
                'type' => 'WeakReference',
                'value' => 'weak-reference-1',
            ),
        ));

        $this->index->deindexUuid('jcr-uuid', false)->shouldBeCalled();
        $this->index->deindexReferrer('internal-uuid', 'prop1', 'reference-1', false)->shouldBeCalled();
        $this->index->deindexReferrer('internal-uuid', 'prop2', 'weak-reference-1', true)->shouldBeCalled();


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
