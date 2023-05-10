<?php

namespace craft\cloud;

use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\models\ImageTransform;

class ImageTransformer implements ImageTransformerInterface
{
    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        return $asset->getUrl();
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {

    }
}
