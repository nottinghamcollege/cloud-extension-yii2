<?php

namespace craft\cloud;

use craft\base\Component;
use craft\base\imagetransforms\ImageEditorTransformerInterface;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\models\ImageTransform;

/**
 * TODO: ImageEditorTransformerInterface
 */
class ImageTransformer extends Component implements ImageTransformerInterface
{
    public const SIGNING_PARAM = 's';
    public const SUPPORTED_IMAGE_FORMATS = ['jpg', 'jpeg', 'gif', 'png', 'heic'];

    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        $assetUrl = $asset->getUrl();

        if (!$assetUrl) {
            return '';
        }

        $transformParams = $this->buildTransformParams($imageTransform);
        $path = parse_url($assetUrl, PHP_URL_PATH);
        $params = $transformParams + [
            self::SIGNING_PARAM => $this->sign($path, $transformParams),
        ];

        return UrlHelper::urlWithParams($assetUrl, $params);
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {
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
