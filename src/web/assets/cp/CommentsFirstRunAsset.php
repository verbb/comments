<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * The Comments page before the first comment: Plugin Kit's page empty state, with the tag to copy.
 */
class CommentsFirstRunAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
            CommentsCpAsset::class,
        ];

        $this->js = [
            'comments-first-run.js',
        ];

        $this->jsOptions = [
            'type' => 'module',
        ];

        $this->css = [
            'plugin-kit.css',
            'comments-first-run.css',
        ];

        parent::init();
    }
}
