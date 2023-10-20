<?php

namespace craft\cloud;

use Craft;
use craft\cache\DbCache;
use craft\cloud\fs\BuildArtifactsFs;
use craft\cloud\fs\CpResourcesFs;
use craft\cloud\Helper as CloudHelper;
use craft\cloud\queue\Queue;
use craft\db\Table;
use craft\helpers\App;
use craft\helpers\ConfigHelper;
use craft\mutex\Mutex;
use craft\queue\Queue as CraftQueue;
use yii\mutex\MysqlMutex;
use yii\mutex\PgsqlMutex;
use yii\web\DbSession;

class Helper
{
    /**
     * With local Bref, AWS_LAMBDA_RUNTIME_API is only set from web requests,
     * while LAMBDA_TASK_ROOT is set for both.
     */
    public static function isCraftCloud(): bool
    {
        return (bool)App::env('AWS_LAMBDA_RUNTIME_API') || App::env('LAMBDA_TASK_ROOT');
    }

    public static function artifactUrl(string $path = ''): string
    {
        return (new BuildArtifactsFs())->createUrl($path);
    }

    public static function setMemoryLimit(int|string $limit, int|string $offset = 0): int|float
    {
        $memoryLimit = ConfigHelper::sizeInBytes($limit) - ConfigHelper::sizeInBytes($offset);
        Craft::$app->getConfig()->getGeneral()->phpMaxMemoryLimit((string) $memoryLimit);
        Craft::info("phpMaxMemoryLimit set to $memoryLimit");

        return $memoryLimit;
    }

    public static function modifyConfig(array &$config, string $appType): void
    {
        if (!CloudHelper::isCraftCloud()) {
            return;
        }

        if ($appType === 'web') {
            $config['components']['session'] = function() {
                return Craft::createObject([
                        'class' => DbSession::class,
                        'sessionTable' => Table::PHPSESSIONS,
                    ] + App::sessionConfig());
            };

            // TODO: make this a behavior instead?
            // load Craft's config
            $config['components']['response'] = [
                'class' => \craft\cloud\web\Response::class,
            ];
        }

        $config['components']['mutex'] = function() {
            return Craft::createObject([
                'class' => Mutex::class,
                'mutex' => Craft::$app->getDb()->getDriverName() === 'pgsql'
                    ? PgsqlMutex::class
                    : MysqlMutex::class,
            ]);
        };

        $config['components']['cache'] = function() {
            return Craft::createObject([
                'class' => DbCache::class,
                'defaultDuration' => Craft::$app->getConfig()->getGeneral()->cacheDuration,
            ]);
        };

        $config['components']['queue'] = [
            'class' => CraftQueue::class,
            'proxyQueue' => Queue::class,
        ];

        $config['components']['assetManager'] = [
            'class' => AssetManager::class,
            'fs' => Craft::createObject(CpResourcesFs::class),
        ];
    }
}
