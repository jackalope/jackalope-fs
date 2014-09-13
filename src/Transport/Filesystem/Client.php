<?php

namespace Jackalope\Transport\Filesystem;

use Jackalope\Transport\BaseTransport;
use Jackalope\Transport\WorkspaceManagementInterface;
use Jackalope\NotImplementedException;
use PHPCR\CredentialsInterface;

/**
 */
class Client extends BaseTransport implements WorkspaceManagementInterface
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryDescriptors()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessibleWorkspaceNames()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function login(CredentialsInterface $credentials = null, $workspaceName = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function logout()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaces()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNode($path)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNodes($paths)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNodesByIdentifier($identifiers)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getProperty($path)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNodeByIdentifier($uuid)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNodePathForIdentifier($uuid, $workspace = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getBinaryStream($path)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getReferences($path, $name = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getWeakReferences($path, $name = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setNodeTypeManager($nodeTypeManager)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNodeTypes($nodeTypes = array())
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setFetchDepth($depth)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getFetchDepth()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setAutoLastModified($autoLastModified)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getAutoLastModified()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function createWorkspace($name, $srcWorkspace = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function deleteWorkspace($name)
    {
    }
}
