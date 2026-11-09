<?php
namespace verbb\comments\web\assets\frontend;

use craft\web\AssetBundle;

class FrontEndCssAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/comments/resources/dist';
        $this->css = [
            'js/comments-frontend.css',
        ];

        parent::init();
    }
}
