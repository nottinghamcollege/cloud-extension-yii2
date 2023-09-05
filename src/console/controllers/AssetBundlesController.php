<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\cloud\AssetBundlePublisher;
use craft\console\Controller;
use ReflectionClass;
use yii\console\Exception;
use yii\console\ExitCode;
use yii\web\AssetBundle;

class AssetBundlesController extends Controller
{
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
            // `do` prints the error, so nothing else to do here.
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
