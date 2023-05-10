<?php
namespace craft\cloud;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\App;
use craft\services\Fs as FsService;
use craft\services\ImageTransforms;
use craft\web\View;
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
        $this->registerEventHandlers();
        $this->setDefinitions();
        $this->configureApp($app);
    }

    protected static function getRedisConfig(): array
    {
        return [
            'hostname' => App::env('CRAFT_CLOUD_REDIS_HOSTNAME') ?? 'localhost',
            'port' => App::env('CRAFT_CLOUD_REDIS_PORT') ?? 6379,
        ];
    }

    protected function registerEventHandlers(): void
    {
        Event::on(
            ImageTransforms::class,
            ImageTransforms::EVENT_REGISTER_IMAGE_TRANSFORMERS,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = ImageTransformer::class;
            }
        );

        Event::on(
            FsService::class,
            FsService::EVENT_REGISTER_FILESYSTEM_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = Fs::class;
            }
        );

        // Base template directory
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $e) {
            if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                $e->roots[$this->id] = $baseDir;
            }
        });
    }

    protected function setDefinitions(): void
    {
        Craft::$container->setDefinitions([
            \craft\cloud\Fs::class => [
                'class' => \craft\cloud\Fs::class,
                'region' => 'us-west-2',
                'bucket' => 'my-bucket',
            ]
        ]);

        // When the module is resolved, the module config is merged into the definition,
        // so we can't override anything set in \craft\web\Application::debugBootstrap
        // or config/debug.php
        Craft::$container->setDefinitions([
            \craft\debug\Module::class => [
                'class' => \craft\debug\Module::class,
                'fs' => Craft::createObject([
                    'class' => Fs::class,
                ]),
                'dataPath' => 'debug',
            ]
        ]);
    }

    protected function configureApp(\yii\base\Application $app): void
    {
        $app->setAliases([
            '@craftCloudCdnUrl' => sprintf(
                'https://cdn.craft.cloud/%s',
                App::env('CRAFT_CLOUD_PROJECT_ID'),
            ),
            '@craftCloudStaticUrl' => sprintf(
                'https://static.craft.cloud/%s/%s',
                App::env('CRAFT_CLOUD_PROJECT_ID'),
                App::env('CRAFT_CLOUD_BUILD_ID'),
            ),
        ]);

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
            'queue' => [
                'class' => \craft\queue\Queue::class,
                'proxyQueue' => [
                    'class' => \yii\queue\sqs\Queue::class,
                    'url' => App::env('CRAFT_CLOUD_SQS_URL'),
                ],
            ],
        ]);
    }
}
