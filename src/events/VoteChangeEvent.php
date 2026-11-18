<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;
use verbb\comments\enums\InteractionType;

use craft\elements\User;
use craft\events\CancelableEvent;

/**
 * Someone's vote on a comment changing: given, switched between up and down, or taken back. Before the
 * change, setting `$isValid` to false stops it, and the person is told their vote couldn't be saved.
 */
class VoteChangeEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Comment $comment;
    public int $identityId;
    /** The person voting, or null for a guest. */
    public ?User $user = null;
    /** The vote after the change, or null when it's taken back. */
    public ?InteractionType $type = null;
    /** The vote before the change, or null when there wasn't one. */
    public ?InteractionType $previousType = null;
}
