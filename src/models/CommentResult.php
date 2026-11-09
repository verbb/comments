<?php
namespace verbb\comments\models;

use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;

use craft\base\Model;

/**
 * Small product result for Comment create/edit — Comment + moderation state.
 * Pending is success.
 */
class CommentResult extends Model
{
    // Public Methods
    // =========================================================================

    public function __construct(
        public Comment $comment,
        public ModerationState $moderationState,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->moderationState->isPubliclyVisible();
    }
}
