<?php

namespace craft\cloud;

use Craft;
use craft\cloud\fs\TmpFs;
use yii\base\Event;
use yii\web\ServerErrorHttpException;

class BinaryResponse
{
    public static function beforeSend(Event $event): void
    {
        if (Helper::isCraftCloud() && $event->sender->stream) {
            static::serveBinaryFromS3();
        }
    }

    protected static function serveBinaryFromS3(): void
    {
        $response = Craft::$app->getResponse();

        /** @var TmpFs $fs */
        $fs = Craft::createObject([
            'class' => TmpFs::class,
        ]);

        $stream = $response->stream[0] ?? null;

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
            'ResponseContentDisposition' => $response->getHeaders()->get('content-disposition'),
        ]);

        // TODO: config
        $s3Request = $fs->getClient()->createPresignedRequest($cmd, '+20 minutes');
        $url = (string) $s3Request->getUri();
        $response->clear();
        $response->redirect($url);
    }
}
