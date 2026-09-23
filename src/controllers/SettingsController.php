<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;

use yii\web\Response;

use verbb\base\controllers\SettingsController as BaseSettingsController;

class SettingsController extends BaseSettingsController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        return $this->renderTemplate('comments/settings', [
            'settings' => Comments::$plugin->getSettings(),
        ]);
    }
}
