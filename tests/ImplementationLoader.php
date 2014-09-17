<?php

/**
 * Implemnentation Loader for filesystem
 */
class ImplementationLoader extends \PHPCR\Test\AbstractLoader
{
    private static $instance = null;

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new ImplementationLoader();
        }

        return self::$instance;
    }

    /**
     * @var string
     */
    private $fixturePath;

    /**
     * Base path for content repository
     * @var string
     */
    private $path;

    protected function __construct()
    {
        parent::__construct('Jackalope\RepositoryFactoryFilesystem', $GLOBALS['phpcr.workspace']);

        $this->unsupportedChapters = array(
            'Query',
            'Export',
            'NodeTypeDiscovery',
            'PermissionsAndCapabilities',
            'Writing',
            'Import',
            'Observation',
            'WorkspaceManagement',
            'ShareableNodes',
            'Versioning',
            'AccessControlManagement',
            'Locking',
            'LifecycleManagement',
            'NodeTypeManagement',
            'RetentionAndHold',
            'Transactions',
            'SameNameSiblings',
            'OrderableChildNodes',
            'PhpcrUtils'
        );

        $this->unsupportedCases = array(
        );

        $this->unsupportedTests = array(
            'Connecting\\RepositoryTest::testLoginException', //TODO: figure out what would be invalid credentials
            'Reading\\NodeReadMethodsTest::testGetSharedSetUnreferenced', // TODO: should this be moved to 14_ShareableNodes
        );

        $this->path = __DIR__ . '/data';
    }

    public function getRepositoryFactoryParameters()
    {
        return array('path' => $this->path);
    }

    public function getCredentials()
    {
        return new \PHPCR\SimpleCredentials('admin', 'admin');
    }

    public function getInvalidCredentials()
    {
        return new \PHPCR\SimpleCredentials('nonexistinguser', '');
    }

    public function getRestrictedCredentials()
    {
        return new \PHPCR\SimpleCredentials('anonymous', 'abc');
    }

    public function prepareAnonymousLogin()
    {
        return true;
    }

    public function getUserId()
    {
        return 'admin';
    }

    public function getRepository()
    {
        $transport = new \Jackalope\Transport\Fs\Client(new \Jackalope\Factory, array('path' => $this->path));
        foreach (array($GLOBALS['phpcr.workspace'], $this->otherWorkspacename) as $workspace) {
            try {
                $transport->createWorkspace($workspace);
            } catch (\PHPCR\RepositoryException $e) {
                if ($e->getMessage() != "Workspace '$workspace' already exists") {
                    // if the message is not that the workspace already exists, something went really wrong
                    throw $e;
                }
            }
        }

        return new \Jackalope\Repository(null, $transport, $this->getRepositoryFactoryParameters());
    }

    public function getFixtureLoader()
    {
        return new \Jackalope\Test\Tester\FilesystemFixtureLoader();
    }

}
