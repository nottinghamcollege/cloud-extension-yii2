<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsRecord;
use Exception;
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

    public function handle(): array
    {
        $content = json_decode($this->record->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($content['id'])) {
            throw new Exception('The SQS message does not contain an ID.');
        }

        $command = sprintf("/opt/bin/php /var/task/craft queue/exec %s 2>&1", $content['id']);

        $timeout = max(1, $this->context->getRemainingTimeInMillis() / 1000 - 1);

        $process = Process::fromShellCommandline($command, null, [
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($this->context, JSON_THROW_ON_ERROR),
        ], null, $timeout);

        $process->run(function ($type, $buffer): void {
            echo $buffer;
        });

        $exitCode = $process->getExitCode();

        if ($exitCode > 0) {
            throw new Exception('The command exited with a non-zero status code: ' . $exitCode);
        }

        return [
            'exitCode' => $exitCode, // will always be 0
            'output' => $process->getOutput(),
        ];
    }
}