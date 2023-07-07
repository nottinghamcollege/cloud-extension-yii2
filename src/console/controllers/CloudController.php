<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use yii\console\ExitCode;
use yii\web\AssetBundle;

class CloudController extends Controller
{
    public function actionUp(): int
    {
        $this->run('/up');
        $this->run('publish-asset-bundles');

        return ExitCode::OK;
    }

    public function actionPublishAssetBundle(string $className): int
    {
        /** @var AssetBundle $assetBundle */
        $assetBundle = Craft::createObject($className);

        if (!is_subclass_of($className, AssetBundle::class)) {
            $this->stderr(sprintf('“%s” must be a subclass of “%s”.', $className, AssetBundle::class), Console::FG_RED);
            return ExitCode::USAGE;
        }

        $assetManager = Craft::$app->getAssetManager();
        $assetBundle->publish($assetManager);

        $this->stdout("Published “{$className}”");
        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }

    public function actionPublishAssetBundles(): int
    {
        $classMap = require(Craft::getAlias('@vendor/composer/autoload_classmap.php'));

        Collection::make($classMap)
            ->keys()
            ->filter(fn($className) => str_contains($className, '\\assets\\') || preg_match('/Asset(Bundle)?$/', $className))
            ->mapWithKeys(function(string $className) {
                $process = new Process([
                    PHP_BINARY,
                    $this->request->getScriptFile(),
                    'cloud/publish-asset-bundle',
                    $className,
                ]);
                $process->start();

                return [$className => $process];
            })
            ->each(function(Process $process, string $className) {
                while ($process->isRunning()) {
                    sleep(1);
                    continue;
                }

                if ($process->isSuccessful()) {
                    $this->stdout($process->getOutput());
                }
            });

        return ExitCode::OK;
    }
}
