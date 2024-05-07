<?php

namespace craft\cloud;

use Craft;
use craft\base\Element;
use craft\events\InvalidateElementCachesEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\TemplateEvent;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use craft\utilities\ClearCaches;
use craft\web\Application as WebApplication;
use craft\web\Response as WebResponse;
use craft\web\UrlManager;
use craft\web\View;
use Illuminate\Support\Collection;
use samdark\log\PsrMessage;
use yii\base\Event;
use yii\caching\TagDependency;

class StaticCache extends \yii\base\Component
{
    public function registerEventHandlers(): void
    {
        Event::on(
            WebApplication::class,
            WebApplication::EVENT_INIT,
            [$this, 'handleWebApplicationInit'],
        );

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            [$this, 'handleBeforeRenderPageTemplate'],
        );

        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            [$this, 'handleAfterRenderPageTemplate'],
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

        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            [$this, 'handleAfterUpdate'],
        );

        Event::on(
            Element::class,
            Element::EVENT_AFTER_DELETE,
            [$this, 'handleAfterUpdate'],
        );
    }

    private function handleWebApplicationInit(Event $event): void
    {
        if (!$this->shouldCollectCacheInfo()) {
            return;
        }

        Craft::$app->getElements()->startCollectingCacheInfo();
    }

    private function handleBeforeRenderPageTemplate(TemplateEvent $event): void
    {
        if (!$this->shouldCollectCacheInfo()) {
            return;
        }

        /** @var UrlManager $urlManager */
        $urlManager = Craft::$app->getUrlManager();
        $matchedElement = $urlManager->getMatchedElement();

        if ($matchedElement) {
            Craft::$app->getElements()->collectCacheInfoForElement($matchedElement);
        }
    }

    private function handleAfterRenderPageTemplate(TemplateEvent $event): void
    {
        // ignore CP and CLI requests
        if (
            $event->templateMode !== View::TEMPLATE_MODE_SITE ||
            !(Craft::$app->getResponse() instanceof WebResponse)
        ) {
            return;
        }

        /** @var TagDependency|null $dependency */
        /** @var int|null $duration */
        [$dependency, $duration] = Craft::$app->getElements()->stopCollectingCacheInfo();

        if ($dependency?->tags) {
            $this->addCacheTagsToResponse($dependency->tags, $duration);
        }
    }

    private function handleAfterUpdate(Event $event): void
    {
        /** @var Element $element */
        $element = $event->sender;

        if (ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        $url = $element->getUrl();

        if (!$url) {
            return;
        }

        $this->purgePrefixes($url);
    }

    private function handleInvalidateElementCaches(InvalidateElementCachesEvent $event): void
    {
        $tags = $event->tags ?? [];

        if (!count($tags)) {
            return;
        }

        $this->purgeTags(...$tags);
    }

    private function handleRegisterCacheOptions(RegisterCacheOptionsEvent $event): void
    {
        $event->options[] = [
            'key' => 'cloud-static-caches',
            'label' => Craft::t('app', 'Craft Cloud static caches'),
            'action' => [$this, 'purgeAll'],
        ];
    }

    public function purgeAll(): void
    {
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $this->purgePrefixes($site->getBaseUrl());
        }
    }

    public function purgePrefixes(string ...$prefixes): void
    {
        $prefixesForHeader = Collection::make($prefixes)
            ->filter()
            ->unique()
            ->values();

        if ($prefixesForHeader->isEmpty()) {
            return;
        }

        if (Craft::$app->getResponse() instanceof WebResponse) {
            $headers = Craft::$app->getResponse()->getHeaders();

            $prefixesForHeader = $prefixesForHeader
                ->diff($headers->get(HeaderEnum::CACHE_PURGE_PREFIX->value, first: false))
                ->values();

            Craft::info(new PsrMessage('Adding cache purge prefixes to response', $prefixesForHeader->all()));

            $prefixesForHeader->each(fn(string $prefix) => $headers->add(
                HeaderEnum::CACHE_PURGE_PREFIX->value,
                $prefix,
            ));
        } else {
            Helper::makeGatewayApiRequest([
                HeaderEnum::CACHE_PURGE_PREFIX->value => $prefixesForHeader->implode(','),
            ]);
        }
    }

    public function purgeTags(string ...$tags): void
    {
        if ($this->shouldIgnoreTags($tags)) {
            Craft::info(new PsrMessage('Ignoring cache tags', $tags));

            return;
        }

        $tagsForHeader = $this->prepareTags($tags);

        if ($tagsForHeader->isEmpty()) {
            return;
        }

        if (Craft::$app->getResponse() instanceof WebResponse) {
            $headers = Craft::$app->getResponse()->getHeaders();

            $tagsForHeader = $tagsForHeader
                ->diff($headers->get(HeaderEnum::CACHE_PURGE_TAG->value, first: false))
                ->values();

            Craft::info(new PsrMessage('Adding cache purge tags to response', $tagsForHeader->all()));

            $tagsForHeader->each(fn(string $tag) => $headers->add(
                HeaderEnum::CACHE_PURGE_TAG->value,
                $tag,
            ));
        } else {
            Helper::makeGatewayApiRequest([
                HeaderEnum::CACHE_PURGE_TAG->value => $tagsForHeader->implode(','),
            ]);
        }
    }

    private function prepareTags(iterable $tags): Collection
    {
        Craft::info(new PsrMessage('Preparing tags', Collection::make($tags)->all()));

        // Header value can't exceed 16KB
        // https://developers.cloudflare.com/cache/how-to/purge-cache/purge-by-tags/#a-few-things-to-remember
        $bytes = 0;

        return Collection::make($tags)
            ->map(fn(string $tag) => $this->removeNonPrintableChars($tag))
            ->filter()
            ->sort(SORT_NATURAL)
            ->map(function(string $tag) {
                return Module::getInstance()->getConfig()->getShortEnvironmentId() . $this->hash($tag);
            })
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

    private function addCacheTagsToResponse(array $tags, $duration = null): void
    {
        $response = Craft::$app->getResponse();
        $headers = $response->getHeaders();

        if (
            $response->isServerError ||
            Craft::$app->getConfig()->getGeneral()->devMode ||
            $this->shouldIgnoreTags($tags)
        ) {
            Craft::info(new PsrMessage('Ignoring cache tags', $tags));

            return;
        }

        $tagsForHeader = $this
            ->prepareTags($tags)
            ->diff($headers->get(HeaderEnum::CACHE_TAG->value, first: false))
            ->values();

        if ($duration === null || $tagsForHeader->isEmpty()) {
            return;
        }

        Craft::info(new PsrMessage('Adding cache tags to response', $tagsForHeader->all()));

        $tagsForHeader->each(fn(string $tag) => $headers->add(
            HeaderEnum::CACHE_TAG->value,
            $tag,
        ));

        $response->setCacheHeaders($duration, false);
    }

    private function shouldIgnoreTags(iterable $tags): bool
    {
        return Collection::make($tags)->contains(function(string $tag) {
            return preg_match('/element::craft\\\\elements\\\\\S+::(drafts|revisions)/', $tag);
        });
    }

    private function shouldCollectCacheInfo(): bool
    {
        return
            Craft::$app->getView()->templateMode === View::TEMPLATE_MODE_SITE &&
            Craft::$app->getResponse() instanceof WebResponse;
    }
}
