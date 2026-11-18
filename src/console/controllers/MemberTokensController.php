<?php
namespace verbb\comments\console\controllers;

use verbb\comments\Comments;

use Craft;
use craft\console\Controller;
use craft\elements\User;
use craft\helpers\Console;

use yii\console\ExitCode;

/**
 * Member tokens for front ends on other sites.
 */
class MemberTokensController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * Revoke every member's tokens, such as after the Member Token Key may have leaked.
     */
    public bool $all = false;


    // Public Methods
    // =========================================================================

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'revoke') {
            $options[] = 'all';
        }

        return $options;
    }

    /**
     * Revokes a member's tokens, by username, email address, ID or UID, or everyone's with `--all`.
     */
    public function actionRevoke(?string $user = null): int
    {
        $tokens = Comments::$plugin->getMemberTokens();

        if ($this->all) {
            $count = $tokens->revokeAll();
            $this->stdout("Revoked $count member tokens.\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        if ($user === null || $user === '') {
            $this->stderr("Name a member by username, email address, ID or UID, or pass --all.\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $query = User::find()->status(null);
        $member = match (true) {
            ctype_digit($user) => $query->id((int)$user)->one(),
            (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $user) => $query->uid($user)->one(),
            default => Craft::$app->getUsers()->getUserByUsernameOrEmail($user),
        };

        if (!$member) {
            $this->stderr("No member matches “{$user}”.\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $count = $tokens->revokeForUser((int)$member->id);
        $this->stdout("Revoked $count member tokens for {$member->username}.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Removes tokens that ran out or were revoked more than a day ago, as garbage collection does.
     */
    public function actionPurge(): int
    {
        $count = Comments::$plugin->getMemberTokens()->deleteExpired();
        $this->stdout("Removed $count expired member tokens and nonces.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
