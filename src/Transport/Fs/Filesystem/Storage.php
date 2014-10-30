<?php

namespace Jackalope\Transport\Fs\Filesystem;

use Jackalope\Transport\Fs\Event\NodeWriteEvent;
use Jackalope\Transport\Fs\Events;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use Jackalope\Transport\Fs\Filesystem\Storage\NodeCopier;
use Jackalope\Transport\Fs\Filesystem\Storage\NodeReader;
use Jackalope\Transport\Fs\Filesystem\Storage\NodeWriter;
use Jackalope\Transport\Fs\Filesystem\Storage\StorageHelper;
use Jackalope\Transport\Fs\NodeSerializer\YamlNodeSerializer;
use Jackalope\Transport\Fs\Filesystem\Storage\Index;

use PHPCR\Util\PathHelper;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Jackalope\Transport\Fs\Model\Node;
use Jackalope\Transport\Fs\Filesystem\Storage\NodeRemover;

class Storage
{
    const WORKSPACE_PATH = '/workspaces';
    const NAMESPACE_FILE = '/namespaces';
    const NS_DELIMITER = ':::';

    const INTERNAL_UUID = 'jackalope:fs:id';
    const JCR_UUID = 'jcr:uuid';
    const JCR_MIXINTYPES = 'jcr:mixinTypes';

    private $filesystem;
    private $nodeWriter;
    private $nodeReader;
    private $helper;
    private $eventDispatcher;
    private $index;

    public function __construct(Filesystem $filesystem, EventDispatcher $eventDispatcher = null)
    {
        $this->filesystem = $filesystem;
        $serializer = new YamlNodeSerializer();
        $pathRegistry = new PathRegistry();
        $this->helper = new StorageHelper();
        $this->index = new Index($this->filesystem);

        $this->nodeWriter = new NodeWriter($this->filesystem, $this->index, $serializer, $pathRegistry, $this->helper);
        $this->nodeReader = new NodeReader($this->filesystem, $this->index, $serializer, $pathRegistry, $this->helper);
        $this->nodeCopier = new NodeCopier($this->nodeReader, $this->nodeWriter);
        $this->nodeRemover = new NodeRemover($this->nodeReader, $this->filesystem, $this->index, $this->helper);
        $this->eventDispatcher = $eventDispatcher ? : new EventDispatcher();
    }

    public function writeNode($workspace, $path, $nodeData)
    {
        $nodeData = $this->nodeWriter->writeNode($workspace, $path, $nodeData);
        $this->eventDispatcher->dispatch(Events::POST_WRITE_NODE, new NodeWriteEvent($workspace, $path, $nodeData));
    }

    public function readNode($workspace, $path)
    {
        $node = $this->nodeReader->readNode($workspace, $path);
        $node->censor();

        return $node;
    }

    public function readNodesByUuids(array $uuids, $internal = false)
    {
        return $this->nodeReader->readNodesByUuids($uuids, $internal);
    }

    public function readBinaryStream($workspace, $path)
    {
        return $this->nodeReader->readBinaryStream($workspace, $path);
    }

    public function readNodeReferrers($workspace, $path, $weak = false, $name)
    {
        return $this->nodeReader->readNodeReferrers($workspace, $path, $weak, $name);
    }

    public function removeNode($workspace, $path)
    {
        $this->nodeRemover->removeNode($workspace, $path);
    }

    public function removeProperty($workspace, $path)
    {
        $propertyName = PathHelper::getNodeName($path);
        $nodePath = PathHelper::getParentPath($path);
        $node = $this->nodeReader->readNode($workspace, $nodePath);

        $property = $node->getProperty($propertyName);

        if (in_array($property['type'], array('Reference', 'WeakReference'))) {
            $this->index->deindexReferrer(
                $node->getPropertyValue(Storage::INTERNAL_UUID),
                $propertyName,
                $property['type'] === 'Reference' ? false : true
            );
        }

        $node->removeProperty($propertyName);
        $this->nodeWriter->writeNode($workspace, $path, $node);
    }

    public function moveNode($workspace, $srcPath, $destPath)
    {
        $destPath = $this->helper->getNodePath($workspace, $destPath, false);
        $this->filesystem->move(
            $this->helper->getNodePath($workspace, $srcPath, false),
            $destPath
        );
    }

    public function copyNode($srcWorkspace, $srcAbsPath, $destWorkspace, $destAbsPath)
    {
        $this->nodeCopier->copyNode(
            $srcWorkspace, $srcAbsPath,
            $destWorkspace, $destAbsPath
        );
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
        $node = new Node();
        $node->setProperty('jcr:primaryType', 'nt:unstructured', 'Name');
        $this->writeNode($name, '/', $node);
    }

    public function ls($workspace, $path)
    {
        $fsPath = dirname($this->helper->getNodePath($workspace, $path));
        $list = $this->filesystem->ls($fsPath);

        return $list;
    }

    public function registerNamespace($workspaceName, $prefix, $uri)
    {
        $ns = $prefix . self::NS_DELIMITER . $uri;
        $out = array();
        if (false === $this->filesystem->exists(self::NAMESPACE_FILE)) {
            $out[] = $ns;
        } else {
            $out = explode("\n", $this->filesystem->read(self::NAMESPACE_FILE));
            $out[] = $ns;
        }

        $this->filesystem->write(self::NAMESPACE_FILE, implode("\n", $out));
    }

    public function unregisterNamespace($workspaceName, $targetPrefix)
    {
        $out = array();

        if (false === $this->filesystem->exists(self::NAMESPACE_FILE)) {
            return true;
        } else {
            $namespaces = explode("\n", $this->filesystem->read(self::NAMESPACE_FILE));
            foreach ($namespaces as $namespace) {
                list($prefix, $uri) = explode(self::NS_DELIMITER, $namespace);
                if ($prefix !== $targetPrefix) {
                    $out[] = $namespace;
                }
            }
        }

        $this->filesystem->write(self::NAMESPACE_FILE, implode("\n", $out));

        return true;
    }

    public function commit()
    {
        $this->eventDispatcher->dispatch(Events::COMMIT, new Event());
    }

    public function getNamespaces()
    {
        $res = array();

        if (!$this->filesystem->exists(self::NAMESPACE_FILE)) {
            return $res;
        }

        $namespaces = explode("\n", $this->filesystem->read(self::NAMESPACE_FILE));
        foreach ($namespaces as $namespace) {
            list($alias, $namespace) = explode(self::NS_DELIMITER, $namespace);
            $res[$alias] = $namespace;
        }

        return $res;
    }
}
