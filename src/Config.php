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
    public ?string $region = null;
    public ?string $cdnSigningKey = null;
    public bool $useCloudFs = true;

    public function __call($name, $params)
    {
        if (property_exists($this, $name)) {
            $this->$name = $params[0];

            return $this;
        }
    }

    public function getUseCloudFs(): bool
    {
        return $this->useCloudFs || Helper::isCraftCloud();
    }

    public function getRegion(): ?string
    {
        return $this->region ?? App::env('AWS_REGION');
    }
}
