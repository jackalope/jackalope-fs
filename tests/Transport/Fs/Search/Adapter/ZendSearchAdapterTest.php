<?php

namespace Jackalope\Transport\Fs\Search\Adapter;

use Jackalope\Transport\Fs\Search\Adapter\ZendSearchAdapter;

class ZendSearchAdapterTest extends AdapterTestCase
{
    public function getAdapter()
    {
        return new ZendSearchAdapter($this->path, $this->nodeTypeManager);
    }
}

