<?php

namespace craft\cloud;

use Craft;
use craft\cloud\fs\CpResourcesFs;
use craft\helpers\FileHelper;

class AssetManager extends \craft\web\AssetManager
{
    public function publish($path, $options = []): array
    {
        $this->preparePaths();
        return parent::publish($path, $options);
    }

    protected function hash($path): string
    {
        if (is_callable($this->hashCallback)) {
            return call_user_func($this->hashCallback, $path);
        }

        $dir = is_file($path) ? dirname($path) : $path;

        // TODO: what?
        /** @phpstan-ignore-next-line */
        $alias = Craft::alias($dir);
        return sprintf('%x', crc32($alias . '|' . FileHelper::lastModifiedTime($path) . '|' . $this->linkAssets));
    }

    protected function preparePaths(): void
    {
        $this->basePath = Craft::getAlias($this->basePath);
        FileHelper::createDirectory($this->basePath);

        if (Module::getInstance()->getConfig()->useAssetBundleCdn) {
            $this->baseUrl = (new CpResourcesFs())->getRootUrl();
        }
    }
}
