<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;

/**
 * The comment editor's Plugin Kit components, built by `vite.cp.config.ts`. Separate from
 * CommentEditAsset because these are ES modules and its script isn't.
 */
class CommentEditKitAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->js = [
            'comment-edit.js',
        ];

        $this->jsOptions = [
            'type' => 'module',
        ];

        $this->css = [
            'plugin-kit.css',
        ];

        parent::init();
    }
}
