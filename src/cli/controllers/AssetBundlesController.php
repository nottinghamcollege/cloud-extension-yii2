<?php

namespace craft\cloud\cli\controllers;

use Craft;
use craft\cloud\AssetBundlePublisher;
use craft\cloud\AssetManager;
use craft\cloud\Helper;
use craft\console\Controller;
use craft\helpers\App;
use Illuminate\Support\Collection;
use ReflectionClass;
use yii\console\Exception;
use yii\console\ExitCode;
use yii\web\AssetBundle;

class AssetBundlesController extends Controller
{
    public bool $quiet = false;
    public ?string $to = null;

    public function init(): void
    {
        if (Helper::isCraftCloud()) {
            throw new Exception('Asset bundle publishing is not supported in a Craft Cloud environment.');
        }

        $this->to = $this->to ?? Craft::$app->getConfig()->getGeneral()->resourceBasePath;

        if (App::env('CRAFT_NO_DB')) {
            $this->getPluginAliases()?->each(function($path, $alias) {
                return Craft::setAlias($alias, $path);
            });
        }

        parent::init();
    }
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'publish-bundle' => [
                'quiet',
                'to',
            ],
            default => [
                'to',
            ],
        });
    }

    public function actionPublishBundle(string $className): int
    {
        try {
            $this->do("Publishing `$className` to `$this->to`", function() use ($className) {
                $rc = new ReflectionClass($className);

                if (!$rc->isSubclassOf(AssetBundle::class) || !$rc->isInstantiable()) {
                    throw new Exception('Not a valid asset bundle.');
                }

                /** @var AssetBundle $assetBundle */
                $assetBundle = Craft::createObject($className);

                // Intentionally not using the component, as it won't be loaded when run from the builder.
                $assetManager = Craft::createObject([
                    'class' => AssetManager::class,
                    'basePath' => $this->to,
                ] + App::assetManagerConfig());

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
        $publisher->to = $this->to;
        $publisher->wait();

        return ExitCode::OK;
    }

    protected function getPluginAliases(): ?Collection
    {
        $path = Craft::$app->getVendorPath() . DIRECTORY_SEPARATOR . 'craftcms' . DIRECTORY_SEPARATOR . 'plugins.php';

        if (!file_exists($path)) {
            return null;
        }

        $plugins = require $path;

        return Collection::make($plugins)->flatMap(fn(array $plugin) => $plugin['aliases'] ?? []);
    }
}
