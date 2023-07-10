<?php

namespace craft\cloud\fs;

use Craft;

class BuildsFs extends Fs
{
    protected ?string $type = 'builds';
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;

    public function getSubfolder(): ?string
    {
        return Craft::$app->getConfig()->getGeneral()->buildId;
    }
}
