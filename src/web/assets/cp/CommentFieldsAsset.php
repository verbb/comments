<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * The Comment Fields settings: previews of the site's comment form and of a posted comment, drawn by
 * Comments' own templates, beside the switches that choose which fields the form asks for and where
 * their answers show.
 */
class CommentFieldsAsset extends AssetBundle
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
            'comment-fields.js',
        ];

        $this->jsOptions = [
            'type' => 'module',
        ];

        $this->css = [
            'comment-fields.css',
        ];

        parent::init();
    }
}
