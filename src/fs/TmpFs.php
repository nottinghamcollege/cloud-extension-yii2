<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class TmpFs extends StorageFs
{
    public function getRootPath(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getRootPath(),
            'tmp',
        ]);
    }

}
