<?php

namespace craft\cloud;

use craft\config\BaseConfig;
use craft\helpers\StringHelper;

/**
 * @method array s3ClientOptions(array $options)
 */
class Config extends BaseConfig
{
    public array $s3ClientOptions = [];
    public string $cdnBaseUrl = 'https://cdn.craft.cloud';
    public string $redisUrl = 'tcp://localhost:6379';
    public string $sqsUrl = '';
    public ?string $environmentId = null;
    public bool $enableCache = false;
    public bool $enableMutex = false;
    public bool $enableSession = false;
    public bool $enableQueue = false;
    public bool $enableCdn = false;
    public bool $enableDebug = false;
    public bool $enableTmpFs = false;
    public bool $allowBinaryResponses = true;

    public function init(): void
    {
        if (Helper::isCraftCloud()) {
            $this->enableCache = true;
            $this->enableMutex = true;
            $this->enableSession = true;
            $this->enableQueue = true;
            $this->enableCdn = true;
            $this->enableDebug = true;
            $this->enableTmpFs = true;
            $this->allowBinaryResponses = false;
        }

        parent::init();
    }

    public function __call($name, $params)
    {
        if (property_exists($this, $name)) {
            $this->$name = $params[0];

            return $this;
        }
    }

    public function getCdnBaseUrl(): string
    {
        return StringHelper::ensureRight($this->cdnBaseUrl, '/');
    }
}
