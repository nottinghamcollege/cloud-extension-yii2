<?php

namespace craft\cloud\fs;

use craft\cloud\Module;
use League\Uri\Components\HierarchicalPath;

class BuildArtifactsFs extends BuildsFs
{
    public ?string $localFsPath = '@webroot';
    public ?string $localFsUrl = '@web';

    protected function useLocalFs(): bool
    {
        return !Module::getInstance()->getConfig()->getUseArtifactCdn();
    }

    public function getPrefix(): string
    {
        if (!Module::getInstance()->getConfig()->getUseArtifactCdn()) {
            return '';
        }

        return HierarchicalPath::fromRelative(
            parent::getPrefix(),
            'artifacts',
        )->withoutEmptySegments()->withoutTrailingSlash();
    }
}
