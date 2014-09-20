<?php

namespace Jackalope\Transport\Fs;

use Jackalope\Transport\BaseTransport;
use Jackalope\Transport\WorkspaceManagementInterface;
use Jackalope\NotImplementedException;
use PHPCR\CredentialsInterface;
use PHPCR\NoSuchWorkspaceException;
use PHPCR\RepositoryInterface;
use PHPCR\Shell\Serializer\NodeNormalizer;
use PHPCR\Shell\Serializer\YamlEncoder;
use PHPCR\ItemNotFoundException;
use Symfony\Component\Yaml\Yaml;
use PHPCR\LoginException;
use PHPCR\RepositoryException;
use PHPCR\Util\PathHelper;
use Jackalope\Transport\Fs\Filesystem\Adapter\LocalAdapter;
use Jackalope\Transport\Fs\Filesystem\Filesystem;
use Jackalope\Transport\Fs\NodeSerializer\YamlNodeSerializer;
use Jackalope\Transport\Fs\Filesystem\Storage;
use Jackalope\Transport\StandardNodeTypes;

/**
 */
class Client extends BaseTransport implements WorkspaceManagementInterface
{
    protected $loggedIn;
    protected $autoLastModified;
    protected $workspaceName = 'default';

    protected $nodeTypeManager;

    protected $fs;
    protected $nodeSerializer;
    protected $storage;

    /**
     * Base path for content repository
     * @var string
     */
    protected $path;

    public function __construct($factory, $parameters = array(), $filesystem = null, $nodeSerializer = null, $storage = null)
    {
        if (!isset($parameters['path'])) {
            throw new \InvalidArgumentException(
                'You must provide the "path" parameter for the filesystem jackalope repository'
            );
        }

        $this->path = $parameters['path'];
        $adapter = new LocalAdapter($this->path);
        $this->storage = $storage ? : new Storage(new Filesystem($adapter));
        $this->nodeSerializer = $nodeSerializer ? : new YamlNodeSerializer();
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
            RepositoryInterface::OPTION_XML_EXPORT_SUPPORTED => false,
            RepositoryInterface::OPTION_XML_IMPORT_SUPPORTED => false,
            RepositoryInterface::QUERY_FULL_TEXT_SEARCH_SUPPORTED => false,
            RepositoryInterface::QUERY_CANCEL_SUPPORTED => false,
            RepositoryInterface::QUERY_JOINS => RepositoryInterface::QUERY_JOINS_NONE,
            RepositoryInterface::QUERY_LANGUAGES => array(),
            RepositoryInterface::QUERY_STORED_QUERIES_SUPPORTED => false,
            RepositoryInterface::WRITE_SUPPORTED => false,
        );
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

        if (!$this->workspaceExists($this->workspaceName)) {
            if ('default' !== $this->workspaceName) {
                throw new NoSuchWorkspaceException(sprintf(
                    'Requested workspace does not exist "%s"', $this->workspaceName
                ));
            }

            // create default workspace if it not exists
            $this->createWorkspace($this->workspaceName);
        }

        if ($credentials) {
            if ($credentials->getUserId() != 'admin' || $credentials->getPassword() != 'admin') {
                throw new LoginException('Invalid credentials (you must connect with admin/admin');
            }
        }

        $this->loggedIn = true;

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
        throw new NotImplementedException(__METHOD__);
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


        return $node;
    }

    /**
     * {@inheritDoc}
     */
    public function getNodes($paths)
    {
        $nodes = array();
        foreach ($paths as $path) {
            $nodes[$path] = $this->storage->readNode($this->workspaceName, $path);
        }

        return $nodes;
    }

    /**
     * {@inheritDoc}
     */
    public function getNodesByIdentifier($identifiers)
    {
        return $this->storage->readNodesByUuids($identifiers);
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
        return current($nodes);
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
        $standardTypes = StandardNodeTypes::getNodeTypeData();
        return $standardTypes;
    }

    /**
     * {@inheritDoc}
     */
    public function setFetchDepth($depth)
    {
        throw new NotImplementedException(__METHOD__);
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

    private function createNode($path, $nodeData)
    {
        $this->storage->writeNode($this->workspaceName, $path, $nodeData);
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
}
