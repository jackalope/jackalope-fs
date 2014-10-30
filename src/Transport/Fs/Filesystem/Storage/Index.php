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
    const IDX_REFERRERS_REV_DIR = 'referrers-rev';
    const IDX_WEAKREFERRERS_DIR = 'referrers-weak';
    const IDX_WEAKREFERRERS_REV_DIR = 'referrers-weak-rev';
    const IDX_JCR_UUID = 'jcr-uuid';
    const IDX_INTERNAL_UUID = 'internal-uuid';

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param Filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Return the internal UUID of referring nodes with a list of the referring
     * properties.
     *
     * e.g. array(
     *      '<node-internal-uuid>': array(
     *          'referringProperty1',
     *          'referringProperty2',
     *      ))
     *
     * @param string $uuid
     * @param string $name Optionally check only for the named property
     * @param boolean $weak To retrieve weak or "strong" references
     *
     * @return ReferreringPropertyCollection
     */
    public function getReferringProperties($uuid, $name = null, $weak = false)
    {
        $indexName = $weak === true ? self::IDX_WEAKREFERRERS_DIR : self::IDX_REFERRERS_DIR;

        $values = $this->readIndex($indexName, $uuid);

        $properties = array();

        foreach ($values as $line) {
            $propertyName = strstr($line, ':', true);

            if (null !== $name && $name != $propertyName) {
                continue;
            }

            $internalUuid = substr($line, strlen($propertyName) + 1);

            if (!isset($properties[$internalUuid])) {
                $properties[$internalUuid] = array();
            }

            $properties[$internalUuid][$propertyName] = $propertyName;
        }

        return $properties;
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
        if (null === $value = $this->readOne($indexName, $uuid)) {
            return null;
        }

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

    /**
     * Remove an indexed UUID
     */
    public function deindexUuid($uuid, $internal = false)
    {
        $indexName = $internal ? self::IDX_INTERNAL_UUID : self::IDX_JCR_UUID;
        $this->deleteIndex($indexName, $uuid);

        if ($internal === false) {
            $this->deleteIndex(self::IDX_WEAKREFERRERS_DIR, $uuid);
            $this->deleteIndex(self::IDX_REFERRERS_DIR, $uuid);
        }
    }

    /**
     * Index the reference from a node referrer
     *
     * @param string $referrerUuid
     * @param string $referrerPropertyName
     * @param string $referencedUuid
     * @param boolean $weak Index a strong or a weak reference
     */
    public function indexReferrer($referrerInternalUuid, $referrerPropertyName, $referencedJcrUuid, $weak = false)
    {
        $indexName = $weak ? self::IDX_WEAKREFERRERS_DIR : self::IDX_REFERRERS_DIR;
        $revIndexName = $weak ? self::IDX_WEAKREFERRERS_REV_DIR : self::IDX_REFERRERS_REV_DIR;
        $indexValue = $referrerPropertyName . ':' . $referrerInternalUuid;
        $this->appendToIndex($indexName, $referencedJcrUuid, $indexValue);
        $this->createIndex($revIndexName, $indexValue, $referencedJcrUuid);
    }

    /**
     * Deindex the reference from a node referrer
     *
     * @param string $referrerUuid
     * @param string $referrerPropertyName
     * @param string $referencedUuid
     * @param boolean $weak Index a strong or a weak reference
     */
    public function deindexReferrer($referrerInternalUuid, $referrerPropertyName, $weak)
    {
        if ($weak) {
            $idxName = self::IDX_WEAKREFERRERS_DIR;
            $revIdxName = self::IDX_WEAKREFERRERS_REV_DIR;
        } else {
            $idxName = self::IDX_REFERRERS_DIR;
            $revIdxName = self::IDX_REFERRERS_REV_DIR;
        }

        $indexValue = $referrerPropertyName . ':' . $referrerInternalUuid;
        $referrerReferrer = $this->readOne($revIdxName, $indexValue);

        if (false === $referrerReferrer) {
            throw new \RuntimeException(sprintf(
                'Could not find reverse index for "%s". Maybe you want to repair your index?',
                $indexValue
            ));
        }

        $this->deleteFromIndexEntry($idxName, $referrerReferrer, $indexValue);
        $this->deleteIndex($revIdxName, $indexValue);
    }

    private function createIndex($indexName, $name, $value)
    {
        $this->writeIndex($indexName, $name, $value);
    }

    private function deleteIndex($indexName, $name)
    {
        $this->filesystem->remove(self::INDEX_DIR . '/' . $indexName . '/' . $name);
    }

    private function deleteFromIndexEntry($indexName, $entryName, $value)
    {
        $currentIndex = $this->readIndex($indexName, $entryName);
        $newIndex = array();
        foreach ($currentIndex as $line) {
            if ($line === $value) {
                continue;
            }

            $newIndex[] = $line;
        }

        $this->writeIndex($indexName, $entryName, $newIndex);
    }

    private function appendToIndex($indexName, $name, $value)
    {
        $indexPath = $this->getIndexPath($indexName, $name);

        if (!$this->filesystem->exists($indexPath)) {
            $this->filesystem->write($indexPath, $value);
            return;
        }

        $index = $this->readIndex($indexName, $name);
        $index[] = $value;
        $this->writeIndex($indexName, $name, $index);
    }

    private function readIndex($indexName, $entryName)
    {
        $path = $this->getIndexPath($indexName, $entryName);

        if (!$this->filesystem->exists($path)) {
            return array();
        }

        $value = $this->filesystem->read($path);
        if ('' === $value) {
            return array();
        }

        $values = explode("\n", $value);

        return $values;
    }

    private function readOne($indexName, $entryName)
    {
        $values = $this->readIndex($indexName, $entryName);

        if (count($values) > 1) {
            throw new \InvalidArgumentException(sprintf(
                'Index "%s" contains more than one entry when trying to retrieve entry "%s"',
                $indexName,
                $entryName
            ));
        }

        return reset($values);
    }

    private function getIndexPath($indexName, $entryName)
    {
        $path = self::INDEX_DIR . '/' . $indexName . '/' . $entryName;

        return $path;
    }

    private function writeIndex($indexName, $entryName, $values)
    {
        $indexPath = $this->getIndexPath($indexName, $entryName);
        $data = implode("\n", (array) $values);
        $this->filesystem->write($indexPath, $data);
    }
}
