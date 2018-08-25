<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;

use Craft;
use craft\web\Controller;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        $settings = Comments::$plugin->getSettings();

        return $this->renderTemplate('comments/settings', [
            'settings' => $settings,
        ]);
    }

}
