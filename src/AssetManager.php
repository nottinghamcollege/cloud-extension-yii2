<?php

namespace craft\cloud;

use Craft;
use craft\cloud\fs\Fs;
use yii\base\InvalidArgumentException;

class AssetManager extends \craft\web\AssetManager
{
    public Fs $fs;
    public $basePath = '';
    public $baseUrl = '';
    private array $_published = [];

    /**
     * @inheritDoc
     */
    protected function publishFile($src): array
    {
        $dir = $this->hash($src);
        $fileName = basename($src);
        $dest = "$dir/$fileName";
        $prefixedDest = $this->fs->prefixPath($dest);
        $stream = @fopen($src, 'rb');

        if (!$stream) {
            throw new InvalidArgumentException("Could not open file for publishing: $src");
        }

        $this->fs->writeFileFromStream($dest, $stream);

        return [$dest, Helper::getCdnUrl($prefixedDest)];
    }

    /**
     * @inheritDoc
     */
    protected function publishDirectory($src, $options): array
    {
        $hash = $this->hash($src);

        // TODO: try/catch
        // if (!$this->fs->directoryExists($hash)) {
            $this->fs->uploadDirectory($src, $hash);
        // }

        // TODO: use getPublicUrl
        $dest = $this->fs->prefixPath($this->hash($src));

        return [$dest, Helper::getCdnUrl($dest)];
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
