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
            $propertyType = $property['type'];
            $propertyValues = (array) $property['value'];
            $propertyLengths = array();
            $binaryHashes = array();

            foreach ($propertyValues as $i => &$value) {
                if (null === $value) {
                    continue;
                }

                // should this be moved "up" ?
                if ($value instanceof \DateTime) {
                    $value = $value->format('c');
                }

                if ($propertyType == 'Binary') {
                    if (is_resource($value)) {
                        $stream = $value;
                        $value = stream_get_contents($stream);
                        fclose($stream);
                    }
                    $propertyLengths[] = strlen(base64_decode($value));

                    $binaryHash = md5($value);
                    $this->binaries[$binaryHash] = $value;
                    $value = $binaryHash;
                } else {
                    $propertyLengths[] = '';
                }
            }

            if (empty($propertyValues)) {
                continue;
            }

            $properties[$propertyName]['type'] = $propertyType;

            if (is_array($property['value'])) {
                $properties[$propertyName]['value'] = $propertyValues;
                $properties[$propertyName]['length'] = $propertyLengths;
            } else {
                $properties[$propertyName]['value'] = reset($propertyValues);
                $properties[$propertyName]['length'] = reset($propertyLengths);
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
