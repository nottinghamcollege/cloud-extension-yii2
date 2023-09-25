<?php

namespace craft\cloud;

use Craft;
use craft\base\Event;
use craft\cache\DbCache;
use craft\cloud\fs\AssetsFs;
use craft\cloud\fs\CpResourcesFs;
use craft\cloud\fs\StorageFs;
use craft\cloud\fs\TmpFs;
use craft\cloud\queue\Queue;
use craft\cloud\web\assets\uploader\UploaderAsset;
use craft\db\Table;
use craft\events\InvalidateElementCachesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\TemplateEvent;
use craft\helpers\App;
use craft\log\Dispatcher;
use craft\services\Elements;
use craft\services\Fs as FsService;
use craft\services\ImageTransforms;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use Illuminate\Support\Collection;
use yii\caching\TagDependency;
use yii\mutex\MysqlMutex;
use yii\mutex\PgsqlMutex;
use yii\web\DbSession;

/**
 * @property-read Config $config
 * @property ?string $id When auto-bootstrapped as an extension, this can be `null`.
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
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
            ? 'craft\\cloud\\cli\\controllers'
            : 'craft\\cloud\\controllers';

        $this->registerEventHandlers();
    }

    /**
     * @inheritDoc
     */
    public function bootstrap($app): void
    {
        /** @var \craft\web\Application|\craft\console\Application $app */

        // Required for controllers to be found
        $app->setModule($this->id, $this);

        $app->getView()->registerTwigExtension(new TwigExtension());

        if (Helper::isCraftCloud()) {

            // Set Craft memory limit to just below PHP's limit
            Helper::setMemoryLimit(ini_get('memory_limit'), $app->getErrorHandler()->memoryReserveSize);

            // TODO: make this a behavior instead?
            $app->set('response', [
                'class' => \craft\cloud\web\Response::class,
            ]);

            if (!$app->getRequest()->getIsConsoleRequest()) {
                Craft::setAlias('@web', $app->getRequest()->getHostInfo());

                $app->getRequest()->secureHeaders = Collection::make($app->getRequest()->secureHeaders)
                    ->reject(fn(string $header) => $header === 'X-Forwarded-Host')
                    ->all();
            }

            /** @var Dispatcher $dispatcher */
            $dispatcher = $app->getLog();

            // Force JSON
            $dispatcher->monologTargetConfig = [
                'allowLineBreaks' => false,
            ];
        }

        // cache table is created on craft cloud/up
        if ($this->getConfig()->enableCache && $app->getDb()->tableExists(Table::CACHE)) {
            $app->set('cache', [
                'class' => DbCache::class,
                'defaultDuration' => $app->getConfig()->getGeneral()->cacheDuration,
            ]);
        }

        if (
            $this->getConfig()->enableSession &&
            !$app->getRequest()->getIsConsoleRequest() &&
            $app->getDb()->tableExists(Table::PHPSESSIONS)
        ) {
            $app->set('session', [
                'class' => DbSession::class,
                'sessionTable' => Table::PHPSESSIONS,
            ] + App::sessionConfig());
        }

        if ($this->getConfig()->enableMutex) {
            $app->set('mutex', [
                'class' => \craft\mutex\Mutex::class,
                'mutex' => $app->getDb()->getDriverName() === 'pgsql'
                    ? PgsqlMutex::class
                    : MysqlMutex::class,
            ]);
        }

        if ($this->getConfig()->enableQueue && $this->getConfig()->sqsUrl) {
            $app->set('queue', [
                'class' => \craft\queue\Queue::class,
                'proxyQueue' => Queue::class,
            ]);
        }

        if ($this->getConfig()->enableCdn) {
            $app->set('assetManager', [
                'class' => AssetManager::class,
                'fs' => Craft::createObject(CpResourcesFs::class),
            ]);

            $app->getConfig()->getGeneral()->resourceBaseUrl(
                Helper::cpResourceUrl(),
            );

            $app->getImages()->supportedImageFormats = ImageTransformer::SUPPORTED_IMAGE_FORMATS;

            /**
             * Currently this is the only reasonable way to change the default transformer
             */
            Craft::$container->set(
                \craft\imagetransforms\ImageTransformer::class,
                ImageTransformer::class,
            );

            // TODO: this is to ensure PHP never transforms. Test this.
            Craft::$container->set(
                \craft\imagetransforms\FallbackTransformer::class,
                ImageTransformer::class,
            );
        }

        if ($this->getConfig()->enableTmpFs) {
            Craft::$container->set(
                \craft\fs\Temp::class,
                TmpFs::class,
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

        // Must be after setting assetManager
        if ($app->getRequest()->getIsCpRequest()) {
            $app->getView()->registerAssetBundle(UploaderAsset::class);
        }
    }

    public function getConfig(): Config
    {
        if (isset($this->_config)) {
            return $this->_config;
        }

        $fileConfig = Craft::$app->getConfig()->getConfigFromFile($this->id);

        /** @var Config $config */
        $config = is_array($fileConfig)
            ? Craft::createObject(['class' => Config::class] + $fileConfig)
            : $fileConfig;

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
                $event->types[] = AssetsFs::class;
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                $e->roots[$this->id] = sprintf('%s/templates', $this->getBasePath());
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(\yii\base\Event $e) {
                /** @var CraftVariable $craftVariable */
                $craftVariable = $e->sender;
                $craftVariable->set('cloud', Module::class);
            }
        );

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            StaticCaching::handleBeforeRenderPageTemplate(...),
        );

        Event::on(
            Elements::class,
            Elements::EVENT_INVALIDATE_CACHES,
            StaticCaching::handleInvalidateCaches(...),
        );

        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            StaticCaching::handleAfterRenderPageTemplate(...),
        );
    }
}
