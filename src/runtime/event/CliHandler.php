<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Handler;
use Exception;
use Symfony\Component\Process\Process;

class CliHandler implements Handler
{
    protected string $scriptPath = '/var/task/craft';

    public function handle(mixed $event, Context $context): array
    {
        $commandArgs = $event['command'] ?? null;

        if (!$commandArgs) {
            throw new Exception('No command found.');
        }

        $php = PHP_BINARY;
        $command = escapeshellcmd("{$php} {$this->scriptPath} {$commandArgs}");
        $timeout = max(1, $context->getRemainingTimeInMillis() / 1000 - 1);

        $process = Process::fromShellCommandline($command, null, [
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($context, JSON_THROW_ON_ERROR),
        ], null, $timeout);

        $process->mustRun(function($type, $buffer): void {
            echo $buffer;
        });

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
        ];
    }
}
