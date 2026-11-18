<?php
namespace verbb\comments\services;

/**
 * Comments 3's `Votes` service, kept only so listeners written for its events still resolve. Comments 4
 * has no such service: in compatibility mode these events still fire, as class-level events, with
 * Comments 3's `VoteEvent`. See `compatibility/events/LegacyEngagementEvents`, and use
 * the events on `Interactions` instead.
 *
 * @deprecated in 4.0.0. Removed when compatibility mode is.
 */
final class Votes
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_VOTE = 'beforeSaveVote';
    public const EVENT_AFTER_SAVE_VOTE = 'afterSaveVote';
    public const EVENT_BEFORE_DELETE_VOTE = 'beforeDeleteVote';
    public const EVENT_AFTER_DELETE_VOTE = 'afterDeleteVote';
}
