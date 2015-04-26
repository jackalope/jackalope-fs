<?php

namespace Jackalope\Transport\Fs\NodeType;

use Jackalope\Transport\Fs\Filesystem\Storage;
use PHPCR\NodeType\NodeTypeDefinitionInterface;
use Jackalope\Transport\Fs\Model\Node;

class NodeTypeStorage
{
    const PATH = '/jcr:system/jcr:nodeTypes';

    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function hasNodeType($workspace, $nodeType)
    {
        return $this->storage->nodeExists($workspace, self::PATH . '/' . $nodeType);
    }

    public function registerNodeType($workspace, NodeTypeDefinitionInterface $definition)
    {
        $node = new Node();
        $node->setProperty('jcr:hasOrderableChildNodes', $definition->hasOrderableChildNodes(), 'Boolean');
        $node->setProperty('jcr:isMixin', $definition->isMixin());
        $node->setProperty('jcr:nodeTypeName', $definition->getName(), 'Name');
        $node->setProperty('jcr:superTypes', $definition->getDeclaredSupertypeNames(), 'Name');
        $node->setProperty('jcr:primaryType', 'nt:nodeType', 'Name');

        $this->storage->writeNode($workspace, self::PATH . '/' . $definition->getName(), $node);

        foreach ($definition->getDeclaredPropertyDefinitions() ? : array() as $propDefinition) {
            $propNode = new Node();
            $propNode->setProperty('jcr:requiredType', $propDefinition->getRequiredType(), 'Reference');
            $propNode->setProperty('jcr:autoCreated', $propDefinition->isAutoCreated(), 'Boolean');
            $propNode->setProperty('jcr:mandatory', $propDefinition->isMandatory(), 'Boolean');
            $propNode->setProperty('jcr:protected', $propDefinition->isProtected(), 'Boolean');
            $propNode->setProperty('jcr:onParentVersion', $propDefinition->getOnParentVersion(), 'String');
            $propNode->setProperty('jcr:name', $propDefinition->getName(), 'String');
            $propNode->setProperty('jcr:multiple', $propDefinition->isMultiple(), 'Boolean');
            $propNode->setProperty('jcr:primaryType', 'nt:propertyDefinition', 'Name');

            $this->storage->writeNode(
                $workspace,
                self::PATH . '/' . $definition->getName() . '/prop-' . $propDefinition->getName(),
                $propNode
            );
        }

        foreach ($definition->getDeclaredChildNodeDefinitions() ? : array() as $childDefinition) {
            $childNode = new Node();
            $childNode->setProperty('jcr:name', $childDefinition->getName(), 'String');
            $childNode->setProperty('jcr:requiredPrimaryTypes', $childDefinition->getRequiredPrimaryTypeNames(), 'Reference');
            $childNode->setProperty('jcr:autoCreated', $childDefinition->isAutoCreated(), 'Boolean');
            $childNode->setProperty('jcr:defaultPrimaryType', $childDefinition->getDefaultPrimaryTypeName(), 'Name');
            $childNode->setProperty('jcr:protected', $childDefinition->isProtected(), 'Boolean');
            $childNode->setProperty('jcr:mandatory', $childDefinition->isMandatory(), 'Boolean');
            $childNode->setProperty('jcr:sameNameSiblings', $childDefinition->allowsSameNameSiblings());
            $childNode->setProperty('jcr:onParentVersion', $childDefinition->getOnParentVersion(), 'String');
            $childNode->setProperty('jcr:primaryType', 'nt:childNodeDefinition', 'Name');

            $this->storage->writeNode(
                $workspace,
                self::PATH . '/' . $definition->getName() . '/child-' . $childDefinition->getName(),
                $childNode
            );
        }
    }

    public function getNodeTypes($workspace)
    {
        $nodeTypeNames = $this->storage->ls($workspace, self::PATH);
        $nodeTypeData = array();

        foreach ($nodeTypeNames['dirs'] as $nodeTypeName) {
            $nodeTypePath = self::PATH . '/' . $nodeTypeName;
            $node = $this->storage->readNode($workspace, $nodeTypePath);

            $propertyData = $this->getPropertyData($workspace, $nodeTypeName, $nodeTypePath);
            $childData = $this->getChildData($workspace, $nodeTypeName, $nodeTypePath);

            $nodeTypeData[$nodeTypeName] = array(
                'name' => $nodeTypeName,
                'isAbstract' => false,
                'isMixin' => $node->getPropertyValue('jcr:isMixin'),
                'isQueryable' => true,
                'hasOrderableChildNodes' => $node->getPropertyValue('jcr:hasOrderableChildNodes'),
                'primaryItemName' => null,
                'declaredSuperTypeNames' => $node->getPropertyValue('jcr:superTypes'),
                'declaredPropertyDefinitions' => $propertyData,
                'declaredNodeDefinitions' => $childData,
            );
        }

        return $nodeTypeData;
    }

    private function getPropertyData($workspace, $nodeType, $path)
    {
        $propertyNodeNames = $this->storage->ls($workspace, $path);

        $propertyData = array();

        foreach ($propertyNodeNames['dirs'] as $propertyNodeName) {
            if (substr($propertyNodeName, 0, 4) !== 'prop') {
                continue;
            }
            $node = $this->storage->readNode($workspace, $path . '/' . $propertyNodeName);

            $data = array(
                'declaringNodeType' => $nodeType,
                'name' => $node->getPropertyValue('jcr:name'),
                'isAutoCreated' => $node->getPropertyValue('jcr:autoCreated'),
                'isMandatory' => $node->getPropertyValue('jcr:mandatory'),
                'isProtected' => $node->getPropertyValue('jcr:protected'),
                'onParentVersion' => $node->getPropertyValue('jcr:onParentVersion'),
                'requiredType' => $node->getPropertyValue('jcr:requiredType'),
                'multiple' => $node->getPropertyValue('jcr:multiple'),
                'fullTextSearchable' => true,
                'queryOrderable' => true,
                'availableQueryOperators' =>
                array(
                    0 => 'jcr.operator.equal.to',
                    1 => 'jcr.operator.not.equal.to',
                    2 => 'jcr.operator.greater.than',
                    3 => 'jcr.operator.greater.than.or.equal.to',
                    4 => 'jcr.operator.less.than',
                    5 => 'jcr.operator.less.than.or.equal.to',
                    6 => 'jcr.operator.like',
                ),
            );

            $propertyData[] = $data;
        }

        return $propertyData;
    }

    private function getChildData($workspace, $nodeType, $path)
    {
        $childNodeNames = $this->storage->ls($workspace, $path);

        $childData = array();

        foreach ($childNodeNames['dirs'] as $childNodeName) {
            if (substr($childNodeName, 0, 5) !== 'child') {
                continue;
            }

            $node = $this->storage->readNode($workspace, $path . '/' . $childNodeName);

            $data = array(
                'declaringNodeType' => $nodeType,
            );

            $map = array(
                'name' => 'jcr:name',
                'isAutoCreated' => 'jcr:autoCreated',
                'isMandatory' => 'jcr:mandatory',
                'isProtected' => 'jcr:protected',
                'onParentVersion' => 'jcr:onParentVersion',
                'allowsSameNameSiblings' => 'jcr:sameNameSiblings',
                'defaultPrimaryTypeName' => 'jcr:defaultPrimaryType',
                'requiredPrimaryTypeNames' => 'jcr:requiredPrimaryTypes',
            );

            $this->mapData($map, $node, $data);

            $childData[] = $data;
        }

        return $childData;
    }

    private function mapData($map, $node, &$data)
    {
        foreach ($map as $key => $propertyName) {
            $data[$key] = $node->hasProperty($propertyName) ? $node->getPropertyValue($propertyName) : null;
        }
    }
}
