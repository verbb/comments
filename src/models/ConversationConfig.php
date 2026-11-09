<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ModerationMode;

use craft\base\Model;
use craft\db\Table;
use craft\elements\User;
use craft\helpers\Db;

/**
 * Fully resolved runtime config for a Conversation owner (global → source → Conversation).
 */
class ConversationConfig extends Model
{
    // Properties
    // =========================================================================

    public int $ownerId;
    public int $siteId;
    public ?int $conversationId = null;
    public bool $enabled = true;
    public bool $closed = false;
    public ModerationMode $moderationMode = ModerationMode::Post;
    public bool $allowGuests = false;
    public CommentSort $sortOrder = CommentSort::Newest;


    // Public Methods
    // =========================================================================

    public function isCommentingEnabled(): bool
    {
        return $this->enabled && !$this->closed;
    }

    /**
     * Whether new Comments should start in Pending (Pre moderation), honouring moderator exclusion.
     */
    public function requiresModeration(): bool
    {
        if ($this->moderationMode !== ModerationMode::Pre) {
            return false;
        }

        $settings = Comments::$plugin->getSettings();

        if (!$settings->moderatorExcluded || !$settings->moderatorUserGroup) {
            return true;
        }

        $currentUser = Comments::$plugin->getService()->getUser();

        if (!$currentUser) {
            return true;
        }

        $groupId = Db::idByUid(Table::USERGROUPS, $settings->moderatorUserGroup);

        if (!$groupId) {
            return true;
        }

        $moderatorIds = User::find()->groupId($groupId)->ids();

        return !in_array($currentUser->id, $moderatorIds, true);
    }
}
