<?php

namespace craft\cloud\cli\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use Illuminate\Support\Collection;
use PHLAK\SemVer\Exceptions\InvalidVersionException;
use PHLAK\SemVer\Version;
use Symfony\Component\Yaml\Yaml;
use yii\console\ExitCode;

class SetupController extends Controller
{
    public function actionIndex(): int
    {
        $this->runAction('config');

        $this->stdout(PHP_EOL);

        // TODO: why do we need to add these locally?
        $this->stdout('Adding required database tables…' . PHP_EOL, Console::FG_GREY);
        $this->run('/setup/php-session-table');
        $this->run('/setup/db-cache-table');

        return ExitCode::OK;
    }

    public function actionConfig(): int
    {
        $config = [];
        $filePath = Craft::getAlias('@root/craft-cloud.yaml');
        $fileName = basename($filePath);
        $defaultPhpVersion = Version::parse('8.2');
        $defaultNodeVersion = Version::parse('20.9');
        $defaultNpmScript = 'build';
        $ddevConfigFile = Craft::getAlias('@root/.ddev/config.yaml');
        $composerJsonFile = Craft::getAlias('@root/composer.json');
        $packageJsonFile = Craft::getAlias('@root/package.json');
        $ddevConfig = file_exists($ddevConfigFile) ? Yaml::parseFile($ddevConfigFile) : null;
        $packageJson = file_exists($packageJsonFile) ? json_decode(file_get_contents($packageJsonFile)) : null;
        $composerJson = file_exists($composerJsonFile) ? json_decode(file_get_contents($composerJsonFile)) : null;
        $confirmMessage = file_exists($filePath)
            ? $this->markdownToAnsi("`{$fileName}` already exists. Overwrite?")
            : $this->markdownToAnsi("Create `{$fileName}`?");

        if (!$this->confirm($confirmMessage, true)) {
            return ExitCode::OK;
        }

        if ($ddevConfig['php_version'] ?? null) {
            try {
                $this->do(
                    "Detected PHP version from DDEV config: `{$ddevConfig['php_version']}`",
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
                    "Detected PHP version from composer.json (_config.platforms.php_): `{$composerJson->config->platform->php}`",
                    function() use ($composerJson, &$defaultPhpVersion) {
                        $defaultPhpVersion = Version::parse($composerJson->config->platform->php);
                    }
                );
            } catch (InvalidVersionException $e) {
            }
        }

        $config['php-version'] = $this->prompt('PHP version:', array(
            'required' => true,
            'default' => "$defaultPhpVersion->major.$defaultPhpVersion->minor",
            'validator' => function(string $value, &$error) {
                if (!preg_match('/^[0-9]+\.[0-9]+$/', $value)) {
                    $error = $this->markdownToAnsi('PHP version must be specified as `major.minor`.');
                    return false;
                }
                return true;
            },
        ));

        if ($packageJson?->scripts && $this->confirm('Run npm script on deploy?', true)) {
            $scripts = Collection::make($packageJson->scripts)->keys();
            $config['npm-script'] = $this->prompt('npm script to run:', [
                'default' => $scripts->contains($defaultNpmScript) ? $defaultNpmScript : null,
                'required' => true,
                'validator' => function(string $value, &$error) use ($scripts) {
                    if (!$scripts->contains($value)) {
                        $error = $this->markdownToAnsi("npm script not found in package.json: `{$value}`");
                        return false;
                    }

                    return true;
                },
            ]);

            if ($defaultNpmScript === $config['npm-script']) {
                unset($config['npm-script']);
            }

            if ($ddevConfig['nodejs_version'] ?? null) {
                try {
                    $this->do(
                        "Detected Node.js version from DDEV config: `{$ddevConfig['nodejs_version']}`",
                        function() use ($ddevConfig, &$defaultNodeVersion) {
                            $defaultNodeVersion = Version::parse($ddevConfig['nodejs_version']);
                        }
                    );
                } catch (InvalidVersionException $e) {
                }
            }

            if ($packageJson->engines?->node) {
                try {
                    $this->do(
                        "Detected Node.js version from package.json (_engines.node_): `{$packageJson->engines->node}`",
                        function() use ($packageJson, &$defaultNodeVersion) {
                            $defaultNodeVersion = Version::parse($packageJson->engines->node);
                        }
                    );
                } catch (InvalidVersionException $e) {
                }
            }

            $config['node-version'] = $this->prompt('Node version:', [
                'required' => false,
                'default' => "$defaultNodeVersion->major.$defaultNodeVersion->minor",
                'validator' => function(string $input, string &$error = null) {
                    if (!preg_match('/^[0-9]+\.[0-9]+$/', $input)) {
                        $error = $this->markdownToAnsi('Node version must be specified as `major.minor`.');
                        return false;
                    }
                    return true;
                },
            ]);
        }

        $this->writeToFile(
            $filePath,
            Yaml::dump($config, 20, 2),
        );

        $this->stdout(PHP_EOL);
        $this->stdout($this->markdownToAnsi('Full configuration reference: https://craftcms.com/knowledge-base/cloud-config'), Console::FG_YELLOW);
        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }
}
