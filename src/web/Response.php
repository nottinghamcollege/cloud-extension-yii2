<?php

namespace craft\cloud\web;

use Craft;
use craft\cloud\fs\TmpFs;
use craft\cloud\Helper;
use yii\web\ServerErrorHttpException;

class Response extends \craft\web\Response
{
    /**
     * @inheritDoc
     */
    public function send(): void
    {
        if (
            $this->stream &&
            Craft::$app->getRequest()->getIsCpRequest() &&
            Helper::isCraftCloud()
        ) {
            $this->serveBinaryFromS3();
        }

        parent::send();
    }

    protected function serveBinaryFromS3(): void
    {
        /** @var TmpFs $fs */
        $fs = Craft::createObject([
            'class' => TmpFs::class,
        ]);
        $stream = $this->stream[0] ?? null;

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
            'ResponseContentDisposition' => $this->getHeaders()->get('content-disposition'),
        ]);

        // TODO: config
        $s3Request = $fs->getClient()->createPresignedRequest($cmd, '+20 minutes');
        $url = (string) $s3Request->getUri();
        $this->clear();
        $this->redirect($url);
    }
}
