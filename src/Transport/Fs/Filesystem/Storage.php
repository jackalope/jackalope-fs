<?php

namespace Jackalope\Transport\Fs\Filesystem;

use Jackalope\Transport\Fs\NodeSerializer\YamlNodeSerializer;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use Jackalope\Transport\Fs\Filesystem\Storage\NodeWriter;
use Jackalope\Transport\Fs\Filesystem\Storage\StorageHelper;

class Storage
{
    const INDEX_DIR = '/indexes';
    const WORKSPACE_PATH = '/workspaces';
    const IDX_REFERRERS_DIR = 'referrers';
    const IDX_WEAKREFERRERS_DIR = 'referrers-weak';
    const IDX_JCR_UUID = 'jcr-uuid';
    const IDX_INTERNAL_UUID = 'internal-uuid';
    const INTERNAL_UUID = 'jackalope:fs:id';

    private $filesystem;
    private $serializer;
    private $pathRegistry;
    private $nodeWriter;
    private $helper;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->serializer = new YamlNodeSerializer();
        $this->pathRegistry = new PathRegistry();
        $this->helper = new StorageHelper();

        $this->nodeWriter = new NodeWriter($this->filesystem, $this->serializer, $this->pathRegistry, $this->helper);
    }

    public function writeNode($workspace, $path, $nodeData)
    {
        $this->nodeWriter->writeNode($workspace, $path, $nodeData);
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
        if (!isset($node->{self::INTERNAL_UUID})) {
            throw new \RuntimeException(sprintf('Internal UUID propery (%s) not set on node at path "%s". This should not happen!', self::INTERNAL_UUID, $path));
        }

        $internalUuid = $node->{self::INTERNAL_UUID};

        $this->pathRegistry->registerUuid($path, $internalUuid);
        unset($node->{self::INTERNAL_UUID});

        return $node;
    }

    public function readNodesByUuids(array $uuids, $internal = false)
    {
        $nodes = array();

        foreach ($uuids as $uuid) {
            $indexName = $internal ? self::IDX_INTERNAL_UUID : self::IDX_JCR_UUID;
            $path = self::INDEX_DIR . '/' . $indexName . '/' . $uuid;

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

    public function readNodeReferrers($workspace, $path, $weak = false, $name)
    {
        $node = $this->readNode($workspace, $path);

        // tests say that we should return an empty iteratable when node is not referenceable
        if (!isset($node->{'jcr:uuid'})) {
            return array();
        }

        $uuid = $node->{'jcr:uuid'};

        $indexName = $weak === true ? self::IDX_WEAKREFERRERS_DIR : self::IDX_REFERRERS_DIR;

        $path = self::INDEX_DIR . '/' . $indexName . '/' . $uuid;

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

    public function remove($path, $recursive = false)
    {
        $this->filesystem->remove($path, $recursive);
    }

    public function nodeExists($workspace, $path)
    {
        return $this->filesystem->exists($this->helper->getNodePath($workspace, $path));
    }

    public function workspaceExists($name)
    {
        return $this->filesystem->exists(self::WORKSPACE_PATH . '/' . $name);
    }

    public function workspaceRemove($name)
    {
        $this->filesystem->remove(self::WORKSPACE_PATH . '/' . $name);
    }

    public function workspaceList()
    {
        $list = $this->filesystem->ls(self::WORKSPACE_PATH);
        return $list['dirs'];
    }

    public function workspaceInit($name)
    {
        $this->writeNode($name, '/', array(
            'jcr:primaryType' => 'rep:root',
            ':jcr:primaryType' => 'Name',
        ));
    }

    public function ls($workspace, $path)
    {
        $fsPath = dirname($this->helper->getNodePath($workspace, $path));
        $list = $this->filesystem->ls($fsPath);

        return $list;
    }
}
