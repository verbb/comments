<?php
namespace verbb\comments\models;

use verbb\comments\Comments;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    // Public Properties
    // =========================================================================

    public $structureId;
    public $closed;
    public $indexSidebarLimit = 25;

    // General
    public $allowAnonymous = false;
    public $requireModeration = true;
    public $autoCloseDays = '';

    // Voting
    public $allowVoting = true;
    public $flaggedCommentLimit = 5;

    // Flagging
    public $allowFlagging = true;
    public $downvoteCommentLimit = 5;

    // Templates - Default
    public $showAvatar = true;
    public $placeholderAvatar;
    public $showTimeAgo = true;
    public $outputDefaultCss = true;
    public $outputDefaultJs = true;

    // Templates - Custom
    public $templateFolderOverride;

    // Security
    public $enableSpamChecks = true;
    public $recaptchaEnabled = false;
    public $recaptchaKey;
    public $recaptchaSecret;
    public $securityMaxLength;
    public $securityFlooding;
    public $securityModeration;
    public $securityBlacklist;
    public $securityBanned;

    // Notifications
    public $notificationAuthorEnabled = true;
    public $notificationReplyEnabled = true;

    // Permissions
    public $permissions;

    // Users
    public $users;


    // Public Methods
    // =========================================================================

    public function getPlaceholderAvatar()
    {
        if ($this->placeholderAvatar && isset($this->placeholderAvatar[0])) {
            return Craft::$app->getElements()->getElementById($this->placeholderAvatar[0]);
        }

        return null;
    }

    public function canComment($element)
    {
        $isClosed = Comments::$plugin->getComments()->checkClosed($element);

        if ($isClosed) {
            return false;
        }

        $hasPermission = Comments::$plugin->getComments()->checkPermissions($element);

        if (!$hasPermission) {
            return false;
        }

        return true;
    }
}
