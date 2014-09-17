<?php

namespace Jackalope\Transport\Fs\Test;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;
use Jackalope\Transport\Fs\Filesystem\Filesystem as FsFilesystem;
use Jackalope\Transport\Fs\Filesystem\Adapter\LocalAdapter;

class FixtureGenerator
{
    const NS_SV = 'http://www.jcp.org/jcr/sv/1.0';

    protected $destDir;
    protected $fs;

    function generateFixtures($srcDir, $destDir)
    {
        $this->storage = new Storage(new FsFilesystem(new LocalAdapter(dirname($destDir))));
        $this->workspaceName = basename($destDir);

        $this->storage->workspaceInit($this->workspaceName);

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir)) as $srcFile) {
            if (!$srcFile->isFile() || $srcFile->getExtension() !== 'xml') {
                continue;
            }

            $dom = new \DOMDocument(1.0);
            $dom->load($srcFile->getPathname());
            $dom->preserveWhitepace = true;
            $dom->format = true;

            $this->iterateNode($dom->firstChild);
        }
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

            $propertyValue = $values;

            if ($propertyName !== 'jcr:mixinTypes' || ($domNode->getAttribute('sv:multiple') && $domNode->getAttribute('sv:multiple') === 'true')) {
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

        $this->storage->writeNode($this->workspaceName, '/' . implode('/', $path), $properties);
    }
}
