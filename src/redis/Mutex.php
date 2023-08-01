<?php

namespace craft\cloud\redis;

use Craft;
use Yii;
use yii\mutex\RetryAcquireTrait;
use craft\cloud\Module;

class Mutex extends \yii\redis\Mutex
{
    use RetryAcquireTrait;

    public const EXPIRE_WEB = 30;
    public const EXPIRE_CONSOLE = 900;

    protected array $_lockValues = [];

    public function __construct($config = [])
    {
        $config += [
            'redis' => [
                'class' => Connection::class,
                'database' => 2,
            ],
            'keyPrefix' => sprintf('mutex.%s.', Module::getInstance()->getConfig()->environmentId),
            'expire' => Craft::$app->getRequest()->getIsConsoleRequest()
                ? self::EXPIRE_CONSOLE
                : self::EXPIRE_WEB,
        ];

        parent::__construct($config);
    }

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
