<a href="https://craftcms.com/cloud" rel="noopener" target="_blank" title="Craft Cloud"><img src="https://raw.githubusercontent.com/craftcms/.github/v3/profile/product-icons/craft-cloud.svg" alt="Craft Cloud icon" width="65"></a>

# Craft Cloud Extension

Welcome to [**Craft Cloud**](https://craft.cloud/)!

This repository contains source code for the `craftcms/cloud` Composer package, which is required to run a Craft project on our first-party hosting platform, Craft Cloud.

When installed, the extension automatically [bootstraps](https://www.yiiframework.com/doc/guide/2.0/en/runtime-bootstrapping) itself and makes necessary [application configuration](https://craftcms.com/docs/4.x/config/app.html) changes for the detected environment:

- :cloud_with_lightning: **Cloud:** There’s no infrastructure settings to worry about—database, queue, cache, and session configuration is handled for you.
- :computer: **Local development:** Craft runs normally, in your favorite [development environment](https://craftcms.com/docs/4.x/installation.html).

:sparkles: To learn more about Cloud, check out [our website](https://craftcms.com/cloud)—or dive right in with [Craft Console](https://console.craftcms.com/cloud). Interested in everything the extension does to get your app ready for Cloud? Read our [Cloud module deep-dive](https://craftcms.com/knowledge-base/cloud-module), in the knowledge base.

## Installation

The Cloud module can be installed in any existing Craft 4.5+ project by running `php craft setup/cloud`. Craft will install the [`craftcms/cloud` package](https://packagist.org/craftcms/cloud) and run the module’s own setup wizard.

When you deploy a project to Cloud, the `cloud/up` command will run, wrapping Craft’s built-in [`up` command](https://craftcms.com/docs/4.x/console-commands.html#up) and adding the cache and session tables (if they’re not already present).

## Filesystem

When setting up your project’s assets, use the provided **Craft Cloud** filesystem type. Read more about [managing assets in Cloud projects](https://craftcms.com/knowledge-base/cloud-filesystem).

## Developer Features

### Template Helpers

The extension provides two new [Twig functions](https://craftcms.com/docs/4.x/dev/functions.html) and one global variable:

#### `artifactUrl()`

Generates a URL to a resource that was uploaded to the CDN during the build and deployment process.

#### `cpResourceUrl()`

Builds a URL for control panel resources. Not typically necessary outside of the native asset publishing loop, but provided in case an existing application makes use of `craft\web\AssetManager::publish()`, directly.

#### `isCraftCloud`

`true` when the app detects it is running on Cloud infrastructure, `false` otherwise.

## Configuration

Most configuration is handled directly by the Cloud infrastructure, through [environment overrides](https://craftcms.com/docs/4.x/config/#environment-overrides). These values are provided strictly for reference, and have limited utility outside the platform.

> [!NOTE]
> Some local development features (like asset synchronization) may require defining environment-specific credentials with `accessKey`, `accessSecret`, `region`, `projectId`, and `environmentId`.

Option | Type | Description
--- | --- | ---
`accessKey` | `string` | AWS access key, used for communicating with storage APIs.
`accessSecret` | `string` | AWS access secret, used in conjunction with the `accessKey`.
`allowBinaryResponses` | `bool` | When disabled, Craft will upload binary response data to S3, then issue a redirect.
`cdnBaseUrl` | `string` | Used when building URLs to [assets](#filesystem) and other build [artifacts](#artifacturl).
`cdnSigningKey` | `string` | A secret value used to protect transform URLs against abuse.
`enableCache` | `bool` | Uses the database for cache data.
`enableCdn` | `bool` | Replaces the default asset manager component with one that publishes to a persistent filesystem.
`enableDebug` | `bool` | Stores debugging artifacts in a persistent filesystem.
`enableMutex` | `bool` | Replaces the default file-based mutex component with the appropriate database driver.
`enableQueue` | `bool` | Replaces the default queue component with a preconfigured SQS-backed driver.
`enableSession` | `bool` | Uses the database for storing sessions.
`enableTmpFs` | `bool` | Use the extension-provided temporary filesystem instead of Craft’s default.
`environmentId` | `string` | 
`projectId` | `string` | 
`redisUrl` | `string` | 
`region` | `string` | The app region.
`s3ClientOptions` | `array` | Additional settings to pass to the `Aws\S3\S3Client` instance when accessing storage APIs.
`sqsUrl` | `string` | With `enableQueue`, determines how Craft communicates with the underlying queue provider.
