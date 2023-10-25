<?php

namespace craft\cloud\cli\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use PHLAK\SemVer\Exceptions\InvalidVersionException;
use PHLAK\SemVer\Version;
use Symfony\Component\Yaml\Yaml;
use yii\console\ExitCode;

class SetupController extends Controller
{
    public function actionIndex(): int
    {
        $this->stdout(PHP_EOL);

        if ($this->confirm('Create a craft-cloud.yaml file?', true)) {
            $this->runAction('create-config');
        }

        // TODO: why do we need to add these locally?
        $this->stdout('Adding required database tables…' . PHP_EOL, Console::FG_GREY);
        $this->run('/setup/php-session-table');
        $this->run('/setup/db-cache-table');

        return ExitCode::OK;
    }

    public function actionCreateConfig(): int
    {
        $config = [];
        $filePath = Craft::getAlias('@root/craft-cloud.yaml');
        $defaultPhpVersion = null;
        $defaultNodeVersion = null;
        $ddevConfigFile = Craft::getAlias('@root/.ddev/config.yaml');
        $composerJsonFile = Craft::getAlias('@root/composer.json');
        $packageJsonFile = Craft::getAlias('@root/package.json');
        $ddevConfig = file_exists($ddevConfigFile) ? Yaml::parseFile($ddevConfigFile) : null;
        $packageJson = file_exists($packageJsonFile) ? json_decode(file_get_contents($packageJsonFile)) : null;
        $composerJson = file_exists($composerJsonFile) ? json_decode(file_get_contents($composerJsonFile)) : null;

        $this->stdout(PHP_EOL);

        if ($ddevConfig['php_version'] ?? null) {
            try {
                $this->do(
                    "Detected PHP version from DDEV config: {$ddevConfig['php_version']}",
                    function() use ($ddevConfig, &$defaultPhpVersion) {
                        $defaultPhpVersion = Version::parse($ddevConfig['php_version']);
                    }
                );
            } catch (InvalidVersionException $e) {
            }
        }

        if ($composerJson?->config?->platform?->php) {
            try {
                $this->do(
                    "Detected PHP version from composer.json (config.platforms.php): {$composerJson->config->platform->php}",
                    function() use ($composerJson, &$defaultPhpVersion) {
                        $defaultPhpVersion = Version::parse($composerJson->config->platform->php);
                    }
                );
            } catch (InvalidVersionException $e) {
            }
        }

        if ($defaultPhpVersion) {
            $this->stdout(PHP_EOL);
        } else {
            $defaultPhpVersion = Version::parse('8.2');
        }

        $config['php-version'] = $this->prompt('PHP version:', [
            'required' => true,
            'default' => "$defaultPhpVersion->major.$defaultPhpVersion->minor",
            'validator' => fn(string $value) => preg_match('/^0-9+\.0-9+$/', $value),
            'error' => 'PHP version must be specified as “major.minor”.',
        ]);

        $this->stdout(PHP_EOL);

        if ($ddevConfig['nodejs_version'] ?? null) {
            try {
                $this->do(
                    "Detected Node.js version from DDEV config: {$ddevConfig['nodejs_version']}",
                    function() use ($ddevConfig, &$defaultNodeVersion) {
                        $defaultNodeVersion = Version::parse($ddevConfig['nodejs_version']);
                    }
                );
            } catch (InvalidVersionException $e) {
            }
        }

        if ($packageJson?->engines?->node) {
            try {
                $this->do(
                    "Detected Node.js version from package.json (engines.node): {$packageJson->engines->node}",
                    function() use ($packageJson, &$defaultNodeVersion) {
                        $defaultNodeVersion = Version::parse($packageJson->engines->node);
                    }
                );
            } catch (InvalidVersionException $e) {
            }
        }

        if ($defaultNodeVersion) {
            $this->stdout(PHP_EOL);
        } else {
            $defaultNodeVersion = Version::parse('20');
        }

        $config['node-version'] = $this->prompt('Node version:', [
            'required' => false,
            'default' => "$defaultNodeVersion->major.$defaultNodeVersion->minor",
            'validator' => function(string $input, string &$error = null) {
                if (!preg_match('/^0-9+\.0-9+$/', $input)) {
                    $error = 'Node version must be specified as “major.minor”.';
                    return false;
                }
                return true;
            },
        ]);

        $this->stdout(PHP_EOL);

        $this->do("Creating “{$filePath}”", fn() => FileHelper::writeToFile(
            $filePath,
            Yaml::dump($config, 20, 2),
        ));

        $this->stdout(PHP_EOL);
        $this->stdout('See https://craftcms.com/knowledge-base/cloud-config for full configuration reference.' . PHP_EOL, Console::FG_GREY);
        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }
}
