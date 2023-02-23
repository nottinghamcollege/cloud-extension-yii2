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
            'cache' => [
                'class' => \yii\redis\Cache::class,
                'redis' => self::getRedisConfig(),
                'defaultDuration' => Craft::$app->getConfig()->getGeneral()->cacheDuration,
                // 'keyPrefix' => 'cache/',
                // 'shareDatabase' => false,
            ],
            'session' => [
                'class' => \yii\redis\Session::class,
                'redis' => self::getRedisConfig(),
                // 'keyPrefix' => 'session/',
            ] + App::sessionConfig(),
            'mutex' => [
                'class' => \craft\mutex\Mutex::class,
                'mutex' => [
                    'class' => \craft\cloud\Mutex::class,
                    'redis' => self::getRedisConfig(),
                    // 'keyPrefix' => 'mutex/'
                    // 'expire' => Craft::$app->request->isConsoleRequest ? 900 : 30,
                ],
            ],
            'queue' => [
                'class' => \craft\queue\Queue::class,
                'proxyQueue' => [
                    'class' => \yii\queue\sqs\Queue::class,
                    'url' => '',
                ],
            ],
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
