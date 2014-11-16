<?php

namespace Transport\Fs;

use Prophecy\PhpUnit\ProphecyTestCase;
use Jackalope\RepositoryFactoryFilesystem;
use PHPCR\SimpleCredentials;
use Symfony\Component\Filesystem\Filesystem;

abstract class FunctionalTestCase extends ProphecyTestCase
{
    protected $path;

    public function getSession($parameters)
    {
        $this->path = __DIR__ . '/../../data';

        $fs = new Filesystem();

        if (file_exists($this->path)) {
            $fs->remove($this->path);
        }

        $parameters = array_merge(array(
            'path' => $this->path, 
        ), $parameters);

        $factory = new RepositoryFactoryFilesystem();
        $repository = $factory->getRepository($parameters);
        $credentials = new SimpleCredentials('admin', 'admin');
        $session = $repository->login($credentials);

        return $session;
    }
}
