<?php

namespace craft\cloud\fs;

use Craft;
use League\Uri\Components\HierarchicalPath;

class BuildsFs extends Fs
{
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;

    // TODO: default to $CRAFT_CLOUD_BUILD_ID?
    public function getPrefix(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getPrefix(),
            'builds',
            Craft::$app->getConfig()->getGeneral()->buildId ?? '',
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }
}
