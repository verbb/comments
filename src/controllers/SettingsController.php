<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\helpers\SettingsTabs;

use yii\web\NotFoundHttpException;
use yii\web\Response;

use verbb\base\controllers\SettingsController as BaseSettingsController;

class SettingsController extends BaseSettingsController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(?string $pane = null): Response
    {
        $requestedPane = $pane ?? 'general';
        $pane = SettingsTabs::canonicalHandle($requestedPane);
        $tabs = SettingsTabs::all();

        if (!SettingsTabs::isPane($pane)) {
            throw new NotFoundHttpException('Settings pane not found.');
        }

        if ($pane !== $requestedPane) {
            return $this->redirect($pane === 'general' ? 'comments/settings' : 'comments/settings/' . $pane);
        }

        return $this->renderTemplate('comments/settings/index', [
            'settings' => Comments::$plugin->getSettings(),
            'selectedTab' => $pane,
            'tab' => $tabs[$pane],
        ]);
    }
}
