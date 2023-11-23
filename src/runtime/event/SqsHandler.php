<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsRecord;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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
                    throw new RuntimeException('The SQS message does not contain a job ID.');
                }

                echo "Executing job: #$jobId";

                $cliHandler->handle([
                    'command' => "cloud/queue/exec {$jobId}",
                ], $context, true);
            } catch (ProcessTimedOutException $e) {
                if (!$cliHandler->shouldRetry()) {
                    echo "Job #$jobId ran for {$cliHandler->getTotalRunningTime()} seconds and will not be retried:\n";
                    echo "Message: #{$record->getMessageId()}\n";

                    $failMessage = 'Job execution exceeded 15 minutes';
                    $cliHandler->handle([
                        'command' => "cloud/queue/fail {$jobId} --message={$failMessage}",
                    ], $context, true);

                    return;
                }

                $this->markAsFailed($record);
            } catch (\Throwable $e) {
                echo "Process threw exception: {$e->getMessage()}";

                return;
            }
        });
    }
}
