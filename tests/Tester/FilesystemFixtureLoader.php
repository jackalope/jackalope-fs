<?php

namespace Jackalope\Test\Tester;

use PHPCR\Test\FixtureLoaderInterface;

/**
 * Filesystem fixture loader
 */
class FilesystemFixtureLoader implements FixtureLoaderInterface
{
    protected $distPath;
    protected $testPath;

    public function __construct($distPath, $testPath)
    {
        $this->distPath = $distPath;
        $this->testPath = $testPath;
    }

    public function import($fixture, $workspaceKey = 'workspace')
    {
    }
}
