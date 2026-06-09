<?php
namespace verbb\comments\assetbundles;

use verbb\comments\Comments;

use Craft;
use craft\web\AssetBundle;

class FrontEndAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $settings = Comments::$plugin->getSettings();

        $css = 'css/comments.css';
        $js = 'js/comments.js';

        if ($baseUrl = $settings->getAssetBaseUrl()) {
            // Reference the (pre-deployed) files from a fixed base URL - e.g. a CDN/file server.
            // No publishing to cpresources, which is ideal for multi-server setups behind a CDN.
            $this->baseUrl = rtrim($baseUrl, '/');

            // Nothing local is published, so we handle cache-busting ourselves: a timestamp while
            // developing, otherwise the installed plugin version.
            $version = Craft::$app->getConfig()->getGeneral()->devMode ? time() : Comments::$plugin->getVersion();
            $css .= '?v=' . $version;
            $js .= '?v=' . $version;
        } else {
            // Default: publish the bundled dist to cpresources at runtime (Craft handles busting).
            $this->sourcePath = '@verbb/comments/resources/dist';
        }

        if ($settings->outputDefaultCss) {
            $this->css = [$css];
        }

        if ($settings->outputDefaultJs) {
            $this->js = [$js];

            $this->jsOptions = ['defer' => 'defer', 'async' => 'async'];
        }

        parent::init();
    }
}
