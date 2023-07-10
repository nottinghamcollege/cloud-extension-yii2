<?php

namespace craft\cloud\fs;

use Illuminate\Support\Collection;

class CpResourcesFs extends BuildsFs
{
    public function getSubfolder(): ?string
    {
        return Collection::make([
            parent::getSubfolder(),
            'cpresources'
        ])->join('/');
    }
}
