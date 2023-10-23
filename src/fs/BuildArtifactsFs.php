<?php

namespace craft\cloud\fs;

use craft\cloud\Module;
use League\Uri\Components\HierarchicalPath;

class BuildArtifactsFs extends BuildsFs
{
    public ?string $localFsPath = '@webroot';
    public ?string $localFsUrl = '@web';

    public function getPrefix(): string
    {
        if (!Module::getInstance()->getConfig()->getUseArtifactCdn()) {
            return '';
        }

        return HierarchicalPath::createRelativeFromSegments([
            parent::getPrefix(),
            'artifacts',
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }
}
