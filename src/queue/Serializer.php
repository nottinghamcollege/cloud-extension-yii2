<?php

namespace craft\cloud\queue;

use craft\cloud\Module;
use yii\helpers\Json;
use yii\queue\serializers\JsonSerializer;

class Serializer extends JsonSerializer
{
    public function serialize($job): string
    {
        $jobArray = $this->toArray($job);
        $jobArray['environmentId'] = Module::getInstance()->getConfig()->environmentId;

        return Json::encode($jobArray, $this->options);
    }
}
