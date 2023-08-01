<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsRecord;
use Exception;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class QueueExecCommand
{
    private $record;

    private Context $context;

    public function __construct(SqsRecord $record, Context $context)
    {
        $this->record = $record;
        $this->context = $context;
    }

    public function handle(): void
    {
        $body = json_decode($this->record->getBody(), false, flags: JSON_THROW_ON_ERROR);
        $jobId = $body->jobId ?? null;

        if (!$jobId) {
            throw new Exception('The SQS message does not contain a valid queue job.');
        }

        $command = sprintf("/opt/bin/php /var/task/craft cloud/queue/exec %s 2>&1", $jobId);

        $timeout = max(1, $this->context->getRemainingTimeInMillis() / 1000 - 1);

        $process = Process::fromShellCommandline($command, null, [
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($this->context, JSON_THROW_ON_ERROR),
        ], null, $timeout);

        echo "Running Craft queue command: $command\n";

        try {
            $process->mustRun(function ($type, $buffer): void {
                echo $buffer;
            });
        } catch(ProcessFailedException $e) {
            echo $e->getMessage();

            return;
        }

        echo "Finished Craft queue command: $command\n";
    }
}
