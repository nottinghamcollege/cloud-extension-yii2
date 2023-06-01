<?php

namespace craft\cloud\fs;

class AssetFs extends Fs
{
    protected ?string $type = 'assets';

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Craft Cloud';
    }
}
