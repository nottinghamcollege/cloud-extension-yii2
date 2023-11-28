<?php

namespace craft\cloud\queue;

use Craft;

class SqsQueue extends \yii\queue\sqs\Queue
{
    protected function pushMessage($message, $ttr, $delay, $priority): string
    {
        $ttr = (string) $ttr;

        /**
         * Delay pushing to SQS until after request is processed.
         *
         * Without this, a job might be pushed to SQS from within a DB
         * transaction and send back to Craft for handling before the
         * transaction ends. At this point, the job ID doesn't exist,
         * so the craft cloud/queue/exec command will fail and SQS
         * will consider the job processed. Once the transaction ends,
         * the job will exist and be indefinitely pending.
         */
        Craft::$app->onAfterRequest(function() use ($message, $ttr, $delay) {

            /**
             * @phpstan-ignore-next-line
             * @see https://github.com/yiisoft/yii2-queue/pull/502
             *
             * Priority is not supported by SQS
             */
            return parent::pushMessage($message, (string) $ttr, $delay, null);
        });

        // Return anything but null, as we don't have an SQS message ID yet.
        return '';
    }
}
