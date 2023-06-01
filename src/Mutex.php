<?php

namespace craft\cloud;

use Yii;
use yii\mutex\RetryAcquireTrait;

class Mutex extends \yii\redis\Mutex
{
    use RetryAcquireTrait;

    protected array $_lockValues = [];

    protected function acquireLock($name, $timeout = 0): bool
    {
        $key = $this->calculateKey($name);
        $value = Yii::$app->security->generateRandomString(20);

        $result = $this->retryAcquire($timeout, function() use ($key, $value) {
            return $this->redis->executeCommand('SET', [$key, $value, 'NX', 'PX', (int) ($this->expire * 1000)]);
        });

        if ($result) {
            $this->_lockValues[$name] = $value;
        }
        return $result;
    }

    /**
     * https://redis.io/docs/manual/patterns/distributed-locks/
     */
    protected function releaseLock($name): bool
    {
        if (
            !isset($this->_lockValues[$name]) ||
            !$this->redis->executeCommand('DEL', [$this->calculateKey($name)])
        ) {
            return false;
        }

        unset($this->_lockValues[$name]);
        return true;
    }
}
