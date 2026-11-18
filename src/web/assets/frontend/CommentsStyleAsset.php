<?php
namespace verbb\comments\web\assets\frontend;

use verbb\comments\Comments;

use craft\web\AssetBundle;

/**
 * The thread's stylesheet: the base and the theme for Light, Dark and Auto, or only the base for
 * theme None. With the CSS Layer setting on, it's the copy wrapped in `@layer comments`.
 */
class CommentsStyleAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/comments/web/assets/frontend/dist';

        // Craft Cloud publishes assets before plugins are loaded.
        $settings = Comments::$plugin?->getSettings();
        $file = $settings?->resolveTheme() === 'none' ? 'comments-base' : 'comments';
        $this->css = [
            'css/' . $file . ($settings?->useCssLayers ? '.layered' : '') . '.css',
        ];

        parent::init();
    }
}
