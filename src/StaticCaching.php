<?php

namespace craft\cloud;

use Craft;
use craft\events\InvalidateElementCachesEvent;
use craft\events\TemplateEvent;
use craft\web\UrlManager;
use craft\web\View;
use Illuminate\Support\Collection;
use yii\caching\TagDependency;

class StaticCaching extends \yii\base\Component
{
    public static function beforeRenderPageTemplate(TemplateEvent $event): void
    {
        // ignore CP requests
        if ($event->templateMode !== View::TEMPLATE_MODE_SITE) {
            return;
        }

        // start collecting element cache info
        Craft::$app->getElements()->startCollectingCacheInfo();

        // capture the matched element, if there was one
        /** @var UrlManager $urlManager */
        $urlManager = Craft::$app->getUrlManager();
        $matchedElement = $urlManager->getMatchedElement();
        if ($matchedElement) {
            Craft::$app->getElements()->collectCacheInfoForElement($matchedElement);
        }
    }

    public static function afterRenderPageTemplate(TemplateEvent $event): void
    {
        // ignore CP requests
        if ($event->templateMode !== View::TEMPLATE_MODE_SITE) {
            return;
        }

        /** @var TagDependency|null $dependency */
        /** @var int|null $duration */
        [$dependency, $duration] = Craft::$app->getElements()->stopCollectingCacheInfo();

        static::addCacheTagsToResponse($dependency?->tags, $duration);

        // Temporary fix
        Craft::$app->getResponse()->getCookies()->removeAll();
    }

    public static function onInvalidateCaches(InvalidateElementCachesEvent $event): void
    {
        static::addCachePurgeTagsToResponse($event->tags ?? []);
    }

    public static function minifyCacheTags(array $tags): Collection
    {
        // TODO: can't exceed 16KB
        // https://developers.cloudflare.com/cache/how-to/purge-cache/purge-by-tags/#a-few-things-to-remember
        return Collection::make($tags)
            ->map(fn(string $tag) => static::minifyCacheTag($tag))
            ->filter()
            ->unique();
    }

    public static function minifyCacheTag(string $tag): ?string
    {
        return sprintf('%x', crc32($tag));
    }

    public static function addCacheTagsToResponse(array $tags, $duration = null): void
    {
        $response = Craft::$app->getResponse();
        $tags = static::minifyCacheTags($tags);

        if ($tags->isNotEmpty()) {
            $response->getHeaders()->set(HeaderEnum::CACHE_TAG->value, $tags->implode(','));
        }

        // TODO: when would this be null?
        if ($duration !== null) {
            $response->getHeaders()->setDefault(
                HeaderEnum::CACHE_CONTROL->value,
                "public, max-age=$duration",
            );

            $response->setCacheHeaders($duration, false);
        }
    }

    public static function addCachePurgeTagsToResponse(array $tags): void
    {
        $tags = static::minifyCacheTags($tags);

        if ($tags->isNotEmpty()) {
            Craft::$app->getResponse()
                ->getHeaders()
                ->add(HeaderEnum::CACHE_TAG_PURGE->value, $tags->implode(','));
        }
    }
}
