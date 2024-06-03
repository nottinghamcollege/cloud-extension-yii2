<?php

namespace craft\cloud\cli\controllers;

use Composer\Semver\Semver;
use Craft;
use craft\console\Controller;
use craft\helpers\App;
use yii\console\Exception;

class BuildController extends Controller
{
    public $defaultAction = 'build';
    public ?string $publishAssetBundlesTo = null;
    public string $craftEdition = '';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'publishAssetBundlesTo',
            'craftEdition',
        ]);
    }

    public function actionBuild(): int
    {
        if (!$this->isEditionValid($this->craftEdition)) {
            throw new Exception('Invalid Craft CMS edition.');
        }

        return $this->run('/cloud/asset-bundles/publish', [
            'to' => $this->publishAssetBundlesTo,
        ]);
    }

    private function isEditionValid(string $edition): bool
    {
        $craftVersion = Craft::$app->getInfo()->version;

        // CRAFT_EDITION is enforced in these versions, so we don't need to validate
        if (App::env('CRAFT_EDITION') && Semver::satisfies($craftVersion, '^4.10 || ^5.2')) {
            return true;
        }

        $editionFromProjectConfig = Craft::$app->getProjectConfig()->get('system.edition', true);

        if (!$editionFromProjectConfig) {
            throw new Exception('Unable to determine the Craft CMS edition.');
        }

        if ($edition !== $editionFromProjectConfig) {
            return false;
        }

        return true;
    }
}
