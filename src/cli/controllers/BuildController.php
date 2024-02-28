<?php

namespace craft\cloud\cli\controllers;

use craft\console\Controller;
use yii\console\ExitCode;

class BuildController extends Controller
{
    public function actionIndex(): int
    {
        $this->run('/cloud/asset-bundles/publish');

        return ExitCode::OK;
    }
}
