<?php

namespace craft\cloud\console\controllers;

use craft\console\Controller;
use yii\console\ExitCode;

class SetupController extends Controller
{
    public function actionIndex(): int
    {
        $this->run('/setup/php-session-table');
        $this->run('/setup/db-cache-table');

        // TODO: link to docs, etc
        $this->run('info/index');
        return ExitCode::OK;
    }
}
