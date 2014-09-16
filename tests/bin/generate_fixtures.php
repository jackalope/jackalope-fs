#!/bin/env php
<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

require_once(__DIR__ . '/../../vendor/autoload.php');

}

$gen = new FixtureGenerator();
$gen->generateFixtures(
    __DIR__ . '/../../vendor/phpcr/phpcr-api-tests/fixtures',
    __DIR__ . '/../data/tests'
);
