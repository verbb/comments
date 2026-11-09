<?php
namespace verbb\comments\console\controllers;

use verbb\comments\Comments;
use verbb\comments\migrations\support\V3UpgradeLog;

use craft\console\Controller;
use craft\helpers\Console;

use yii\console\ExitCode;

/**
 * Checks and repairs for Comments data.
 */
class DiagnosticsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Reports problems with comment hierarchy and owners.
     */
    public function actionScan(): int
    {
        $report = Comments::$plugin->getDiagnostics()->runIntegrityScan();

        foreach ($report['v4']['issues'] as $issue) {
            $this->stdout("{$issue['severity']}: {$issue['message']}\n", $issue['severity'] === 'error' ? Console::FG_RED : Console::FG_YELLOW);
        }

        foreach ($report['upgrade'] as $entry) {
            if ($entry['severity'] !== 'info') {
                $this->stdout('Comments 3 upgrade: ' . V3UpgradeLog::describe($entry['code']) . " ({$entry['count']})\n");
            }
        }

        if (!$report['v4']['issues']) {
            $this->stdout("No problems found.\n", Console::FG_GREEN);
        }

        return $report['hasErrors'] ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Recalculates comment counts and latest activity for every conversation.
     */
    public function actionRebuildCounts(): int
    {
        $result = Comments::$plugin->getDiagnostics()->rebuildAllAggregates();

        $this->stdout("Rebuilt {$result['rebuilt']} conversations.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
