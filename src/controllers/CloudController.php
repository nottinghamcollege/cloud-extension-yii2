<?php

namespace craft\cloud\controllers;

use Craft;
use craft\cloud\fs\Fs;
use craft\cloud\fs\StorageFs;
use craft\elements\Asset;
use craft\helpers\Assets;
use craft\web\Controller;
use yii\web\Response;

class CloudController extends Controller
{
    public function actionGetUploadUrl(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();
        $file = $this->request->getRequiredBodyParam('file');
        $filename = $file['name'];
        // $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        // $filename = sprintf('%s.%s', uniqid('upload', true), $extension);
        $folderId = $this->request->getRequiredBodyParam('folderId');
        $folder = Craft::$app->getAssets()->findFolder(['id' => $folderId]);
        $pathInVolume = "{$folder->path}$filename";

        /** @var StorageFs $fs */
        $fs = $folder->getVolume()->getFs();

        $cmd = $fs->getClient()->getCommand('PutObject', [
            'Bucket' => $fs->getBucketName(),
            'Key' => $fs->prefixPath($pathInVolume),
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

        $folder = Craft::$app->getAssets()->findFolder(['id' => $folderId]);

        $targetFs = $folder->getVolume()->getFs();
        $targetPath = $folder->path . $filename;

        /** @var Fs $fs */
        // $fs = Craft::createObject(Fs::class);
        // $fs->renameFile($tempBucketKey, $targetPath);

        $asset = new Asset();
        $asset->setFilename($filename);
        $asset->folderId = $folder->id;
        $asset->setVolumeId($folder->volumeId);
        $asset->uploaderId = Craft::$app->getUser()->getId();
        $asset->avoidFilenameConflicts = true;
        $asset->dateModified = new \DateTime();
        $result = $elementsService->saveElement($asset);

        if (!$result) {
            $errors = $asset->getFirstErrors();
            return $this->asFailure(implode("\n", $errors));
        }

        return $this->asSuccess(data: [
            'filename' => $asset->getFilename(),
            'assetId' => $asset->id,
        ]);
    }
}
