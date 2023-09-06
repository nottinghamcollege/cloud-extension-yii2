<?php

namespace craft\cloud\fs;

use League\Uri\Components\HierarchicalPath;

class AssetsFs extends Fs
{
    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Craft Cloud';
    }

    public function prefixPath(string $path = ''): string
    {
        return parent::prefixPath(HierarchicalPath::createRelativeFromSegments([
            'assets',
            $path,
        ]));
    }
}
