<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Model\Node;
use Jackalope\Transport\Fs\Filesystem\Storage;
use Jackalope\Transport\Fs\Filesystem\Filesystem;
use PHPCR\ReferentialIntegrityException;

class NodeRemover
{
    private $nodeReader;
    private $filesystem;
    private $index;
    private $helper;


    private $nodesToRemove = array();

    public function __construct(
        NodeReader $nodeReader,
        Filesystem $filesystem,
        Index $index,
        StorageHelper $helper
    )
    {
        $this->nodeReader = $nodeReader;
        $this->filesystem = $filesystem;
        $this->index = $index;
        $this->helper = $helper;
    }

    /**
     * Write the given node data.
     *
     * @return array Node data
     */
    public function removeNodes($workspace, $paths)
    {
        foreach ($paths as $path) {
            $this->processNode($workspace, $path, 1);
        }

        foreach ($paths as $path) {
            $this->processNode($workspace, $path, 2);
        }

        foreach ($this->nodesToRemove as $node) {
            foreach ($node->getProperties() as $propertyName => $property) {
                if (false === in_array($property['type'], array('Reference', 'WeakReference'))) {
                    continue;
                }

                $this->index->deindexReferrer(
                    $node->getPropertyValue(Storage::INTERNAL_UUID),
                    $propertyName,
                    $property['type'] === 'Reference' ? false : true
                );
            }

            if ($node->hasProperty(Storage::JCR_UUID)) {
                $this->index->deindexUuid($node->getPropertyValue(Storage::JCR_UUID), false);
            }
        }

        foreach ($paths as $path) {
            $this->filesystem->remove($this->helper->getNodePath($workspace, $path, false), true);
        }

        $this->internalUuids = array();
        $this->nodesToRemove = array();
    }

    private function processNode($workspace, $path, $pass)
    {
        $node = $this->nodeReader->readNode($workspace, $path);
        $internalUuid = $node->getPropertyValue(Storage::INTERNAL_UUID);

        if ($pass == 1) {
            $this->internalUuids[$internalUuid] = true;
        }

        if ($pass == 2) {
            if ($node->hasProperty(Storage::JCR_UUID)) {
                $jcrUuid = $node->getPropertyValue(Storage::JCR_UUID);
                $this->checkReferringProperties($jcrUuid, $path);
            }

            $this->nodesToRemove[] = $node;
        }

        foreach ($node->getChildrenNames() as $childName) {
            $this->processNode(
                $workspace,
                $path . '/' . $childName,
                $pass
            );
        }
    }

    private function checkReferringProperties($jcrUuid, $path)
    {
        $referrers = $this->index->getReferringProperties($jcrUuid);

        if (count($referrers) > 0) {
            $extraReferrers = array();

            foreach (array_keys($referrers) as $referrerInternalUuid) {
                if (!isset($this->internalUuids[$referrerInternalUuid])) {
                    $extraReferrers[] = $this->index->getNodeLocationForUuid($referrerInternalUuid, true);
                }
            }

            if (count($extraReferrers) > 0) {
                throw new ReferentialIntegrityException(sprintf(
                    'Could not delete node with UUID "%s" at path "%s", it has the follwing referrers: "%s"',
                    $jcrUuid,
                    $path,
                    implode('", "', $extraReferrers)
                ));
            }

        }
    }
}
