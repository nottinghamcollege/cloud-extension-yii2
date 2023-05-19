<?php

namespace craft\cloud;

use Craft;
use craft\cloud\fs\CpResourcesFs;
use craft\cloud\fs\Fs;
use yii\base\InvalidArgumentException;

class AssetManager extends \craft\web\AssetManager
{
    public Fs $fs;
    public $basePath = '';

    public function init(): void
    {
        $this->fs = Craft::createObject([
            'class' => CpResourcesFs::class,
        ]);

        $this->baseUrl = $this->fs->getRootUrl();
        parent::init();
    }

    /**
     * @inheritDoc
     */
    protected function publishFile($src): array
    {
        $dir = $this->hash($src);
        $fileName = basename($src);
        $dest = "$dir/$fileName";
        $prefixedDest = $this->fs->getPrefix($dest);
        $stream = @fopen($src, 'rb');

        if (!$stream) {
            throw new InvalidArgumentException("Could not open file for publishing: $src");
        }

        $this->fs->writeFileFromStream($dest, $stream);

        return [$dest, Module::getCdnUrl($prefixedDest)];
    }

    /**
     * @inheritDoc
     */
    protected function publishDirectory($src, $options): array
    {
        $hash = $this->hash($src);
        $dest = $this->fs->getPrefix($this->hash($src));
        // TODO: try/catch, options, check if exists first?
        // Note: Flysystem's directoryExists doesn't work.
        $this->fs->uploadDirectory($src, $hash);

        return [$dest, Module::getCdnUrl($dest)];
    }

    /**
     * @inheritDoc
     * Always use published assets, regardless of isEphemeral
     */
    public function publish($path, $options = []): array
    {
        if ($options['force'] ?? false) {
            return parent::publish($path, $options);
        }

        return [$path, $this->getPublishedUrl($path)];
    }
}
