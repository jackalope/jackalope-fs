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
    public function serialize($nodeData)
    {
        $res = Yaml::parse($nodeData);

        $ret = new \stdClass;
        foreach ($res as $key => $property) {
            $value = $property['value'];

            $ret->$key = $value;
            $ret->{':' . $key} = $property['type'];
        }

        return $ret;
    }
}
