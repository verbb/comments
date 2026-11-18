<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\helpers\SettingsTabs;
use verbb\comments\services\FormFields;

use Craft;

use yii\web\NotFoundHttpException;
use yii\web\Response;

use verbb\base\controllers\SettingsController as BaseSettingsController;

class SettingsController extends BaseSettingsController
{
    // Public Methods
    // =========================================================================

    /**
     * Settings write to project config, and some of them, such as the attachment subfolder, are Twig,
     * so only an admin may see or save them, and only where admin changes are allowed. Craft lets
     * anyone who can access the plugin reach these actions, moderators included. The Spam settings
     * have their own page, `SpamProtectionController`.
     */
    public function beforeAction($action): bool
    {
        // The Spam pane became the Spam Protection page. Its old address redirects there before Verbb
        // Base's own admin check, so whoever can manage spam settings can follow it. Route parameters
        // aren't bound yet, so the pane comes from the address, `comments/settings/<pane>`.
        if ($action->id === 'index' && SettingsTabs::canonicalHandle((string)$this->request->getSegment(3)) === 'spam') {
            $this->redirect('comments/spam-protection');

            return false;
        }

        $this->requireAdmin();

        return parent::beforeAction($action);
    }

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

    /**
     * The Comment field layout is saved with the settings. Its custom fields can't use a handle Comments
     * reserves, such as `comment` or `operationId`, which would collide with what the site's form, the
     * data API and GraphQL send.
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        if ($this->request->getBodyParam('settings.fieldLayout') !== null && $this->request->getParam('pluginHandle') === 'comments') {
            $layout = Craft::$app->getFields()->assembleLayoutFromPost('settings');
            $layout->type = Comment::class;
            $layout->reservedFieldHandles = FormFields::RESERVED_HANDLES;

            if (!$layout->validate()) {
                $settings = Comments::$plugin->getSettings();
                $settings->setAttributes($this->request->getParam('settings', []), false);
                $settings->addError('fieldLayout', implode(' ', $layout->getFirstErrors()));
                $this->setFailFlash(Craft::t('comments', 'Couldn’t save settings.'));

                Craft::$app->getUrlManager()->setRouteParams([
                    'settings' => $settings,
                ]);

                return null;
            }
        }

        return parent::actionSaveSettings();
    }
}
