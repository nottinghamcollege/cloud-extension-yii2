<a href="https://craftcms.com/cloud" rel="noopener" target="_blank" title="Craft Cloud"><img src="https://raw.githubusercontent.com/craftcms/.github/v3/profile/product-icons/craft-cloud.svg" alt="Craft Cloud icon" width="65"></a>

# Craft Cloud Extension

Welcome to [**Craft Cloud**](https://craftcms.com/cloud)!

This repository contains source code for the `craftcms/cloud` Composer package, which is required to run a Craft project on our first-party hosting platform, Craft Cloud.

When installed, the extension automatically [bootstraps](https://www.yiiframework.com/doc/guide/2.0/en/runtime-bootstrapping) itself and makes necessary [application configuration](https://craftcms.com/docs/4.x/config/app.html) changes for the detected environment:

- :cloud_with_lightning: **Cloud:** There’s no infrastructure settings to worry about—database, queue, cache, and session configuration is handled for you.
- :computer: **Local development:** Craft runs normally, in your favorite [development environment](https://craftcms.com/docs/4.x/installation.html).

:sparkles: To learn more about Cloud, check out [our website](https://craftcms.com/cloud)—or dive right in with [Craft Console](https://console.craftcms.com/cloud). Interested in everything the extension does to get your app ready for Cloud? Read our [Cloud extension deep-dive](https://craftcms.com/knowledge-base/cloud-extension), in the knowledge base.

## Installation

The Cloud extension can be installed in any existing Craft 4.6+ project by running `php craft setup/cloud`. Craft will add the `craftcms/cloud` package and run the extension’s own setup wizard.

> [!TIP]
> This process includes the creation of a [`craft-cloud.yaml` configuration file](https://craftcms.com/knowledge-base/cloud-config) which helps Cloud understand your project’s structure and determines which versions of PHP and Node your project will use during builds and at runtime.

When you [deploy](https://craftcms.com/knowledge-base/cloud-deployment) a project to Cloud, the `cloud/up` command will run, wrapping Craft’s built-in [`up` command](https://craftcms.com/docs/4.x/console-commands.html#up) and adding the cache and session tables (if they’re not already present).

## Filesystem

When setting up your project’s assets, use the provided **Craft Cloud** filesystem type. Read more about [managing assets in Cloud projects](https://craftcms.com/knowledge-base/cloud-assets).

## Developer Features

### Template Helpers

#### `cloud.artifactUrl()`

Generates a URL to a resource that was uploaded to the CDN during the build and deployment process.

```twig
{# Output a script tag with a build-specific URL: #}
<script src="{{ cloud.artifactUrl('dist/js/app.js') }}"></script>

{# You can also use the extension-provided alias: #}
{% js '@artifactBaseUrl/dist/js/app.js' %}
```

Read more about [how to use artifact URLs](https://craftcms.com/knowledge-base/cloud-builds#artifact-uRLs).

#### `cloud.isCraftCloud`

`true` when the app detects it is running on Cloud infrastructure, `false` otherwise.

```twig
{% if cloud.isCraftCloud %}
  Welcome to Cloud!
{% endif %}
```

### Aliases

The following aliases are available, in addition to [those provided by Craft](https://craftcms.com/docs/4.x/config/#aliases).

#### `@web`

On Cloud, the `@web` alias is guaranteed to be the correct environment URL for each HTTP context, whether that be a [preview domain](https://craftcms.com/knowledge-base/cloud-domains#preview-domains) or [custom domain](https://craftcms.com/knowledge-base/cloud-domains#adding-a-domain).

#### `@artifactBaseUrl`

Equivalent to [`cloud.artifactUrl()`](#artifactUrl), this allows [Project Config](https://craftcms.com/docs/4.x/project-config.html) settings to take advantage of dynamic, build-specific CDN URLs.

## Configuration

Most configuration (to Craft and the extension itself) is handled directly by Cloud infrastructure, through [environment overrides](https://craftcms.com/docs/4.x/config/#environment-overrides). These options are provided strictly for reference, and have limited utility outside the platform.

| Option | Type | Description |
| --- | --- | --- |
| `artifactBaseUrl` | `string\|null` | Directly set a fully-qualified URL to build artifacts. |
| `s3ClientOptions` | `array` | Additional settings to pass to the `Aws\S3\S3Client` instance when accessing storage APIs. |
| `cdnBaseUrl` | `string` | Used when building URLs to [assets](#filesystem) and other build [artifacts](#artifacturl). |
| `sqsUrl` | `string` | Determines how Craft communicates with the underlying queue provider. |
| `projectId` | `string` | UUID of the current project. |
| `environmentId` | `string` | UUID of the current [environment](https://craftcms.com/knowledge-base/cloud-environments). |
| `buildId` | `string` | UUID of the current [build](https://craftcms.com/knowledge-base/cloud-builds). |
| `accessKey` | `string` | AWS access key, used for communicating with storage APIs. |
| `accessSecret` | `string` | AWS access secret, used in conjunction with the `accessKey`. |
| `accessToken` | `string` | AWS access token. |
| `redisUrl` | `string` | Connection string for the environment’s Redis instance. |
| `signingKey` | `string` | A secret value used to protect transform URLs against abuse. |
| `useAssetBundleCdn` | `boolean` | Whether or not to enable the CDN for asset bundles. |
| `previewDomain` | `string\|null` | Set when accessing an environment from its [preview domain](https://craftcms.com/knowledge-base/cloud-domains#preview-domains). |
| `useQueue` | `boolean` | Whether or not to use Cloud’s SQS-backed queue driver. |
| `region` | `string` | The app region, chosen when creating the project. |
| `useAssetCdn` | `boolean` | Whether or not to enable the CDN for uploaded assets. |
| `useArtifactCdn` | `boolean` | Whether or not to enable the CDN for build artifacts and asset bundles. |

These options can also be set via environment overrides beginning with `CRAFT_CLOUD_`.
