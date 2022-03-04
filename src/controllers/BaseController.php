<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;

use craft\web\Controller;

use yii\web\Response;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings(): Response
    {
        $settings = Comments::$plugin->getSettings();

        return $this->renderTemplate('comments/settings', [
            'settings' => $settings,
        ]);
    }

}
