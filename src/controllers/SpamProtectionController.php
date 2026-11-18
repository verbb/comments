<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\services\SpamSettings;

use Craft;
use craft\web\Controller;

use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * The Spam Protection page. Its settings are kept in this installation's database rather than project
 * config, so admins and anyone with **Manage spam settings** can change them on any environment,
 * including where admin changes are off.
 */
class SpamProtectionController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();

        if (!Comments::$plugin->getSpamSettings()->canManage()) {
            throw new ForbiddenHttpException('User is not permitted to perform this action.');
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $settings = Comments::$plugin->getSettings();
        $spamSettings = Comments::$plugin->getSpamSettings();
        $secrets = [];

        foreach (SpamSettings::SECRET_KEYS as $key) {
            $secrets[$key] = $spamSettings->displayValue($settings, $key);
        }

        return $this->renderTemplate('comments/spam-protection/index', [
            'settings' => $settings,
            'secrets' => $secrets,
        ]);
    }

    /**
     * A secret posted back as its mask keeps what was saved.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $settings = Comments::$plugin->getSettings();
        $spamSettings = Comments::$plugin->getSpamSettings();
        $spamSettings->applyPosted($settings, (array)$this->request->getBodyParam('settings', []));

        if (!$spamSettings->save($settings)) {
            $this->setFailFlash(Craft::t('comments', 'Couldn’t save settings.'));

            return null;
        }

        $this->setSuccessFlash(Craft::t('comments', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
