<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Throwable;

class SqsHandler extends \Bref\Event\Sqs\SqsHandler
{
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            echo "Handling SQS message: #{$record->getMessageId()}";
            $jobId = null;
            $cliHandler = new CliHandler();

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

                $cliHandler->handle([
                    'command' => "cloud/queue/exec {$jobId}",
                ], $context, true);
            } catch (Throwable $e) {
                echo "Exception class: " . get_class($e) . "\n";
                echo "Exception message: " . $e->getMessage() . "\n";
                if ($e instanceof ProcessTimedOutException) {
                    if (!$cliHandler->shouldRetry()) {
                        echo "Job ran for {$cliHandler->getTotalRunningTime()} seconds and will not be retried:\n";
                        echo "Message: #{$record->getMessageId()}\n";
                        echo "Job: " . ($jobId ? "#$jobId" : 'unknown');

                        return;
                    }
                }

                echo "Marking SQS record as failed for retry:\n";
                echo "Message: #{$record->getMessageId()}\n";
                echo "Job: " . ($jobId ? "#$jobId" : 'unknown');

                $this->markAsFailed($record);
            }
        }
    }
}
