<?php

namespace craft\cloud;

use Craft;
use craft\base\Event;
use craft\cloud\console\controllers\CloudController as ConsoleController;
use craft\cloud\controllers\CloudController as WebController;
use craft\cloud\fs\AssetFs;
use craft\cloud\fs\BuildsFs;
use craft\cloud\fs\StorageFs;
use craft\cloud\redis\Mutex;
use craft\cloud\web\assets\uploader\UploaderAsset;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\App;
use craft\services\Fs as FsService;
use craft\services\ImageTransforms;
use craft\web\Response;
use craft\web\View;
use yii\redis\Connection;

/**
 * @property-read Config $config
 * @property ?string $id When auto-bootstrapped as an extension, this can be `null`.
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    public const REDIS_DATABASE_CACHE = 0;
    public const REDIS_DATABASE_SESSION = 1;
    public const REDIS_DATABASE_MUTEX = 2;
    public const MUTEX_EXPIRE_WEB = 30;
    public const MUTEX_EXPIRE_CONSOLE = 900;

    private Config $_config;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        $this->id = $this->id ?? 'cloud';

        // Set instance early so our dependencies can use it
        self::setInstance($this);

        $this->controllerNamespace = Craft::$app->getRequest()->getIsConsoleRequest()
            ? 'craft\\cloud\\console\\controllers'
            : 'craft\\cloud\\controllers';

        $this->registerEventHandlers();
    }

    /**
     * @inheritDoc
     */
    public function bootstrap($app): void
    {
        $app->controllerMap[$this->id] = [
            'class' => Craft::$app->getRequest()->getIsConsoleRequest()
                ? ConsoleController::class
                : WebController::class,
        ];

        if ($this->getConfig()->enableCache) {
            $app->set('cache', [
                'class' => \yii\redis\Cache::class,
                'redis' => $this->getRedisConfig([
                    'database' => self::REDIS_DATABASE_CACHE,
                ]),
                'defaultDuration' => Craft::$app->getConfig()->getGeneral()->cacheDuration,
            ]);
        }

        if ($this->getConfig()->enableSession && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            $app->set('session', [
                    'class' => \yii\redis\Session::class,
                    'redis' => $this->getRedisConfig([
                        'database' => self::REDIS_DATABASE_SESSION,
                    ]),
                ] + App::sessionConfig());
        }

        if ($this->getConfig()->enableMutex) {
            $app->set('mutex', [
                'class' => \craft\mutex\Mutex::class,
                'mutex' => [
                    'class' => Mutex::class,
                    'redis' => $this->getRedisConfig([
                        'database' => self::REDIS_DATABASE_MUTEX,
                    ]),
                    'expire' => Craft::$app->getRequest()->getIsConsoleRequest()
                        ? self::MUTEX_EXPIRE_CONSOLE
                        : self::MUTEX_EXPIRE_WEB,
                ],
            ]);
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

        if ($this->getConfig()->enableCdn) {
            $buildsFs = Craft::createObject(BuildsFs::class);
            $app->set('assetManager', [
                'class' => AssetManager::class,
                'fs' => $buildsFs,
            ]);

            $app->set('images', [
                'class' => \craft\services\Images::class,
                'supportedImageFormats' => ImageTransformer::SUPPORTED_IMAGE_FORMATS,
            ]);

            Craft::$container->set(
                Connection::class,
                \craft\cloud\redis\Connection::class,
            );

            /**
             * Currently this is the only reasonable way to change the default transformer
             */
            Craft::$container->set(
                \craft\imagetransforms\ImageTransformer::class,
                ImageTransformer::class,
            );
        }

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $app->getView()->registerAssetBundle(UploaderAsset::class);
        }

        if ($this->getConfig()->enableTmpFs) {
            Craft::$container->set(
                \craft\fs\Temp::class,
                [
                    'class' => StorageFs::class,
                    'subfolder' => 'tmp',
                ],
            );
        }

        /**
         * We have to use DI here (can't use setModule), as
         * \craft\web\Application::debugBootstrap will be called after and override it.
         */
        if ($this->getConfig()->enableDebug) {
            Craft::$container->set(
                \craft\debug\Module::class,
                [
                    'class' => \craft\debug\Module::class,
                    'fs' => Craft::createObject(StorageFs::class),
                    'dataPath' => 'debug',
                ],
            );
        }
    }

    public function getConfig(): Config
    {
        if (isset($this->_config)) {
            return $this->_config;
        }

        $fileConfig = Craft::$app->getConfig()->getConfigFromFile($this->id);
        /** @var Config $config */
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

    public function handleBeforeSend(\yii\base\Event $event): void
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
        $path = uniqid('binary', true);

        // TODO: set expiry
        $fs->writeFileFromStream($path, $stream);

        // TODO: use \League\Flysystem\AwsS3V3\AwsS3V3Adapter::temporaryUrl
        $cmd = $fs->getClient()->getCommand('GetObject', [
            'Bucket' => $fs->getBucketName(),
            'Key' => $fs->prefixPath($path),
            'ResponseContentDisposition' => $response->getHeaders()->get('content-disposition'),
        ]);

        $s3Request = $fs->getClient()->createPresignedRequest($cmd, '+20 minutes');
        $url = (string) $s3Request->getUri();
        $response->clear();
        $response->redirect($url);
    }

    protected function getRedisConfig(array $config = []): array
    {
        $urlParts = parse_url($this->getConfig()->redisUrl);

        return $config + [
            'scheme' => $urlParts['scheme'],
            'hostname' => $urlParts['host'],
            'port' => $urlParts['port'],
        ];
    }
}
