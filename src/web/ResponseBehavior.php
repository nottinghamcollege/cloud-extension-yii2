<?php

namespace craft\cloud\web;

use Craft;
use craft\cloud\fs\TmpFs;
use craft\cloud\HeaderEnum;
use craft\web\Response;
use Illuminate\Support\Collection;
use yii\base\Behavior;
use yii\base\Event;
use yii\web\Response as YiiResponse;
use yii\web\ServerErrorHttpException;

/**
 * @property Response $owner
 */
class ResponseBehavior extends Behavior
{
    protected array $csvHeaders = [];

    public function init(): void
    {
        $this->csvHeaders = [
            HeaderEnum::CACHE_TAG->value,
            HeaderEnum::CACHE_TAG_PURGE->value,
        ];
    }

    public function events(): array
    {
        return [
            YiiResponse::EVENT_AFTER_PREPARE => [$this, 'afterPrepare'],
        ];
    }

    public function afterPrepare(Event $event): void
    {
        foreach ($this->csvHeaders as $name) {
            $this->joinHeaderValues($name, ', ');
        }

        if ($event->sender->stream) {
            $this->serveBinaryFromS3();
        }
    }

    /**
     * @throws ServerErrorHttpException
     */
    protected function serveBinaryFromS3(): void
    {
        /** @var TmpFs $fs */
        $fs = Craft::createObject([
            'class' => TmpFs::class,
        ]);

        $stream = $this->owner->stream[0] ?? null;

        if (!$stream) {
            throw new ServerErrorHttpException('Invalid stream in response.');
        }

        $path = uniqid('binary', true);

        // TODO: set expiry
        $fs->writeFileFromStream($path, $stream);

        // TODO: use \League\Flysystem\AwsS3V3\AwsS3V3Adapter::temporaryUrl?
        $cmd = $fs->getClient()->getCommand('GetObject', [
            'Bucket' => $fs->getBucketName(),
            'Key' => $fs->prefixPath($path),
            'ResponseContentDisposition' => $this->owner->getHeaders()->get('content-disposition'),
        ]);

        // TODO: config
        $s3Request = $fs->getClient()->createPresignedRequest($cmd, '+20 minutes');
        $url = (string) $s3Request->getUri();
        $this->owner->clear();
        $this->owner->redirect($url);
    }

    protected function joinHeaderValues($name, string $glue): ?string
    {
        $headers = $this->owner->getHeaders();

        $value = Collection::make($headers->get($name, null, false))
            ->filter()
            ->join($glue);

        if (!$value) {
            return null;
        }

        $headers->set($name, $value);

        return $value;
    }
}
