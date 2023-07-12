<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class CpResourcesFs extends BuildsFs
{
    public function getBasePath(): HierarchicalPath
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getBasePath(),
            'cpresources',
        ]);
    }
}
