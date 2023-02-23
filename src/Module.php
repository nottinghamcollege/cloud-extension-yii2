<?php
namespace craft\cloud;

use Craft;
use craft\helpers\App;

class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    /**
     * @inheritDoc
     */
    public function bootstrap($app): void
    {
        $app->setComponents([
            'cache' => fn() => Craft::createObject([
                'class' => \yii\redis\Cache::class,
                'redis' => self::getRedisConfig(),
                // 'keyPrefix' => 'cache_',
                // 'shareDatabase' => false,
            ]),
            'session' => fn() => Craft::createObject([
                'class' => \yii\redis\Session::class,
                'redis' => self::getRedisConfig(),
            ] + App::sessionConfig()),
            'mutex' => fn() => Craft::createObject([
                'class' => \craft\mutex\Mutex::class,
                'mutex' => [
                    'class' => \yii\redis\Mutex::class,
                    'redis' => self::getRedisConfig(),
                    // 'expire' => Craft::$app->request->isConsoleRequest ? 900 : 30,
                ],
            ]),
            'queue' => Craft::createObject([
                'class' => \craft\queue\Queue::class,
                'proxyQueue' => [
                    'class' => \yii\queue\sqs\Queue::class,
                    'url' => '',
                ],
            ]),
        ]);
    }

    public static function getRedisConfig(): array
    {
        return [
            'hostname' => App::env('REDIS_HOSTNAME') ?? 'localhost',
            'port' => App::env('REDIS_PORT') ?? 6379,
        ];
    }
}
