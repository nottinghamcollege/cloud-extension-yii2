<?php

namespace craft\cloud;

use Craft;
use craft\events\InvalidateElementCachesEvent;
use craft\services\Elements;

class ElementsBehavior extends \yii\base\Behavior
{
    public function events(): array
    {
        return [
            Elements::EVENT_INVALIDATE_CACHES => [$this, 'invalidateCaches'],
        ];
    }

    public function invalidateCaches(InvalidateElementCachesEvent $event): void
    {
        // 'tags' will purge Cache-Tags in req
        // 'all' will do a full purge
        $purgeMode = 'tags';

        Craft::$app->getResponse()
            ->getHeaders()
            ->add('Cache-Purge', $purgeMode);
    }
}
