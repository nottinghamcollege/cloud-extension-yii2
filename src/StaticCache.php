<?php

namespace craft\cloud;

use Craft;
use craft\events\ElementEvent;
use craft\events\InvalidateElementCachesEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\TemplateEvent;
use craft\services\Elements;
use craft\utilities\ClearCaches;
use craft\web\UrlManager;
use craft\web\View;
use Illuminate\Support\Collection;
use samdark\log\PsrMessage;
use yii\base\Event;
use yii\caching\TagDependency;

class StaticCache extends \yii\base\Component
{
    private ?int $cacheDuration = null;
    private Collection $tags;
    private Collection $tagsToPurge;
    private Collection $urlPrefixesToPurge;

    public function init(): void
    {
        $this->tags = new Collection();
        $this->tagsToPurge = new Collection();
        $this->urlPrefixesToPurge = new Collection();
    }

    public function registerEventHandlers(): void
    {
        Event::on(
            \craft\web\Application::class,
            \craft\web\Application::EVENT_INIT,
            [$this, 'handleInitWebApplication'],
        );

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            [$this, 'handleBeforeRenderPageTemplate'],
        );

        Event::on(
            \craft\web\Response::class,
            \yii\web\Response::EVENT_AFTER_PREPARE,
            [$this, 'handleAfterPrepareWebResponse'],
        );

        Event::on(
            Elements::class,
            Elements::EVENT_INVALIDATE_CACHES,
            [$this, 'handleInvalidateElementCaches'],
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            [$this, 'handleUpdateElement'],
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            [$this, 'handleUpdateElement'],
        );

        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            [$this, 'handleRegisterCacheOptions'],
        );

        Craft::$app->onAfterRequest(function() {
            // TODO: combine these into one request
            if ($this->tagsToPurge->isNotEmpty()) {
                $this->purgeTags(...$this->tagsToPurge);
            }

            if ($this->urlPrefixesToPurge->isNotEmpty()) {
                $this->purgeUrlPrefixes(...$this->urlPrefixesToPurge);
            }
        });
    }

    public function handleInitWebApplication(Event $event): void
    {
        // TODO: ifRequestIsCacheable
        Craft::$app->getElements()->startCollectingCacheInfo();
    }

    public function handleAfterPrepareWebResponse(Event $event): void
    {
        /** @var TagDependency|null $dependency */
        /** @var int|null $duration */
        [$dependency, $duration] = Craft::$app->getElements()->stopCollectingCacheInfo();
        $elementTags = $dependency?->tags ?? [];
        $preparedElementTags = $this->prepareElementTags(...$elementTags);
        $this->tags->push(...$preparedElementTags);

        // Don't override the cache duration if it's already set
        $this->cacheDuration = $this->cacheDuration ?? $duration;

        $this->addCacheHeadersToWebResponse();
    }

    public function handleBeforeRenderPageTemplate(TemplateEvent $event): void
    {
        /** @var UrlManager $urlManager */
        $urlManager = Craft::$app->getUrlManager();
        $matchedElement = $urlManager->getMatchedElement();

        if ($matchedElement) {
            Craft::$app->getElements()->collectCacheInfoForElement($matchedElement);
        }
    }

    public function handleInvalidateElementCaches(InvalidateElementCachesEvent $event): void
    {
        $tags = $this->prepareElementTags(...$event->tags);
        $this->tagsToPurge->push(...$tags);
    }

    public function handleRegisterCacheOptions(RegisterCacheOptionsEvent $event): void
    {
        $event->options[] = [
            'key' => 'craft-cloud-caches',
            'label' => Craft::t('app', 'Craft Cloud caches'),
            'action' => [$this, 'purgeAll'],
        ];
    }

    public function handleUpdateElement(ElementEvent $event): void
    {
        $element = $event->element;
        $url = $element->getUrl();

        if (!$url) {
            return;
        }

        $this->urlPrefixesToPurge->push($url);
    }

    public function purgeAll(): void
    {
        $this->tagsToPurge->push(Module::getInstance()->getConfig()->environmentId);
        // $this->purgeTags(Module::getInstance()->getConfig()->environmentId);
    }

    private function addCacheHeadersToWebResponse(): void
    {
        $this->cacheDuration = $this->cacheDuration ?? Craft::$app->getConfig()->getGeneral()->cacheDuration;

        Craft::info(new PsrMessage('Setting cache headers', [
            'duration' => $this->cacheDuration,
        ]));

        Craft::$app->getResponse()->setCacheHeaders(
            $this->cacheDuration,
            false,
        );

        $headers = Craft::$app->getResponse()->getHeaders();
        $existingTags = $headers->get(HeaderEnum::CACHE_TAG->value, first: false) ?? [];

        // remove existing tags and prepend for prefixing
        $headers->remove(HeaderEnum::CACHE_TAG->value);
        $this->tags->prepend(...$existingTags);

        // prefix tags
        $this->tags = $this->tags->map(fn(string $tag) => Module::getInstance()->getConfig()->getShortEnvironmentId() . $tag);

        // Prepend environment ID
        $this->tags->prepend(Module::getInstance()->getConfig()->environmentId);

        $this->tags->each(fn(string $tag) => $headers->add(
            HeaderEnum::CACHE_TAG->value,
            $tag,
        ));
    }

    private function purgeTags(string ...$tags): void
    {
        // ensure everything starts with env prefix
        // however, the gateway could/should do this as well
    }

    private function purgeUrlPrefixes(string ...$urls): void
    {
    }

    private function prepareElementTags(string ...$tags): Collection
    {
        // hash
        return new Collection($tags);
    }
}
