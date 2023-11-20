<?php

namespace craft\cloud;

use Craft;
use craft\mutex\MutexTrait;
use GuzzleHttp\Exception\RequestException;
use yii\base\Exception;

class Mutex extends \yii\mutex\Mutex
{
    use MutexTrait;

    /**
     * @inheritDoc
     */
    protected function acquireLock($name, $timeout = 0): bool
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $url = Module::getInstance()->getConfig()->getPreviewDomainUrl();

            if (!$url) {
                throw new Exception();
            }

            try {
                Craft::createGuzzleClient()
                    ->request('HEAD', $url, [
                        'headers' => [
                            HeaderEnum::MUTEX_ACQUIRE_LOCK->value => $name,
                        ],
                    ]);
            } catch (RequestException $e) {
                Craft::error('Unable to acquire mutex lock: ' . $e->getMessage());

                return false;
            }

            return true;
        }

        Craft::$app->getResponse()->getHeaders()->add(
            HeaderEnum::MUTEX_ACQUIRE_LOCK->value, $name,
        );

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function releaseLock($name): bool
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $url = Module::getInstance()->getConfig()->getPreviewDomainUrl();

            if (!$url) {
                throw new Exception();
            }

            try {
                Craft::createGuzzleClient()
                    ->request('HEAD', $url, [
                        'headers' => [
                            HeaderEnum::MUTEX_RELEASE_LOCK->value => $name,
                        ],
                    ]);
            } catch (RequestException $e) {
                Craft::error('Unable to release mutex lock: ' . $e->getMessage());

                return false;
            }
        }

        Craft::$app->getResponse()->getHeaders()->add(
            HeaderEnum::MUTEX_RELEASE_LOCK->value, $name,
        );

        return true;
    }
}
