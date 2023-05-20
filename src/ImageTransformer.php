<?php

namespace craft\cloud;

use craft\base\Component;
use craft\base\imagetransforms\EagerImageTransformerInterface;
use craft\base\imagetransforms\ImageEditorTransformerInterface;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\models\ImageTransform;

class ImageTransformer extends Component implements ImageTransformerInterface, ImageEditorTransformerInterface
{
    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        $assetUrl = $asset->getUrl();

        if (!$assetUrl) {
            return '';
        }

        $transformParams = $this->buildTransformParams($imageTransform);
        $path = parse_url($assetUrl, PHP_URL_PATH);
        $params = $transformParams + [
            's' => $this->sign($path, $transformParams),
        ];

        return UrlHelper::urlWithParams($assetUrl, $params);
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {

    }

    public function startImageEditing(Asset $asset): void
    {
        // TODO: Implement startImageEditing() method.
    }

    public function flipImage(bool $flipX, bool $flipY): void
    {
        // TODO: Implement flipImage() method.
    }

    public function scaleImage(int $width, int $height): void
    {
        // TODO: Implement scaleImage() method.
    }

    public function rotateImage(float $degrees): void
    {
        // TODO: Implement rotateImage() method.
    }

    public function getEditedImageWidth(): int
    {
        // TODO: Implement getEditedImageWidth() method.
    }

    public function getEditedImageHeight(): int
    {
        // TODO: Implement getEditedImageHeight() method.
    }

    public function crop(int $x, int $y, int $width, int $height): void
    {
        // TODO: Implement crop() method.
    }

    public function finishImageEditing(): string
    {
        // TODO: Implement finishImageEditing() method.
    }

    public function cancelImageEditing(): string
    {
        // TODO: Implement cancelImageEditing() method.
    }

    public function buildTransformParams(ImageTransform $imageTransform): array
    {
        //        'anim',
//        'background',
//        'blur',
//        'border',
//        'brightness',
//        'compression',
//        'contrast',
//        'dpr',
//        'fit',
//        'format',
//        'gamma',
//        'gravity',
//        'height',
//        'metadata',
//        'onerror',
//        'quality',
//        'rotate',
//        'sharpen',
//        'trim',
//        'width',

        return [
            'width' => $imageTransform->width,
            'height' => $imageTransform->height,
            'quality' => $imageTransform->quality,
            'format' => $imageTransform->format,
        ];
    }

    public function sign(string $path, $params): string
    {
        $paramString = http_build_query($params);
        $data = "$path#?$paramString";

        return hash_hmac(
            'sha256',
            $data,
            App::env('CRAFT_CLOUD_ASSET_SIGNING_KEY')
        );
    }
}
