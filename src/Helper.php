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
        return App::env('AWS_LAMBDA_RUNTIME_API') || App::env('LAMBDA_TASK_ROOT');
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

    /**
     * A version of tableExists that doesn't rely on the cache component
     */
    public static function tableExists(string $table, ?string $schema = null): bool
    {
        $db = Craft::$app->getDb();
        $params = [
            ':tableName' => $db->getSchema()->getRawTableName($table),
        ];

        if ($db->getIsMysql()) {
            // based on yii\db\mysql\Schema::findTableName()
            $sql = <<<SQL
SHOW CREATE TABLES LIKE :tableName
SQL;

            $sql = "";
        } else {
            // based on yii\db\pgsql\Schema::findTableName()
            $sql = <<<SQL
SELECT c.relname
FROM pg_class c
INNER JOIN pg_namespace ns ON ns.oid = c.relnamespace
WHERE ns.nspname = :schemaName AND c.relkind IN ('r','v','m','f', 'p')
and c.relname = :tableName
SQL;
            $params[':schemaName'] = $schema ?? $db->getSchema()->defaultSchema;
        }

        return (bool)$db->createCommand($sql, $params)->queryScalar();
    }

    public static function modifyConfig(array &$config, string $appType): void
    {
        if (!CloudHelper::isCraftCloud()) {
            return;
        }

        if ($appType === 'web') {
            $config['components']['session'] = function() {
                $config = App::sessionConfig();

                if (static::tableExists(Table::PHPSESSIONS)) {
                    $config['class'] = DbSession::class;
                    $config['sessionTable'] = Table::PHPSESSIONS;
                }

                return Craft::createObject($config);
            };
        }

        $config['components']['cache'] = function() {
            $config = static::tableExists(Table::CACHE) ? [
                'class' => DbCache::class,
                'cacheTable' => Table::CACHE,
                'defaultDuration' => Craft::$app->getConfig()->getGeneral()->cacheDuration,
            ] : App::cacheConfig();

            return Craft::createObject($config);
        };

        $config['components']['mutex'] = function() {
            return Craft::createObject([
                'class' => Mutex::class,
                'mutex' => Craft::$app->getDb()->getDriverName() === 'pgsql'
                    ? PgsqlMutex::class
                    : MysqlMutex::class,
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
