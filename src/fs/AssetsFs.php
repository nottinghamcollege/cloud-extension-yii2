<?php

namespace craft\cloud\fs;

use craft\cloud\Module;
use League\Uri\Components\HierarchicalPath;

class AssetsFs extends Fs
{
    public ?string $localFsPath = '@webroot/craft-cloud/{handle}';
    public ?string $localFsUrl = '@web/craft-cloud/{handle}';

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Craft Cloud';
    }

    public function getPrefix(): string
    {
        if (!Module::getInstance()->getConfig()->getUseAssetCdn()) {
            return '';
        }

        return HierarchicalPath::createRelativeFromSegments([
            parent::getPrefix(),
            'assets',
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }
}
