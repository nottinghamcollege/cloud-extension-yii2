<?php

namespace craft\cloud\redis;

use craft\cloud\Module;

class Session extends \yii\redis\Session
{
    public function __construct($config = [])
    {
        $config += [
            'redis' => [
                'class' => Connection::class,
                'database' => 1,
            ],
            'keyPrefix' => sprintf('session.%s.', Module::getInstance()->getConfig()->environmentId),
        ];

        parent::__construct($config);
    }
}
