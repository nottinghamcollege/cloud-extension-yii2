<?php

namespace craft\cloud;

use Craft;
use craft\mutex\MutexTrait;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Collection;
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

        $headers = Collection::make([
            HeaderEnum::MUTEX_ACQUIRE_LOCK->value => $name,
        ]);

        try {
            $request = new Request('HEAD', (string) $url, $headers->all());
            $context = Helper::createSigningContext($headers->keys());
            $context->signer()->sign($request);
            Craft::createGuzzleClient()->send($request);
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

        $headers = Collection::make([
            HeaderEnum::MUTEX_RELEASE_LOCK->value => $name,
        ]);

        try {
            $request = new Request('HEAD', (string) $url, $headers->all());
            $context = Helper::createSigningContext($headers->keys());
            $context->signer()->sign($request);
            Craft::createGuzzleClient()->send($request);
        } catch (\Throwable $e) {
            Craft::error('Unable to release mutex lock: ' . $e->getMessage());

            return false;
        }

        return true;
    }
}
