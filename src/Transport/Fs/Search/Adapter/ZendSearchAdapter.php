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

class ZendSearchAdapter implements SearchAdapterInterface
{
    const SEARCH_PATH = 'zend-search-indexes';

    private $path;
    private $indexes = array();
    private $filesystem;

    /**
     * @param string $path Path to search index
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->filesystem = new Filesystem();
    }

    /**
     * {@inheritDoc}
     */
    public function index($workspace, $path, $nodeData)
    {
        $index = $this->getIndex($workspace);
        $document = new Document();

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

            switch ($typeName) {
                case PropertyType::STRING:
                    $document->addField(Field::Text($propertyName, $propertyValue));
            }


        } while (current($nodeData));

        $index->addDocument($document);
    }

    /**
     * {@inheritDoc}
     */
    public function query($workspace, QueryObjectModelInterface $qom)
    {
        $index = $this->getIndex($workspace);

        throw new \Exception('I am here');
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
