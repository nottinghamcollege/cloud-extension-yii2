<?php

namespace craft\cloud\cli\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

class UpController extends Controller
{
    public function actionIndex(): int
    {
        $this->run('asset-bundles/publish');
        $this->run('/setup/php-session-table');
        $this->run('/setup/db-cache-table');

        // TODO: wrap with events
        if (Craft::$app->getIsInstalled()) {
            $this->run('/up');
        }

        return ExitCode::OK;
    }
}
