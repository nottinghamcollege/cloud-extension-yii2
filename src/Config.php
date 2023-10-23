<?php

namespace craft\cloud;

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
    protected bool $useCloudFs = true;

    public function __call($name, $params)
    {
        if (property_exists($this, $name)) {
            $this->$name = $params[0];

            return $this;
        }
    }

    public function getUseCloudFs(): bool
    {
        return App::env('CRAFT_CLOUD_USE_CLOUD_FS') ?? ($this->useCloudFs || Helper::isCraftCloud());
    }

    public function setUseCloudFs(bool $value): static
    {
        $this->useCloudFs = $value;

        return $this;
    }

    /**
     * @used-by Module::getConfig()
     * Alias to match Craft convention
     */
    public function useCloudFs(bool $value): static
    {
        return $this->setUseCloudFs($value);
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
}
