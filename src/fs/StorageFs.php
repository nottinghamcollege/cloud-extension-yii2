<?php

namespace craft\cloud\fs;

class StorageFs extends Fs
{
    protected ?string $type = 'storage';
    public bool $hasUrls = false;
}
