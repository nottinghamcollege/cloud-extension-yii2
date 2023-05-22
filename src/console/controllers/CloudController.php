<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\cloud\AssetHelper;
use craft\cloud\AssetManager;
use craft\console\Controller;
use craft\helpers\Console;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use Throwable;
use yii\console\ExitCode;
use yii\web\AssetBundle;

class CloudController extends Controller
{
    protected array $processes = [];
    public function actionPublishAssetBundle(string $className): int
    {
        /** @var AssetBundle $assetBundle */
        $assetBundle = Craft::createObject($className);

        if (!is_subclass_of($className, AssetBundle::class)) {
            $this->stderr(sprintf('“%s” must be a subclass of “%s”.', $className, AssetBundle::class), Console::FG_RED);
            return ExitCode::USAGE;
        }

        $assetManager = Craft::createObject(AssetManager::class);
        $assetBundle->publish($assetManager);

        return ExitCode::OK;
    }

    public function actionPublishAssetBundles(): int
    {
        $classMap = require(Craft::getAlias('@vendor/composer/autoload_classmap.php'));

        Collection::make($classMap)
            ->keys()
            ->filter(fn($className) => str_contains($className, '\\assets\\') || preg_match('/Asset(Bundle)?$/', $className))
            ->each(function(string $className) {
                $process = new Process([
                    PHP_BINARY,
                    $this->request->getScriptFile(),
                    'cloud/publish-asset-bundle',
                    $className,
                ]);

                // TODO: run in parallel
                try {
                    $this->do("Publishing “{$className}”", fn() => $process->run(function ($type, $buffer) {
                        if ($type === Process::ERR) {
                            return;
                        }

                        $this->stdout($buffer);
                    }));
                } catch(Throwable) {
                    // Carry on, we expect some failures
                }

                return $process;
            });

        return ExitCode::OK;
    }
}

