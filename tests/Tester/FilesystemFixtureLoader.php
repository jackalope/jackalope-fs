<?php

namespace Jackalope\Test\Tester;

use PHPCR\Test\FixtureLoaderInterface;
use Jackalope\Transport\Fs\Test\FixtureGenerator;

/**
 * Filesystem fixture loader
 */
class FilesystemFixtureLoader implements FixtureLoaderInterface
{
    public function __construct()
    {
    }

    public function import($fixture, $workspaceKey = 'workspace')
    {
        $fixtureGenerator = new FixtureGenerator();
        $srcDir = __DIR__ . '/../../vendor/phpcr/phpcr-api-tests/fixtures';
        $fixtureGenerator->generateFixtures($srcDir, __DIR__ . '/../data/tests');
    }
}
