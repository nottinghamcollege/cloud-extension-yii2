<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class StorageFs extends Fs
{
    public bool $hasUrls = false;

    public function getRootPath(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getRootPath(),
            'storage',
        ]);
    }
}
