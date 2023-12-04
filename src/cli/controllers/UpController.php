<?php

namespace craft\cloud\cli\controllers;

use Craft;
use craft\cloud\EventEnum;
use craft\console\Controller;
use craft\events\CancelableEvent;
use yii\console\ExitCode;

class UpController extends Controller
{
    public function actionIndex(): int
    {
        $event = new CancelableEvent();
        $this->trigger(EventEnum::BEFORE_UP->value, $event);

        if (!$event->isValid) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->run('/setup/php-session-table');
        $this->run('/setup/db-cache-table');

        if (Craft::$app->getIsInstalled()) {
            $this->run('/up');
            $this->run('/clear-caches/cloud-static-caches');
        }

        $event = new CancelableEvent();
        $this->trigger(EventEnum::AFTER_UP->value, $event);

        if (!$event->isValid) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
