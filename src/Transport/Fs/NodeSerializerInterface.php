<?php

namespace Jackalope\Transport\Fs;

use Jackalope\Transport\Fs\Model\Node;

interface NodeSerializerInterface
{
    /**
     * Serialize a (Jackalope FS) Node into the storage format
     *
     * @param Node $node
     * @return mixed
     */
    public function serialize(Node $data);

    /**
     * Deserialize the storage format to a (Jackalope FS) Node
     *
     * @param mixed
     * @return Node
     */
    public function deserialize($data);
}
