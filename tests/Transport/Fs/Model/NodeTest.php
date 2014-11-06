<?php

namespace Jackalope\Transport\Fs\Node;

use Jackalope\Factory;
use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Prophecy\PhpUnit\ProphecyTestCase;
use Jackalope\Transport\Fs\Model\Node;
use PHPCR\PropertyType;

class NodeTest extends ProphecyTestCase
{
    public function testGetProperties()
    {
        $data = array(
            'jcr:primaryType' => 'nt:unstructured',
            ':jcr:primaryType' => 'Name',
            'jcr:data' => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
            ':jcr:data' => 'Binary',
            'multidata' => 
            array (
                0 => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
                1 => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
            ),
            ':multidata' => 'Binary',
            'jcr:lastModified' => '2009-04-27T13:01:07.472+02:00',
            ':jcr:lastModified' => 'Date',
            'jcr:mimeType' => 'text/plain',
            ':jcr:mimeType' => 'String',
            'zeronumber' => '0',
            ':zeronumber' => 'Long',
            'mydateprop' => '2011-04-21T14:34:20.431+01:00',
            ':mydateprop' => 'Date',
            'multidate' => 
            array (
                0 => '2011-04-22T14:34:20.000+01:00',
                1 => '2011-10-23T14:34:20.000+01:00',
                2 => '2010-10-23T14:34:20.000+01:00',
            ),
            ':multidate' => 'Date',
            'multivalue' => 
            array (
                0 => '200',
                1 => '0',
                2 => '100',
            ),
            ':multivalue' => 'Long',
            'jackalope:fs:id' => 'bc9a994c-7001-43e3-ba45-81403b457a53',
            ':jackalope:fs:id' => 'String',
        );

        $node = new Node($data);
        $node = $node->getProperties();

        $this->assertEquals(array(
                0 => '2011-04-22T14:34:20.000+01:00',
                1 => '2011-10-23T14:34:20.000+01:00',
                2 => '2010-10-23T14:34:20.000+01:00',
        ), $node['multidate']['value']);
        $this->assertEquals('Date', $node['multidate']['type']);

        $this->assertEquals('text/plain', $node['jcr:mimeType']['value']);
        $this->assertEquals('String', $node['jcr:mimeType']['type']);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetPropertiesInvalid()
    {
        $data = array(
            'jcr:primaryType' => 'nt:unstructured',
            ':jcr:primaryType' => 'Name',
            'jcr:data' => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
            ':jcr:data' => 'Binary',
            'multidata' => 
            array (
                0 => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
                1 => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
            ),
        );

        new Node($data);
    }

    public function testSetProperty()
    {
        $node = new Node();
        $node->setProperty('foobar', 'Foo');

        $prop = $node->getProperty('foobar');

        $this->assertEquals('String', $prop['type']);
        $this->assertEquals('Foo', $prop['value']);
    }

    public function testToJackalopeStructure()
    {
        $data = array(
            'jcr:primaryType' => '',
            ':jcr:primaryType' => 'Name',
            'jcr:data' => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
            ':jcr:data' => 'Binary',
            'multidata' => 
            array (
                0 => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
                1 => 'aDEuIENoYXB0ZXIgMSBUaXRsZQoKKiBmb28KKiBiYXIKKiogZm9vMgoqKiBmb28zCiogZm9vMAoKfHwgaGVhZGVyIHx8IGJhciB8fAp8IGggfCBqIHwKCntjb2RlfQpoZWxsbyB3b3JsZAp7Y29kZX0KCiMgZm9vCg==',
            ),
            ':multidata' => 'Binary'
        );

        $node = new Node($data);
        $res = get_object_vars($node->toJackalopeStructure());
        $this->assertSame($data, $res);
    }

    public function testSetPropertyTypeInteger()
    {
        $node = new Node();
        $node->setProperty('foobar', 'Foobar', 2);
        $this->assertEquals('Binary', $node->getPropertyType('foobar'));
    }

    public function testFromPhpcrNode()
    {
        $phpcrNode = $this->prophesize('PHPCR\NodeInterface');
        $prop1 = $this->prophesize('PHPCR\PropertyInterface');

        $prop1->getType()->willReturn(1);
        $prop1->getValue()->willReturn('Foo');

        $phpcrNode->getProperties()->willReturn(array(
            'prop1' => $prop1->reveal()
        ));

        $node = new Node($phpcrNode->reveal());

        $this->assertEquals(array(
            'type' => 'String',
            'value' => 'Foo'
        ), $node->getProperty('prop1'));
    }
}

