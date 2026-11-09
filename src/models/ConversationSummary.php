<?php
namespace verbb\comments\models;

use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ModerationMode;

use craft\base\Model;

use DateTime;

/**
 * Transient Conversation summary for product APIs — not a persisted Record.
 */
class ConversationSummary extends Model
{
    // Public Methods
    // =========================================================================

    public function __construct(
        public int $conversationId,
        public int $ownerId,
        public int $siteId,
        public int $commentCount = 0,
        public ?DateTime $lastActivityAt = null,
        public bool $closed = false,
        public ?ModerationMode $moderationMode = null,
        public ?bool $allowGuests = null,
        public ?CommentSort $sortOrder = null,
        array $config = [],
    ) {
        parent::__construct($config);
    }
}
