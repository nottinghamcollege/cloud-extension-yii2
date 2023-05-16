<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\App;
use craft\web\AssetBundle;
use Illuminate\Support\Collection;
use yii\console\ExitCode;

class CloudController extends Controller
{
    public function actionPublishAssetBundles(): int
    {
        $classMap = require(Craft::getAlias('@vendor/composer/autoload_classmap.php'));

        Collection::make($classMap)
            ->keys()

            // why?
            ->filter(fn($className) => str_starts_with($className, 'craft\\web\\assets\\'))

            ->filter(fn($className) => is_subclass_of($className, AssetBundle::class))
            ->each(function($className) {
                $assetManager = Craft::createObject(App::assetManagerConfig());

                /** @var AssetBundle $assetBundle */
                $assetBundle = Craft::createObject($className);

                $this->do("Publishing “{$className}”", fn() => $assetBundle->publish($assetManager));
            });

        return ExitCode::OK;
    }
}

