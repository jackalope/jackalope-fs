<?php

namespace Jackalope\Transport\Fs\NodeSerializer;

use Jackalope\Transport\Fs\NodeSerializerInterface;
use Symfony\Component\Yaml\Yaml;
use PHPCR\Util\UUIDHelper;

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

        $ret = new \stdClass;
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
                $ret->$key = $property['length'];
            } else {
                $ret->$key = $newValues;
            }

            $ret->{':' . $key} = $type;
        }

        return $ret;
    }

    public function serialize($nodeData)
    {
        $properties = array();
        $this->binaries = array();

        do {
            $propertyName = key($nodeData);
            $propertyValue = current($nodeData);
            $propertyLength = array();

            // should this be moved "up" ?
            if ($propertyValue instanceof \DateTime) {
                $propertyValue = $propertyValue->format('c');
            }

            next($nodeData);
            $propertyTypeName = key($nodeData);
            $propertyTypeValue = current($nodeData);

            if (':' !== substr($propertyTypeName, 0, 1)) {
                throw new \InvalidArgumentException(sprintf(
                    'Property values must be followed by a type, e.g. "title" => "My title" MUST be followed by ":title" => "String". For "%s => %s"',
                    $propertyTypeName, $propertyTypeValue
                ));
            }

            if ($propertyTypeValue == 'Binary') {
                $binaryHashes = array();
                foreach ((array) $propertyValue as $binaryData) {
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

            $properties[$propertyName]['type'] = $propertyTypeValue;
            $properties[$propertyName]['value'] = $propertyValue;

            if (!empty($propertyLength)) {
                $properties[$propertyName]['length'] = $propertyLength;
            }

        } while (false !== next($nodeData));

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
