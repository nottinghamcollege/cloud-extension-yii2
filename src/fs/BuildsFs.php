<?php

namespace craft\cloud\fs;

use craft\cloud\Module;
use League\Uri\Components\HierarchicalPath;

abstract class BuildsFs extends Fs
{
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;

    public function getPrefix(): string
    {
        if (!Module::getInstance()->getConfig()->getUseArtifactCdn()) {
            return '';
        }

        return HierarchicalPath::createRelativeFromSegments([
            parent::getPrefix(),
            'builds',
            Module::getInstance()->getConfig()->buildId,
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }
}
