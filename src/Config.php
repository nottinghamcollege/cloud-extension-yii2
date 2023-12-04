<?php

namespace craft\cloud;

use Craft;
use craft\config\BaseConfig;
use craft\helpers\App;
use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;

/**
 * @method array s3ClientOptions(array $options)
 */
class Config extends BaseConfig
{
    public ?string $artifactBaseUrl = null;
    public array $s3ClientOptions = [];
    public string $cdnBaseUrl = 'https://cdn.craft.cloud';
    public ?string $sqsUrl = null;
    public ?string $projectId = null;
    public ?string $environmentId = null;
    public ?string $buildId = 'current';
    public ?string $accessKey = null;
    public ?string $accessSecret = null;
    public ?string $signingKey = null;
    public bool $useAssetBundleCdn = true;
    public ?string $previewDomain = null;
    public bool $useMutex = true;
    public bool $useQueue = true;
    protected ?string $region = null;
    protected bool $useAssetCdn = true;
    protected bool $useArtifactCdn = true;

    public function init(): void
    {
        if (!Helper::isCraftCloud()) {
            $this->useAssetCdn = false;
            $this->useArtifactCdn = false;
            $this->useAssetBundleCdn = false;
            $this->useMutex = false;
            $this->useQueue = false;
        }
    }

    public function attributeLabels(): array
    {
        return [
            'projectId' => Craft::t('app', 'Project ID'),
            'environmentId' => Craft::t('app', 'Environment ID'),
            'buildId' => Craft::t('app', 'Build ID'),
        ];
    }

    public function __call($name, $params)
    {
        if (property_exists($this, $name)) {
            $this->$name = $params[0];

            return $this;
        }

        return parent::__call($name, $params);
    }

    public function getUseAssetCdn(): bool
    {
        return App::env('CRAFT_CLOUD_USE_ASSET_CDN') ?? $this->useAssetCdn;
    }

    public function setUseAssetCdn(bool $value): static
    {
        $this->useAssetCdn = $value;

        return $this;
    }

    /**
     * @used-by Module::getConfig()
     * Alias to match Craft convention
     */
    public function useAssetCdn(bool $value): static
    {
        return $this->setUseAssetCdn($value);
    }

    public function getUseArtifactCdn(): bool
    {
        return App::env('CRAFT_CLOUD_USE_ARTIFACT_CDN') ?? $this->useArtifactCdn;
    }

    public function setUseArtifactCdn(bool $value): static
    {
        $this->useArtifactCdn = $value;

        return $this;
    }

    /**
     * @used-by Module::getConfig()
     * Alias to match Craft convention
     */
    public function useArtifactCdn(bool $value): static
    {
        return $this->setUseArtifactCdn($value);
    }

    public function getRegion(): ?string
    {
        return App::env('CRAFT_CLOUD_REGION') ?? $this->region ?? App::env('AWS_REGION');
    }

    public function setRegion(?string $value): static
    {
        $this->region = $value;

        return $this;
    }

    /**
     * @used-by Module::getConfig()
     * Alias to match Craft convention
     */
    public function region(?string $value): static
    {
        return $this->setRegion($value);
    }

    public function getShortEnvironmentId(): ?string
    {
        return $this->environmentId
            ? substr($this->environmentId, 0, 8)
            : null;
    }

    public function getPreviewDomainUrl(): ?UriInterface
    {
        if (!$this->previewDomain) {
            return null;
        }

        return (Uri::new())
            ->withHost($this->previewDomain)
            ->withScheme('https');
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['environmentId', 'projectId'],
            'required',
            'when' => fn(Config $model) => $model->getUseAssetCdn(),
        ];

        $rules[] = [
            ['environmentId', 'buildId'],
            'required',
            'when' => fn(Config $model) => $model->getUseArtifactCdn(),
        ];

        return $rules;
    }
}
