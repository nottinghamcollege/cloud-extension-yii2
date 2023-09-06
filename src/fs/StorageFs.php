<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class StorageFs extends Fs
{
    public bool $hasUrls = false;

    public function prefixPath(string $path = ''): string
    {
        return parent::prefixPath(HierarchicalPath::createRelativeFromSegments([
            'storage',
            $path,
        ]));
    }
}
