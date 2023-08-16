<?php

namespace craft\cloud\console\controllers;

use craft\console\Controller;
use yii\console\ExitCode;

class UpController extends Controller
{
    public function actionIndex(): int
    {
        // TODO: wrap with events
        $this->run('/setup/php-session-table');
        $this->run('/setup/db-cache-table');
        $this->run('/up');
        $this->run('asset-bundles/publish');

        return ExitCode::OK;
    }
}
