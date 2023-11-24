<?php

namespace craft\cloud;

use Craft;
use craft\mutex\MutexTrait;
use yii\base\Exception;

class Mutex extends \yii\mutex\Mutex
{
    use MutexTrait;

    /**
     * @inheritDoc
     */
    protected function acquireLock($name, $timeout = 0): bool
    {
        $url = Module::getInstance()->getConfig()->getPreviewDomainUrl();
        if (!$url) {
            throw new Exception();
        }

        try {
            Craft::createGuzzleClient()
                ->request('HEAD', (string) $url, [
                    'headers' => [
                        HeaderEnum::MUTEX_ACQUIRE_LOCK->value => $name,
                    ],
                ]);
        } catch (\Throwable $e) {
            Craft::error('Unable to acquire mutex lock: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function releaseLock($name): bool
    {
        $url = Module::getInstance()->getConfig()->getPreviewDomainUrl();

        if (!$url) {
            throw new Exception();
        }

        try {
            Craft::createGuzzleClient()
                ->request('HEAD', (string) $url, [
                    'headers' => [
                        HeaderEnum::MUTEX_RELEASE_LOCK->value => $name,
                    ],
                ]);
        } catch (\Throwable $e) {
            Craft::error('Unable to release mutex lock: ' . $e->getMessage());

            return false;
        }

        return true;
    }
}
