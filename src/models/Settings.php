<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentStatus;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;

class Settings extends Model
{
    // Properties
    // =========================================================================

    public ?string $structureUid = null;
    public bool $closed = false;
    public int $indexSidebarLimit = 25;
    public bool $indexSidebarGroup = true;
    public bool $indexSidebarIndividualElements = false;
    public array $defaultQueryStatus = [Comment::STATUS_APPROVED];

    // General
    public bool $allowGuest = false;
    public ?string $guestNotice = null;
    public bool $guestRequireEmailName = true;
    public bool $guestShowEmailName = true;
    public bool $requireModeration = true;
    public ?string $moderatorUserGroup = null;
    public bool $moderatorExcluded = true;
    public mixed $autoCloseDays = null;
    public mixed $maxReplyDepth = null;
    public mixed $maxUserComments = null;

    // Voting
    public bool $allowVoting = true;
    public bool $allowGuestVoting = false;
    public int $downvoteCommentLimit = 5;
    public bool $hideVotingForThreshold = false;

    // Flagging
    public bool $allowFlagging = true;
    public bool $allowGuestFlagging = false;
    public int $flaggedCommentLimit = 5;

    // Templates - Default
    public bool $showAvatar = true;
    public mixed $placeholderAvatar = null;
    public bool $enableGravatar = false;
    public bool $showTimeAgo = true;
    public bool $outputDefaultCss = true;
    public bool $outputDefaultJs = true;

    // Templates - Custom
    public ?string $templateFolderOverride = null;
    public ?string $templateEmail = null;

    // Security
    public bool $enableSpamChecks = true;
    public ?string $securityMaxLength = null;
    public ?string $securityFlooding = null;
    public ?string $securityModeration = null;
    public ?string $securitySpamlist = null;
    public ?string $securityBanned = null;
    public bool $securityMatchExact = false;
    public bool $recaptchaEnabled = false;
    public ?string $recaptchaKey = null;
    public ?string $recaptchaSecret = null;
    public float $recaptchaMinScore = 0.5;

    // Notifications
    public bool $notificationAuthorEnabled = true;
    public bool $notificationReplyEnabled = true;
    public bool $notificationSubscribeAuto = false;
    public bool $notificationSubscribeDefault = true;
    public bool $notificationSubscribeEnabled = false;
    public bool $notificationSubscribeCommentEnabled = false;
    public bool $notificationModeratorEnabled = false;
    public bool $notificationModeratorEditEnabled = false;
    public bool $notificationModeratorApprovedEnabled = false;
    public array $notificationAdmins = [];
    public bool $notificationAdminEnabled = false;
    public bool $notificationFlaggedEnabled = false;
    public bool $useQueueForNotifications = false;

    // Permissions
    public array $permissions = [];

    // Users
    public array $users = [];

    // Custom Fields
    public bool $showCustomFieldNames = false;
    public bool $showCustomFieldInstructions = false;

    // CP Sorting
    public string $sortDefaultKey = 'structure';
    public string $sortDefaultDirection = 'asc';

    private ?Asset $_placeholderAvatar = null;


    // Public Methods
    // =========================================================================

    public function getPlaceholderAvatar(): ?Asset
    {
        if ($this->_placeholderAvatar !== null) {
            return $this->_placeholderAvatar;
        }

        if ($this->placeholderAvatar && isset($this->placeholderAvatar[0])) {
            /* @noinspection PhpIncompatibleReturnTypeInspection */
            return $this->_placeholderAvatar = Craft::$app->getElements()->getElementById($this->placeholderAvatar[0], Asset::class);
        }

        return null;
    }

    public function canComment($element): bool
    {
        $isAllowed = $this->commentingAvailable($element);

        return $isAllowed['permission'];
    }

    public function commentingAvailable($element): array
    {
        $isClosed = Comments::$plugin->getComments()->checkManuallyClosed($element);

        if ($isClosed) {
            return CommentStatus::ManuallyClosed;
        }

        $isExpired = Comments::$plugin->getComments()->checkExpired($element);

        if ($isExpired) {
            return CommentStatus::Expired;
        }

        $hasPermission = Comments::$plugin->getComments()->checkPermissions($element);

        if (!$hasPermission) {
            return CommentStatus::Unpermitted;
        }

        $currentUser = Comments::$plugin->getService()->getUser();

        if (!$currentUser && !$this->allowGuest) {
            return CommentStatus::NoGuests;
        }

        if ($this->maxUserComments && $currentUser) {
            // Has the user already commented X amount of times on this element?
            $count = Comment::find()
                ->ownerId($element->id)
                ->ownerSiteId($element->siteId)
                ->userId($currentUser->id)
                ->count();

            if ($count >= $this->maxUserComments) {
                return CommentStatus::TooManyComments;
            }
        }

        return CommentStatus::Allowed;
    }

    public function getStructureId(): ?int
    {
        if ($this->structureUid) {
            return Db::idByUid(Table::STRUCTURES, $this->structureUid);
        }

        // Create the structure if it doesn't exist
        if ($structure = Comments::$plugin->createAndStoreStructure()) {
            $this->structureUid = $structure->uid;

            return $structure->id;
        }

        return null;
    }

    public function getEnabledNotificationAdmins(): array
    {
        $notificationAdmins = $this->notificationAdmins ?: [];

        return ArrayHelper::where($notificationAdmins, 'enabled');
    }

    public function getRecaptchaKey()
    {
        return App::parseEnv($this->recaptchaKey);
    }

    public function getRecaptchaSecret()
    {
        return App::parseEnv($this->recaptchaSecret);
    }

    public function doesRequireModeration(): bool
    {
        // Check if we require moderation at all
        if ($this->requireModeration) {
            // Check if we should exclude the current user, if they are in the moderator group
            if ($this->moderatorExcluded && $this->moderatorUserGroup) {
                if ($currentUser = Comments::$plugin->getService()->getUser()) {
                    $groupId = Db::idByUid(Table::USERGROUPS, $this->moderatorUserGroup);
                    $moderators = User::find()->groupId($groupId)->ids();

                    if (in_array($currentUser->id, $moderators)) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
    }
}
