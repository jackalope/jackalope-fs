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

/**
 */
class Client extends BaseTransport implements WorkspaceManagementInterface
{
    protected $workspaceName;
    protected $loggedIn;
    protected $autoLastModified;

    protected $nodeTypeManager;

    protected $fs;

    /**
     * Base path for content repository
     * @var string
     */
    protected $path;

    public function __construct($factory, $parameters = array())
    {
        if (!isset($parameters['path'])) {
            throw new \InvalidArgumentException(
                'You must provide the "path" parameter for the filesystem jackalope repository'
            );
        }

        $this->path = $parameters['path'];
        $adapter = new LocalAdapter($this->path);
        $this->fs = new Filesystem($adapter);
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
        $files = $this->fs->ls($this->path);
        return $files['dirs'];
    }

    /**
     * {@inheritDoc}
     */
    public function login(CredentialsInterface $credentials = null, $workspaceName = null)
    {
        $this->validateWorkspaceName($workspaceName);

        $this->workspaceName = $workspaceName ? : 'default';

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
        if (!$this->nodeExists($path)) {
            throw new ItemNotFoundException(sprintf(
                'Could not find node record at "%s"',
                $path
            ));
        }

        $nodeRecordPath = $this->getNodeRecordPath($path);
        $nodeContent = $this->fs->read($nodeRecordPath);
        $res = Yaml::parse($nodeContent);
        $ret = new \stdClass;
        foreach ($res as $key => $value) {
            $ret->$key = $value;
        }

        return $ret;
    }

    /**
     * {@inheritDoc}
     */
    public function getNodes($paths)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getNodesByIdentifier($identifiers)
    {
        throw new NotImplementedException(__METHOD__);
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
        throw new NotImplementedException(__METHOD__);
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
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getReferences($path, $name = null)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getWeakReferences($path, $name = null)
    {
        throw new NotImplementedException(__METHOD__);
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
        throw new NotImplementedException(__METHOD__);
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
        $this->workspaceName = $name;

        if (null !== $srcWorkspace) {
            throw new NotImplementedException('Creating workspace as clone of existing workspace not supported');
        }

        if ($this->workspaceExists($name)) {
            throw new RepositoryException("Workspace '$name' already exists");
        }

        $this->createNode('/', array(
            'jcr:primaryType' => array(
                'type' => 'Name',
                'value' => 'rep:root',
            )
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function deleteWorkspace($name)
    {
        $this->fs->remove($this->getWorkspacePath($name));
    }

    public function workspaceExists($name)
    {
        return $this->fs->exists($this->getWorkspacePath($name));
    }

    private function getWorkspacePath($name)
    {
        return '/' . $name;
    }

    private function getNodeRecordPath($path)
    {
        $path = $this->normalizePath($path);
        if ($path) {
            $path .= '/';
        }

        $workspacePath = $this->getWorkspacePath($this->workspaceName);
        $nodeRecordPath = $workspacePath . '/' . $path . 'node.yml';

        return $nodeRecordPath;
    }

    private function nodeExists($path)
    {
        $path = $this->getNodeRecordPath($path);
        return $this->fs->exists($path);
    }

    private function createNode($path, $nodeData)
    {
        $node = array();
        foreach ($nodeData as $propertyName => $propertyConfig) {
            if (isset($propertyConfig['type'])) {
                $node[':' . $propertyName] = $propertyConfig['type'];
            }

            if (isset($propertyConfig['value'])) {
                $node[$propertyName] = $propertyConfig['value'];
            }
        }

        $res = Yaml::dump($node);

        $nodeRecordPath = $this->getNodeRecordPath($path);
        $this->fs->write($nodeRecordPath, $res);
    }

    private function validateWorkspaceName($name)
    {
        $res = PathHelper::assertValidLocalName($name);

        if (!$res) {
            throw new RepositoryException(sprintf('Invalid workspace name "%s"', $name));
        }
    }

    private function normalizePath($path)
    {
        $path = trim($path);
        if (strlen($path) == 1 && $path == '/') {
            return '';
        }

        return $path;
    }
}
