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

    public function move($srcPath, $destPath)
    {
        $this->fs->rename(
            $this->getAbsPath($srcPath), 
            $this->getAbsPath($destPath)
        );
    }

    public function copy($srcPath, $destPath)
    {
        $this->fs->mirror(
            $this->getAbsPath($srcPath), 
            $this->getAbsPath($destPath)
        );
    }

    public function exists($path)
    {
        $path = $this->getAbsPath($path);
        $exists = file_exists($path);

        return $exists;
    }

    public function ls($path)
    {
        $absPath = $this->getAbsPath($path);
        if (!is_dir($absPath)) {
            $absPath = $this->getAbsPath(dirname($absPath));
        }

        $res = opendir($absPath);

        $files = array(
            'files' => array(),
            'dirs' => array(),
        );

        while ($file = readdir($res)) {
            if (in_array($file, array('..', '.'))) {
                continue;
            }

            if (is_dir($absPath . '/' . $file)) {
                $files['dirs'][] = $file;
            } else {
                $files['files'][] = $file;
            }
        }

        return $files;
    }

    public function stream($path)
    {
        if (!$this->exists($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Could not find file to stream at "%s"', $this->getAbsPath($path)
            ));
        }
        $absPath = $this->getAbsPath($path);
        $res = fopen($absPath, 'r');
        $this->handles[] = $res;

        return $res;
    }

    private function getAbsPath($path)
    {
        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }

        return $this->path . '/' . $path;
    }
}
