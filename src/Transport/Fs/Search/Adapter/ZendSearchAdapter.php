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

    /**
     * @param string $path Path to search index
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->filesystem = new Filesystem();
        $this->qomWalker = new ZendSearchQOMWalker();
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
        $localNodeName = PathHelper::getLocalNodeName($path);
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
     * {@inheritDoc}
     * array(
     *     //row 1
     *     array(
     *         //column1
     *         array('dcr:name' => 'value1',
     *               'dcr:value' => 'value2',
     *               'dcr:selectorName' => 'value3' //optional
     *         ),
     *         //column 2...
     *     ),
     *     //row 2
     *     array(...
     * )
     */
    public function queryOld($workspace, QueryObjectModelInterface $qom)
    {
        $query = $this->qomWalker->walkQOMQuery($qom);
        $index = $this->getIndex($workspace);
        $results = $index->find($query);

        $selector = $this->qomWalker->getSource();
        $selectorName = $selector->getSelectorName() ?: $selector->getNodeTypeName();

        $rows = array();
        $columns = $qom->getColumns();
        $columnNames = array();

        foreach ($columns as $column) {
            $columnPropertyNames[$column->getPropertyName()] = $column;
            $columnNames[$column->getColumnName()] = $column;
        }

        foreach ($results as $result) {
            $selectedColumns = array();

            $row = array();
            $document = $result->getDocument();

            foreach ($document->getFieldNames() as $fieldName) {
                if ($columns) {
                    if (!isset($columnPropertyNames[$fieldName])) {
                        continue;
                    }

                    $column = $columnPropertyNames[$fieldName];
                    $name = $column->getColumnName();
                } else {
                    if ($fieldName === 'jcr:path') {
                        $name = $fieldName;
                    } else {
                        $name = $selectorName . '.' . $fieldName;
                    }
                }

                $field = $document->getField($fieldName);
                $row[] = array(
                    'dcr:name' => $name,
                    'dcr:value' => $field->getUtf8Value()
                );

                $selectedColumns[$name] = $name;
            }

            // add any columns which were specified by not contained in the results
            foreach (array_keys($columnNames) as $columnName) {
                if (false === isset($selectedColumns[$columnName])) {
                    $row[] = array(
                        'dcr:name' => $columnName,
                        'dcr:value' =>  '',
                    );
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function query($workspace, $qom)
    {
        $query = $this->qomWalker->walkQOMQuery($qom);
        $index = $this->getIndex($workspace);
        $data = $index->find($query);

        $primarySource = $this->qomWalker->getSource();
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
                        'dcr:value' => $document->getField(self::IDX_PATH)
                    );
                }

                $result[] = array(
                    'dcr:name' => 'jcr:path',
                    'dcr:value' => $document->getField(self::IDX_PATH),
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
}
