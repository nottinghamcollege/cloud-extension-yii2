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
        ];

        parent::__construct($config);
    }

    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        /** @phpstan-ignore-next-line  */
        return parent::pushMessage($message, (string) $ttr, $delay, $priority);
    }
}
