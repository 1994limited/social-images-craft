<?php

namespace nineteenninetyfour\socialimages\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

/**
 * The control panel's pages (built from resources/js with `npm run build`).
 */
class CpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__.'/dist';
        $this->depends = [CraftCpAsset::class];
        $this->js = ['cp.js'];
        $this->css = ['cp.css'];

        parent::init();
    }
}
