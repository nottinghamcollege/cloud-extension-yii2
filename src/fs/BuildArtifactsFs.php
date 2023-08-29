<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class BuildArtifactsFs extends BuildsFs
{
    public function getRootPath(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getRootPath(),
            'artifacts',
        ]);
    }
}
