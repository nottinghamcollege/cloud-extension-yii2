<?php

namespace craft\cloud\fs;

use craft\cloud\Module;
use craft\cloud\StaticCacheTag;

class CdnFs extends Fs
{
    protected ?string $expires = '1 years';
    public bool $hasUrls = true;

    /**
     * @inheritDoc
     */
    protected function invalidateCdnPath(string $path): bool
    {
        try {
            $prefix = sprintf('cdn:%s:', Module::getInstance()->getConfig()->environmentId);
            $tag = StaticCacheTag::create($this->prefixPath($path))
                ->minify(false)
                ->withPrefix($prefix);

            Module::getInstance()->getStaticCache()->purgeTags($tag);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
