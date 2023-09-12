<?php

namespace craft\cloud\cli\controllers;

use Craft;
use craft\cloud\AssetBundlePublisher;
use craft\console\Controller;
use ReflectionClass;
use yii\console\Exception;
use yii\console\ExitCode;
use yii\web\AssetBundle;

class AssetBundlesController extends Controller
{
    public bool $quiet = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'publish-bundle' => [
                'quiet',
            ],
            default => [],
        });
    }

    public function actionPublishBundle(string $className): int
    {
        try {
            $this->do("Publishing “{$className}”", function() use ($className) {
                $rc = new ReflectionClass($className);

                if (!$rc->isSubclassOf(AssetBundle::class) || !$rc->isInstantiable()) {
                    throw new Exception('Not a valid asset bundle.');
                }

                /** @var AssetBundle $assetBundle */
                $assetBundle = Craft::createObject($className);
                $assetManager = Craft::$app->getAssetManager();
                $assetBundle->publish($assetManager);
            });
        } catch (\Throwable $e) {
            if (!$this->quiet) {
                throw $e;
            }
        }

        return ExitCode::OK;
    }

    public function actionPublish(): int
    {
        $publisher = new AssetBundlePublisher();
        $publisher->wait();

        return ExitCode::OK;
    }
}
