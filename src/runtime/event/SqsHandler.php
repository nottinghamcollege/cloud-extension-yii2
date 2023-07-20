<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Craft;
use craft\queue\QueueInterface;
use RuntimeException;

class SqsHandler extends \Bref\Event\Sqs\SqsHandler
{
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            try {
                (new QueueExecCommand($record, $context))->handle();
            } catch (RuntimeException $e) {
                // echo the exception to the output but continue processing the other records
                echo $e->getMessage();
            }
        }

    }
}
