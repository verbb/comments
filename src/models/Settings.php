<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;

use Craft;
use craft\base\Model;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\ArrayHelper;
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
    public $guestNotice = '';
    public $guestRequireEmailName = true;
    public $guestShowEmailName = true;
    public $requireModeration = true;
    public $moderatorUserGroup;
    public $autoCloseDays = '';
    public $maxReplyDepth;
    public $maxUserComments;

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
    public $enableGravatar = false;
    public $showTimeAgo = true;
    public $outputDefaultCss = true;
    public $outputDefaultJs = true;

    // Templates - Custom
    public $templateFolderOverride;
    public $templateEmail;

    // Security
    public $enableSpamChecks = true;
    public $securityMaxLength;
    public $securityFlooding;
    public $securityModeration;
    public $securitySpamlist;
    public $securityBanned;
    public $securityMatchExact = false;
    public $recaptchaEnabled = false;
    public $recaptchaKey;
    public $recaptchaSecret;

    // Notifications
    public $notificationAuthorEnabled = true;
    public $notificationReplyEnabled = true;
    public $notificationSubscribeAuto = false;
    public $notificationSubscribeDefault = true;
    public $notificationSubscribeEnabled = false;
    public $notificationSubscribeCommentEnabled = false;
    public $notificationModeratorEnabled = false;
    public $notificationModeratorApprovedEnabled = false;
    public $notificationAdmins = [];
    public $notificationAdminEnabled = false;
    public $notificationFlaggedEnabled = false;

    // Permissions
    public $permissions;

    // Users
    public $users;

    // Custom Fields
    public $showCustomFieldNames = false;
    public $showCustomFieldInstructions = false;

    // Deprecated

    /**
     * @deprecated in 1.4.0. Use Settings::$allowGuest instead.
     */
    public $allowAnonymous;

    /**
     * @deprecated in 1.4.0. Use Settings::$allowGuestVoting instead.
     */
    public $allowAnonymousVoting;

    /**
     * @deprecated in 1.4.0. Use Settings::$allowGuestFlagging instead.
     */
    public $allowAnonymousFlagging;

    private $_placeholderAvatar = null;


    // Public Methods
    // =========================================================================

    public function getPlaceholderAvatar()
    {
        if ($this->_placeholderAvatar !== null) {
            return $this->_placeholderAvatar;
        }

        if ($this->placeholderAvatar && isset($this->placeholderAvatar[0])) {
            return $this->_placeholderAvatar = Craft::$app->getElements()->getElementById($this->placeholderAvatar[0], Asset::class);
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

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser && !$this->allowGuest) {
            return false;
        }

        if ($this->maxUserComments && $currentUser) {
            // Has the user already commented X amount of times on this element?
            $count = Comment::find()->ownerId($element->id)->userId($currentUser->id)->count();

            if ($count >= $this->maxUserComments) {
                return false;
            }
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

    public function getEnabledNotificationAdmins()
    {
        $notificationAdmins = $this->notificationAdmins ?? [];

        return ArrayHelper::where($notificationAdmins, 'enabled');
    }
}
