<?php

namespace craft\cloud\fs;

use craft\cloud\Module;
use League\Uri\Components\HierarchicalPath;

class BuildsFs extends Fs
{
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;

    public function getPrefix(): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            parent::getPrefix(),
            'builds',
            Module::getInstance()->getConfig()->buildId ?? '',
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }
}
