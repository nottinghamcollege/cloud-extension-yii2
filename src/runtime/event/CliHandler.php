<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Bref\Event\Handler;
use craft\cloud\runtime\Runtime;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use yii\base\Exception;

class CliHandler implements Handler
{
    public const EXIT_CODE_TIMEOUT = 187;
    public const MAX_EXECUTION_BUFFER_SECONDS = 5;
    public ?Process $process = null;
    protected string $scriptPath = '/var/task/craft';
    protected ?float $totalRunningTime = null;
    public int $maxAttempts = 10;
    public int $attempts = 0;

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
        $remainingSeconds = $context->getRemainingTimeInMillis() / 1000;
        $timeout = max(1, $remainingSeconds - 1);
        $this->process = Process::fromShellCommandline($command, null, [
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($context, JSON_THROW_ON_ERROR),
        ], null, $timeout);
        $exitCode = null;

        echo "Function time remaining: {$remainingSeconds} seconds";

        try {
            echo "Running command with $timeout second timeout: $command";

            /** @throws ProcessTimedOutException|ProcessFailedException */
            $this->process->mustRun(function($type, $buffer): void {
                echo $buffer;
            });

            echo "Command succeeded after {$this->getTotalRunningTime()} seconds: $command\n";
        } catch (\Throwable $e) {
            $this->attempts++;
            echo "Command failed after {$this->getTotalRunningTime()} seconds: $command\n";
            echo "Exception while handling CLI event:\n";
            echo "{$e->getMessage()}\n";
            echo "{$e->getTraceAsString()}\n";

            $exitCode = $e instanceof ProcessTimedOutException
                ? self::EXIT_CODE_TIMEOUT
                : $exitCode;

            if ($throw) {
                throw $e;
            }
        }

        return [
            'exitCode' => $exitCode ?? $this->process->getExitCode(),
            'output' => $this->process->getErrorOutput() . $this->process->getOutput(),
            'runningTime' => $this->getTotalRunningTime(),
        ];
    }

    public function getTotalRunningTime(): float
    {
        if ($this->totalRunningTime !== null) {
            return $this->totalRunningTime;
        }

        if (!$this->process) {
            throw new Exception('Process does not exist');
        }

        return max(0, microtime(true) - $this->process->getStartTime());
    }

    public function getRemainingAttempts(): int
    {
        return $this->maxAttempts - $this->attempts;
    }

    public function shouldRetry(): bool
    {
        if (!$this->getRemainingAttempts()) {
            return false;
        }

        $diff = Runtime::MAX_EXECUTION_SECONDS - $this->getTotalRunningTime();

        return $diff > static::MAX_EXECUTION_BUFFER_SECONDS;
    }
}
