<?php
namespace craft\cloud;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\App;
use craft\services\Fs as FsService;
use yii\base\Event;

class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    public const REDIS_DATABASE_CACHE = 0;
    public const REDIS_DATABASE_SESSION = 1;
    public const REDIS_DATABASE_MUTEX = 2;
    public const MUTEX_EXPIRE_WEB = 30;
    public const MUTEX_EXPIRE_CONSOLE = 900;

    /**
     * @inheritDoc
     */
    public function bootstrap($app): void
    {
        Event::on(FsService::class, FsService::EVENT_REGISTER_FILESYSTEM_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Fs::class;
        });

        // Craft::$container->setDefinitions([
        //     \craft\debug\Module::class => [
        //         'class' => \craft\debug\Module::class,
        //         'fs' => Craft::createObject([
        //             'class' => Fs
        //         ]),
        //         'dataPath' => 'debug',
        //     ]
        // ]);

        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $app->setComponents([
                'session' => [
                    'class' => \yii\redis\Session::class,
                    'redis' => self::getRedisConfig() + [
                        'database' => self::REDIS_DATABASE_SESSION
                    ],
                ] + App::sessionConfig(),
            ]);
        }

        $app->setComponents([
            'cache' => [
                'class' => \yii\redis\Cache::class,
                'redis' => self::getRedisConfig() + [
                    'database' => self::REDIS_DATABASE_CACHE
                ],
                'defaultDuration' => Craft::$app->getConfig()->getGeneral()->cacheDuration,
            ],
            'mutex' => [
                'class' => \craft\mutex\Mutex::class,
                'mutex' => [
                    'class' => \craft\cloud\Mutex::class,
                    'redis' => self::getRedisConfig() + [
                        'database' => self::REDIS_DATABASE_MUTEX
                    ],
                    'expire' => Craft::$app->getRequest()->getIsConsoleRequest()
                        ? self::MUTEX_EXPIRE_CONSOLE
                        : self::MUTEX_EXPIRE_WEB,
                ],
            ],
            // 'queue' => [
            //     'class' => \craft\queue\Queue::class,
            //     'proxyQueue' => [
            //         'class' => \yii\queue\sqs\Queue::class,
            //         'url' => App::env('CRAFT_CLOUD_SQS_URL'),
            //     ],
            // ],
        ]);
    }

    public static function getRedisConfig(): array
    {
        return [
            'hostname' => App::env('CRAFT_CLOUD_REDIS_HOSTNAME') ?? 'localhost',
            'port' => App::env('CRAFT_CLOUD_REDIS_PORT') ?? 6379,
        ];
    }
}
