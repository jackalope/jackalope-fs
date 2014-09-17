<?php

namespace Jackalope\Transport\Fs\Filesystem;

interface AdapterInterface
{
    public function write($path, $contents);

    public function mkdir($path);

    public function read($path);

    public function remove($path, $recursive = false);

    public function exists($path);

    public function ls($path);
}
