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

        $this->addTags(...$preparedElementTags);
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
        $this->purgeItemsByHeader(
            HeaderEnum::CACHE_PURGE_PREFIX->value,
            ...$urlPrefixes,
        );
    }

    public function purgeTags(string ...$tags): void
    {
        $this->purgeItemsByHeader(
            HeaderEnum::CACHE_PURGE_TAG->value,
            ...$tags,
        );
    }

    public function getTags(): Collection
    {
        if (!$this->shouldBeCacheable()) {
            return new Collection();
        }

        $tags = Craft::$app
            ->getResponse()
            ->getHeaders()
            ->get(HeaderEnum::CACHE_TAG->value, first: false) ?? [];

        return new Collection($tags);
    }

    public function addTags(string ...$tags): void
    {
        if (!$this->shouldBeCacheable()) {
            return;
        }

        $headers = Craft::$app->getResponse()->getHeaders();
        $tags = $this->prepareTagsForResponse(
            Module::getInstance()->getConfig()->environmentId,
            ...$this->getTags(),
            ...$tags,
        );

        Craft::info(new PsrMessage('Adding tags to {header} header', [
            'header' => HeaderEnum::CACHE_TAG->value,
            'tags' => $tags->all(),
        ]));

        $headers->remove(HeaderEnum::CACHE_TAG->value);
        $tags->each(fn(string $tag) => $headers->add(
            HeaderEnum::CACHE_TAG->value,
            $tag,
        ));
    }

    public function removeTags(string ...$tags): void
    {
        if (!$this->shouldBeCacheable()) {
            return;
        }

        $tags = $this->getTags()->diff($tags);

        Craft::info(new PsrMessage('Removing tags from {header} header', [
            'tags' => $tags->all(),
        ]));

        $headers = Craft::$app->getResponse()->getHeaders();
        $headers->remove(HeaderEnum::CACHE_TAG->value);
        $tags->each(fn(string $tag) => $headers->add(
            HeaderEnum::CACHE_TAG->value,
            $tag,
        ));
    }

    private function purgeItemsByHeader($header, ...$items): void
    {
        $items = Collection::make($items);
        $response = Craft::$app->getResponse();
        $isWebResponse = $response instanceof \craft\web\Response;

        if ($isWebResponse) {
            $existingTags = $response->getHeaders()->get($header, first: false) ?? [];
            $items->push(...$existingTags);
        }

        $items = $items->filter()->unique();

        if ($items->isEmpty()) {
            return;
        }

        Craft::info(new PsrMessage('Purging items via {header}', [
            'items' => $items->all(),
            'header' => $header,
        ]));

        if ($isWebResponse) {
            $response->getHeaders()->remove($header);
            $items->each(fn(string $tag) => $response->getHeaders()->add(
                $header,
                $tag,
            ));
        } else {
            Helper::makeGatewayApiRequest([
                $header => $items->implode(','),
            ]);
        }
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
