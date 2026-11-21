<?php
namespace verbb\comments\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

/**
 * Opens a moderator's reply in Craft's slideout, from a comment's page, its slideout or an entry's
 * Comments panel. The form itself, and Editor with it, loads with the slideout.
 */
class CommentReplyAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = __DIR__;

        $this->depends = [
            CraftCpAsset::class,
        ];

        $this->js = [
            'js/comments-reply.js',
        ];

        parent::init();
    }
}
