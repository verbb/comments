<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;

use verbb\editor\web\assets\editor\EditorAsset;

class CommentEditAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = __DIR__;

        $this->depends = [
            CommentsCpAsset::class,
            CommentEditKitAsset::class,
            EditorAsset::class,
        ];

        $this->css = [
            'css/comments-edit.css',
        ];

        $this->js = [
            'js/comments-edit.js',
        ];

        parent::init();
    }
}
