<?php

namespace Jackalope\Transport\Fs\Model;

use PHPCR\NodeInterface;

/**
 * @author Daniel Leech <daniel@dantleech.com>
 */
class NodeCollection extends \ArrayObject
{
    public function toJackalopeStructures()
    {
        $ret = array();
        foreach ($this as $name => $node) {
            $ret[$name] = $node->toJackalopeStructure();
        }

        return $ret;
    }
}
