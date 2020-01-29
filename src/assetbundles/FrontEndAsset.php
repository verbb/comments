<?php
namespace verbb\comments\assetbundles;

use verbb\comments\Comments;

use Craft;
use craft\web\AssetBundle;

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

            $this->jsOptions = ['defer' => 'defer', 'async' => 'async'];
        }

        parent::init();
    }
}
