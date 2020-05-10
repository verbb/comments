<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\Model;
use craft\db\Table;
use craft\helpers\Db;

class Settings extends Model
{
    // Public Properties
    // =========================================================================

    public $structureUid;
    public $closed;
    public $indexSidebarLimit = 25;
    public $indexSidebarGroup = true;
    public $indexSidebarIndividualElements = false;
    public $defaultQueryStatus = [Comment::STATUS_APPROVED];

    // General
    public $allowGuest = false;
    public $guestRequireEmailName = true;
    public $guestShowEmailName = true;
    public $requireModeration = true;
    public $moderatorUserGroup;
    public $autoCloseDays = '';

    // Voting
    public $allowVoting = true;
    public $allowGuestVoting = false;
    public $downvoteCommentLimit = 5;
    public $hideVotingForThreshold = false;

    // Flagging
    public $allowFlagging = true;
    public $allowGuestFlagging = false;
    public $flaggedCommentLimit = 5;

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
    public $notificationSubscribeAuto = false;
    public $notificationSubscribeDefault = true;
    public $notificationSubscribeEnabled = false;
    public $notificationSubscribeCommentEnabled = false;
    public $notificationModeratorEnabled = false;
    public $notificationModeratorApprovedEnabled = false;

    // Permissions
    public $permissions;

    // Users
    public $users;

    // Deprecated
    public $allowAnonymous;
    public $allowAnonymousVoting;
    public $allowAnonymousFlagging;


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

    public function getStructureId()
    {
        if ($this->structureUid) {
            return Db::idByUid(Table::STRUCTURES, $this->structureUid);
        }

        return null;
    }
}
