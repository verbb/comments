<?php
namespace verbb\comments\compatibility\console;

use verbb\comments\services\Compatibility;

use craft\console\Controller;
use craft\helpers\Console;

use yii\console\ExitCode;

/**
 * Comments 3's `comments/base/…` commands, registered only in compatibility mode. There's nothing left for
 * them to do, so each says so and succeeds, and a deploy script that still runs one carries on.
 */
class LegacyBaseController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Comments 4 serves its assets through Craft, so there's nothing to publish.
     */
    public function actionPublishAssets(): int
    {
        return $this->_retired('publish-assets', 'Comments 4 serves its CSS and JavaScript through Craft’s asset manager, so there’s nothing to publish.');
    }

    /**
     * Comments 4 doesn't keep comments in a structure.
     */
    public function actionResaveStructure(): int
    {
        return $this->_retired('resave-structure', 'Comments 4 doesn’t keep comments in a structure, so there’s nothing to resave.');
    }

    /**
     * Comments 4 doesn't keep comments in a structure.
     */
    public function actionSetStructure(?string $structureUid = null): int
    {
        return $this->_retired('set-structure', 'Comments 4 doesn’t keep comments in a structure, so there’s nothing to set.');
    }


    // Private Methods
    // =========================================================================

    private function _retired(string $command, string $message): int
    {
        Compatibility::deprecated("console.{$command}", "The `comments/base/{$command}` command has been removed. {$message} Remove it from your deploy scripts.");

        $this->stdout("comments/base/{$command} is no longer needed. {$message}" . PHP_EOL, Console::FG_YELLOW);

        return ExitCode::OK;
    }
}
