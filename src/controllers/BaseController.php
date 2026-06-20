<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;

use Craft;
use craft\web\Controller;

use yii\web\Response;

use Throwable;

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

    public function actionPublishAssets(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin(false);

        $settings = Comments::$plugin->getSettings();

        if (!$settings->getAssetBasePath()) {
            return $this->asFailure(Craft::t('comments', 'No “Asset Base Path” is configured.'));
        }

        try {
            $copied = Comments::$plugin->getComments()->publishFrontEndAssets();
        } catch (Throwable $e) {
            return $this->asFailure(Craft::t('comments', 'Couldn’t publish assets: {error}', ['error' => $e->getMessage()]));
        }

        return $this->asSuccess(Craft::t('comments', '{count} front-end asset(s) published.', ['count' => count($copied)]));
    }

}
