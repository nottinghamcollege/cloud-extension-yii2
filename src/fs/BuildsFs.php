<?php

namespace craft\cloud\fs;

use Craft;
use League\Uri\Components\HierarchicalPath;

class BuildsFs extends Fs
{
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;

    public function getRootPath(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getRootPath(),
            'builds',
            Craft::$app->getConfig()->getGeneral()->buildId ?? '',
        ]);
    }
}
