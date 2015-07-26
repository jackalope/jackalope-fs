<?php

namespace Jackalope\Transport\Fs\Filesystem;

class PathRegistry
{
    protected $pathMap = array();
    protected $propertyMap = array();

    public function registerUuid($path, $uuid)
    {
        $this->pathMap[$path] = $uuid;
    }

    public function getUuid($path)
    {
        if (!isset($this->pathMap[$path])) {
            throw new \InvalidArgumentException(sprintf(
                'Path "%s" has not been registered in path registry', $path));
        }

        return $this->pathMap[$path];
    }

    public function hasPath($path)
    {
        return isset($this->pathMap[$path]);
    }
}
