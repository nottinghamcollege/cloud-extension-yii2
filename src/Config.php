<?php

namespace craft\cloud;

use Craft;
use craft\config\BaseConfig;
use craft\helpers\App;

/**
 * @method array s3ClientOptions(array $options)
 */
class Config extends BaseConfig
{
    public array $s3ClientOptions = [];
    public string $cdnBaseUrl = 'https://cdn.craft.cloud';
    public ?string $sqsUrl = null;
    public ?string $projectId = null;
    public ?string $environmentId = null;
    public ?string $buildId = null;
    public ?string $accessKey = null;
    public ?string $accessSecret = null;
    public ?string $cdnSigningKey = null;
    protected ?string $region = null;
    protected bool $useAssetCdn = true;
    protected bool $useArtifactCdn = true;

    public function init(): void
    {
        if (!Helper::isCraftCloud()) {
            $this->useAssetCdn = false;
            $this->useArtifactCdn = false;
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
    }

    public function getUseAssetCdn(): bool
    {
        return App::env('CRAFT_CLOUD_USE_ASSET_CDN') ?? ($this->useAssetCdn || Helper::isCraftCloud());
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
        return App::env('CRAFT_CLOUD_USE_ARTIFACT_CDN') ?? ($this->useArtifactCdn || Helper::isCraftCloud());
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

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['projectId', 'environmentId'],
            'required',
            'when' => fn(Config $model) => $model->getUseAssetCdn() || $model->getUseArtifactCdn(),
        ];

        $rules[] = [
            'buildId',
            'required',
            'when' => fn(Config $model) => $model->getUseArtifactCdn(),
        ];

        return $rules;
    }
}
