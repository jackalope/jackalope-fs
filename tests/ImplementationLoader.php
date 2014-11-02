<?php

use Symfony\Component\Filesystem\Filesystem;

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
     * Base path for content repository
     * @var string
     */
    private $path;

    protected function __construct()
    {
        parent::__construct('Jackalope\RepositoryFactoryFilesystem', $GLOBALS['phpcr.workspace']);

        $this->unsupportedChapters = array(
            // to be supported
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
            'PhpcrUtils',

            // not supported yet
            'PermissionsAndCapabilities',
            'Observation',
        );

        $this->unsupportedCases = array(
                    'Query\\XPath', // Query language 'xpath' not implemented.
                    'Query\\Sql1', // Query language 'sql' is legacy and only makes sense with jackrabbit

                    'Writing\\CloneMethodsTest', // TODO: Support for workspace->clone, node->update, node->getCorrespondingNodePath
        );

        $this->unsupportedTests = array(
            'Connecting\\RepositoryTest::testLoginException', //TODO: figure out what would be invalid credentials
            'Reading\\NodeReadMethodsTest::testGetSharedSetUnreferenced', // TODO: should this be moved to 14_ShareableNodes
            'Reading\SessionNamespaceRemappingTest::testSetNamespacePrefix', // not supported by jackalope
            'Reading\\SessionReadMethodsTest::testImpersonate', //TODO: Check if that's implemented in newer jackrabbit versions.

            // see https://github.com/jackalope/jackalope/pull/229
            'Reading\\NodeReadMethodsTest::testGetNodesTypeFilter', //TODO implement node type filtering
            'Reading\\NodeReadMethodsTest::testGetNodesTypeFilterList', //TODO implement node type filtering
            'Query\\NodeViewTest::testSeekable', // see https://github.com/phpcr/phpcr-api-tests/issues/141

            // not supported by jackalope
            'Query\\QueryManagerTest::testGetQuery',
            'Query\\QueryManagerTest::testGetQueryInvalid',
            'Query\\QueryObjectSql2Test::testGetStoredQueryPath',

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

            // this doesn't work with ZendSearch -- need to implement a native search engine
            'Writing\CombinedManipulationsTest::testAddAndRemoveAndAdd',
            'Writing\CombinedManipulationsTest::testRemoveAndAddToplevelNode',
            'Writing\CombinedManipulationsTest::testRemoveAndAddAndRemoveToplevelNode',

            'Writing\\CopyMethodsTest::testCopyUpdateOnCopy', //TODO: update-on-copy is currently not supported
            'Writing\\CopyMethodsTest::testCopyUpdateOnCopy', //TODO: update-on-copy is currently not supported

            'Writing\LastModifiedTest::testUpdateText',
            'Writing\LastModifiedTest::testUpdateBinary',
            'Writing\LastModifiedTest::testRemoveProperty',

        );

        $this->path = __DIR__ . '/data';

        // start with a clean system
        $filesystem = new Filesystem();
        $filesystem->remove($this->path);
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
        $transport = new \Jackalope\Transport\Fs\Client(new \Jackalope\Factory, array(
            'path' => $this->path,
            'search_enabled' => true,
        ));
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
