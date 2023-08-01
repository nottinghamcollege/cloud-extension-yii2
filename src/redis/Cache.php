<?php

namespace craft\cloud\redis;

use Craft;
use craft\cloud\Module;

class Cache extends \yii\redis\Cache
{
    public function __construct($config = [])
    {
        $config += [
            'redis' => [
                'class' => Connection::class,
                'database' => 0,
            ],
            'keyPrefix' => sprintf('cache.%s.', Module::getInstance()->getConfig()->environmentId),
            'defaultDuration' => Craft::$app->getConfig()->getGeneral()->cacheDuration,
        ];

        parent::__construct($config);
    }
}
