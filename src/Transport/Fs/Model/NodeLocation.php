<?php

namespace Jackalope\Transport\Fs\Model;

/**
 * Represents the location of a node
 */
class NodeLocation
{
    private $workspace;
    private $path;

    public function __construct($workspace, $path)
    {
        $this->workspace = $workspace;
        $this->path = $path;
    }

    public function getWorkspace()
    {
        return $this->workspace;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function __toString()
    {
        return sprintf('%s:%s', $this->getWorkspace(), $this->getPath());
    }
}
