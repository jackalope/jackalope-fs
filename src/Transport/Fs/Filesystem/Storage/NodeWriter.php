<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializerInterface;
use Jackalope\Transport\Fs\Filesystem\PathRegistry;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Filesystem\Storage;

class NodeWriter
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

    /**
     * Write the given node data.
     *
     * @return array Node data
     */
    public function writeNode($workspace, $path, $nodeData)
    {
        $internalUuid = $this->getOrCreateInternalUuid($path);
        $this->setProperty($nodeData, Storage::INTERNAL_UUID, $internalUuid, 'String');

        $jcrUuid = null;
        if (isset($nodeData['jcr:mixinTypes'])) {
            $mixinTypes = $nodeData['jcr:mixinTypes'];

            if (in_array('mix:referenceable', $mixinTypes)) {
                if (!isset($nodeData['jcr:uuid'])) {
                    $jcrUuid = UUIDHelper::generateUUID();
                    $this->setProperty($nodeData, 'jcr:uuid', $uuid);
                } else {
                    $jcrUuid = $nodeData['jcr:uuid'];
                }
            }
        }

        $serialized = $this->serializer->serialize($nodeData);

        $absPath = $this->helper->getNodePath($workspace, $path);
        $this->filesystem->write($absPath, $serialized);

        foreach ($this->serializer->getSerializedBinaries() as $binaryHash => $binaryData) {
            $binaryPath = sprintf('%s/%s.bin', dirname($absPath), $binaryHash);
            $this->filesystem->write($binaryPath, base64_decode($binaryData));
        }

        $this->createIndex(Storage::IDX_INTERNAL_UUID, $internalUuid, $workspace . ':' . $path);

        if ($jcrUuid) {
            $this->createIndex(Storage::IDX_JCR_UUID, $jcrUuid, $workspace . ':' . $path);
        }

        foreach ($nodeData as $key => $value) {
            if (substr($key, 0, 1) !== ':') {
                continue;
            }

            $propertyName = substr($key, 1);
            $propertyValues = (array) $nodeData[$propertyName];

            foreach ($propertyValues as $propertyValue) {
                if ($value === 'Reference') {
                    $this->appendToIndex(Storage::IDX_REFERRERS_DIR, $propertyValue, $propertyName . ':' . $internalUuid);
                }

                if ($value === 'WeakReference') {
                    $this->appendToIndex(Storage::IDX_WEAKREFERRERS_DIR, $propertyValue, $propertyName . ':' . $internalUuid);
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

    private function setProperty(&$nodeData, $field, $value, $type)
    {
        $nodeData[$field] = $value;
        $nodeData[':' . $field] = 'String';
    }

    private function createIndex($type, $name, $value)
    {
        $this->filesystem->write(Storage::INDEX_DIR . '/' . $type . '/' . $name, $value);
    }

    private function appendToIndex($type, $name, $value)
    {
        $indexPath = Storage::INDEX_DIR . '/' . $type . '/' . $name;

        if (!$this->filesystem->exists($indexPath)) {
            $this->filesystem->write($indexPath, $value);
            return;
        }

        $index = $this->filesystem->read($indexPath);
        $index .= "\n" . $value;
        $this->filesystem->write($indexPath, $index);
    }
}
