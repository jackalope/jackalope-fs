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
            'Export',
            'NodeTypeDiscovery',
            'PermissionsAndCapabilities',
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
            'Reading\SessionNamespaceRemappingTest::testSetNamespacePrefix', // not supported by jackalope
            'Reading\\SessionReadMethodsTest::testImpersonate', //TODO: Check if that's implemented in newer jackrabbit versions.
            'Query\\NodeViewTest::testSeekable', // see https://github.com/phpcr/phpcr-api-tests/issues/141

            // not supported by jackalope
            'Query\\QueryManagerTest::testGetQuery',
            'Query\\QueryManagerTest::testGetQueryInvalid',
            'Query\\QueryObjectSql2Test::testGetStoredQueryPath',

            // sql2 + xpath not supported by FS
            'Query\\Sql1\\QueryOperationsTest::testQueryField',
            'Query\\Sql1\\QueryOperationsTest::testQueryFieldSomenull',
            'Query\\Sql1\\QueryOperationsTest::testQueryOrder',
            'Query\\XPath\\QueryOperationsTest::testQueryField',
            'Query\\XPath\\QueryOperationsTest::testQueryFieldSomenull',
            'Query\\XPath\\QueryOperationsTest::testQueryOrder',


            'Query\\QuerySql2OperationsTest::testQueryJoin',
            'Query\\QuerySql2OperationsTest::testQueryJoinChildnode',
            'Query\\QuerySql2OperationsTest::testQueryJoinReference',
            'Query\\QuerySql2OperationsTest::testQueryJoinWithAlias',
            'Query\\QuerySql2OperationsTest::testQueryLeftJoin',
            'Query\\QuerySql2OperationsTest::testQueryRightJoin',

            // length not supported
            'Query\QuerySql2OperationsTest::testLengthOperandOnBinaryProperty',
            'Query\QuerySql2OperationsTest::testLengthOperandOnEmptyProperty',
            'Query\QuerySql2OperationsTest::testLengthOperandOnStringProperty',


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
        return false;
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
