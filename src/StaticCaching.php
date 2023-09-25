<?php

namespace craft\cloud;

use Craft;
use craft\events\InvalidateElementCachesEvent;
use craft\events\TemplateEvent;
use craft\web\UrlManager;
use craft\web\View;
use yii\caching\TagDependency;

class StaticCaching
{
    public static function handleAfterRenderPageTemplate(TemplateEvent $event): void
    {
        /** @var View $view */
        $view = $event->sender;

        /** @var TagDependency|null $dependency */
        /** @var int|null $duration */
        [$dependency, $duration] = Craft::$app->getElements()->stopCollectingCacheInfo();

        if ($dependency?->tags) {
            $response = Craft::$app->getResponse();
            $response->getHeaders()->set('Cache-Tags', implode(' ', $dependency->tags));
            $response->setCacheHeaders($duration, false);
        }

    }

    public static function handleBeforeRenderPageTemplate(TemplateEvent $event): void
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

    public static function handleInvalidateCaches(InvalidateElementCachesEvent $event): void
    {
        Craft::$app->getResponse()->getHeaders()->add('Purge-Cache-Tag', implode(' ', $event->tags));
    }
}
