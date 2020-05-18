<?php
namespace verbb\comments\console\controllers;

use verbb\comments\Comments;

use Craft;
use craft\console\Controller;

use yii\console\ExitCode;
use yii\web\Response;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionResaveStructure()
    {
        $settings = Comments::$plugin->getSettings();

        Comments::$plugin->createAndStoreStructure();

        $this->stdout('Comments structure re-generated.' . PHP_EOL);
        $this->stdout('Structure ID: ' . $settings->structureId . PHP_EOL);
        $this->stdout('Structure UID: ' . $settings->structureUid . PHP_EOL);

        return ExitCode::OK;
    }
}