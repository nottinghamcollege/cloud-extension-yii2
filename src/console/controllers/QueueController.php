<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\cloud\queue\TestJob;
use craft\console\Controller;
use craft\helpers\ConfigHelper;
use craft\queue\Queue;
use craft\web\twig\variables\Paginate;
use yii\console\Exception;
use yii\console\ExitCode;

class QueueController extends Controller
{
    public string $message = '';
    public bool $run = false;
    public bool $throw = false;
    public int|string $timeout = 0;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match($actionID) {
            'push-test-job' => [
                'message',
                'run',
                'throw',
                'timeout',
            ],
            default => [],
        });
    }

    public function actionExec(string $jobId): int
    {
        $this->do('Executing job', function() use ($jobId) {
            /** @var Queue $queue */
            $queue = Craft::$app->getQueue();
            $jobFound = $queue->executeJob($jobId);

            if (!$jobFound) {
                throw new Exception('Job not found.');
            }
        });

        return ExitCode::OK;
    }

    public function actionPushTestJob(): int
    {
        $job = new TestJob([
            'message' => $this->message,
            'throw' => $this->throw,
            'timeout' => ConfigHelper::durationInSeconds($this->timeout),
        ]);

        $this->do('Pushing test job', function() use($job) {
            $jobId = Craft::$app->getQueue()->push($job);

            if ($this->run) {
                $this->do('Running test job', fn() => $this->actionExec($jobId));
            }
        });

        return ExitCode::OK;
    }
}
