<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ModerationMode;

use craft\base\Model;

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

        return !Comments::$plugin->getSettings()->isExemptFromModeration();
    }
}
