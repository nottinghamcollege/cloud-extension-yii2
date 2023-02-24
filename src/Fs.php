<?php

namespace craft\cloud;

class Fs extends \craft\awss3\Fs
{
    public static function displayName(): string
    {
        return 'Craft Cloud';
    }
}
