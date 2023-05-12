<?php

namespace craft\cloud;

use Craft;
use craft\helpers\App;
use craft\helpers\Assets;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;

class Fs extends \craft\awss3\Fs
{
    public string $bucket = '$CRAFT_CLOUD_PROJECT_ID';
    public string $region = '$AWS_REGION';
    public string $bucketSelectionMode = 'manual';
    public bool $makeUploadsPublic = false;

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
