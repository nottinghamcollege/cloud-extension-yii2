<?php

namespace craft\cloud\fs;

use Craft;
use League\Uri\Components\HierarchicalPath;

class BuildsFs extends Fs
{
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;

    public function getBasePath(): HierarchicalPath
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getBasePath(),
            'builds',
            Craft::$app->getConfig()->getGeneral()->buildId ?? '',
        ]);
    }
}
