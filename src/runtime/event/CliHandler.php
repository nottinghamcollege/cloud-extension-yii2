<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Handler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class CliHandler implements Handler
{
    public const EXIT_CODE_TIMEOUT = 187;
    protected string $scriptPath = '/var/task/craft';

    /**
     * @inheritDoc
     */
    public function handle(mixed $event, Context $context, $throw = false): array
    {
        $commandArgs = $event['command'] ?? null;

        if (!$commandArgs) {
            throw new \Exception('No command found.');
        }

        $php = PHP_BINARY;
        $command = escapeshellcmd("{$php} {$this->scriptPath} {$commandArgs}");
        $timeout = max(1, $context->getRemainingTimeInMillis() / 1000 - 1);
        $process = Process::fromShellCommandline($command, null, [
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($context, JSON_THROW_ON_ERROR),
        ], null, $timeout);
        $exitCode = null;

        try {
            echo "Running command: $command";

            /** @throws ProcessTimedOutException|ProcessFailedException */
            $process->mustRun(function($type, $buffer): void {
                echo $buffer;
            });

            echo "Command succeeded after {$this->getRunningTime($process)} seconds: $command\n";
        } catch (\Throwable $e) {
            echo "Command failed after {$this->getRunningTime($process)} seconds: $command\n";
            echo "{$e->getMessage()}\n";

            $exitCode = $e instanceof ProcessTimedOutException
                ? self::EXIT_CODE_TIMEOUT
                : $exitCode;

            if ($throw) {
                throw $e;
            }
        }

        return [
            'exitCode' => $exitCode ?? $process->getExitCode(),
            'output' => $process->getErrorOutput() . $process->getOutput(),
            'runningTime' => $this->getRunningTime($process),
        ];
    }

    public static function getRunningTime(Process $process): float
    {
        return $process->getLastOutputTime() - $process->getStartTime();
    }
}
