<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializerInterface;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;
use PHPCR\Util\PathHelper;
use PHPCR\PropertyType;
use Jackalope\Transport\Fs\Filesystem\Storage\Index;
use Jackalope\Transport\Fs\Model\NodeCollection;

class NodeReader
{
    private $pathRegistry;
    private $serializer;
    private $filesystem;
    private $helper;
    private $index;

    public function __construct(
        Filesystem $filesystem,
        Index $index,
        NodeSerializerInterface $serializer,
        PathRegistry $pathRegistry,
        StorageHelper $helper
    ) {
        $this->pathRegistry = $pathRegistry;
        $this->serializer = $serializer;
        $this->filesystem = $filesystem;
        $this->helper = $helper;
        $this->index = $index;
    }

    /**
     * @param string $workspace
     * @param string $path
     * @param boolean $censor Do not return internal properties
     *
     * @return Node
     */
    public function readNode($workspace, $path)
    {
        $nodeData = $this->filesystem->read($this->helper->getNodePath($workspace, $path));

        if (!$nodeData) {
            throw new \RuntimeException(sprintf(
                'No node data at path "%s".', $path
            ));
        }

        $node = $this->serializer->deserialize($nodeData);

        // Move to node ?
        if (false === $node->hasProperty('jcr:mixinTypes')) {
            $node->setProperty('jcr:mixinTypes', array(), 'Name');
        }

        $nodePath = $this->helper->getNodePath($workspace, $path, false);
        $children = $this->filesystem->ls($nodePath);
        $children = $children['dirs'];

        foreach ($children as $childName) {
            $node->addChildName($childName);
        }

        // the user shouldn't know about the internal UUID
        if (false === $node->hasProperty(Storage::INTERNAL_UUID)) {
            throw new \RuntimeException(sprintf('Internal UUID propery (%s) not set on node at path "%s". This should not happen!', Storage::INTERNAL_UUID, $path));
        }

        $internalUuid = $node->getPropertyValue(Storage::INTERNAL_UUID);

        $this->pathRegistry->registerUuid($path, $internalUuid);

        $node->setProperty('jcr:path', $path);

        return $node;
    }

    public function readNodesByUuids(array $uuids, $internal = false)
    {
        $nodeCollection = new NodeCollection();

        foreach ($uuids as $uuid) {
            $location = $this->index->getNodeLocationForUuid($uuid, $internal);

            if (null === $location) {
                continue;
            }

            try {
                $node = $this->readNode($location->getWorkspace(), $location->getPath());
                $nodeCollection[$location->getPath()] = $node;
            } catch (\InvalidArgumentException $e) {
                // not found
            }
        }

        return $nodeCollection;
    }

    public function readBinaryStream($workspace, $path)
    {
        $parentPath = PathHelper::getParentPath($path);
        $propertyName = PathHelper::getNodeName($path);

        $nodeData = $this->filesystem->read($this->helper->getNodePath($workspace, $parentPath));

        if (!$nodeData) {
            throw new \RuntimeException(sprintf(
                'No node data at path "%s".', $path
            ));
        }
        $this->serializer->deserialize($nodeData);
        $binaryHashMap = $this->serializer->getBinaryHashMap();

        if (!isset($binaryHashMap[$propertyName])) {
            throw new \InvalidArgumentException(sprintf(
                'Could not locate binary for property at path "%s"',
                $path
            ));
        }

        $originalBinaryHash = $binaryHashMap[$propertyName];
        $binaryHashes = (array) $originalBinaryHash;
        
        $streams = array();
        foreach ($binaryHashes as $binaryHash) {
            $path = $this->helper->getBinaryPath($workspace, $parentPath, $binaryHash);
            $streams[] = $this->filesystem->stream($path);
        }

        $res = is_array($originalBinaryHash) ? $streams : reset($streams);

        return $res;
    }

    public function readNodeReferrers($workspace, $path, $weak = false, $name)
    {
        $node = $this->readNode($workspace, $path);

        // tests say that we should return an empty iteratable when node is not referenceable
        if (false === $node->hasProperty('jcr:uuid')) {
            return array();
        }

        $uuid = $node->getPropertyValue('jcr:uuid');

        $referrers = $this->index->getReferringProperties($uuid, $name, $weak);
        $referrerPaths = array();

        foreach ($referrers as $internalUuid => $propertyNames) {
            foreach (array_keys($propertyNames) as $propertyName) {
                $referrer = $this->readNodesByUuids(array($internalUuid), true);
                $referrerPaths[] = sprintf('%s/%s', key($referrer), $propertyName);
            }
        }

        return $referrerPaths;
    }
}
