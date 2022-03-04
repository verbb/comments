<?php
namespace verbb\comments\console\controllers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;

use yii\console\ExitCode;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionResaveStructure(): int
    {
        $settings = Comments::$plugin->getSettings();

        Comments::$plugin->createAndStoreStructure();

        $this->stdout('Comments structure re-generated.' . PHP_EOL);
        $this->stdout('Structure ID: ' . $settings->structureId . PHP_EOL);
        $this->stdout('Structure UID: ' . $settings->structureUid . PHP_EOL);

        return ExitCode::OK;
    }

    public function actionSetStructure($structureUid = null): int
    {
        if (!$structureUid) {
            $this->stderr('Structure UID not provided.' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $structure = Craft::$app->getStructures()->getStructureByUid($structureUid, true);

        if (!$structure) {
            $this->stderr("Structure with UID $structureUid does not exist." . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("-----------------------------------------------" . PHP_EOL, Console::FG_YELLOW);
        $this->stdout("You are about to force-set all comments to be assigned to structure ID $structure->id. This action cannot be undone." . PHP_EOL, Console::FG_YELLOW);
        $this->stdout("Please ensure you make a database backup before proceeding." . PHP_EOL, Console::FG_YELLOW);
        $this->stdout("-----------------------------------------------" . PHP_EOL, Console::FG_YELLOW);

        if (!$this->confirm('Do you want to proceed?')) {
            return ExitCode::OK;
        }

        $db = Craft::$app->getDb()->createCommand();
        $comments = Comment::find()->all();

        foreach ($comments as $comment) {
            if ($comment->structureId == $structure->id) {
                continue;
            }

            $db->update('{{%structureelements}}', [
                'structureId' => $structure->id,
            ], ['elementId' => $comment->id])->execute();

            $this->stdout("Updating comment #$comment->id to structure ID $structure->id." . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}