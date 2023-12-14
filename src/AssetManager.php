<?php

namespace craft\cloud;

use Craft;
use craft\cloud\fs\CpResourcesFs;
use craft\helpers\FileHelper;

class AssetManager extends \craft\web\AssetManager
{
    public bool $cacheSourcePaths = false;

    public function init(): void
    {
        $this->preparePaths();
        parent::init();
    }

    public function publish($path, $options = []): array
    {
        $this->preparePaths();
        return parent::publish($path, $options);
    }

    protected function preparePaths(): void
    {
        $this->basePath = Craft::getAlias($this->basePath);

        if (!Helper::isCraftCloud()) {
            FileHelper::createDirectory($this->basePath);
        }

        if (Module::getInstance()->getConfig()->useAssetBundleCdn) {
            $this->baseUrl = (new CpResourcesFs())->getRootUrl();
        }
    }

    protected function hash($path): string
    {
        $dir = is_file($path) ? dirname($path) : $path;
        $data = [
            // @phpstan-ignore-next-line
            Craft::alias($dir),
            $this->linkAssets,
        ];

        return sprintf(
            '%x',
            crc32(implode('|', $data)),
        );
    }
}
