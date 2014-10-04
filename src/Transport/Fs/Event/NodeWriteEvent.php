<?php

namespace Jackalope\Transport\Fs\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event which is dispatched when a node is written.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class NodeWriteEvent extends Event
{
    protected $workspace;
    protected $path;
    protected $nodeData;

    public function __construct($workspace, $path, $nodeData)
    {
        $this->workspace = $workspace;
        $this->path = $path;
        $this->nodeData = $nodeData;
    }

    public function getWorkspace() 
    {
        return $this->workspace;
    }

    public function getPath() 
    {
        return $this->path;
    }

    public function getNodeData() 
    {
        return $this->nodeData;
    }
}
