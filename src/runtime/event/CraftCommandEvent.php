<?php

namespace craft\cloud\runtime\event;

use Bref\Context\Context;
use Exception;
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
        $command = sprintf("/opt/bin/php /var/task/craft %s 2>&1", $this->event['command']);

        $timeout = max(1, $this->context->getRemainingTimeInMillis() / 1000 - 1);

        $process = Process::fromShellCommandline($command, null, [
            'LAMBDA_INVOCATION_CONTEXT' => json_encode($this->context, JSON_THROW_ON_ERROR),
        ], null, $timeout);

        $process->run(function($type, $buffer): void {
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
