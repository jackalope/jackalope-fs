<?php

namespace Jackalope\Transport\Fs;

use Jackalope\Factory;
use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Prophecy\PhpUnit\ProphecyTestCase;

class ClientTest extends ProphecyTestCase
{
    protected $fs;
    public function setUp()
    {
        $factory = new Factory();
        $this->fs = $this->prophesize('Jackalope\Transport\Fs\Filesystem\Filesystem');
        $this->client = new Client($factory, array('path' => __DIR__ . '/../../data'), $this->fs->reveal());
    }

    public function testGetNode()
    {
        $yamlData = <<<EOT
'jcr:primaryType':
    type: Name
    value: 'nt:folder'
'jcr:created':
    type: Date
    value: '2011-03-21T14:34:20.431+01:00'
'jcr:createdBy':
    type: String
    value: admin
'jackalope:fs:id':
    type: String
    value: 1234
EOT
        ;
        $this->fs->exists('/workspaces/default/foo/node.yml')->willReturn(true);
        $this->fs->read('/workspaces/default/foo/node.yml')->willReturn($yamlData);
        $this->fs->ls('/workspaces/default/foo')->willReturn(array('dirs' => array(), 'files' => array()));
        $res = $this->client->getNode('/foo');

        $this->assertEquals('Date', $res->{':jcr:created'});
        $this->assertEquals('admin', $res->{'jcr:createdBy'});
    }
}
