<?php

namespace craft\cloud;

use Craft;
use craft\helpers\Assets;

class Fs extends \craft\awss3\Fs
{
    public string $bucket = '$CRAFT_CLOUD_PROJECT_ID';
    public string $region = '$AWS_REGION';
    public string $bucketSelectionMode = 'manual';

    public static function displayName(): string
    {
        return 'Craft Cloud';
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('fsSettings', [
            'fs' => $this,
            'periods' => array_merge(['' => ''], Assets::periodList()),
        ]);
    }
}
