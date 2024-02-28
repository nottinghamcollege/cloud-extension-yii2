<?php

namespace craft\cloud\cli\controllers;

use craft\console\Controller;
use yii\console\ExitCode;

class ValidateController extends Controller
{
    public $defaultAction = 'plan';

    public function actionPlan(string $plan): int
    {
        $this->do('Validating Plan', function() {
        });

        return ExitCode::OK;
    }
}
