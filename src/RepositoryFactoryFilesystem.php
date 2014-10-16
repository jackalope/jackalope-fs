<?php

namespace Jackalope;

use PHPCR\RepositoryFactoryInterface;

/**
 * This factory creates repositories with the Doctrine DBAL transport
 *
 * Use repository factory based on parameters (the parameters below are examples):
 *
 * <pre>
 *    $parameters = array('jackalope.doctrine_dbal_connection' => $dbConn);
 *    $factory = new \Jackalope\RepositoryFactoryDoctrineDBAL();
 *    $repository = $factory->getRepository($parameters);
 * </pre>
 *
 * @license http://www.apache.org/licenses Apache License Version 2.0, January 2004
 * @license http://opensource.org/licenses/MIT MIT License
 *
 * @api
 */
class RepositoryFactoryFilesystem implements RepositoryFactoryInterface
{
    public function getRepository(array $parameters = null)
    {
        $factory = new Factory();
        $transport = $factory->get('Transport\Fs\Client', array($parameters));
        $options = array();

        return new Repository($factory, $transport, $options);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getConfigurationKeys()
    {
        return array(
            'path',
            'search_enabled',
        );
    }
}

