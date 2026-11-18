<?php
namespace verbb\comments\web\assets\frontend;

use craft\web\AssetBundle;

/**
 * The comments script built from `@verbb/comments-browser`, which draws every thread on the page.
 * `craft.comments.render()` registers it unless **Load Bundled JavaScript** is off.
 */
class CommentsScriptAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/comments/web/assets/frontend/dist';
        $this->js = [
            'js/comments.js',
        ];
        $this->jsOptions = [
            'type' => 'module',
        ];

        parent::init();
    }
}
