<?php

namespace Jackalope\Transport\Fs\Model;

use PHPCR\NodeInterface;
use PHPCR\PropertyType;
use Jackalope\Transport\Fs\Filesystem\Storage;
use PHPCR\ItemNotFoundException;
use PHPCR\RepositoryException;

/**
 * Class that encapsulates the strange Jackalope node data structure
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class Node
{
    private $properties = array();
    private $childrenNames = array();

    public function __construct($data = array())
    {
        if ($data instanceof NodeInterface) {
            $this->parsePhpcrNode($data);
        } elseif (is_array($data)) {
            // Read node returns a stdClass as required by Jackalope, but storeNodes is
            // passed an array -- this means we need to normalize when doing internal
            // operations betwee the two (e.g. removing properties).
            if ($data instanceof \stdClass) {
                $data = get_object_vars($data);
            }

            $this->parse($data);
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Do not know how to parse value of type "%s"', gettype($data)
            ));
        }
    }

    public function censor()
    {
        $this->removeProperty(Storage::INTERNAL_UUID);

        // hack required by getNodeByIdentifier
        $this->removeProperty('jcr:path');
    }

    public function toJackalopeStructure()
    {
        $ret = new \stdClass();

        // Jackalope doesn't bother diferintiating between
        // children nodes and children properties so we have
        // to build the children first and then overwrite them
        // with any properties with the same name.

        foreach ($this->childrenNames as $childName) {
            $ret->{$childName} = new \stdClass();
        }

        foreach ($this->properties as $name => $data) {
            $ret->{$name} = $data['value'];
            $ret->{':' . $name} = $data['type'];
        }

        return $ret;
    }

    public function fromPhpcrProperties($phpcrProperties)
    {
        $this->parsePhpcrNodeProperties($phpcrProperties);
    }

    public function setProperty($name, $value = null, $type = 'String')
    {
        if (is_integer($type)) {
            $type = PropertyType::nameFromValue($type);
        }

        $this->properties[$name] = array(
            'value' => $value,
            'type' => $type,
        );
    }

    public function getProperty($name)
    {
        if (!isset($this->properties[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown property "%s"',
                $name
            ));
        }

        return $this->properties[$name];
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function hasProperty($name)
    {
        return isset($this->properties[$name]);
    }

    public function getPropertyValue($name)
    {
        $property = $this->getProperty($name);
        return $property['value'];
    }

    public function removeProperty($name)
    {
        unset($this->properties[$name]);
    }

    public function getPropertyType($name)
    {
        $property = $this->getProperty($name);
        return $property['type'];
    }

    private function parse($data)
    {
        if (empty($data)) {
            return;
        }

        do {
            $propertyName = key($data);
            $propertyValue = current($data);

            // if propertyValue is an object, then it is a child node and we
            // shouldn't continue. Note that this only happens during an internal
            // round trip from NodeReader::readNode to NodeWriter::writeNode
            if ($propertyValue instanceof \stdClass) {
                continue;
            }

            next($data);
            $propertyTypeName = key($data);
            $propertyTypeValue = current($data);

            if (':' !== substr($propertyTypeName, 0, 1)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid jackalope data structure  expected propertyName to be followed by type, e.g. ' .
                    '"title" => "My title" MUST be followed by ":title" => "String". For %s = %s',
                    var_export($propertyTypeName, true), var_export($propertyTypeValue, true)
                ));
            }

            $this->setProperty($propertyName, $propertyValue, $propertyTypeValue);
        } while (false !== next($data));
    }

    private function parsePhpcrNode(NodeInterface $node)
    {
        $this->parsePhpcrNodeProperties($node->getProperties());
    }

    private function parsePhpcrNodeProperties($properties)
    {
        // is there some common code which does this?
        foreach ($properties as $name => $property) {
            $value = null;
            switch ($property->getType()) {
                case PropertyType::DATE:
                    $value = $property->getDate();
                    break;
                case PropertyType::REFERENCE:
                case PropertyType::WEAKREFERENCE:
                    try {
                        $references = $property->getValue();
                    } catch (ItemNotFoundException $e) {
                        continue;
                    } catch (RepositoryException $e) {
                        continue;
                    }

                    if ($property->isMultiple()) {
                        $value = array();
                        foreach ($references as $reference) {
                            $value[] = $reference->getPropertyValue('jcr:uuid');
                        }
                    } else {
                        $value = $references->getPropertyValue('jcr:uuid');
                    }
                    break;
                default:
                    $value = $property->getValue();
            }

            $this->setProperty($name, $value, $property->getType());
        }
    }

    public function addChildName($name)
    {
        $this->childrenNames[] = $name;
    }

    public function getChildrenNames()
    {
        return $this->childrenNames;
    }
}
