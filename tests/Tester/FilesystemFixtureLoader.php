<?php

namespace Jackalope\Test\Tester;

use PHPCR\Test\FixtureLoaderInterface;
use Jackalope\Transport\Fs\Test\FixtureGenerator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Filesystem fixture loader
 */
class FilesystemFixtureLoader implements FixtureLoaderInterface
{
    public function import($fixture, $workspaceKey = null)
    {
        $destDir = __DIR__ . '/../data/tests';
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/../data');
        $fs->mkdir(__DIR__ . '/../data');


        $fixtureGenerator = new FixtureGenerator();
        $srcDir = __DIR__ . '/../../vendor/phpcr/phpcr-api-tests/fixtures/' . $fixture . '.xml';
        $fixtureGenerator->generateFixtures($srcDir, $destDir);
    }
}
