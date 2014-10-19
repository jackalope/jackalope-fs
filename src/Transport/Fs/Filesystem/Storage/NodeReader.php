<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializerInterface;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;
use PHPCR\Util\PathHelper;

class NodeReader
{
    private $pathRegistry;
    private $serializer;
    private $filesystem;
    private $helper;

    public function __construct(
        Filesystem $filesystem,
        NodeSerializerInterface $serializer,
        PathRegistry $pathRegistry,
        StorageHelper $helper
    )
    {
        $this->pathRegistry = $pathRegistry;
        $this->serializer = $serializer;
        $this->filesystem = $filesystem;
        $this->helper = $helper;
    }

    public function readNode($workspace, $path)
    {
        $nodeData = $this->filesystem->read($this->helper->getNodePath($workspace, $path));

        if (!$nodeData) {
            throw new \RuntimeException(sprintf(
                'No node data at path "%s".', $path
            ));
        }

        $node = $this->serializer->deserialize($nodeData);

        if (!isset($node->{'jcr:mixinTypes'})) {
            $node->{'jcr:mixinTypes'} = array();
        }

        $nodePath = $this->helper->getNodePath($workspace, $path, false);
        $children = $this->filesystem->ls($nodePath);
        $children = $children['dirs'];

        foreach ($children as $childName) {
            $node->{$childName} = new \stdClass();
        }

        // the user shouldn't know about the internal UUID
        if (!isset($node->{Storage::INTERNAL_UUID})) {
            throw new \RuntimeException(sprintf('Internal UUID propery (%s) not set on node at path "%s". This should not happen!', Storage::INTERNAL_UUID, $path));
        }

        $internalUuid = $node->{Storage::INTERNAL_UUID};

        $this->pathRegistry->registerUuid($path, $internalUuid);
        unset($node->{Storage::INTERNAL_UUID});
        // we store the lengths as the values

        return $node;
    }

    public function readNodesByUuids(array $uuids, $internal = false)
    {
        $nodes = array();

        foreach ($uuids as $uuid) {
            $indexName = $internal ? Storage::IDX_INTERNAL_UUID : Storage::IDX_JCR_UUID;
            $path = Storage::INDEX_DIR . '/' . $indexName . '/' . $uuid;

            if (!$this->filesystem->exists($path)) {
                continue;
            }

            $value = $this->filesystem->read($path);
            $workspace = strstr($value, ':', true);
            $path = substr($value, strlen($workspace) + 1);

            $node = $this->readNode($workspace, $path);
            $nodes[$path] = $node;
        }

        return $nodes;
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

        return is_array($originalBinaryHash) ? $streams : reset($streams);
    }

    public function readNodeReferrers($workspace, $path, $weak = false, $name)
    {
        $node = $this->readNode($workspace, $path);

        // tests say that we should return an empty iteratable when node is not referenceable
        if (!isset($node->{'jcr:uuid'})) {
            return array();
        }

        $uuid = $node->{'jcr:uuid'};

        $indexName = $weak === true ? Storage::IDX_WEAKREFERRERS_DIR : Storage::IDX_REFERRERS_DIR;

        $path = Storage::INDEX_DIR . '/' . $indexName . '/' . $uuid;

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

        $referrerPaths = array();

        foreach ($propertyNames as $propertyName => $internalUuid) {

            $referrer = $this->readNodesByUuids(array($internalUuid), true);
            $referrerPaths[] = sprintf('%s/%s', key($referrer), $propertyName);
        }

        return $referrerPaths;
    }
}
