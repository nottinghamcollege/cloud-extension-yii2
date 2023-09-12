<?php

namespace craft\cloud\cli\controllers;

use Craft;
use craft\cloud\queue\TestJob;
use craft\console\Controller;
use craft\queue\Queue;
use yii\console\Exception;
use yii\console\ExitCode;

class QueueController extends Controller
{
    /**
     * The number of jobs to push to the queue
     */
    public int $count = 1;

    /**
     * The amount of time each job should sleep for
     */
    public int $seconds = 0;

    /**
     * Whether to run the job immediately
     */
    public bool $run = false;

    /**
     * Whether the job should throw an exception
     */
    public bool $throw = false;

    /**
     * The exception message, when self::$throw is `true`
     */
    public string $message = '';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'test-job' => [
                'message',
                'run',
                'throw',
                'seconds',
                'count',
            ],
            default => [],
        });
    }

    public function actionExec(string $jobId): int
    {
        $this->do("Executing job #$jobId", function() use ($jobId) {
            /** @var Queue $queue */
            $queue = Craft::$app->getQueue();
            $jobFound = $queue->executeJob($jobId);

            if (!$jobFound) {
                throw new Exception('Job not found.');
            }
        });

        return ExitCode::OK;
    }

    public function actionTestJob(): int
    {
        for ($i = 0; $i < $this->count; $i++) {
            $job = new TestJob([
                'message' => $this->message,
                'throw' => $this->throw,
                'seconds' => $this->seconds,
            ]);

            $this->do('Pushing test job', function() use ($job) {
                $jobId = Craft::$app->getQueue()->push($job);

                if ($this->run) {
                    $this->do('Running test job', fn() => $this->actionExec($jobId));
                }
            });
        }

        return ExitCode::OK;
    }
}
