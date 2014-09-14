<?php

namespace J\Transport\Filesysteackalope\Transport\Filesystem\Filesystem\Adapter;

use Filesystem\AdapterInterface;

class LocalAdapter implements AdapterInterface
{
    protected $path;
    protected $mode;

    public function __construct($path, $mode = 0777)
    {
        $this->path = $path;
        $this->mode = $mode;
    }
    
    public function write($path, $contents)
    {
        $this->ensureDirectoryExists(dirname($path));
    }

    public function mkdir($path)
    {
        mkdir($path, $this->mode, true);
    }

    public function read($path)
    {
        if (!$this->exists($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Could not find file at "%s"', $this->getAbsPath($path)
            ));
        }

        return file_get_contents($this->getAbsPath($path));
    }

    public function remove($path, $recursive = false)
    {
        $absPath = $this->getAbsPath($path);

        $removeFunc = function ($absPath) {
            if (false === unlink($absPath)) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not remove file "%s"', $absPath
                ));
            }
        };

        if (!$recursive) {
            $removeFunc($absPath);
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absPath), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file => $obj) {
            $removeFunc($file);
        }
    }

    public function exists($path)
    {
        return file_exists($this->getAbsPath($path));
    }

    public function ensureDirectoryExists($path)
    {
        if (!file_exists($path)) {
            $this->mkdir($path, true);
        }
    }

    private function getAbsPath($path)
    {
        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }

        return $this->path . '/' . $path;
    }
}
