<?php

namespace craft\cloud\cli\controllers;

use craft\cloud\fs\Fs;
use craft\console\Controller;
use craft\elements\Asset;
use yii\base\Exception;
use yii\console\ExitCode;

class AssetsController extends Controller
{
    public array $volumes = [];

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'replace-metadata' => ['volumes'],
            default => []
        });
    }

    public function actionReplaceMetadata(): int
    {
        $assets = Asset::find()
            ->volume($this->volumes)
            ->collect();

        $this->do('Replacing metadata', function() use ($assets) {
            $assets->each(function(Asset $asset) {
                $this->replaceAssetMetadata($asset);
            });
        });

        return ExitCode::OK;
    }

    protected function replaceAssetMetadata(Asset $asset): void
    {
        $fs = $asset->getVolume()->getFs();

        if (!$fs instanceof Fs) {
            throw new Exception('Invalid filesystem type.');
        }

        $pathInFs = $fs->prefixPath($asset->getPath());
        $fs->replaceMetadata($pathInFs);
    }
}
