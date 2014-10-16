<?php

namespace Jackalope\Transport\Fs\Search;

interface SearchAdapterInterface
{
    /**
     * Index the given node data
     *
     * @param string $workspaceName
     * @param sting $path
     * @param array $nodeData
     */
    public function index($workspace, $path, $nodeData);
}
