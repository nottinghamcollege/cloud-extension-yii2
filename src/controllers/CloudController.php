<?php

namespace craft\cloud\controllers;

use Craft;
use craft\cloud\fs\Fs;
use craft\elements\Asset;
use craft\fields\Assets as AssetsField;
use craft\helpers\Assets;
use craft\web\Controller;
use DateTime;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class CloudController extends Controller
{
    public function actionGetUploadUrl(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();
        $originalFilename = $this->request->getRequiredBodyParam('filename');
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = sprintf('%s.%s', uniqid('upload', true), $extension);
        $folderId = $this->request->getBodyParam('folderId');
        $fieldId = $this->request->getBodyParam('fieldId');

        if (!$folderId && !$fieldId) {
            throw new BadRequestHttpException('No target destination provided for uploading');
        }

        if (!$folderId) {
            /** @var AssetsField|null $field */
            $field = Craft::$app->getFields()->getFieldById($fieldId);
            $elementId = $this->request->getBodyParam('elementId');
            $siteId = $this->request->getBodyParam('siteId');
            $element = $elementId
                ? Craft::$app->getElements()->getElementById($elementId, null, $siteId)
                : null;

            $folderId = $field->resolveDynamicPathToFolderId($element);
        }

        if (!$folderId) {
            throw new BadRequestHttpException('The target destination provided for uploading is not valid');
        }

        $folder = Craft::$app->getAssets()->findFolder(['id' => $folderId]);

        if (!$folder) {
            throw new BadRequestHttpException('The target folder provided for uploading is not valid');
        }

        $pathInVolume = "{$folder->path}$filename";

        /** @var Fs $fs */
        $fs = $folder->getVolume()->getFs();

        // TODO: add an fs upload and use that
        $cmd = $fs->getClient()->getCommand('PutObject', [
            'Bucket' => $fs->getBucketName(),
            'Key' => $fs->prefixPath($pathInVolume),
        ]);

        // TODO: use setting
        $s3Request = $fs->getClient()->createPresignedRequest($cmd, '+20 minutes');
        $url = (string) $s3Request->getUri();

        return $this->asJson([
            'url' => $url,
            'originalFilename' => $originalFilename,
            'filename' => $filename,
            'bucket' => $fs->getBucketName(),
            'key' => $fs->prefixPath($filename),
        ]);
    }


    public function actionCreateAsset(): Response
    {
        $this->requireAcceptsJson();

        $filename = $this->request->getRequiredBodyParam('filename');
        $originalFilename = $this->request->getRequiredBodyParam('originalFilename');
        $lastModified = $this->request->getBodyParam('lastModified');
        $elementsService = Craft::$app->getElements();

        if (!$filename) {
            throw new BadRequestHttpException('No file was uploaded');
        }

        $folderId = (int)$this->request->getBodyParam('folderId') ?: null;
        $fieldId = (int)$this->request->getBodyParam('fieldId') ?: null;

        if (!$folderId && !$fieldId) {
            throw new BadRequestHttpException('No target destination provided for uploading');
        }

        $assets = Craft::$app->getAssets();
        $selectionCondition = null;

        if (empty($folderId)) {
            /** @var AssetsField|null $field */
            $field = Craft::$app->getFields()->getFieldById((int)$fieldId);

            if (!$field instanceof AssetsField) {
                throw new BadRequestHttpException('The field provided is not an Assets field');
            }

            if ($elementId = $this->request->getBodyParam('elementId')) {
                $siteId = $this->request->getBodyParam('siteId') ?: null;
                $element = $elementsService->getElementById($elementId, null, $siteId);
            } else {
                $element = null;
            }
            $folderId = $field->resolveDynamicPathToFolderId($element);

            $selectionCondition = $field->getSelectionCondition();
            if ($selectionCondition instanceof ElementCondition) {
                $selectionCondition->referenceElement = $element;
            }
        }

        if (empty($folderId)) {
            throw new BadRequestHttpException('The target destination provided for uploading is not valid');
        }

        $folder = $assets->findFolder(['id' => $folderId]);

        if (!$folder) {
            throw new BadRequestHttpException('The target folder provided for uploading is not valid');
        }

        // Check the permissions to upload in the resolved folder.
        // $this->requireVolumePermissionByFolder('saveAssets', $folder);

        // $normalizedFilename = Assets::prepareAssetName($filename);

        // if ($selectionCondition) {
        //     $tempFolder = Craft::$app->getAssets()->getUserTemporaryUploadFolder();
        //     if ($folder->id !== $tempFolder->id) {
        //         // upload to the user's temp folder initially, with a temp name
        //         $originalFolder = $folder;
        //         $originalFilename = $filename;
        //         $folder = $tempFolder;
        //         $filename = uniqid('asset', true) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        //     }
        // }

        $asset = new Asset();
        $asset->newFilename = Assets::prepareAssetName($originalFilename);
        $asset->setFilename($filename);
        $asset->folderId = $folder->id;
        $asset->setVolumeId($folder->volumeId);
        $asset->uploaderId = Craft::$app->getUser()->getId();
        $asset->avoidFilenameConflicts = true;
        $asset->dateModified = $lastModified ? new DateTime('@' . $lastModified) : null;

        // kind, size, width, height?

        if (isset($originalFilename)) {
            $asset->title = Assets::filename2Title(pathinfo($originalFilename, PATHINFO_FILENAME));
        }

        $result = $elementsService->saveElement($asset);

        // In case of error, let user know about it.
        if (!$result) {
            $errors = $asset->getFirstErrors();
            return $this->asFailure(implode("\n", $errors));
        }

        // if ($selectionCondition) {
        //     if (!$selectionCondition->matchElement($asset)) {
        //         // delete and reject it
        //         $elementsService->deleteElement($asset, true);
        //         return $this->asFailure(Craft::t('app', '{filename} isn’t selectable for this field.', [
        //             'filename' => $uploadedFile->name,
        //         ]));
        //     }
        //
        //     if (isset($originalFilename, $originalFolder)) {
        //         // move it into the original target destination
        //         $asset->newFilename = $originalFilename;
        //         $asset->newFolderId = $originalFolder->id;
        //         $asset->setScenario(Asset::SCENARIO_MOVE);
        //
        //         if (!$elementsService->saveElement($asset)) {
        //             $errors = $asset->getFirstErrors();
        //             return $this->asJson([
        //                 'error' => $this->asFailure(implode("\n", $errors)),
        //             ]);
        //         }
        //     }
        // }

        if ($asset->conflictingFilename !== null) {
            $conflictingAsset = Asset::findOne(['folderId' => $folder->id, 'filename' => $asset->conflictingFilename]);

            return $this->asJson([
                'conflict' => Craft::t('app', 'A file with the name “{filename}” already exists.', ['filename' => $asset->conflictingFilename]),
                'assetId' => $asset->id,
                'filename' => $asset->conflictingFilename,
                'conflictingAssetId' => $conflictingAsset->id ?? null,
                'suggestedFilename' => $asset->suggestedFilename,
                'conflictingAssetUrl' => ($conflictingAsset && $conflictingAsset->getVolume()->getFs()->hasUrls) ? $conflictingAsset->getUrl() : null,
            ]);
        }

        return $this->asSuccess(data: [
            'filename' => $asset->getFilename(),
            'assetId' => $asset->id,
        ]);
    }
}
