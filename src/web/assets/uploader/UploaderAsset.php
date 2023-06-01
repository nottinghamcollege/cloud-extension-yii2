<?php

namespace craft\cloud\web\assets\uploader;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class UploaderAsset extends AssetBundle
{
    /** @inheritdoc */
    public $sourcePath = __DIR__ . '/dist';

    /** @inheritdoc */
    public $js = [
        'axios.js',
    ];

    /**
     * @inheritdoc
     */
    public $depends = [
        CpAsset::class,
    ];
}
