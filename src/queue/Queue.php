<?php

namespace craft\cloud\queue;

class Queue extends \yii\queue\sqs\Queue
{
    /**
     * TODO: remove this once released: https://github.com/yiisoft/yii2-queue/pull/502
     */
    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        /** @phpstan-ignore-next-line  */
        return parent::pushMessage($message, (string) $ttr, $delay, $priority);
    }
}
