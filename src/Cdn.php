<?php

namespace craft\cloud;

use craft\cloud\fs\CdnFs;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;

class Cdn extends \yii\base\Component
{
    public function purgePrefixes(array $prefixes): ResponseInterface
    {
        $prefixes = Collection::make($prefixes)
            ->map(function($prefix) {
                return StringHelper::removeLeft(
                    $prefix,
                    (new CdnFs())->getRootUrl(),
                );
            });

        $headers = Collection::make([
            HeaderEnum::CACHE_PURGE_PREFIX->value => $prefixes->implode(','),
        ]);

        return Helper::makeCdnApiRequest($headers);
    }
}
