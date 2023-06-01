<?php

namespace craft\cloud\fs;

class CpResourcesFs extends Fs
{
    protected ?string $type = 'cpresources';
    protected ?string $expires = '1 year';
    public bool $hasUrls = true;
}
