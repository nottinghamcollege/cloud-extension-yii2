<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class TmpFs extends StorageFs
{
    public function getBasePath(): HierarchicalPath
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getBasePath(),
            'tmp',
        ]);
    }

}
