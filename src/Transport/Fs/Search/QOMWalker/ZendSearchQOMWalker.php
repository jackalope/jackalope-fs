<?php

namespace Jackalope\Transport\Fs\Search\QOMWalker;

use Jackalope\NotImplementedException;
use Jackalope\Query\QOM\PropertyValue;
use Jackalope\Query\QOM\QueryObjectModel;
use Jackalope\Transport\DoctrineDBAL\RepositorySchema;
use Jackalope\Transport\DoctrineDBAL\Util\Xpath;

use PHPCR\NamespaceException;
use PHPCR\NodeType\NodeTypeManagerInterface;
use PHPCR\Query\InvalidQueryException;
use PHPCR\Query\QOM\QueryObjectModelConstantsInterface as QOMConstants;
use PHPCR\Query\QOM;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use ZendSearch\Lucene;
use Jackalope\Transport\Fs\Search\Adapter\ZendSearchAdapter;

/**
 * Converts QOM a ZendSearch query
 */
class ZendSearchQOMWalker
{
    /**
     * @var NodeTypeManagerInterface
     */
    private $nodeTypeManager;

    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param NodeTypeManagerInterface $manager
     * @param Connection               $conn
     * @param array                    $namespaces
     */
    public function __construct(NodeTypeManagerInterface $nodeTypeManager)
    {
        $this->nodeTypeManager = $nodeTypeManager;
    }

    private function escape($string)
    {
        foreach (explode(' ', '\\ + - && || ! ( ) { } [ ] ^ " ~ * ? :') as $specialChar) {
            $string = str_replace($specialChar, '\\' . $specialChar, $string);
        }

        return $string;
    }

    /**
     * @param QOM\QueryObjectModelInterface $qom
     *
     * @return string
     */
    public function walkQOMQuery(QueryObjectModel $qom)
    {
        $source = $qom->getSource();

        $query = new Lucene\Search\Query\Boolean();
        $parts = array();
        $parts[] = '(' . $this->walkSource($source) . ')';

        if ($constraint = $qom->getConstraint()) {
            $parts[] = '(' . $this->walkConstraint($constraint) . ')';
        }

        // $orderings = $qom->getOrderings();
        $query = join(' AND ', $parts);

        return $query;
    }

    /**
     * @param QOM\SourceInterface $source
     *
     * @return string
     *
     * @throws NotImplementedException
     */
    public function walkSource(QOM\SourceInterface $source)
    {
        if ($source instanceof QOM\SelectorInterface) {
            return $this->walkSelectorSource($source);
        }

        throw new NotImplementedException(sprintf("The source class '%s' is not supported", get_class($source)));
    }

    /**
     * @param QOM\SelectorInterface $source
     *
     * @return Term
     */
    public function walkSelectorSource(QOM\SelectorInterface $source)
    {
        $this->source = $source;
        $nodeTypeName = $source->getNodeTypeName();

        if (!$this->nodeTypeManager->hasNodeType($nodeTypeName)) {
            throw new InvalidQueryException(sprintf('Node type does not exist "%s"', $nodeTypeName));
        }

        return sprintf('%s:%s', $this->escape('jcr:primaryType'), $this->escape($source->getNodeTypeName()));
    }

    /**
     * @param \PHPCR\Query\QOM\ConstraintInterface $constraint
     *
     * @return string
     *
     * @throws InvalidQueryException
     */
    private function walkConstraint(QOM\ConstraintInterface $constraint)
    {
        if ($constraint instanceof QOM\AndInterface) {
            return $this->walkAndConstraint($constraint);
        }
        if ($constraint instanceof QOM\OrInterface) {
            return $this->walkOrConstraint($constraint);
        }
        if ($constraint instanceof QOM\NotInterface) {
            return $this->walkNotConstraint($constraint);
        }
        if ($constraint instanceof QOM\ComparisonInterface) {
            return $this->walkComparisonConstraint($constraint);
        }
        if ($constraint instanceof QOM\DescendantNodeInterface) {
            return $this->walkDescendantNodeConstraint($constraint);
        }
        if ($constraint instanceof QOM\ChildNodeInterface) {
            return $this->walkChildNodeConstraint($constraint);
        }
        if ($constraint instanceof QOM\PropertyExistenceInterface) {
            return $this->walkPropertyExistenceConstraint($constraint);
        }
        if ($constraint instanceof QOM\SameNodeInterface) {
            return $this->walkSameNodeConstraint($constraint);
        }
        if ($constraint instanceof QOM\FullTextSearchInterface) {
            return $this->walkFullTextSearchConstraint($constraint);
        }

        throw new InvalidQueryException('Constraint "' . get_class($constraint) . '" not yet supported.');
    }

    private function walkAndConstraint(QOM\AndInterface $constraint)
    {
        $left = $this->walkConstraint($constraint->getConstraint1());
        $right = $this->walkConstraint($constraint->getConstraint2());

        return sprintf('%s AND %s', $left, $right);
    }

    private function walkOrConstraint(QOM\OrInterface $constraint)
    {
        $left = $this->walkConstraint($constraint->getConstraint1());
        $right = $this->walkConstraint($constraint->getConstraint2());

        return sprintf('%s OR %s', $left, $right);
    }

    private function walkNotConstraint(QOM\NotInterface $constraint)
    {
        $not = $this->walkConstraint($constraint->getConstraint());

        return sprintf('NOT %s', $not);
    }

    private function walkComparisonConstraint(QOM\ComparisonInterface $constraint)
    {
        $operand1 = $this->walkOperand($constraint->getOperand1());
        $operand2 = $this->walkOperand($constraint->getOperand2());
        $padding = '%0' . strlen(PHP_INT_MAX) .'s';

        switch ($constraint->getOperator()) {
            case QOMConstants::JCR_OPERATOR_EQUAL_TO:
                return sprintf('%s:"%s"', $operand1, $operand2);
            case QOMConstants::JCR_OPERATOR_NOT_EQUAL_TO:
                return sprintf('NOT %s:"%s"', $operand1, $operand2);
            case QOMConstants::JCR_OPERATOR_LESS_THAN:
                return sprintf('%s:{-' . $padding . ' TO %s}', $operand1, PHP_INT_MAX, $operand2);
            case QOMConstants::JCR_OPERATOR_LESS_THAN_OR_EQUAL_TO:
                return sprintf('%s:[-' . $padding . ' TO %s]', $operand1, PHP_INT_MAX, $operand2);
            case QOMConstants::JCR_OPERATOR_GREATER_THAN:
                return sprintf('%s:{' . $padding .' TO %s}', $operand1, $operand2, PHP_INT_MAX);
            case QOMConstants::JCR_OPERATOR_GREATER_THAN_OR_EQUAL_TO:
                return sprintf('%s:[' . $padding . ' TO %s]', $operand1, $operand2, PHP_INT_MAX);
            case QOMConstants::JCR_OPERATOR_LIKE:
                return sprintf('%s:"%s"', $operand1, str_replace('%', '*', $operand2));
        }

        throw new InvalidQueryException('Operator "' . $constraint->getOperator() . '" not yet supported.');
    }

    private function walkPropertyExistenceConstraint(QOM\PropertyExistenceInterface $constraint)
    {
        $selectorName = $constraint->getSelectorName();
        $propertyName = $constraint->getPropertyName();

        return sprintf('%s:*', $propertyName);
    }

    private function walkSameNodeConstraint(QOM\SameNodeInterface $constraint)
    {
        $selectorName = $constraint->getSelectorName();
        $path = $constraint->getPath();

        return sprintf( '%s:%s', $this->escape(ZendSearchAdapter::IDX_PATH), $path);
    }

    private function walkChildNodeConstraint(QOM\ChildNodeInterface $constraint)
    {
        $selectorName = $constraint->getSelectorName();
        $path = $constraint->getParentPath();

        return sprintf('%s:%s', $this->escape(ZendSearchAdapter::IDX_PARENTPATH), $path);
    }

    private function walkDescendantNodeConstraint(QOM\DescendantNodeInterface $constraint)
    {
        $selectorName = $constraint->getSelectorName();
        $path = $constraint->getAncestorPath();

        return sprintf( '%s:%s/*', $this->escape(ZendSearchAdapter::IDX_PATH), $path);
    }

    private function walkFullTextSearchConstraint(QOM\FullTextSearchInterface $constraint)
    {
        $selectorName = $constraint->getSelectorName();
        $propertyName = $constraint->getPropertyName();
        $fullTextSearch = $constraint->getFullTextSearchExpression();

        return sprintf('%s:%s', $this->escape($propertyName), $fullTextSearch);
    }

    /**
     * @param QOM\OperandInterface $operand
     */
    private function walkOperand(QOM\OperandInterface $operand)
    {
        if ($operand instanceof QOM\NodeNameInterface) {
            return ZendSearchAdapter::IDX_NODENAME;
        }
        if ($operand instanceof QOM\NodeLocalNameInterface) {
            return ZendSearchAdapter::IDX_NODELOCALNAME;
        }
        if ($operand instanceof QOM\LowerCaseInterface) {
        }
        if ($operand instanceof QOM\UpperCaseInterface) {
        }
        if ($operand instanceof QOM\LiteralInterface) {
            return $this->escape($operand->getLiteralValue());
        }
        if ($operand instanceof QOM\PropertyValueInterface) {
            return $this->escape($operand->getPropertyName());
        }
        if ($operand instanceof QOM\LengthInterface) {
            return $this->walkOperand($operand->getPropertyValue());
        }
        if ($operand instanceof QOM\LowerCaseInterface) {
            return $this->walkOperand($operand->getOperand());
        }
        if ($operand instanceof QOM\UpperCaseInterface) {
            return $this->walkOperand($operand->getOperand());
        }

        throw new InvalidQueryException("Dynamic operand " . get_class($operand) . " not yet supported.");
    }
}
