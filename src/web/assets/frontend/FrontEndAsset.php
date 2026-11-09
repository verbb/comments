<?php
namespace verbb\comments\web\assets\frontend;

use craft\web\AssetBundle;

class FrontEndAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/comments/resources/dist';
        $this->js = [
            'js/comments-frontend.js',
        ];
        $this->jsOptions = [
            'type' => 'module',
            'defer' => true,
        ];

        parent::init();
    }
}
