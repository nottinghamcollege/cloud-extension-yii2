<?php

namespace craft\cloud;

use craft\config\BaseConfig;

/**
 * @method array s3ClientOptions(array $options)
 */
class Config extends BaseConfig
{
    public array $s3ClientOptions = [];
    public string $cdnBaseUrl = 'https://cdn.craft.cloud';
    public bool $enableCache = false;
    public bool $enableMutex = false;
    public bool $enableSession = false;
    public bool $enableQueue = false;
    public bool $enableCdn = false;
    public bool $enableDebug = false;
    public bool $allowBinaryResponses = true;

    public function init(): void
    {
        if (Module::isCraftCloud()) {
            $this->enableCache = true;
            $this->enableMutex = true;
            $this->enableSession = true;
            $this->enableQueue = true;
            $this->enableCdn = true;
            $this->enableDebug = true;
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
}
