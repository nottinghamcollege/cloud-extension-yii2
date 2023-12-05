<?php

namespace craft\cloud;

use Craft;
use craft\cache\DbCache;
use craft\cloud\fs\BuildArtifactsFs;
use craft\cloud\Helper as CloudHelper;
use craft\cloud\queue\SqsQueue;
use craft\cloud\runtime\Runtime;
use craft\db\Table;
use craft\helpers\App;
use craft\helpers\ConfigHelper;
use craft\mutex\Mutex;
use craft\queue\Queue as CraftQueue;
use HttpSignatures\Context;
use Illuminate\Support\Collection;
use yii\mutex\MysqlMutex;
use yii\mutex\PgsqlMutex;
use yii\web\DbSession;

class Helper
{
    public static function isCraftCloud(): bool
    {
        return App::env('CRAFT_CLOUD') ?? App::env('AWS_LAMBDA_RUNTIME_API') ?? false;
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
SHOW TABLES LIKE :tableName
SQL;
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

        $config['components']['queue'] = function() {
            $ttr = Runtime::MAX_EXECUTION_SECONDS - 1;

            return Craft::createObject([
                'class' => CraftQueue::class,
                'ttr' => $ttr,
                'proxyQueue' => Module::getInstance()->getConfig()->useQueue ? [
                    'class' => SqsQueue::class,
                    'ttr' => $ttr,
                    'url' => Module::getInstance()->getConfig()->sqsUrl,
                    'region' => Module::getInstance()->getConfig()->getRegion(),
                ] : null,
            ]);
        };

        $config['components']['assetManager'] = function() {
            $config = [
                'class' => AssetManager::class,
            ] + App::assetManagerConfig();

            return Craft::createObject($config);
        };
    }

    public static function createSigningContext(iterable $headers = []): Context
    {
        $headers = Collection::make($headers);

        return new Context([
            'keys' => [
                'hmac' => Module::getInstance()->getConfig()->signingKey,
            ],
            'algorithm' => 'hmac-sha256',
            'headers' => $headers->push('(request-target)')->all(),
        ]);
    }

    public static function base64UrlEncode(string $data): string
    {
        $base64Url = strtr(base64_encode($data), '+/', '-_');

        return rtrim($base64Url, '=');
    }
}
