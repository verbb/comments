<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * The Commenters screens: Plugin Kit's DataTable for the list, and the review setting form.
 */
class CommentersAsset extends AssetBundle
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
            'commenters.js',
        ];

        $this->jsOptions = [
            'type' => 'module',
        ];

        $this->css = [
            'plugin-kit.css',
            'commenters.css',
        ];

        parent::init();
    }
}
