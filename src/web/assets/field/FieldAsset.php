<?php

namespace nineteenninetyfour\socialimages\web\assets\field;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * The Social image field's preview: shows the entry's image, waits for one
 * being made, and remakes it on request.
 */
class FieldAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__.'/dist';
        $this->depends = [CpAsset::class];
        $this->js = ['field.js'];
        $this->css = ['field.css'];

        parent::init();
    }
}
