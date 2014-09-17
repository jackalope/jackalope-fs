<?php

namespace Jackalope\Transport\Fs\Test;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use PHPCR\Util\UUIDHelper;

class FixtureGenerator
{
    const NS_SV = 'http://www.jcp.org/jcr/sv/1.0';

    protected $destDir;
    protected $fs;

    function generateFixtures($srcDir, $destDir)
    {
        $this->destDir = $destDir;
        $this->fs = new Filesystem();
        $this->fs->remove($destDir);
        mkdir($destDir);
        $this->makeRootNode();

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

    function makeRootNode()
    {
        $uuid = UUIDHelper::generateUUID();
        $node = array(
            'jcr:uuid' => array(
                'type' => 'String',
                'value' => $uuid,
            ),
            'jcr:primaryType' => array(
                'type' => 'String',
                'value' => 'nt:unstructured',
            ),
        );

        $yaml = Yaml::dump($node);
        $path = $this->destDir .'/node.yml';
        file_put_contents($path, $yaml);
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
        foreach ($domNode->childNodes as $domProperty) {
            if ($domProperty->nodeName != 'sv:property') {
                continue;
            }

            $node[$domProperty->getAttributeNS(self::NS_SV, 'name')] = array(
                'type' => $domProperty->getAttributeNs(self::NS_SV, 'type'),
                'value' => trim($domProperty->nodeValue)
            );
        }

        $ancestors = $xpath->query('ancestor::*', $domNode);
        $path = array();

        foreach ($ancestors as $ancestorNode) {
            $path[] = $ancestorNode->getAttributeNs(self::NS_SV, 'name');
        }
    
        $path[] = $domNode->getAttributeNs(self::NS_SV, 'name');

        $path = implode('/', $path);
        $filePath = sprintf('%s/%s/node.yml', $this->destDir, $path);
        $dirPath = dirname($filePath);
        if (!file_exists($dirPath)) {
            $this->fs->mkdir($dirPath);
        }

        $yaml = Yaml::dump($node);
        file_put_contents($filePath, $yaml);
    }
}
