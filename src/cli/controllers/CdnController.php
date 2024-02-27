<?php

namespace craft\cloud\cli\controllers;

use craft\cloud\fs\CdnFs;
use craft\cloud\HeaderEnum;
use craft\cloud\Helper;
use craft\console\Controller;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use yii\console\ExitCode;

class CdnController extends Controller
{
    public function actionPurgePrefixes(string ...$prefixes): int
    {
        $this->do('Purging prefixes', function() use ($prefixes) {
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

            Helper::makeCdnApiRequest($headers);
        });

        return ExitCode::OK;
    }
}
