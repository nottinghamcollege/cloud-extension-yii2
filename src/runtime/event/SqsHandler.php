<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsRecord;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;

class SqsHandler extends \Bref\Event\Sqs\SqsHandler
{
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        $records = Collection::make($event->getRecords());

        echo "Processing SQS event with {$records->count()} records.";

        $records->each(function(SqsRecord $record) use ($context) {
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
                    throw new \Exception('The SQS message does not contain a job ID.');
                }

                $cliHandler->handle([
                    'command' => "cloud/queue/exec {$jobId}",
                ], $context, true);
            } catch (RuntimeException $e) {
                if ($e instanceof ProcessTimedOutException && !$cliHandler->shouldRetry()) {
                    echo "Job #$jobId will not be retried:\n";
                    echo "Attempts: {$cliHandler->attempts}\n";
                    echo "Message: #{$record->getMessageId()}\n";
                    echo "Running Time: {$cliHandler->getTotalRunningTime()} seconds\n";

                    $failMessage = $cliHandler->getRemainingAttempts()
                        ? 'Job exceeded maximum running time: 15 minutes'
                        : "Job exceeded maximum attempts: {$cliHandler->maxAttempts}";

                    try {
                        (new CliHandler())->handle([
                            'command' => "cloud/queue/fail {$jobId} --message={$failMessage}",
                        ], $context, true);
                    } catch (\Throwable $e) {
                        $this->markAsFailed($record);
                    }

                    return;
                }

                // triggers SQS retry
                $this->markAsFailed($record);
            }
        });
    }
}
