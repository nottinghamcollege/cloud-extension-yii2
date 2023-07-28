<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

class QueueController extends Controller
{
    public function actionExec(int $jobId): int
    {
        $jobFound = Craft::$app->getQueue()->executeJob($jobId);

        return $jobFound ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
