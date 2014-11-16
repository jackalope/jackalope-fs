<?php

namespace Jackalope\Transport\Fs\Search\Adapter;

use Prophecy\PhpUnit\ProphecyTestCase;
use PHPCR\Util\QOM\Sql2ToQomQueryConverter;
use Transport\Fs\FunctionalTestCase;
use Jackalope\Transport\Fs\Model\Node;
use Jackalope\Transport\Fs\Filesystem\Storage;

abstract class AdapterTestCase extends FunctionalTestCase
{
    protected $nodeTypeManager;
    protected $adapter;
    protected $queryManager;

    public function setUp()
    {
        $this->session = $this->getSession(array(
            'filesystem.adapter' => 'array'
        ));
        $this->nodeTypeManager = $this->session->getWorkspace()->getNodeTypeManager();
        $this->queryManager = $this->session->getWorkspace()->getQueryManager();
        $this->adapter = $this->getAdapter();
    }

    abstract protected function getAdapter();

    public function provideQuery()
    {
        return array(
            array('simple', 'SELECT * FROM [nt:unstructured]', 2),
            array('simple', 'SELECT * FROM [nt:unstructured] WHERE field1 = "value 1"', 1)
        );
    }

    /**
     * @dataProvider provideQuery
     */
    public function testQuery($nodeDataName, $query, $expectedNbResults)
    {
        $this->indexNodeData($nodeDataName);

        $res = $this->adapter->query('workspace', $this->queryToQOM($query));
        $this->assertCount($expectedNbResults, $res);
    }

    /**
     * @dataProvider provideQuery
     */
    public function testQueryDoubleIndex($nodeDataName, $query, $expectedNbResults)
    {
        $this->indexNodeData($nodeDataName);
        $this->indexNodeData($nodeDataName);

        $res = $this->adapter->query('workspace', $this->queryToQOM($query));
        $this->assertCount($expectedNbResults, $res);
    }

    public function testQueryNewIndex()
    {
        $this->indexNodeData('simple');
        $this->indexNodeData('article');

        $query = 'SELECT * FROM [nt:unstructured] WHERE title = "Article Title"';
        $res = $this->adapter->query('workspace', $this->queryToQOM($query));
        $this->assertCount(1, $res);
    }

    protected function getNodeData($name)
    {
        switch ($name) {
            case 'article':
                return array(
                    array(
                        '/node/node-new',
                        array(
                            Storage::INTERNAL_UUID => 'article',
                            'jcr:primaryType' => 'nt:unstructured',
                            'title' => 'Article Title',
                            'body' => 'This is the article body',
                        ),
                    ),
                );
            case 'simple':
            default:
                return array(
                    array(
                        '/node/node1',
                        array(
                            Storage::INTERNAL_UUID => 'simple1',
                            'jcr:primaryType' => 'nt:unstructured',
                            'field1' => 'value 1',
                            'field2' => 'value 2',
                        ),
                    ),
                    array(
                        '/node/node2',
                        array(
                            Storage::INTERNAL_UUID => 'simple2',
                            'jcr:primaryType' => 'nt:unstructured',
                            'field1' => 'value 3',
                            'field2' => 'value 4',
                        ),
                    ),
                );
        }
    }

    protected function indexNodeData($nodeDataName)
    {
        $nodeData = $this->getNodeData($nodeDataName);

        foreach ($nodeData as $nodeDatum) {
            list($path, $properties) = $nodeDatum;
            $node = new Node();

            foreach ($properties as $key => $value) {
                $node->setProperty($key, $value);
            }

            $this->adapter->index('workspace', $path, $node);
        }
    }

    protected function queryToQOM($query)
    {
        $parser = new Sql2ToQomQueryConverter($this->queryManager->getQOMFactory());
        $qom = $parser->parse($query);

        return $qom;
    }
}


