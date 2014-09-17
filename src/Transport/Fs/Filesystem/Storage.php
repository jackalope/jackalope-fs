<?php

namespace Jackalope\Transport\Fs\Filesystem;

use PHPCR\Util\PathHelper;
use Jackalope\Transport\Fs\NodeSerializer\YamlNodeSerializer;

class Storage
{
    const INDEX_DIR = '/indexes';
    const WORKSPACE_PATH = '/workspaces';

    protected $filesystem;
    protected $serializer;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->serializer = new YamlNodeSerializer();
    }

    public function writeNode($workspace, $path, $nodeData)
    {
        $serialized = $this->serializer->serialize($nodeData);
        $absPath = $this->getNodePath($workspace, $path);
        $this->filesystem->write($absPath, $serialized);

        if (isset($nodeData['jcr:uuid'])) {
            $uuid = $nodeData['jcr:uuid'];
            $this->createIndex('uuid', $uuid, $workspace . ':' . $path);
        }
    }

    public function readNode($workspace, $path)
    {
        $nodeData = $this->filesystem->read($this->getNodePath($workspace, $path));

        if (!$nodeData) {
            throw new \RuntimeException(sprintf(
                'No node data at path "%s".', $path
            ));
        }

        $node = $this->serializer->deserialize($nodeData);

        return $node;
    }

    public function readNodesByIndexes($type, array $keys)
    {
        $nodes = array();

        foreach ($keys as $key) {
            $path = self::INDEX_DIR . '/' . $type . '/' . $key;

            if (!$this->filesystem->exists($path)) {
                throw new \InvalidArgumentException(sprintf(
                    'Index "%s" of type "%s" does not exist', $key, $type
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

    public function remove($path, $recursive = false)
    {
        $this->filesystem->remove($path, $recursive);
    }

    public function nodeExists($workspace, $path)
    {
        return $this->filesystem->exists($this->getNodePath($workspace, $path));
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
        $fsPath = dirname($this->getNodePath($workspace, $path));
        $list = $this->filesystem->ls($fsPath);

        return $list;
    }

    private function createIndex($type, $name, $value)
    {
        $this->filesystem->write(self::INDEX_DIR . '/' . $type . '/' . $name, $value);
    }

    private function getNodePath($workspace, $path)
    {
        $path = PathHelper::normalizePath($path);

        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }

        if ($path) {
            $path .= '/';
        }

        $nodeRecordPath = self::WORKSPACE_PATH . '/' . $workspace . '/' . $path . 'node.yml';

        return $nodeRecordPath;
    }
}
