<?php

namespace craft\cloud\fs;

use Craft;
use League\Uri\Components\HierarchicalPath;

class BuildsFs extends Fs
{
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;

    public function prefixPath(string $path = ''): string
    {
        // TODO: default ot $CRAFT_CLOUD_BUILD_ID
        return parent::prefixPath(HierarchicalPath::createRelativeFromSegments([
            'builds',
            Craft::$app->getConfig()->getGeneral()->buildId ?? '',
            $path,
        ]));
    }
}
