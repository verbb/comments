<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\services\SpamSettings;

use Craft;
use craft\helpers\App;
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
        $saved = [];

        foreach (SpamSettings::ENV_KEYS as $key) {
            $saved[$key] = $settings->$key;
        }

        $spamSettings->applyPosted($settings, (array)$this->request->getBodyParam('settings', []));

        // People who manage spam settings without being admins can't read the server's environment, so a key
        // they enter can't refer to a variable or alias, anywhere in it, unless an admin already saved it. A
        // site key is resolved and sent to every visitor, which would publish any secret the server holds.
        if (!Craft::$app->getUser()->getIsAdmin()) {
            foreach (SpamSettings::ENV_KEYS as $key) {
                $value = $settings->$key;

                if (is_string($value) && $value !== $saved[$key] && (str_contains($value, '$') || str_starts_with(ltrim($value), '@') || App::parseEnv($value) !== $value)) {
                    $settings->addError($key, Craft::t('comments', 'Only admins can use an environment variable or alias here.'));
                }
            }
        }

        if ($settings->hasErrors() || !$spamSettings->save($settings)) {
            $this->setFailFlash(Craft::t('comments', 'Couldn’t save settings.'));

            return null;
        }

        $this->setSuccessFlash(Craft::t('comments', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
