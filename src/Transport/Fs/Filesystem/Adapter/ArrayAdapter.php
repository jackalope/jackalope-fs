<?php

namespace Jackalope\Transport\Fs\Filesystem\Adapter;

use Jackalope\Transport\Fs\Filesystem\AdapterInterface;

/**
 * Array adapter for testing
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class ArrayAdapter implements AdapterInterface
{
    private $paths = array();

    public function write($path, $contents)
    {
        $this->paths[$path] = $contents;
    }

    public function mkdir($path)
    {
        $this->paths[$path] = array();
    }

    public function read($path)
    {
        if (!isset($this->paths[$path])) {
            throw new \InvalidArgumentException(sprintf(
                'Could not find file at "%s"', $path
            ));
        }

        return $this->paths[$path];
    }

    public function remove($path, $recursive = false)
    {
        unset($this->paths[$path]);
    }

    public function move($srcPath, $destPath)
    {
        $this->paths[$destPath] = $this->read($srcPath);
        $this->remove($srcPath);
    }

    public function copy($srcPath, $destPath)
    {
        $this->paths[$destPath] = $this->read($srcPath);
    }

    public function exists($path)
    {
        return isset($this->paths[$path]);
    }

    public function ls($targetPath)
    {
        $children = array();

        foreach (array_keys($this->paths) as $path) {
            if ($path !== $targetPath && 0 === strpos($path, $targetPath)) {
                $children[] = $path;
            }
        }

        return $children;
    }

    public function stream($path)
    {
        throw new \InvalidArgumentException('Not implemented');
    }
}

