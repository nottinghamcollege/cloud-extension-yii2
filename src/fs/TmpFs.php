<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class TmpFs extends StorageFs
{
    public function getPrefix(): string
    {
        return HierarchicalPath::fromRelative(
            parent::getPrefix(),
            'tmp',
        )->withoutEmptySegments()->withoutTrailingSlash();
    }
}
