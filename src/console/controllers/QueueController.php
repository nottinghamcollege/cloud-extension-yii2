<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\Exception;
use yii\console\ExitCode;

class QueueController extends Controller
{
    public function actionExec(int $jobId): int
    {
        $this->do('Executing job', function() use ($jobId) {
            $jobFound = Craft::$app->getQueue()->executeJob($jobId);

            if (!$jobFound) {
                throw new Exception('Job not found.');
            }
        });

        return ExitCode::OK;
    }
}
