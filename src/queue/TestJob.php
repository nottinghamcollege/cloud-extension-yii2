<?php

namespace craft\cloud\queue;

use Craft;
use craft\queue\BaseJob;
use yii\console\Exception;

class TestJob extends BaseJob
{
    public string $message = '';
    public bool $throw = false;
    public int $timeout = 0;

    public function execute($queue): void
    {
        if ($this->timeout) {
            Craft::info("Sleeping for {$this->timeout} seconds…", __METHOD__);
            sleep($this->timeout);
        }

        if ($this->throw) {
            Craft::info("Throwing exception…", __METHOD__);
            throw new Exception($this->message);
        }
    }
}
