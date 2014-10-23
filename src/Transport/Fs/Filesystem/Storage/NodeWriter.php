<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializerInterface;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;
use Jackalope\Transport\Fs\Filesystem\Storage\Index;

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
    public function writeNode($workspace, $path, $nodeData)
    {
        // Read node returns a stdClass as required by Jackalope, but storeNodes is
        // passed an array -- this means we need to normalize when doing internal
        // operations betwee the two (e.g. removing properties).
        if ($nodeData instanceof \stdClass) {
            $nodeData = get_object_vars($nodeData);
        }
        $internalUuid = $this->getOrCreateInternalUuid($path);
        $this->setProperty($nodeData, Storage::INTERNAL_UUID, $internalUuid, 'String');

        $jcrUuid = null;
        if (isset($nodeData['jcr:mixinTypes'])) {
            $mixinTypes = $nodeData['jcr:mixinTypes'];

            if (in_array('mix:referenceable', $mixinTypes)) {
                if (!isset($nodeData['jcr:uuid'])) {
                    $jcrUuid = UUIDHelper::generateUUID();
                    $this->setProperty($nodeData, 'jcr:uuid', $jcrUuid);
                } else {
                    $jcrUuid = $nodeData['jcr:uuid'];
                }
            }
        }

        $serialized = $this->serializer->serialize($nodeData);

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

        // parse the inefficient Jackalope node structure
        foreach ($nodeData as $key => $value) {
            if (substr($key, 0, 1) !== ':') {
                continue;
            }

            $propertyName = substr($key, 1);
            $propertyValues = (array) $nodeData[$propertyName];

            // propertyValues are UUIDs when the property type is a reference
            foreach ($propertyValues as $propertyValue) {
                if ($value === 'Reference') {
                    $this->index->indexReferrer($internalUuid, $propertyName, $propertyValue, false);
                }

                if ($value === 'WeakReference') {
                    $this->index->indexReferrer($internalUuid, $propertyName, $propertyValue, false);
                }
            }
        }

        return $nodeData;
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

    private function setProperty(&$nodeData, $field, $value, $type = 'String')
    {
        $nodeData[$field] = $value;
        $nodeData[':' . $field] = $type;
    }
}
