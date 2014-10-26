<?php

namespace Jackalope\Transport\Fs\NodeSerializer;

use Jackalope\Transport\Fs\NodeSerializerInterface;
use Symfony\Component\Yaml\Yaml;
use PHPCR\Util\UUIDHelper;
use Jackalope\Transport\Fs\Model\Node;

class YamlNodeSerializer implements NodeSerializerInterface
{
    private $binaries;
    private $binaryHashMap = array();

    /**
     * Static method for debugging
     */
    public static function parse($nodeData)
    {
        $serializer = new YamlSerializer;
        return $serializer->deserialize($nodeData);
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize($yamlData)
    {
        $res = Yaml::parse($yamlData);

        $node = new Node();

        foreach ($res as $key => $property) {
            $values = $property['value'];
            $type = $property['type'];
            $newValues = array();

            foreach ((array) $values as $value) {
                switch ($property['type']) {
                    case 'Boolean':
                        $value = $value === 'false' ? false : true;
                        break;
                }

                $newValues[] = $value;
            }

            if (false === is_array($values)) {
                $newValues = reset($newValues);
            }

            if ($type === 'Binary') {
                $this->binaryHashMap[$key] = $property['value'];
                $newValues = $property['length'];
            }

            $node->setProperty($key, $newValues, $type);
        }

        return $node;
    }

    public function serialize(Node $node)
    {
        $properties = array();
        $this->binaries = array();

        foreach ($node->getProperties() as $propertyName => $property) {
            $propertyValue = $property['value'];
            $propertyType = $property['type'];
            $propertyLength = array();

            // should this be moved "up" ?
            if ($propertyValue instanceof \DateTime) {
                $propertyValue = $propertyValue->format('c');
            }

            if ($propertyType == 'Binary') {
                $binaryHashes = array();
                foreach ((array) $propertyValue as $binaryData) {
                    if (is_resource($binaryData)) {
                        $binaryData = stream_get_contents($binaryData);
                    }
                    $binaryHash = md5($binaryData);
                    $binaryHashes[] = $binaryHash;
                    $propertyLength[] = strlen(base64_decode($binaryData));
                    $this->binaries[$binaryHash] = $binaryData;
                }

                if (is_array($propertyValue)) {
                    $propertyValue = array();
                    foreach ($binaryHashes as $binaryHash) {
                        $propertyValue[] = $binaryHash;
                    }
                } else {
                    $propertyValue = reset($binaryHashes);
                    $propertyLength = reset($propertyLength);
                }
            }

            $properties[$propertyName]['type'] = $propertyType;
            $properties[$propertyName]['value'] = $propertyValue;

            if (!empty($propertyLength)) {
                $properties[$propertyName]['length'] = $propertyLength;
            }
        }

        $yaml = Yaml::dump($properties);

        return $yaml;
    }

    public function getSerializedBinaries()
    {
        return $this->binaries;
    }

    public function getBinaryHashMap()
    {
        return $this->binaryHashMap;
    }
}
