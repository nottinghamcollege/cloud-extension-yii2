<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class AssetFs extends Fs
{
    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Craft Cloud';
    }

    public function getBasePath(): HierarchicalPath
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getBasePath(),
            'assets',
        ]);
    }
}
