<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializerInterface;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;

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

        return $node;
    }

    public function readNodesByUuids(array $uuids, $internal = false)
    {
        $nodes = array();

        foreach ($uuids as $uuid) {
            $indexName = $internal ? Storage::IDX_INTERNAL_UUID : Storage::IDX_JCR_UUID;
            $path = Storage::INDEX_DIR . '/' . $indexName . '/' . $uuid;

            if (!$this->filesystem->exists($path)) {
                throw new \InvalidArgumentException(sprintf(
                    'Index "%s" of type "%s" does not exist', $uuid, $indexName
                ));
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
        $propertyValues = $this->getPropertyValue($workspace, $path, 'Binary');
        $nodePath = $this->helper->getNodePath($workspace, dirname($path));
        $streams = array();

        foreach ((array) $propertyValues as $propertyValue) {
            $binaryPath = sprintf('%s/%s.bin', dirname($nodePath), $propertyValue);

            if (!$this->filesystem->exists($binaryPath)) {
                throw new \RuntimeException(sprintf(
                    'Expected binary file for property "%s" to exist at path "%s" but it doesn\'t',
                    $path, $binaryPath
                ));
            }

            $streams[] = $this->filesystem->stream($binaryPath);
        }

        if (is_array($propertyValues)) {
            return $streams;
        }

        return reset($streams);
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
        $internalUuids = array();

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

    private function getPropertyValue($workspace, $path, $type)
    {
        $node = $this->readNode($workspace, dirname($path));
        $propertyName = basename($path);

        if (!isset($node->{$propertyName})) {
            return null;
        }

        $propertyType = $node->{':' . $propertyName};

        if ($propertyType !== $type) {
            throw new \InvalidArgumentException(sprintf(
                'Expected property to be of type "%s" but it is of type "%s"',
                $type, $propertyType
            ));
        }

        $propertyValue = $node->{$propertyName};
        return $propertyValue;
    }
}
