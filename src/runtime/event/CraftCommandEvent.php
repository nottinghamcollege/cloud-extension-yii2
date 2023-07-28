<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CraftCommandEvent
{
    private $event;

    private Context $context;

    public function __construct($event, Context $context)
    {
        $this->event = $event;
        $this->context = $context;
    }

    public function run(): array
    {
        $craftCommand = $this->event['command'];
        $command = sprintf("/opt/bin/php /var/task/craft %s 2>&1", $craftCommand);

        $timeout = max(1, $this->context->getRemainingTimeInMillis() / 1000 - 1);

        $process = Process::fromShellCommandline($command, null, [
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($this->context, JSON_THROW_ON_ERROR),
        ], null, $timeout);

        echo "Running Craft command: $craftCommand";

        try {
            $process->mustRun(function ($type, $buffer): void {
                echo $buffer;
            });
        } catch(ProcessFailedException $e) {
            return [
                'exitCode' => $e->getProcess()->getExitCode(),
                'output' => $e->getMessage(),
            ];
        }

        echo "Finished Craft command: $craftCommand";

        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
        ];
    }
}
