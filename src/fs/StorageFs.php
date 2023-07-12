<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;
use League\Uri\Contracts\PathInterface;

class StorageFs extends Fs
{
    public bool $hasUrls = false;

    public function getBasePath(): HierarchicalPath
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getBasePath(),
            'storage',
        ]);
    }
}
