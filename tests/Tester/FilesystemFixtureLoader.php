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
    public function import($fixture, $workspaceKey = 'tests')
    {
        // jackalope 
        if ($workspaceKey == 'additionalWorkspace') {
            $workspaceKey = 'testsAdditional';
        }
        $destDir = __DIR__ . '/../data/workspaces/' . $workspaceKey;
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/../data');
        $fs->mkdir($destDir);

        $fixtureGenerator = new FixtureGenerator();
        $srcDir = __DIR__ . '/../../vendor/phpcr/phpcr-api-tests/fixtures/' . $fixture . '.xml';
        $fixtureGenerator->generateFixtures($workspaceKey, __DIR__ . '/../data', $srcDir);
    }
}
