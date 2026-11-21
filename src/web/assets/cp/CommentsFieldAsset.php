<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

class CommentsFieldAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = __DIR__;

        // The shared avatar styles live in the plugin's CP stylesheet.
        $this->depends = [
            CraftCpAsset::class,
            CommentsCpAsset::class,
            CommentReplyAsset::class,
        ];

        $this->css = [
            'css/comments-field.css',
        ];

        $this->js = [
            'js/comments-field.js',
        ];

        parent::init();
    }
}
