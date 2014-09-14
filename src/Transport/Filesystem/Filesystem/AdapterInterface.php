<?php

namespace Jackalope\Transport\Filesystem\Filesystem;

interface AdapterInterface
{
    public function write($path, $contents);

    public function mkdir($path);

    public function read($path, $contents);

    public function remove($path, $recursive = false);

    public function exists($path);
}
