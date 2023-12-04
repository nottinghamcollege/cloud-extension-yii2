<a href="https://craftcms.com/cloud" rel="noopener" target="_blank" title="Craft Cloud"><img src="https://raw.githubusercontent.com/craftcms/.github/v3/profile/product-icons/craft-cloud.svg" alt="Craft Cloud icon" width="65"></a>

# Craft Cloud Extension

Welcome to [**Craft Cloud**](https://craftcms.com/cloud)!

This repository contains source code for the `craftcms/cloud` Composer package, which is required to run a Craft project on our first-party hosting platform, Craft Cloud.

When installed, the extension automatically [bootstraps](https://www.yiiframework.com/doc/guide/2.0/en/runtime-bootstrapping) itself and makes necessary [application configuration](https://craftcms.com/docs/4.x/config/app.html) changes for the detected environment:

- :cloud_with_lightning: **Cloud:** There’s no infrastructure settings to worry about—database, queue, cache, and session configuration is handled for you.
- :computer: **Local development:** Craft runs normally, in your favorite [development environment](https://craftcms.com/docs/4.x/installation.html).

:sparkles: To learn more about Cloud, check out [our website](https://craftcms.com/cloud)—or dive right in with [Craft Console](https://console.craftcms.com/cloud). Interested in everything the extension does to get your app ready for Cloud? Read our [Cloud module deep-dive](https://craftcms.com/knowledge-base/cloud-extension), in the knowledge base.

## Installation

The Cloud module can be installed in any existing Craft 4.5+ project by running `php craft setup/cloud`. Craft will install the [`craftcms/cloud` package](https://packagist.org/craftcms/cloud) and run the module’s own setup wizard.

When you deploy a project to Cloud, the `cloud/up` command will run, wrapping Craft’s built-in [`up` command](https://craftcms.com/docs/4.x/console-commands.html#up) and adding the cache and session tables (if they’re not already present).

## Filesystem

When setting up your project’s assets, use the provided **Craft Cloud** filesystem type. Read more about [managing assets in Cloud projects](https://craftcms.com/knowledge-base/cloud-assets).

## Developer Features

### Template Helpers

The extension provides two new [Twig functions](https://craftcms.com/docs/4.x/dev/functions.html) and one global variable:

#### `artifactUrl()`

Generates a URL to a resource that was uploaded to the CDN during the build and deployment process.

#### `isCraftCloud`

`true` when the app detects it is running on Cloud infrastructure, `false` otherwise.

### Aliases

The following aliases are available, in addition to [those provided by Craft](https://craftcms.com/docs/4.x/config/#aliases).

#### `@web`

We override the `@web` alias to guarantee that the correct environment URL is used in all HTTP contexts.

#### `@artifactBaseUrl`

Equivalent to [`artifactsUrl()`](#artifactsUrl), this allows [Project Config](https://craftcms.com/docs/4.x/project-config.html) settings to take advantage of dynamically-determined CDN URLs.

## Configuration

Most configuration (to Craft and the extension itself) is handled directly by Cloud infrastructure, through [environment overrides](https://craftcms.com/docs/4.x/config/#environment-overrides). These options are provided strictly for reference, and have limited utility outside the platform.

| Option            | Type      | Description                                                                                 |
| ----------------- | --------- | ------------------------------------------------------------------------------------------- |
| `accessKey`       | `string`  | AWS access key, used for communicating with storage APIs.                                   |
| `accessSecret`    | `string`  | AWS access secret, used in conjunction with the `accessKey`.                                |
| `cdnBaseUrl`      | `string`  | Used when building URLs to [assets](#filesystem) and other build [artifacts](#artifacturl). |
| `signingKey`   | `string`  | A secret value used to protect transform URLs against abuse.                                |
| `useAssetCdn`     | `boolean` | Whether or not to enable the CDN for uploaded assets.                                       |
| `useArtifactCdn`  | `boolean` | Whether or not to enable the CDN for build artifacts and asset bundles.                     |
| `environmentId`   | `string`  | UUID of the current environment.                                                            |
| `projectId`       | `string`  | UUID of the current project.                                                                |
| `region`          | `string`  | The app region, chosen when creating the project.                                           |
| `s3ClientOptions` | `array`   | Additional settings to pass to the `Aws\S3\S3Client` instance when accessing storage APIs.  |
| `sqsUrl`          | `string`  | Determines how Craft communicates with the underlying queue provider.                       |

These options can also be set via environment overrides beginning with `CRAFT_CLOUD_`.
