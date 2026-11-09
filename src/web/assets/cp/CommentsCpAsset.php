<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

use verbb\base\web\assets\cp\CpAsset as VerbbCpAsset;

class CommentsCpAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = __DIR__;

        $this->depends = [
            VerbbCpAsset::class,
            CraftCpAsset::class,
        ];

        $this->css = [
            'css/comments-cp.css',
        ];

        parent::init();
    }
}
