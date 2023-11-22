<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use craft\cloud\runtime\Runtime;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class SqsHandler extends \Bref\Event\Sqs\SqsHandler
{
    public const MAX_EXECUTION_BUFFER_SECONDS = 5;

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            echo "Handling SQS message: #{$record->getMessageId()}";
            $jobId = null;

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
                    'command' => "cloud/queue/exec {$jobId}",
                ], $context, true);
            } catch (Throwable $e) {
                echo "Exception class: " . get_class($e) . "\n";
                echo "Exception message: " . $e->getMessage() . "\n";
                if ($e instanceof ProcessTimedOutException) {
                    $process = $e->getProcess();

                    if (!$this->shouldRetry($process)) {
                        $runningTime = CliHandler::getRunningTime($process);
                        echo "Job ran for {$runningTime} seconds and will not be retried:\n";
                        echo "Message: #{$record->getMessageId()}\n";
                        echo "Job: " . ($jobId ? "#$jobId" : 'unknown');

                        throw $e;
                    }
                }

                echo "Marking SQS record as failed for retry:\n";
                echo "Message: #{$record->getMessageId()}\n";
                echo "Job: " . ($jobId ? "#$jobId" : 'unknown');

                $this->markAsFailed($record);
            }
        }
    }

    protected function shouldRetry(Process $process): bool
    {
        $diff = Runtime::MAX_EXECUTION_SECONDS - CliHandler::getRunningTime($process);

        return $diff > static::MAX_EXECUTION_BUFFER_SECONDS;
    }
}
