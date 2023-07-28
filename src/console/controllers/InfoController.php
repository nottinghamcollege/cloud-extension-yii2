<?php

namespace craft\cloud\console\controllers;

use Craft;
use craft\cloud\Module;
use craft\console\Controller;
use yii\console\ExitCode;

class InfoController extends Controller
{
    public function actionIndex(): int
    {
        $packageName = 'craftcms/cloud';
        $packageVersion = \Composer\InstalledVersions::getVersion($packageName);

        $this->table([
            'Extension',
            'App ID',
            'Environment ID',
            'Build ID',
            'scriptFile',
        ], [
            [
                "$packageName:$packageVersion",
                Craft::$app->id,
                Module::getInstance()->getConfig()->environmentId,
                Craft::$app->getConfig()->getGeneral()->buildId,
                $this->request->getScriptFile(),
            ],
        ]);
        return ExitCode::OK;
    }
}
