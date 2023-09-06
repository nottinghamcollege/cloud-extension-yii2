<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class CpResourcesFs extends BuildsFs
{
    public function prefixPath(string $path = ''): string
    {
        return parent::prefixPath(HierarchicalPath::createRelativeFromSegments([
            'cpresources',
            $path,
        ]));
    }
}
