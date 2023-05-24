<?php
namespace craft\cloud;

use Craft;
use craft\base\Event as Event;
use craft\cloud\console\controllers\CloudController;
use craft\cloud\fs\AssetFs;
use craft\cloud\fs\StorageFs;
use craft\config\BaseConfig;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\App;
use craft\services\Fs as FsService;
use craft\services\ImageTransforms;
use craft\web\Response;
use craft\web\View;
use yii\base\Event as YiiEvent;

/**
 *
 * @property-read Config $config
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    public const REDIS_DATABASE_CACHE = 0;
    public const REDIS_DATABASE_SESSION = 1;
    public const REDIS_DATABASE_MUTEX = 2;
    public const MUTEX_EXPIRE_WEB = 30;
    public const MUTEX_EXPIRE_CONSOLE = 900;

    private BaseConfig $_config;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        // When automatically bootstrapped, id will be `null`.
        $this->id = $this->id ?? 'cloud';

        $this->controllerNamespace = Craft::$app->getRequest()->getIsConsoleRequest()
            ? 'craft\\cloud\\console\\controllers'
            : 'craft\\cloud\\controllers';

        $this->registerEventHandlers();
        $this->setDefinitions();
    }

    /**
     * @inheritDoc
     */
    public function bootstrap($app): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $app->controllerMap[$this->id] = [
                'class' => CloudController::class,
            ];
        }

        if ($this->getConfig()->enableCache) {
            $app->set('cache', [
                'class' => \yii\redis\Cache::class,
                'redis' => $this->getRedisConfig([
                    'database' => self::REDIS_DATABASE_CACHE
                ]),
                'defaultDuration' => Craft::$app->getConfig()->getGeneral()->cacheDuration,
            ]);
        }

        if ($this->getConfig()->enableMutex) {
            $app->set('mutex', [
                'class' => \craft\mutex\Mutex::class,
                'mutex' => [
                    'class' => \craft\cloud\Mutex::class,
                    'redis' => $this->getRedisConfig([
                        'database' => self::REDIS_DATABASE_MUTEX
                    ]),
                    'expire' => Craft::$app->getRequest()->getIsConsoleRequest()
                        ? self::MUTEX_EXPIRE_CONSOLE
                        : self::MUTEX_EXPIRE_WEB,
                ],
            ]);
        }

        if ($this->getConfig()->enableSession && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            $app->set('session', [
                'class' => \yii\redis\Session::class,
                'redis' => $this->getRedisConfig([
                    'database' => self::REDIS_DATABASE_SESSION
                ]),
            ] + App::sessionConfig());
        }

        // TODO: https://github.com/craftcms/cloud/issues/155
        if ($this->getConfig()->enableQueue) {
            $app->set('queue', [
                'class' => \craft\queue\Queue::class,
                'proxyQueue' => [
                    'class' => \yii\queue\sqs\Queue::class,
                    'url' => $this->getConfig()->sqsUrl,
                ],
            ]);
        }
    }

    public function getConfig(): Config
    {
        if (isset($this->_config)) {
            return $this->_config;
        }

        $fileConfig = Craft::$app->getConfig()->getConfigFromFile($this->id);
        $config = is_array($fileConfig) ? Craft::createObject(Config::class, $fileConfig) : $fileConfig;
        $this->_config = Craft::configure($config, App::envConfig(Config::class, 'CRAFT_CLOUD_'));

        return $this->_config;
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
                $event->types[] = AssetFs::class;
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                $e->roots[$this->id] = sprintf('%s/templates', $this->getBasePath());
            }
        );

        if (!$this->getConfig()->allowBinaryResponses) {
            Event::once(
                Response::class,
                \yii\web\Response::EVENT_BEFORE_SEND,
                [$this, 'handleBeforeSend']
            );
        }
    }

    protected function setDefinitions(): void
    {
        if ($this->getConfig()->enableCdn) {
            // TODO: check full list with Cloudflare
            // supportedImageFormats DI isnt working
            Craft::$container->setDefinitions([
                \craft\services\Images::class => [
                    'class' => \craft\services\Images::class,
                    'supportedImageFormats' => ['jpg', 'jpeg', 'gif', 'png', 'heic'],
                ]
            ]);
            Craft::$app->getImages()->supportedImageFormats = ['jpg', 'jpeg', 'gif', 'png', 'heic'];

            /**
             * Currently this is the only reasonable way to change the default
             */
            Craft::$container->setDefinitions([
                \craft\imagetransforms\ImageTransformer::class => [
                    'class' => ImageTransformer::class,
                ]
            ]);

            Craft::$container->setDefinitions([
                \craft\web\AssetManager::class => [
                    'class' => AssetManager::class,
                ]
            ]);
        }

        if ($this->getConfig()->enableDebug) {
            Craft::$container->setDefinitions([
                \craft\debug\Module::class => [
                    'class' => \craft\debug\Module::class,
                    'fs' => Craft::createObject([
                        'class' => StorageFs::class,
                        'subfolder' => 'debug',
                    ]),
                    'dataPath' => '',
                ]
            ]);
        }
    }

    protected function handleBeforeSend(YiiEvent $event): void
    {
        /** @var Response $response */
        $response = $event->sender;

        if (!$response->stream) {
            return;
        }

        /** @var StorageFs $fs */
        $fs = Craft::createObject([
            'class' => StorageFs::class,
            'subfolder' => 'tmp',
        ]);
        $stream = $response->stream[0];
        $path = uniqid('', true);

        // TODO: set expiry
        $fs->writeFileFromStream($path, $stream);

        $cmd = $fs->getClient()->getCommand('GetObject', [
            'Bucket' => $fs->getBucketName(),
            'Key' => $fs->getPrefix($path),
            'ResponseContentDisposition' => $response->getHeaders()->get('content-disposition'),
        ]);

        $s3Request = $fs->getClient()->createPresignedRequest($cmd, '+20 minutes');
        $url = (string) $s3Request->getUri();
        $response->redirect($url);
    }

    public function getRedisConfig(array $config = []): array
    {
        $urlParts = parse_url($this->getConfig()->redisUrl);

        return $config + [
            'scheme' => $urlParts['scheme'],
            'hostname' => $urlParts['host'],
            'port' => $urlParts['port'],
        ];
    }
}
