<?php

namespace Jackalope\Transport\Fs;

use ArrayObject;
use Jackalope\Factory;
use Jackalope\NodeType\NodeType;
use Jackalope\Transport\BaseTransport;
use Jackalope\Transport\WorkspaceManagementInterface;
use Jackalope\NotImplementedException;
use PHPCR\CredentialsInterface;
use PHPCR\NoSuchWorkspaceException;
use PHPCR\Query\QOM\QueryObjectModelInterface;
use PHPCR\RepositoryInterface;
use PHPCR\ItemNotFoundException;
use PHPCR\LoginException;
use PHPCR\RepositoryException;
use PHPCR\SimpleCredentials;
use PHPCR\Util\PathHelper;
use Jackalope\Transport\Fs\Filesystem\Adapter\LocalAdapter;
use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializer\YamlNodeSerializer;
use Jackalope\Transport\Fs\Filesystem\Storage;
use Jackalope\Transport\StandardNodeTypes;
use Jackalope\Transport\WritingInterface;
use Jackalope\Transport\QueryInterface;
use Jackalope\Node as JackalopeNode;
use Jackalope\NodeType\NodeProcessor;
use PHPCR\NamespaceRegistryInterface;
use PHPCR\ItemExistsException;
use PHPCR\Util\ValueConverter;
use Jackalope\Query\Query;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Jackalope\Transport\Fs\Search\Adapter\ZendSearchAdapter;
use Jackalope\Transport\Fs\Search\IndexSubscriber;
use PHPCR\Util\QOM\Sql2ToQomQueryConverter;
use PHPCR\Query\InvalidQueryException;
use PHPCR\PathNotFoundException;
use Jackalope\Transport\Fs\Model\Node;
use Jackalope\Transport\NodeTypeManagementInterface;
use Jackalope\Transport\Fs\NodeType\NodeTypeStorage;

/**
 */
class Client extends BaseTransport implements WorkspaceManagementInterface, WritingInterface, QueryInterface, NodeTypeManagementInterface
{
    private $loggedIn;

    /** @var NodeProcessor */
    private $nodeProcessor;
    /** @var NodeTypeStorage */
    private $nodeTypeStorage;

    // not yet implemented
    private $autoLastModified;
    private $workspaceName = 'default';
    private $nodeTypeManager;
    private $nodeSerializer;
    /** @var Storage  */
    private $storage;
    /** @var SimpleCredentials */
    private $credentials;
    private $valueConverter;
    private $eventDispatcher;
    /** @var  */
    private $searchAdapter;
    /** @var Factory */
    private $factory;

    private $searchEnabled;
    private $zendHideDestructException;

    /**
     * Base path for content repository
     * @var string
     */
    protected $path;

    public function __construct($factory, $parameters = array(), Filesystem $filesystem = null)
    {
        if (!isset($parameters['path'])) {
            throw new \InvalidArgumentException(
                'You must provide the "path" parameter for the filesystem jackalope repository'
            );
        }

        $this->path = $parameters['path'];
        $this->zendHideDestructException = isset($parameters['search.zend.hide_destruct_exception']) ? $parameters['search.zend.hide_destruct_exception'] : false;
        $this->searchEnabled = isset($parameters['search.enabled']) ? $parameters['search.enabled'] : true;
        $this->eventDispatcher = new EventDispatcher();
        $adapter = $filesystem ? : new LocalAdapter($this->path);
        $this->storage = new Storage(new Filesystem($adapter), $this->eventDispatcher);
        $this->valueConverter = new ValueConverter();
        $this->nodeSerializer = new YamlNodeSerializer();
        $this->nodeTypeStorage = new NodeTypeStorage($this->storage);
        $this->factory = $factory;

        $this->registerEventSubscribers();
    }

    private function getSearchAdapter()
    {
        $this->searchAdapter = new ZendSearchAdapter($this->path, $this->nodeTypeManager, $this->zendHideDestructException);

        return $this->searchAdapter;
    }

    private function registerEventSubscribers()
    {
        if ($this->searchEnabled) {
            $this->eventDispatcher->addSubscriber(
                new IndexSubscriber($this->getSearchAdapter())
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRepositoryDescriptors()
    {
        return array(
            RepositoryInterface::IDENTIFIER_STABILITY => RepositoryInterface::IDENTIFIER_STABILITY_INDEFINITE_DURATION,
            RepositoryInterface::REP_NAME_DESC  => 'jackalope_filesystem',
            RepositoryInterface::REP_VENDOR_DESC => 'Jackalope Community',
            RepositoryInterface::REP_VENDOR_URL_DESC => 'http://github.com/jackalope',
            RepositoryInterface::REP_VERSION_DESC => '0.1',
            RepositoryInterface::SPEC_NAME_DESC => 'Content Repository for PHP',
            RepositoryInterface::SPEC_VERSION_DESC => '2.1',
            RepositoryInterface::NODE_TYPE_MANAGEMENT_AUTOCREATED_DEFINITIONS_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_INHERITANCE => RepositoryInterface::NODE_TYPE_MANAGEMENT_INHERITANCE_MINIMAL,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_MULTIPLE_BINARY_PROPERTIES_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_MULTIVALUED_PROPERTIES_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_ORDERABLE_CHILD_NODES_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_OVERRIDES_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_PRIMARY_ITEM_NAME_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_PROPERTY_TYPES => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_RESIDUAL_DEFINITIONS_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_SAME_NAME_SIBLINGS_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_UPDATE_IN_USE_SUPPORTED => false,
            RepositoryInterface::NODE_TYPE_MANAGEMENT_VALUE_CONSTRAINTS_SUPPORTED => false,
            RepositoryInterface::OPTION_ACCESS_CONTROL_SUPPORTED => false,
            RepositoryInterface::OPTION_ACTIVITIES_SUPPORTED => false,
            RepositoryInterface::OPTION_BASELINES_SUPPORTED => false,
            RepositoryInterface::OPTION_JOURNALED_OBSERVATION_SUPPORTED => false,
            RepositoryInterface::OPTION_LIFECYCLE_SUPPORTED => false,
            RepositoryInterface::OPTION_LOCKING_SUPPORTED => false,
            RepositoryInterface::OPTION_NODE_AND_PROPERTY_WITH_SAME_NAME_SUPPORTED => false,
            RepositoryInterface::OPTION_NODE_TYPE_MANAGEMENT_SUPPORTED => false,
            RepositoryInterface::OPTION_OBSERVATION_SUPPORTED => false,
            RepositoryInterface::OPTION_RETENTION_SUPPORTED => false,
            RepositoryInterface::OPTION_SHAREABLE_NODES_SUPPORTED => false,
            RepositoryInterface::OPTION_SIMPLE_VERSIONING_SUPPORTED => true,
            RepositoryInterface::OPTION_TRANSACTIONS_SUPPORTED => false,
            RepositoryInterface::OPTION_UNFILED_CONTENT_SUPPORTED => false,
            RepositoryInterface::OPTION_UPDATE_MIXIN_NODETYPES_SUPPORTED => false,
            RepositoryInterface::OPTION_UPDATE_PRIMARY_NODETYPE_SUPPORTED => false,
            RepositoryInterface::OPTION_VERSIONING_SUPPORTED => false,
            RepositoryInterface::OPTION_WORKSPACE_MANAGEMENT_SUPPORTED => false,
            RepositoryInterface::OPTION_XML_EXPORT_SUPPORTED => true,
            RepositoryInterface::OPTION_XML_IMPORT_SUPPORTED => false,
            RepositoryInterface::QUERY_FULL_TEXT_SEARCH_SUPPORTED => false,
            RepositoryInterface::QUERY_CANCEL_SUPPORTED => false,
            RepositoryInterface::QUERY_JOINS => RepositoryInterface::QUERY_JOINS_NONE,
            RepositoryInterface::QUERY_LANGUAGES => array(),
            RepositoryInterface::QUERY_STORED_QUERIES_SUPPORTED => false,
            RepositoryInterface::WRITE_SUPPORTED => true,
        );
    }

    public function registerNodeTypes($nodeTypes, $allowUpdate)
    {
        $standardNodeTypes = StandardNodeTypes::getNodeTypeData();

        foreach ($nodeTypes as $nodeType) {
            /** @var NodeType $nodeType */
            if (isset($standardNodeTypes[$nodeType->getName()])) {
                throw new RepositoryException(sprintf(
                    'Cannot overwrite standard node type "%s"', $nodeType->getName()
                ));
            }

            if (!$allowUpdate) {
                if ($this->nodeTypeStorage->hasNodeType($this->workspaceName, $nodeType->getName())) {
                    throw new RepositoryException(sprintf(
                        'Node type "%s" already exists and allowUpdate is false',
                        $nodeType->getName()
                    ));
                }
            }
            $this->nodeTypeStorage->registerNodeType($this->workspaceName, $nodeType);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessibleWorkspaceNames()
    {
        $workspaces = $this->storage->workspaceList();
        return $workspaces;
    }

    /**
     * {@inheritDoc}
     */
    public function login(CredentialsInterface $credentials = null, $workspaceName = null)
    {
        $this->validateWorkspaceName($workspaceName);

        if ($workspaceName) {
            $this->workspaceName = $workspaceName;
        }

        if (null === $credentials || !$credentials instanceof SimpleCredentials) {
            throw new LoginException('No credentials provided');
        }

        if (!$this->workspaceExists($this->workspaceName)) {
            if ('default' !== $this->workspaceName) {
                throw new NoSuchWorkspaceException(sprintf(
                    'Requested workspace does not exist "%s"', $this->workspaceName
                ));
            }

            // create default workspace if it not exists
            $this->createWorkspace($this->workspaceName);
        }

        if ($credentials->getUserId() !== 'admin' || $credentials->getPassword() !== 'admin') {
            throw new LoginException('Invalid credentials (you must connect with admin/admin');
        }

        $this->loggedIn = true;
        $this->credentials = $credentials;
        $this->init();

        return $this->workspaceName;
    }

    /**
     * {@inheritDoc}
     */
    public function logout()
    {
        $this->loggedIn = false;
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaces()
    {
        return array_merge(
            $this->storage->getNamespaces(),
            array(
                NamespaceRegistryInterface::PREFIX_EMPTY => NamespaceRegistryInterface::NAMESPACE_EMPTY,
                NamespaceRegistryInterface::PREFIX_JCR => NamespaceRegistryInterface::NAMESPACE_JCR,
                NamespaceRegistryInterface::PREFIX_NT => NamespaceRegistryInterface::NAMESPACE_NT,
                NamespaceRegistryInterface::PREFIX_MIX => NamespaceRegistryInterface::NAMESPACE_MIX,
                NamespaceRegistryInterface::PREFIX_XML => NamespaceRegistryInterface::NAMESPACE_XML,
                NamespaceRegistryInterface::PREFIX_SV => NamespaceRegistryInterface::NAMESPACE_SV,
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getNode($path)
    {
        if (!$this->storage->nodeExists($this->workspaceName, $path)) {
            throw new ItemNotFoundException(sprintf(
                'Could not find node record at "%s" for workspace "%s"',
                $path,
                $this->workspaceName
            ));
        }

        $node = $this->storage->readNode($this->workspaceName, $path);

        return $node->toJackalopeStructure();
    }

    /**
     * {@inheritDoc}
     */
    public function getNodes($paths)
    {
        $nodes = array();
        foreach ($paths as $path) {
            try {
                $node = $this->getNode($path);
            } catch (ItemNotFoundException $e) {
                continue;
            }

            $nodes[$path] = $node;
        }

        return $nodes;
    }

    /**
     * {@inheritDoc}
     */
    public function getNodesByIdentifier($identifiers)
    {
        $nodeCollection = $this->storage->readNodesByUuids($identifiers);
        return $nodeCollection->toJackalopeStructures();
    }

    /**
     * {@inheritDoc}
     */
    public function getProperty($path)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getNodeByIdentifier($uuid)
    {
        $nodes = $this->getNodesByIdentifier(array($uuid));
        $node = current($nodes);

        if (!$node) {
            throw new ItemNotFoundException(sprintf(
                'Could not find node with UUID "%s"', $uuid
            ));
        }

        $node->{':jcr:path'} = $node->{'jcr:path'};

        return $node;
    }

    /**
     * {@inheritDoc}
     */
    public function getNodePathForIdentifier($uuid, $workspace = null)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getBinaryStream($path)
    {
        return $this->storage->readBinaryStream($this->workspaceName, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function getReferences($path, $name = null)
    {
        return $this->storage->readNodeReferrers($this->workspaceName, $path, false, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function getWeakReferences($path, $name = null)
    {
        return $this->storage->readNodeReferrers($this->workspaceName, $path, true, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function setNodeTypeManager($nodeTypeManager)
    {
        $this->nodeTypeManager = $nodeTypeManager;
    }

    /**
     * {@inheritDoc}
     */
    public function getNodeTypes($nodeTypes = array())
    {
        $types = array_merge(
            StandardNodeTypes::getNodeTypeData(),
            $this->nodeTypeStorage->getNodeTypes($this->workspaceName)
        );

        return $types;
    }

    /**
     * {@inheritDoc}
     */
    public function setFetchDepth($depth)
    {
        // do nothing. there is no performance benefit to be gained by premptively fetching
        // nodes from the filesystem.
    }

    /**
     * {@inheritDoc}
     */
    public function getFetchDepth()
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function setAutoLastModified($autoLastModified)
    {
        $this->autoLastModified = $autoLastModified;
    }

    /**
     * {@inheritDoc}
     */
    public function getAutoLastModified()
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function createWorkspace($name, $srcWorkspace = null)
    {
        if (null !== $srcWorkspace) {
            throw new NotImplementedException('Creating workspace as clone of existing workspace not supported');
        }

        if ($this->workspaceExists($name)) {
            throw new RepositoryException("Workspace '$name' already exists");
        }

        $this->storage->workspaceInit($name);

        $this->workspaceName = $name;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteWorkspace($name)
    {
        $this->storage->removeWorkspace($name);
    }

    public function workspaceExists($name)
    {
        return $this->storage->workspaceExists($name);
    }

    private function validateWorkspaceName($name)
    {
        $res = PathHelper::assertValidLocalName($name);

        if (!$res) {
            throw new RepositoryException(sprintf('Invalid workspace name "%s"', $name));
        }
    }

    private function assertLoggedIn()
    {
        if (false === $this->loggedIn) {
            throw new \InvalidArgumentException(
                'You are not logged in'
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function assertValidName($name)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function copyNode($srcAbsPath, $destAbsPath, $srcWorkspace = null)
    {
        if ($srcWorkspace) {
            $this->validateWorkspace($srcWorkspace);
        } else {
            $srcWorkspace = $this->workspaceName;
        }

        $this->validatePath($srcWorkspace, $srcAbsPath);
        $this->validatePath($this->workspaceName, PathHelper::getParentPath($destAbsPath));

        try {
            $this->storage->copyNode($srcWorkspace, $srcAbsPath, $this->workspaceName, $destAbsPath);
        } catch (\Exception $e) {
            throw new RepositoryException(sprintf(
                $e->getMessage(), $e->getCode(), $e
            ));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function cloneFrom($srcWorkspace, $srcAbsPath, $destAbsPath, $removeExisting)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function updateNode(JackalopeNode $node, $srcWorkspace)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function moveNodes(array $operations)
    {
        foreach ($operations as $operation) {
            $this->storage->moveNode($this->workspaceName, $operation->srcPath, $operation->dstPath);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function moveNodeImmediately($srcAbsPath, $dstAbsPath)
    {
        $this->storage->moveNode($this->workspaceName, $srcAbsPath, $dstAbsPath);
    }

    /**
     * {@inheritDoc}
     */
    public function reorderChildren(JackalopeNode $node)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteNodes(array $operations)
    {
        $paths = array();

        foreach ($operations as $operation) {
            $paths[] = $operation->srcPath;
        }

        $this->storage->removeNodes($this->workspaceName, $paths);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteProperties(array $operations)
    {
        foreach ($operations as $operation) {
            $this->storage->removeProperty($this->workspaceName, $operation->srcPath);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteNodeImmediately($path)
    {
        $this->storage->removeNodes($this->workspaceName, array($path));
    }

    /**
     * {@inheritDoc}
     */
    public function deletePropertyImmediately($path)
    {
        $this->storage->removeProperty($this->workspaceName, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function storeNodes(array $operations)
    {
        foreach ($operations as $operation) {
            $phpcrNode = $operation->node;
            $node = $this->phpcrNodeToNode($phpcrNode);

            if ($this->storage->nodeExists($this->workspaceName, $operation->srcPath)) {
                throw new ItemExistsException(sprintf(
                    'Node at path "%s" already exists',
                    $operation->srcPath
                ));
            }

            $this->storage->writeNode($this->workspaceName, $operation->srcPath, $node);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateProperties(JackalopeNode $phpcrNode)
    {
        $this->assertLoggedIn();
        $this->nodeProcessor->process($phpcrNode);
        $node = $this->phpcrNodeToNode($phpcrNode);
        $this->storage->writeNode($this->workspaceName, $phpcrNode->getPath(), $node);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function registerNamespace($prefix, $uri)
    {
        $this->storage->registerNamespace($this->workspaceName, $prefix, $uri);

        $this->init();
    }

    /**
     * {@inheritDoc}
     */
    public function unregisterNamespace($prefix)
    {
        $this->storage->unregisterNamespace($this->workspaceName, $prefix);
    }

    /**
     * {@inheritDoc}
     */
    public function prepareSave()
    {
        // nothing
    }

    /**
     * {@inheritDoc}
     */
    public function finishSave()
    {
        $this->storage->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackSave()
    {
    }

    public function query(Query $query)
    {
        if (!$query instanceof QueryObjectModelInterface) {
            $parser = new Sql2ToQomQueryConverter($this->factory->get('Query\QOM\QueryObjectModelFactory'));
            try {
                $qom = $parser->parse($query->getStatement());
                $qom->setLimit($query->getLimit());
                $qom->setOffset($query->getOffset());
            } catch (\Exception $e) {
                throw new InvalidQueryException('Invalid query: '.$query->getStatement(), null, $e);
            }
        } else {
            $qom = $query;
        }

        return $this->getSearchAdapter()->query($this->workspaceName, $qom);
    }

    public function getSupportedQueryLanguages()
    {
        return array('JCR-SQL2', 'JCR-JQOM');
    }

    private function init()
    {
        $this->nodeProcessor = new NodeProcessor(
            $this->credentials->getUserID(),
            new ArrayObject($this->getNamespaces())
        );
    }

    private function phpcrNodeToNode(JackalopeNode $node)
    {
        if ($node->isDeleted()) {
            $properties = $node->getPropertiesForStoreDeletedNode();
        } else {
            $this->nodeProcessor->process($node);
            $properties = $node->getProperties();
        }

        $node = new Node();
        $node->fromPhpcrProperties($properties);

        return $node;
    }

    private function validateWorkspace($workspaceName)
    {
        if (false === $this->workspaceExists($workspaceName)) {
            throw new NoSuchWorkspaceException(sprintf(
                'Workspace "%s" does not exist',
                $workspaceName
            ));
        }
    }

    private function validatePath($workspaceName, $path)
    {
        if (false === $this->storage->nodeExists($workspaceName, $path)) {
            throw new PathNotFoundException(sprintf(
                'Path "%s" not found in workspace "%s"',
                $path, $workspaceName
            ));
        }
    }
}
