<?php

namespace craft\cloud\fs;

class AssetFs extends Fs
{
    protected ?string $typePrefix = 'assets';

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Craft Cloud';
    }
}
