<?php

namespace craft\cloud;

use Craft;
use craft\helpers\App;
use craft\helpers\StringHelper;
use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;
use League\Uri\UriTemplate;

class Helper
{

    public static function getBuildUrl(string $path = ''): UriInterface
    {
        return self::getCdnUrl("{environmentId}/builds/{buildId}/${path}");
    }

    public static function getCdnUrl(string $path = ''): UriInterface
    {
        $template = new UriTemplate(
            self::collapseSlashes($path),
            [
                'environmentId' => Module::getInstance()->getConfig()->environmentId ?? '__ENVIRONMENT_ID__',
                'buildId' => Craft::$app->getConfig()->getGeneral()->buildId ?? '__BUILD_ID__',
                'projectId' => Craft::$app->id ?? '__PROJECT_ID__',
            ]
        );

        return Uri::createFromBaseUri(
            $template->expand(),
            Module::getInstance()->getConfig()->getCdnBaseUrl(),
        );
    }

    /**
     * With local Bref, AWS_LAMBDA_RUNTIME_API is only set from web requests,
     * while LAMBDA_TASK_ROOT is set for both.
     */
    public static function isCraftCloud(): bool
    {
        return (bool)App::env('AWS_LAMBDA_RUNTIME_API') || App::env('LAMBDA_TASK_ROOT');
    }

    public static function collapseSlashes(string $path): string
    {
        return preg_replace('#/{2,}#', '/', $path);
    }
}
