<?php
namespace verbb\comments\assetbundles;

use verbb\comments\Comments;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class FrontEndAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init()
    {
        $settings = Comments::$plugin->getSettings();

        $this->sourcePath = "@verbb/comments/resources/dist";

        if ($settings->outputDefaultCss) {
            $this->css = [
                'css/comments.css',
            ];
        }

        if ($settings->outputDefaultJs) {
            $this->js = [
                'js/comments.js',
            ];
        }

        parent::init();
    }
}
