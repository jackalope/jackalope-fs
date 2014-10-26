<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializerInterface;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;
use Jackalope\Transport\Fs\Filesystem\Storage\Index;
use Jackalope\Transport\Fs\Model\Node;

class NodeWriter
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
    )
    {
        $this->pathRegistry = $pathRegistry;
        $this->serializer = $serializer;
        $this->filesystem = $filesystem;
        $this->helper = $helper;
        $this->index = $index;
    }

    /**
     * Write the given node data.
     *
     * @return array Node data
     */
    public function writeNode($workspace, $path, Node $node)
    {
        $internalUuid = $this->getOrCreateInternalUuid($path);
        $node->setProperty(Storage::INTERNAL_UUID, $internalUuid, 'String');

        $jcrUuid = null;
        if ($node->hasProperty('jcr:mixinTypes')) {
            $mixinTypes = $node->getPropertyValue('jcr:mixinTypes');

            if (in_array('mix:referenceable', $mixinTypes)) {
                if (false === $node->hasProperty('jcr:uuid')) {
                    $jcrUuid = UUIDHelper::generateUUID();
                    $node->setProperty('jcr:uuid', $jcrUuid);
                } else {
                    $jcrUuid = $node->getPropertyValue('jcr:uuid');
                }
            }
        }

        $serialized = $this->serializer->serialize($node);

        $absPath = $this->helper->getNodePath($workspace, $path);
        $this->filesystem->write($absPath, $serialized);

        foreach ($this->serializer->getSerializedBinaries() as $binaryHash => $binaryData) {
            // TODO: use Helper to get path ...,
            $binaryPath = sprintf('%s/%s.bin', dirname($absPath), $binaryHash);
            $this->filesystem->write($binaryPath, base64_decode($binaryData));
        }

        $this->index->indexUuid($internalUuid, $workspace, $path, true);

        if ($jcrUuid) {
            $this->index->indexUuid($jcrUuid, $workspace, $path, false);
        }

        foreach ($node->getProperties() as $propertyName => $property) {
            foreach ((array) $property['value'] as $propertyValue) {
                if ($property['type'] === 'Reference') {
                    $this->index->indexReferrer($internalUuid, $propertyName, $propertyValue, false);
                }

                if ($property['type'] === 'WeakReference') {
                    $this->index->indexReferrer($internalUuid, $propertyName, $propertyValue, true);
                }
            }
        }

        return $node;
    }

    private function getOrCreateInternalUuid($path)
    {
        if (!$this->pathRegistry->hasPath($path)) {
            $internalUuid = UUIDHelper::generateUUID();
        } else {
            $internalUuid = $this->pathRegistry->getUuid($path);
        }

        if (!$internalUuid) {
            throw new \RuntimeException('Failed to determine internal UUID');
        }

        return $internalUuid;
    }
}
