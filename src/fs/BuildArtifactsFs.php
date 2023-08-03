<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class BuildArtifactsFs extends BuildsFs
{
    public function getBasePath(): HierarchicalPath
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getBasePath(),
            'artifacts',
        ]);
    }
}
