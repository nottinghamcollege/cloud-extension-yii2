<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class TmpFs extends StorageFs
{
    public function prefixPath(string $path = ''): string
    {
        return parent::prefixPath(HierarchicalPath::createRelativeFromSegments([
            'tmp',
            $path,
        ]));
    }
}
