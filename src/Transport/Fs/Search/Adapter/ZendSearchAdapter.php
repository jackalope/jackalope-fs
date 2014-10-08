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
    public function query($workspace, QueryObjectModelInterface $qom)
    {
        $index = $this->getIndex($workspace);
        list($selectors, $selectorAliases, $query) = $this->qomWalker->walkQOMQuery($qom);
        $results = $index->find($query);

        $selectorName = $this->qomWalker->getSource();
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
