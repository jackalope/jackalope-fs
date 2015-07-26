<?php

namespace Jackalope\Transport\Fs\Event;

use Symfony\Component\EventDispatcher\Event;
use Jackalope\Transport\Fs\Model\Node;

/**
 * Event which is dispatched when a node is written.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class NodeWriteEvent extends Event
{
    protected $workspace;
    protected $path;
    protected $node;

    public function __construct($workspace, $path, Node $node)
    {
        $this->workspace = $workspace;
        $this->path = $path;
        $this->node = $node;
    }

    public function getWorkspace()
    {
        return $this->workspace;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getNode()
    {
        return $this->node;
    }
}
