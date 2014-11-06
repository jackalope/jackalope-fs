<?php

namespace Jackalope\Transport\Fs\Search;

use Jackalope\Transport\Fs\Model\Node;

interface SearchAdapterInterface
{
    /**
     * Index the given node data
     *
     * @param string $workspaceName
     * @param sting $path
     * @param array $nodeData
     */
    public function index($workspace, $path, Node $node);
}
