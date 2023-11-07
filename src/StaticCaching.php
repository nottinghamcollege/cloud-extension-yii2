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
    public function beforeRenderPageTemplate(TemplateEvent $event): void
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

    public function afterRenderPageTemplate(TemplateEvent $event): void
    {
        // ignore CP requests
        if ($event->templateMode !== View::TEMPLATE_MODE_SITE) {
            return;
        }

        /** @var TagDependency|null $dependency */
        /** @var int|null $duration */
        [$dependency, $duration] = Craft::$app->getElements()->stopCollectingCacheInfo();

        $this->addCacheTagsToResponse($dependency?->tags, $duration);
    }

    public function onInvalidateCaches(InvalidateElementCachesEvent $event): void
    {
        $this->addCachePurgeTagsToResponse($event->tags ?? []);
    }

    protected function formatTags(array $tags): Collection
    {
        // Header value can't exceed 16KB
        // https://developers.cloudflare.com/cache/how-to/purge-cache/purge-by-tags/#a-few-things-to-remember
        $bytes = 0;

        return Collection::make($tags)
            ->sort(SORT_NATURAL)
            ->map(fn(string $tag) =>
                Module::getInstance()->getConfig()->getShortEnvironmentId() . $this->hash($tag)
            )
            ->filter()
            ->unique()
            ->filter(function($tag) use ($bytes) {
                // plus one for comma
                $bytes += strlen($tag) + 1;

                return $bytes < 16 * 1024;
            })
            ->values();
    }

    protected function hash(string $string): ?string
    {
        return sprintf('%x', crc32($string));
    }

    protected function addCacheTagsToResponse(array $tags, $duration = null): void
    {
        $response = Craft::$app->getResponse();
        $tags = $this->formatTags($tags);

        if (
            $tags->isEmpty() ||
            Craft::$app->getConfig()->getGeneral()->devMode
        ) {
            return;
        }

        $response->getHeaders()->set(HeaderEnum::CACHE_TAG->value, $tags->implode(','));

        if ($duration !== null) {
            $response->getHeaders()->setDefault(
                HeaderEnum::CACHE_CONTROL->value,
                "public, max-age=$duration",
            );

            $response->setCacheHeaders($duration, false);
        }
    }

    protected function addCachePurgeTagsToResponse(array $tags): void
    {
        $tags = $this->formatTags($tags);

        if ($tags->isNotEmpty()) {
            Craft::$app->getResponse()
                ->getHeaders()
                ->add(
                    HeaderEnum::CACHE_TAG_PURGE->value,
                    $tags->slice(0, 30)->implode(','),
                );
        }
    }
}
