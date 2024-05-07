<?php

namespace craft\cloud;

use Craft;
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
    private const CDN_TAG_PREFIX = 'cdn:';

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
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            [$this, 'handleRegisterCacheOptions'],
        );
    }

    public function handleInitWebApplication(Event $event): void
    {
        if (!$this->shouldBeCacheable()) {
            return;
        }

        Craft::$app->getElements()->startCollectingCacheInfo();
    }

    public function handleAfterPrepareWebResponse(Event $event): void
    {
        if (!$this->shouldBeCacheable()) {
            return;
        }

        /** @var TagDependency|null $dependency */
        /** @var int|null $duration */
        [$dependency, $duration] = Craft::$app->getElements()->stopCollectingCacheInfo();
        $elementTags = $dependency?->tags ?? [];
        $preparedElementTags = $this->prepareElementTags(...$elementTags);
        $duration = $duration ?? Craft::$app->getConfig()->getGeneral()->cacheDuration;

        Craft::info(new PsrMessage('Adding cache headers to response', [
            'duration' => $duration,
        ]));

        Craft::$app->getResponse()->setCacheHeaders(
            $duration ?? Craft::$app->getConfig()->getGeneral()->cacheDuration,
            false,
        );

        $this->addTagsToWebResponse(...$preparedElementTags);
    }

    public function handleBeforeRenderPageTemplate(TemplateEvent $event): void
    {
        if (!$this->shouldBeCacheable()) {
            return;
        }

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

        if ($tags->isEmpty()) {
            return;
        }

        $this->purgeTags(...$tags);
    }

    public function handleRegisterCacheOptions(RegisterCacheOptionsEvent $event): void
    {
        $event->options[] = [
            'key' => 'cloud-caches',
            'label' => Craft::t('app', 'Craft Cloud caches'),
            'action' => [$this, 'purgeAll'],
        ];
    }

    public function purgeAll(): void
    {
        $environmentId = Module::getInstance()->getConfig()->environmentId;

        $this->purgeTags(
            $environmentId,
            self::CDN_TAG_PREFIX . $environmentId,
        );
    }

    public function purgeUrlPrefixes(string ...$urlPrefixes): void
    {
        $urlPrefixes = Collection::make($urlPrefixes)
            ->filter()
            ->unique()
            ->values();

        if ($urlPrefixes->isEmpty()) {
            return;
        }

        Craft::info(new PsrMessage('Purging URL prefixes', [
            'urlPrefixes' => $urlPrefixes->all(),
        ]));

        if (Craft::$app->getResponse() instanceof \yii\console\Response) {
            Helper::makeGatewayApiRequest([
                HeaderEnum::CACHE_PURGE_PREFIX->value => $urlPrefixes->implode(','),
            ]);

            return;
        }

        $headers = Craft::$app->getResponse()->getHeaders();

        Craft::info(new PsrMessage('Adding {header} header to response', [
            'header' => HeaderEnum::CACHE_PURGE_PREFIX->value,
            'prefixes' => $urlPrefixes->all(),
        ]));

        $urlPrefixes->each(fn(string $prefix) => $headers->add(
            HeaderEnum::CACHE_PURGE_PREFIX->value,
            $prefix,
        ));
    }

    public function purgeTags(string ...$tags): void
    {
        $tags = $this->prepareTagsForResponse(...$tags);

        if ($tags->isEmpty()) {
            return;
        }

        Craft::info(new PsrMessage('Purging tags', [
            'tags' => $tags->all(),
        ]));

        if (Craft::$app->getResponse() instanceof \yii\console\Response) {
            Helper::makeGatewayApiRequest([
                HeaderEnum::CACHE_PURGE_TAG->value => $tags->implode(','),
            ]);

            return;
        }

        $headers = Craft::$app->getResponse()->getHeaders();

        Craft::info(new PsrMessage('Adding {header} header to response', [
            'header' => HeaderEnum::CACHE_PURGE_TAG->value,
            'tags' => $tags->all(),
        ]));

        $tags->each(fn(string $tag) => $headers->add(
            HeaderEnum::CACHE_PURGE_TAG->value,
            $tag,
        ));
    }

    private function prepareTagsForResponse(string ...$tags): Collection
    {
        // Header value can't exceed 16KB
        // https://developers.cloudflare.com/cache/how-to/purge-cache/purge-by-tags/#a-few-things-to-remember
        $bytes = 0;

        return Collection::make($tags)
            ->map(fn(string $tag) => $this->removeNonPrintableChars($tag))
            ->filter()
            ->unique()
            ->filter(function($tag) use (&$bytes) {
                // plus one for comma
                $bytes += strlen($tag) + 1;

                return $bytes < 16 * 1024;
            })
            ->values();
    }

    private function removeNonPrintableChars(string $string): string
    {
        return preg_replace('/[^[:print:]]/', '', $string);
    }

    private function hash(string $string): string
    {
        return sprintf('%x', crc32($string));
    }

    private function addTagsToWebResponse(string ...$tags): void
    {
        $tags = Collection::make($tags)
            ->prepend(Module::getInstance()->getConfig()->environmentId);

        Craft::info(new PsrMessage('Adding {header} header to response', [
            'header' => HeaderEnum::CACHE_TAG->value,
            'tags' => $tags->all(),
        ]));

        $headers = Craft::$app->getResponse()->getHeaders();
        $tags->each(fn(string $tag) => $headers->add(
            HeaderEnum::CACHE_TAG->value,
            $tag,
        ));
    }

    private function shouldBeCacheable(): bool
    {
        $response = Craft::$app->getResponse();

        return
            Craft::$app->getView()->templateMode === View::TEMPLATE_MODE_SITE &&
            $response instanceof \craft\web\Response &&
            !$response->getIsServerError();
    }

    private function prepareElementTags(string ...$tags): Collection
    {
        if ($this->shouldIgnoreElementTags(...$tags)) {
            Craft::info(new PsrMessage('Ignoring cache tags', [
                'tags' => $tags,
            ]));

            return new Collection();
        }

        return Collection::make($tags)
            ->sort(SORT_NATURAL)
            ->map(function(string $tag) {
                return Module::getInstance()->getConfig()->getShortEnvironmentId() . $this->hash($tag);
            });
    }

    private function shouldIgnoreElementTags(string ...$tags): bool
    {
        return Collection::make($tags)->contains(function(string $tag) {
            return preg_match('/element::craft\\\\elements\\\\\S+::(drafts|revisions)/', $tag);
        });
    }
}
