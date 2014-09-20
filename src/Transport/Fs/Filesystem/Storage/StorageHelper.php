<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use Jackalope\Transport\Fs\Filesystem\Storage;
use PHPCR\Util\PathHelper;

class StorageHelper
{
    public function getNodePath($workspace, $path, $withFilename = true)
    {
        $path = PathHelper::normalizePath($path);

        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }

        if ($path) {
            $path .= '/';
        }

        $nodeRecordPath = Storage::WORKSPACE_PATH . '/' . $workspace . '/' . $path . 'node.yml';

        if ($withFilename === false) {
            $nodeRecordPath = dirname($nodeRecordPath);
        }

        return $nodeRecordPath;
    }
}
