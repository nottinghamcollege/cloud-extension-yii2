<?php

namespace craft\cloud\runtime;

use Bref\Bref;
use Bref\FpmRuntime\FpmHandler;
use Bref\Runtime\LambdaRuntime;
use craft\cloud\runtime\event\EventHandler;
use Throwable;

class Runtime
{
    public static function run(): void
    {
        // In the FPM runtime process (our process) we want to log all errors and warnings
        ini_set('display_errors', '1');
        error_reporting(E_ALL);

        Bref::triggerHooks('beforeStartup');

        $lambdaRuntime = LambdaRuntime::fromEnvironmentVariable('fpm');

        $appRoot = getenv('LAMBDA_TASK_ROOT');
        $handlerFile = $appRoot . '/' . getenv('_HANDLER');
        if (!is_file($handlerFile)) {
            $lambdaRuntime->failInitialization("Handler `$handlerFile` doesn't exist");
        }

        // use the Bref fpm handler to start php-fpm
        $phpFpm = new FpmHandler($handlerFile);
        try {
            $phpFpm->start();
        } catch (Throwable $e) {
            $lambdaRuntime->failInitialization('Error while starting PHP-FPM', $e);
        }

        // create our own event handler and pass the fpm handler to it
        $handler = new EventHandler($phpFpm);

        /** @phpstan-ignore-next-line */
        while (true) {
            $lambdaRuntime->processNextEvent($handler);
        }
    }
}
