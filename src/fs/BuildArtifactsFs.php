<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class BuildArtifactsFs extends BuildsFs
{
    public function getPrefix(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getPrefix(),
            'artifacts',
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }
}
