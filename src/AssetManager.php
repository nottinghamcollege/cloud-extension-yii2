<?php

namespace craft\cloud;

use Craft;
use craft\cloud\fs\Fs;
use craft\helpers\FileHelper;
use League\Uri\Components\HierarchicalPath;
use yii\base\InvalidArgumentException;

class AssetManager extends \craft\web\AssetManager
{
    public Fs $fs;
    private array $_published = [];

    public function init()
    {
        $this->baseUrl = $this->fs->getRootUrl();
        $this->basePath = Craft::getAlias('@webroot/cpresources');
        FileHelper::createDirectory($this->basePath);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    protected function publishFile($src): array
    {
        $hash = $this->hash($src);
        $fileName = basename($src);
        $dest = (string) HierarchicalPath::new("$hash/$fileName");
        $stream = @fopen($src, 'rb');

        if (!$stream) {
            throw new InvalidArgumentException("Could not open file for publishing: $src");
        }

        copy($src, HierarchicalPath::new("$this->basePath/$dest"));

        return [$dest, $this->fs->createUrl($dest)];
    }

    /**
     * @inheritDoc
     */
    protected function publishDirectory($src, $options): array
    {
        $hash = $this->hash($src);

        FileHelper::copyDirectory(
            $src,
            HierarchicalPath::new("$this->basePath/$hash"),
        );

        return [$hash, $this->fs->createUrl($hash)];
    }

    /**
     * @inheritDoc
     * Always publish from cli, never from web
     */
    public function publish($path, $options = []): array
    {
        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            return [$path, $this->getPublishedUrl($path)];
        }

        $path = Craft::getAlias($path);

        if (isset($this->_published[$path])) {
            return $this->_published[$path];
        }

        $src = realpath($path);

        if ($src === false) {
            throw new InvalidArgumentException("The file or directory to be published does not exist: $path");
        }

        if (!is_readable($src)) {
            throw new InvalidArgumentException("The file or directory to be published is not readable: $path");
        }

        if (is_file($src)) {
            return $this->_published[$path] = $this->publishFile($src);
        }

        return $this->_published[$path] = $this->publishDirectory($src, $options);
    }
}
