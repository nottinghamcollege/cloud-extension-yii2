<?php

namespace craft\cloud;

use Craft;
use craft\elements\Asset;
use craft\events\AssetEvent;
use craft\helpers\Assets;
use yii\base\Behavior;

/**
 * @property Asset $owner
 */
class AssetBehavior extends Behavior
{
    public string $tempBucket;
    public string $tempBucketKey;

    public function events(): array
    {
        return parent::events() + [
            // Asset::EVENT_BEFORE_HANDLE_FILE => 'beforeHandleFile'
        ];
    }

    public function beforeHandleFile(AssetEvent $event): void
    {
        /** @var Asset|AssetBehavior $asset */
        $asset = $event->asset;
        $newLocation = $asset->newLocation;
        $asset->newLocation = null;
        $asset->tempFilePath = null;

        if ($asset->getScenario() !== Asset::SCENARIO_CREATE) {
            return;
        }

        if (!isset($asset->tempBucket, $asset->tempBucketKey, $newLocation)) {
            return;
        }

        [$folderId, $filename] = Assets::parseFileLocation($newLocation);

        $newFolder = Craft::$app->getAssets()->getFolderById($folderId);
        $newVolume = $newFolder->getVolume();

        Craft::$app->getAssets()->moveAsset($asset, $newFolder, $filename);
    }
}
