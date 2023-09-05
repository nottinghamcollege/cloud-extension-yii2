<?php

namespace craft\cloud;

use Craft;
use craft\cloud\fs\BuildArtifactsFs;
use craft\cloud\fs\CpResourcesFs;
use craft\helpers\App;
use craft\helpers\ConfigHelper;

class Helper
{
    /**
     * With local Bref, AWS_LAMBDA_RUNTIME_API is only set from web requests,
     * while LAMBDA_TASK_ROOT is set for both.
     */
    public static function isCraftCloud(): bool
    {
        return (bool)App::env('AWS_LAMBDA_RUNTIME_API') || App::env('LAMBDA_TASK_ROOT');
    }

    public static function artifactUrl(string $path = ''): ?string
    {
        return Module::getInstance()->getConfig()->enableCdn
            ? (new BuildArtifactsFs())->createUrl($path)
            : null;
    }

    public static function cpResourceUrl(string $path = ''): ?string
    {
        return Module::getInstance()->getConfig()->enableCdn
            ? (new CpResourcesFs())->createUrl($path)
            : null;
    }

    public static function setMemoryLimit(int|string $limit, int|string $offset = 0): int|float
    {
        $memoryLimit = ConfigHelper::sizeInBytes($limit) - ConfigHelper::sizeInBytes($offset);
        Craft::$app->getConfig()->getGeneral()->phpMaxMemoryLimit((string) $memoryLimit);
        Craft::info("phpMaxMemoryLimit set to $memoryLimit");

        return $memoryLimit;
    }
}
