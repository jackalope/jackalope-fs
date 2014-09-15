<?php

namespace Jackalope\Transport\Fs\Filesystem\Adapter;

use Jackalope\Transport\Fs\Filesystem\AdapterInterface;
use Symfony\Component\Filesystem\Filesystem as SfFilesystem;

class LocalAdapter implements AdapterInterface
{
    protected $path;
    protected $mode;

    public function __construct($path, $mode = 0777)
    {
        $this->path = $path;
        $this->mode = $mode;
        $this->fs = new SfFilesystem();
    }
    
    public function write($path, $contents)
    {
        $this->fs->dumpFile($this->getAbsPath($path), $contents, $this->mode);
    }

    public function mkdir($path)
    {
        $this->fs->mkdir($this->getAbsPath($path));
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
        $this->fs->remove($this->getAbsPath($path));
    }

    public function exists($path)
    {
        return file_exists($this->getAbsPath($path));
    }

    private function getAbsPath($path)
    {
        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }

        return $this->path . '/' . $path;
    }
}
