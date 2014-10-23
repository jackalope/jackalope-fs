<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Model\NodeLocation;
use Jackalope\Transport\Fs\Filesystem\Filesystem;

/**
 * Filesystem index
 * Responsible for managing relational indexes
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class Index
{
    const INDEX_DIR = '/indexes';
    const IDX_REFERRERS_DIR = 'referrers';
    const IDX_WEAKREFERRERS_DIR = 'referrers-weak';
    const IDX_JCR_UUID = 'jcr-uuid';
    const IDX_INTERNAL_UUID = 'internal-uuid';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Return all propertyNames and the internal FS UUID of their respective
     * nodes that refer to the given UUID
     *
     * @param string $uuid
     * @param boolean $weak To retrieve weak or "strong" references
     *
     * @return array
     */
    public function getReferringProperties($uuid, $name = null, $weak = false)
    {
        $indexName = $weak === true ? self::IDX_WEAKREFERRERS_DIR : self::IDX_REFERRERS_DIR;
        $path = self::INDEX_DIR . '/' . $indexName . '/' . $uuid;

        if (!$this->filesystem->exists($path)) {
            return array();
        }

        $value = $this->filesystem->read($path);
        $values = explode("\n", $value);

        $propertyNames = array();

        foreach ($values as $line) {
            $propertyName = strstr($line, ':', true);

            if (null !== $name && $name != $propertyName) {
                continue;
            }

            $internalUuid = substr($line, strlen($propertyName) + 1);
            $propertyNames[$propertyName] = $internalUuid;
        }

        return $propertyNames;
    }

    /**
     * Return the location of the node with the given UUID
     *
     * Return NULL if UUID was not found
     *
     * @return NodeLocation|null
     */
    public function getNodeLocationForUuid($uuid, $internal = false)
    {
        $indexName = $internal ? self::IDX_INTERNAL_UUID : self::IDX_JCR_UUID;
        $path = self::INDEX_DIR . '/' . $indexName . '/' . $uuid;

        if (!$this->filesystem->exists($path)) {
            return null;
        }

        $value = $this->filesystem->read($path);
        $workspace = strstr($value, ':', true);
        $path = substr($value, strlen($workspace) + 1);

        $nodeLocation = new NodeLocation($workspace, $path);

        return $nodeLocation;
    }

    /**
     * Create an index for a UUID, either internal or jcr:uuid
     * as indicated by the $internal flag
     *
     * @param string $uuid
     * @param string $workspace
     * @param string $path
     * @param boolean $internal
     */
    public function indexUuid($uuid, $workspace, $path, $internal = false)
    {
        $indexName = $internal ? self::IDX_INTERNAL_UUID : self::IDX_JCR_UUID;
        $this->createIndex($indexName, $uuid, $workspace . ':' . $path);
    }

    public function indexReferrer($referrerUuid, $referrerPropertyName, $referencedUuid, $weak = false)
    {
        $indexName = $weak ? self::IDX_WEAKREFERRERS_DIR : self::IDX_REFERRERS_DIR;
        $this->appendToIndex($indexName, $referencedUuid, $referrerPropertyName . ':' . $referrerUuid);
    }

    private function createIndex($type, $name, $value)
    {
        $this->filesystem->write(self::INDEX_DIR . '/' . $type . '/' . $name, $value);
    }

    private function appendToIndex($type, $name, $value)
    {
        $indexPath = self::INDEX_DIR . '/' . $type . '/' . $name;

        if (!$this->filesystem->exists($indexPath)) {
            $this->filesystem->write($indexPath, $value);
            return;
        }

        $index = $this->filesystem->read($indexPath);
        $index .= "\n" . $value;
        $this->filesystem->write($indexPath, $index);
    }
}
