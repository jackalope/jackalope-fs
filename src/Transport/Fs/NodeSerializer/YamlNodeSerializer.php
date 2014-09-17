<?php

namespace Jackalope\Transport\Fs\NodeSerializer;

use Jackalope\Transport\Fs\NodeSerializerInterface;
use Symfony\Component\Yaml\Yaml;

class YamlNodeSerializer implements NodeSerializerInterface
{
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
        do {
            $propertyName = key($nodeData);
            $propertyValue = current($nodeData);

            $properties[$propertyName]['value'] = $propertyValue;
            next($nodeData);
            $propertyTypeName = key($nodeData);
            $propertyTypeValue = current($nodeData);

            if (':' !== substr($propertyTypeName, 0, 1)) {
                throw new \InvalidArgumentException(sprintf(
                    'Property values must be followed by a type, e.g. "title" => "My title" MUST be followed by ":title" => "String". For "%s => %s"',
                    $propertyTypeName, $propertyTypeValue
                ));
            }

            $properties[$propertyName]['type'] = $propertyTypeValue;
        } while (next($nodeData));

        $yaml = Yaml::dump($properties);

        return $yaml;
    }
}
