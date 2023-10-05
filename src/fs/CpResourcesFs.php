<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class CpResourcesFs extends BuildsFs
{
    public function getPrefix(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getPrefix(),
            'cpresources',
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }
}
