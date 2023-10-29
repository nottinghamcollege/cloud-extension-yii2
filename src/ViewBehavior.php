<?php

namespace craft\cloud;

use Craft;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\TemplateEvent;
use craft\web\UrlManager;
use craft\web\View;
use yii\caching\TagDependency;

class ViewBehavior extends \yii\base\Behavior
{
    public function events(): array
    {
        return [
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE => [$this, 'beforeRenderPageTemplate'],
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE => [$this, 'afterRenderPageTemplate'],
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS => [$this, 'registerCpTemplateRoots'],
        ];
    }

    public function registerCpTemplateRoots(RegisterTemplateRootsEvent $e): void
    {
        $e->roots[Module::getInstance()->id] = sprintf('%s/templates', Module::getInstance()->getBasePath());
    }

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
}
