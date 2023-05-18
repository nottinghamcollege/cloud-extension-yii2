<?php
namespace craft\cloud;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Craft;
use craft\base\Event as Event;
use craft\cloud\console\controllers\CloudController;
use craft\cloud\fs\Fs;
use craft\cloud\fs\AssetFs;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\App;
use craft\services\Fs as FsService;
use craft\services\ImageTransforms;
use craft\web\Response;
use craft\web\View;
use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;
use League\Uri\UriTemplate;
use yii\base\Event as YiiEvent;

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
    public function init(): void
    {
        $this->registerEventHandlers();
        $this->setDefinitions();

        $this->controllerNamespace = Craft::$app->getRequest()->getIsConsoleRequest()
            ? 'craft\\cloud\\console\\controllers'
            : 'craft\\cloud\\controllers';

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function bootstrap($app): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $app->controllerMap['cloud'] = [
                'class' => CloudController::class,
            ];
        }

        $app->setAliases([
            '@craftCloudBuildBaseUrl' => self::getBuildUrl(),
        ]);

        if (self::isCraftCloud()) {
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
        }

        // TODO: https://github.com/craftcms/cloud/issues/155
        // $app->setComponents([
        //     'queue' => [
        //         'class' => \craft\queue\Queue::class,
        //         'proxyQueue' => [
        //             'class' => \yii\queue\sqs\Queue::class,
        //             'url' => App::env('CRAFT_CLOUD_SQS_URL'),
        //         ],
        //     ],
        // ]);
    }

    public static function isCraftCloud(): bool
    {
        return (bool) App::env('AWS_LAMBDA_RUNTIME_API');
    }

    public static function getCdnUrl(string $path = ''): UriInterface
    {
        $template = new UriTemplate(
            self::collapseSlashes($path),
            [
                'environmentId' => self::getEnvironmentId() ?? '__ENVIRONMENT_ID__',
                'buildId' => Craft::$app->getConfig()->getGeneral()->buildId ?? '__BUILD_ID__',
                'projectId' => Craft::$app->id ?? '__PROJECT_ID__',
            ]
        );

        return Uri::createFromBaseUri(
            $template->expand(),
            App::env('CRAFT_CLOUD_CDN_BASE_URL') ?? 'https://cdn.craft.cloud',
        );
    }

    public static function getBuildUrl(string $path = ''): UriInterface
    {
        return self::getCdnUrl("{environmentId}/builds/{buildId}/${path}");
    }

    public static function getCpResourcesUrl(string $path = ''): UriInterface
    {
        return self::getCdnUrl("{environmentId}/cpresources/${path}");
    }

    public static function getEnvironmentId(): ?string
    {
        return App::env('CRAFT_CLOUD_ENVIRONMENT_ID');
    }

    protected static function collapseSlashes(string $path): string
    {
        return preg_replace('#/{2,}#', '/', $path);
    }

    protected static function getRedisConfig(): array
    {
        $url = App::env('CRAFT_CLOUD_REDIS_URL') ?? 'tcp://localhost:6379';
        $urlParts = parse_url($url);

        return [
            'scheme' => $urlParts['scheme'],
            'hostname' => $urlParts['host'],
            'port' => $urlParts['port'],
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

        Event::once(
            Response::class,
            \yii\web\Response::EVENT_BEFORE_SEND,
            function (YiiEvent $event) {

                /** @var Response $response */
                $response = $event->sender;

                if (!$response->stream) {
                    return;
                }

                $fs = new Fs();
                $stream = $response->stream[0];
                $path = sprintf('%s/storage/tmp/%s', App::env('CRAFT_CLOUD_ENVIRONMENT_ID'), uniqid('', true));

                // TODO: set expiry
                $fs->writeFileFromStream($path, $stream);
                $s3Client = new S3Client([
                    'credentials' => new Credentials(
                        App::env('AWS_ACCESS_KEY_ID'),
                        App::env('AWS_SECRET_ACCESS_KEY')
                    ),
                    'version' => 'latest',
                    'region' => App::env('AWS_REGION'),
                ]);

                $cmd = $s3Client->getCommand('GetObject', [
                    'Bucket' => App::env('CRAFT_APP_ID'),
                    'Key' => $path,
                    'ResponseContentDisposition' => $response->getHeaders()->get('content-disposition'),
                ]);

                $s3Request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
                $url = (string) $s3Request->getUri();
                $response->redirect($url);
            }
        );
    }

    public function setDefinitions(): void
    {
        // When the module is resolved, the module config is merged into the definition,
        // so we can't override anything set in \craft\web\Application::debugBootstrap
        // or config/debug.php
        if (self::isCraftCloud()) {
            Craft::$container->setDefinitions([
                \craft\debug\Module::class => [
                    'class' => \craft\debug\Module::class,
                    'fs' => Craft::createObject([
                        'class' => Fs::class,
                    ]),
                    'dataPath' => sprintf('%s/storage/debug', App::env('CRAFT_CLOUD_ENVIRONMENT_ID')),
                ]
            ]);

                // Craft::$container->setDefinitions([
                //     \craft\web\AssetManager::class => [
                //         'class' => AssetManager::class,
                //         'fs' => Craft::createObject([
                //             'class' => Fs::class,
                //         ]),
                //     ]
                // ]);

            // TODO: check full list with Cloudflare
            Craft::$container->setDefinitions([
                \craft\services\Images::class => [
                    'class' => \craft\services\Images::class,
                    'supportedImageFormats' => ['jpg', 'jpeg', 'gif', 'png', 'heic'],
                ]
            ]);
        }
    }
}
