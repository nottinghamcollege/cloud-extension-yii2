<?php

namespace craft\cloud\controllers;

use Craft;
use craft\cloud\fs\StorageFs;
use craft\web\Controller;
use yii\web\Response;

class CloudController extends Controller
{
    public function actionGetUploadUrl(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();
        $file = $this->request->getRequiredBodyParam('file');
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = sprintf('%s.%s', uniqid('upload', true), $extension);

        /** @var StorageFs $tmpFs */
        $fs = Craft::createObject([
            'class' => StorageFs::class,
            'subfolder' => 'tmp',
        ]);

        $cmd = $fs->getClient()->getCommand('PutObject', [
            'Bucket' => $fs->getBucketName(),
            'Key' => $fs->prefixPath($filename),
        ]);

        // TODO: use setting
        $s3Request = $fs->getClient()->createPresignedRequest($cmd, '+20 minutes');
        $url = (string) $s3Request->getUri();

        return $this->asJson([
            'url' => $url,
            'file' => $file,
            'bucket' => $fs->getBucketName(),
            'key' => $fs->prefixPath($filename),
            'filename' => $filename,
        ]);
    }


    public function actionCreateAsset(): Response
    {
        $this->requireAcceptsJson();

        $elementsService = Craft::$app->getElements();
        $tempBucket = $this->request->getBodyParam('bucket') ?: null;
        $tempBucketKey = $this->request->getBodyParam('key') ?: null;
        $filename = $this->request->getBodyParam('filename') ?: null;
        $folderId = (int)$this->request->getBodyParam('folderId') ?: null;
        $fieldId = (int)$this->request->getBodyParam('fieldId') ?: null;
    }
}

