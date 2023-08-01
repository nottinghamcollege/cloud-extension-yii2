<?php

namespace craft\cloud\queue;

use craft\cloud\Module;

class Queue extends \yii\queue\sqs\Queue
{
    public function __construct($config = [])
    {
        $config += [
            'url' => Module::getInstance()->getConfig()->sqsUrl,
            'region' => Module::getInstance()->getConfig()->getRegion(),
            'serializer' => Serializer::class,
        ];

        parent::__construct($config);
    }
}
