<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use RuntimeException;
use Throwable;

class SqsHandler extends \Bref\Event\Sqs\SqsHandler
{
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            echo "Handling SQS message: #{$record->getMessageId()}";

            try {
                $body = json_decode(
                    $record->getBody(),
                    associative: false,
                    flags: JSON_THROW_ON_ERROR
                );
                $jobId = $body->jobId ?? null;

                if (!$jobId) {
                    throw new RuntimeException('The SQS message does not contain a job ID.');
                }

                echo "Executing job: #$jobId";

                (new CliHandler())->handle([
                    'command' => "cloud/queue/exec {$jobId}"
                ] , $context);
            } catch (Throwable $e) {
                echo "Marking SQS record as failed:\n";
                echo "Message: #{$record->getMessageId()}\n";
                echo "Job: #$jobId";
                $this->markAsFailed($record);
            }
        }
    }
}
