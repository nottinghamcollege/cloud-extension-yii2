<?php

namespace craft\cloud;

use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;

class Cdn extends \yii\base\Component
{
    public function purgeTags(array $tags): ResponseInterface
    {
        $headers = Collection::make([
            HeaderEnum::CACHE_PURGE_TAG->value => $tags,
        ]);

        return Helper::makeCdnApiRequest($headers);
    }
}
