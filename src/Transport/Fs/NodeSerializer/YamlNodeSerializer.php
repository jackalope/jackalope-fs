<?php

namespace Jackalope\Transport\Fs\NodeSerializer;

use Jackalope\Transport\Fs\NodeSerializerInterface;
use Symfony\Component\Yaml\Yaml;
use PHPCR\Util\UUIDHelper;

class YamlNodeSerializer implements NodeSerializerInterface
{
    protected $binaries;

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
            $value = $property['value'];

            $ret->$key = $value;
            $ret->{':' . $key} = $property['type'];
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
                $binaries = array();
                foreach ((array) $propertyValue as $binaryData) {
                    $binaryHash = md5($binaryData);
                    $binaries[$binaryHash] = $binaryData;
                    $this->binaries[$binaryHash] = $binaryData;
                }

                $binaryHashes = array_keys($binaries);
                if (is_array($propertyValue)) {
                    $propertyValue = array();
                    foreach ($binaryHashes as $binaryHash) {
                        $propertyValue[] = $binaryHash;
                    }
                } else {
                    $propertyValue = reset($binaryHashes);
                }
            }

            $properties[$propertyName]['type'] = $propertyTypeValue;
            $properties[$propertyName]['value'] = $propertyValue;
        } while (false !== next($nodeData));

        $yaml = Yaml::dump($properties);

        return $yaml;
    }

    public function getSerializedBinaries()
    {
        return $this->binaries;
    }
}
