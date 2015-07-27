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
use ZendSearch\Lucene\Search\Query\Wildcard;
use Jackalope\Transport\Fs\Model\Node;
use Jackalope\Transport\Fs\Search\Adapter\Zend\Index;

/**
 * Search adapter for the native PHP ZendSearch Lucene library.
 *
 * Note that this library is not suitable for large amounts of data.
 *
 * If you have speed issues consider using or writing anothe adapter
 * (e.g. for ElasticSearch or Solr)
 */
class ZendSearchAdapter implements SearchAdapterInterface
{
    const SEARCH_PATH = 'meta/zend-search-indexes';
    const IDX_PATH = 'jcr:path';
    const IDX_NODENAME = 'jcr:nodename';
    const IDX_NODELOCALNAME = 'jcr:nodename';
    const IDX_PARENTPATH= 'jcr:parentpath';

    const VALUE_BOOLEAN_FALSE = '__BOOLEAN_FALSE_';
    const MULTIVALUE_SEPARATOR = '__//_';

    private $path;
    private $indexes = array();
    private $filesystem;
    private $qomWalker;
    private $nodeTypeManager;
    private $hideDestructException;

    /**
     * @param string $path Path to search index
     */
    public function __construct($path, NodeTypeManagerInterface $nodeTypeManager = null, $hideDestructException = false)
    {
        $this->path = $path;
        $this->filesystem = new Filesystem();
        $this->nodeTypeManager = $nodeTypeManager;
        $this->hideDestructException = $hideDestructException;
        Analyzer::setDefault(new ExactMatchAnalyzer());
        Wildcard::setMinPrefixLength(0);
    }

    /**
     * {@inheritDoc}
     */
    public function index($workspace, $path, Node $node)
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

        foreach ($node->getProperties() as $propertyName => $property) {
            $propertyValue = $property['value'];
            $propertyType = $property['type'];

            if ($propertyName === Storage::INTERNAL_UUID) {
                $document->addField(Field::Keyword(Storage::INTERNAL_UUID, $propertyValue));
                continue;
            }

            switch ($propertyType) {
                case PropertyType::TYPENAME_STRING:
                case PropertyType::TYPENAME_NAME:
                case PropertyType::TYPENAME_PATH:
                case PropertyType::TYPENAME_URI:
                    $value = (array) $propertyValue;
                    $value = join(self::MULTIVALUE_SEPARATOR, $value);
                    $document->addField(Field::Text($propertyName, $value));
                    break;
                case PropertyType::TYPENAME_DATE:
                    $values = (array) $propertyValue;
                    foreach ($values as $i => $value) {
                        if ($value instanceof \DateTime) {
                            $values[$i] = $value->format('c');
                        }
                    }
                    $value = join(self::MULTIVALUE_SEPARATOR, $values);
                    $document->addField(Field::Text($propertyName, $value));
                    break;
                case PropertyType::TYPENAME_DECIMAL:
                case PropertyType::TYPENAME_LONG:
                case PropertyType::TYPENAME_DOUBLE:
                    $values = (array) $propertyValue;
                    foreach ($values as &$value) {
                        $value = sprintf('%0' . strlen(PHP_INT_MAX) .'s', $value);
                    }
                    $value = join(self::MULTIVALUE_SEPARATOR, $values);
                    $document->addField(Field::Text($propertyName, $value));
                    break;
                case PropertyType::TYPENAME_BOOLEAN:
                    $values = (array) $propertyValue;

                    foreach ($values as &$value) {
                        if ($propertyValue === 'false') {
                            $value = self::VALUE_BOOLEAN_FALSE;
                        } else {
                            $value = 1;
                        }
                    }
                    $value = join(self::MULTIVALUE_SEPARATOR, $values);
                    $document->addField(Field::Text($propertyName, $value));
                    break;
            }
        };

        $index->addDocument($document);
    }

    /**
     * Heavily copied from doctrine-dbal. This should be factored up to Jackalope.
     */
    public function query($workspace, QueryObjectModelInterface $qom)
    {
        $qomWalker = $this->getQomWalker();
        $query = $qomWalker->walkQOMQuery($qom);
        $findArgs = $qomWalker->getOrderings();

        array_unshift($findArgs, $query);

        $index = $this->getIndex($workspace);

        $data = call_user_func_array(array(&$index, 'find'), $findArgs);

        $primarySource = $qomWalker->getSource();
        $primaryType = $primarySource->getSelectorName() ?: $primarySource->getNodeTypeName();
        $selectors = array($primarySource);

        $offset = $qom->getOffset() ? : 0;
        $limit = $qom->getLimit();

        $results = $properties = $standardColumns = array();
        foreach ($data as $i => $hit) {

            // offset and limit
            // note that Lucene provides Lucene::setResultSetLimit but no offset capability 
            if ($i < $offset) {
                continue;
            }
            if (null !== $limit && $i == ($offset + $limit)) {
                break;
            }

            $result = array();
            $document = $hit->getDocument();

            /** @var SelectorInterface $selector */
            foreach ($selectors as $selector) {
                $selectorName   = $selector->getSelectorName() ?: $selector->getNodeTypeName();

                if ($primaryType === $selector->getNodeTypeName()) {
                    $result[] = array(
                        'dcr:name' => 'jcr:path',
                        'dcr:value' => $document->getField(self::IDX_PATH)->getUtf8Value(),
                        'dcr:selectorName' => $selectorName
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
                $propertyName = $column->getPropertyName();

                if (Storage::JCR_UUID === $propertyName) {
                    $dcrValue = $document->getField(self::IDX_UUID)->getUtf8Value();
                } else {
                    if (isset($properties[$selectorName][$propertyName])) {
                        $dcrValue = $properties[$selectorName][$propertyName];
                    } else {
                        $dcrValue = '';
                    }
                }

                if (isset($standardColumns[$selectorName][$propertyName])) {
                    unset($standardColumns[$selectorName][$propertyName]);
                }

                $result[] = array(
                    'dcr:name' => ($column->getColumnName() === $propertyName && isset($properties[$selectorName][$propertyName])
                        ? $selectorName.'.'.$propertyName : $column->getColumnName()),
                    'dcr:value' => $this->normalizeValue($dcrValue),
                    'dcr:selectorName' => $selectorName ? : $primaryType,
                );
            }

            foreach ($standardColumns as $selectorName => $columns) {
                foreach ($columns as $propertyName => $value) {
                    $result[] = array(
                        'dcr:name' => $primaryType.'.'.$propertyName,
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
            $index = new Index($indexPath, true);
        } else {
            $index = new Index($indexPath, false);
        }

        $index->setHideException($this->hideDestructException);

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

    /**
     * Normalize the given value.
     * The index uses some special values, f.e. for boolean
     */
    private function normalizeValue($value)
    {
        if ($value === self::VALUE_BOOLEAN_FALSE) {
            return false;
        }

        if (strstr($value, self::MULTIVALUE_SEPARATOR)) {
            $value = explode(self::MULTIVALUE_SEPARATOR, $value);
        }

        return $value;
    }
}
