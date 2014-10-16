<?php

namespace Jackalope\Transport\Fs\Search\Adapter;

use ZendSearch\Lucene\Lucene;
use Jackalope\Transport\Fs\Search\SearchAdapterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Jackalope\Transport\Fs\Filesystem\Storage;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Document\Field;
use PHPCR\PropertyType;
use Jackalope\Query\Query;
use PHPCR\Query\QOM\QueryObjectModelInterface;
use Jackalope\Transport\Fs\Search\QOMWalker\ZendSearchQOMWalker;
use PHPCR\Util\PathHelper;
use Jackalope\Transport\Fs\Search\Adapter\Zend\ExactMatchAnalyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use PHPCR\NodeType\NodeTypeManagerInterface;

class ZendSearchAdapter implements SearchAdapterInterface
{
    const SEARCH_PATH = 'zend-search-indexes';
    const IDX_PATH = 'jcr:path';
    const IDX_NODENAME = 'jcr:nodename';
    const IDX_NODELOCALNAME = 'jcr:nodename';
    const IDX_PARENTPATH= 'jcr:parentpath';

    private $path;
    private $indexes = array();
    private $filesystem;
    private $qomWalker;
    private $nodeTypeManager;

    /**
     * @param string $path Path to search index
     */
    public function __construct($path, NodeTypeManagerInterface $nodeTypeManager = null)
    {
        $this->path = $path;
        $this->filesystem = new Filesystem();
        $this->nodeTypeManager = $nodeTypeManager;
        Analyzer::setDefault(new ExactMatchAnalyzer());
    }

    /**
     * {@inheritDoc}
     */
    public function index($workspace, $path, $nodeData)
    {
        $index = $this->getIndex($workspace);
        $document = new Document();
        $nodeName = PathHelper::getNodeName($path);
        $localNodeName = $nodeName; // PathHelper::getLocalNodeName($path);
        $parentPath = PathHelper::getParentPath($path);

        $document->addField(Field::Keyword(self::IDX_PATH, $path));
        $document->addField(Field::Keyword(self::IDX_NODENAME, $nodeName));
        $document->addField(Field::Keyword(self::IDX_NODELOCALNAME, $localNodeName));
        $document->addField(Field::Keyword(self::IDX_PARENTPATH, $parentPath));

        do {
            $propertyName = key($nodeData);
            $propertyValue = current($nodeData);
            next($nodeData);
            $typeName = key($nodeData);
            $typeValue = current($nodeData);

            if ($propertyName === Storage::INTERNAL_UUID) {
                $document->addField(Field::Keyword(Storage::INTERNAL_UUID, $propertyValue));
                continue;
            }


            switch ($typeValue) {
                case PropertyType::TYPENAME_STRING:
                case PropertyType::TYPENAME_LONG:
                case PropertyType::TYPENAME_DOUBLE:
                case PropertyType::TYPENAME_DATE:
                case PropertyType::TYPENAME_NAME:
                case PropertyType::TYPENAME_PATH:
                case PropertyType::TYPENAME_URI:
                case PropertyType::TYPENAME_DECIMAL:
                case PropertyType::TYPENAME_BOOLEAN:
                    $value = (array) $propertyValue;
                    $value = join(' ', $value);
                    $document->addField(Field::Text($propertyName, $value));
                    break;
            }

        } while (current($nodeData));

        $index->addDocument($document);
    }

    /**
     * Heavily copied from doctrine-dbal. This should be factored up to Jackalope
     */
    public function query($workspace, $qom)
    {
        $query = $this->getQomWalker()->walkQOMQuery($qom);
        $index = $this->getIndex($workspace);
        $data = $index->find($query);

        $primarySource = $this->getQomWalker()->getSource();
        $primaryType = $primarySource->getSelectorName() ?: $primarySource->getNodeTypeName();
        $selectors = array($primarySource);

        $results = $properties = $standardColumns = array();
        foreach ($data as $hit) {
            $result = array();
            $document = $hit->getDocument();

            /** @var SelectorInterface $selector */
            foreach ($selectors as $selector) {
                $selectorName   = $selector->getSelectorName() ?: $selector->getNodeTypeName();

                if ($primaryType === $selector->getNodeTypeName()) {
                    $result[] = array(
                        'dcr:name' => 'jcr:path',
                        'dcr:value' => $document->getField(self::IDX_PATH)->getUtf8Value(),
                    );
                }

                $result[] = array(
                    'dcr:name' => 'jcr:path',
                    'dcr:value' => $document->getField(self::IDX_PATH)->getUtf8Value(),
                    'dcr:selectorName' => $selectorName
                );

                $result[] = array(
                    'dcr:name' => 'jcr:score',
                    'dcr:value' => $hit->score,
                    'dcr:selectorName' => $selectorName
                );

                if (0 === count($qom->getColumns())) {
                    $selectorPrefix = null !== $selector->getSelectorName() ? $selectorName . '.' : '';
                    $result[] = array(
                        'dcr:name' => $selectorPrefix . 'jcr:primaryType',
                        'dcr:value' => $primaryType,
                        'dcr:selectorName' => $selectorName
                    );
                }


                $properties[$selectorName] = array();
                foreach ($document->getFieldNames() as $fieldName) {
                    $field = $document->getField($fieldName);
                    $properties[$selectorName][$fieldName] = $field->getUtf8Value();
                }

                // TODO: add other default columns that Jackrabbit provides to provide a more consistent behavior
                if (isset($properties[$selectorName]['jcr:createdBy'])) {
                    $standardColumns[$selectorName]['jcr:createdBy'] = $properties[$selectorName]['jcr:createdBy'];
                }
                if (isset($properties[$selectorName]['jcr:created'])) {
                    $standardColumns[$selectorName]['jcr:created'] = $properties[$selectorName]['jcr:created'];
                }
            }

            foreach ($qom->getColumns() as $column) {
                $selectorName = $column->getSelectorName();
                $columnName = $column->getPropertyName();

                if ('jcr:uuid' === $columnName) {
                    $dcrValue = $document->getField(self::IDX_UUID)->getUtf8Value();
                } else {
                    if (isset($properties[$selectorName][$columnName])) {
                        $dcrValue = $properties[$selectorName][$columnName];
                    } else {
                        $dcrValue = '';
                    }
                }

                $dcrValue = 'jcr:uuid' === $columnName
                    ? $row[$columnPrefix . 'identifier']
                    : (isset($properties[$selectorName][$columnName]) ? $properties[$selectorName][$columnName] : '')
                ;

                if (isset($standardColumns[$selectorName][$columnName])) {
                    unset($standardColumns[$selectorName][$columnName]);
                }

                $result[] = array(
                    'dcr:name' => ($column->getColumnName() === $columnName && isset($properties[$selectorName][$columnName])
                        ? $selectorName.'.'.$columnName : $column->getColumnName()),
                    'dcr:value' => $dcrValue,
                    'dcr:selectorName' => $selectorName ?: $primaryType,
                );
            }

            foreach ($standardColumns as $selectorName => $columns) {
                foreach ($columns as $columnName => $value) {
                    $result[] = array(
                        'dcr:name' => $primaryType.'.'.$columnName,
                        'dcr:value' => $value,
                        'dcr:selectorName' => $selectorName,
                    );
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    private function getIndexPath($workspace)
    {
        return sprintf('%s/%s/%s', $this->path, self::SEARCH_PATH, $workspace);
    }

    private function getIndex($workspace)
    {
        if (isset($this->indexes[$workspace])) {
            return $this->indexes[$workspace];
        }

        $indexPath = $this->getIndexPath($workspace);

        if (!file_exists($indexPath)) {
            $index = Lucene::create($indexPath);
        } else {
            $index = Lucene::open($indexPath);
        }

        $this->indexes[$workspace] = $index;

        return $index;
    }

    /**
     * Lazy load because this class is used by the testing implementation loader
     * for indexing, and we do not have a node type manager then.
     */
    private function getQOMWalker()
    {
        if ($this->qomWalker) {
            return $this->qomWalker;
        }

        $this->qomWalker = new ZendSearchQOMWalker($this->nodeTypeManager);

        return $this->qomWalker;
    }
}
