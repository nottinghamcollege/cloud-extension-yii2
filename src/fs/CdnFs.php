<?php

namespace craft\cloud\fs;

use craft\cloud\Module;

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
            Module::getInstance()->getCdn()->purgeTags([
                $this->prefixPath($path),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
