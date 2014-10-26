<?php

namespace Jackalope\Transport\Fs\Test;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;
use Jackalope\Transport\Fs\Filesystem\Filesystem as FsFilesystem;
use Jackalope\Transport\Fs\Filesystem\Adapter\LocalAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Jackalope\Transport\Fs\Search\IndexSubscriber;
use Jackalope\Transport\Fs\Search\Adapter\ZendSearchAdapter;
use Jackalope\Transport\Fs\Model\Node;

class FixtureGenerator
{
    const NS_SV = 'http://www.jcp.org/jcr/sv/1.0';

    protected $fs;

    function generateFixtures($workspaceName, $dataDir, $srcDir)
    {
        $this->workspaceName = $workspaceName;
        $eventDispatcher = new EventDispatcher();
        $searchAdapter = new ZendSearchAdapter($dataDir);

        $eventDispatcher->addSubscriber(
            new IndexSubscriber($searchAdapter)
        );

        $this->storage = new Storage(new FsFilesystem(new LocalAdapter($dataDir)), $eventDispatcher);
        $this->storage->registerNamespace($this->workspaceName, 'test', 'http://example.com');

        $this->storage->workspaceInit($this->workspaceName);

        if (is_file($srcDir)) {
            return $this->loadFile($srcDir);
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir)) as $srcFile) {
            if (!$srcFile->isFile() || $srcFile->getExtension() !== 'xml') {
                continue;
            }

            $this->loadFile($srcFile->getPathname());
        }
    }

    function loadFile($filePath)
    {
        $dom = new \DOMDocument(1.0);
        $dom->load($filePath);
        $dom->preserveWhitepace = true;
        $dom->format = true;

        $this->iterateNode($dom->firstChild);
    }

    function iterateNode(\DomNode $domNode)
    {
        if ($domNode->nodeName == 'sv:node') {
            $this->persistSystemNode($domNode);
        }

        if (!$domNode->childNodes) {
            return;
        }

        foreach ($domNode->childNodes as $child) {
            $this->iterateNode($child);
        }
    }

    function persistSystemNode(\DomNode $domNode)
    {
        $xpath = new \DOMXpath($domNode->ownerDocument);
        $properties = array();
        foreach ($domNode->childNodes as $domProperty) {
            if ($domProperty->nodeName != 'sv:property') {
                continue;
            }

            $propertyName = $domProperty->getAttributeNS(self::NS_SV, 'name');
            $propertyType = $domProperty->getAttributeNs(self::NS_SV, 'type');

            $values = array();
            foreach ($domProperty->childNodes as $childNode) {
                if ($childNode->nodeName != 'sv:value') {
                    continue;
                }

                $values[] = $childNode->nodeValue;
            }

            if ($propertyName === 'jcr:mixinTypes' || $domProperty->getAttributeNs(self::NS_SV, 'multiple') === 'true' || count($values) > 1) {
                $propertyValue = $values;
            } else {
                $propertyValue = current($values);
            }

            $properties[$propertyName] = $propertyValue;
            $properties[':' . $propertyName] = $propertyType;
        }

        $ancestors = $xpath->query('ancestor::*', $domNode);
        $path = array();

        foreach ($ancestors as $ancestorNode) {
            $path[] = $ancestorNode->getAttributeNs(self::NS_SV, 'name');
        }

        $path[] = $domNode->getAttributeNs(self::NS_SV, 'name');
        $node = new Node($properties);

        $this->storage->writeNode($this->workspaceName, '/' . implode('/', $path), $node);
    }
}
