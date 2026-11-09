<?php
namespace verbb\comments\enums;

/**
 * Canonical interaction kinds.
 *
 * Maps v3 up/down votes; at most one current interaction per identity/comment.
 */
enum InteractionType: string
{
    // Constants
    // =========================================================================

    case Upvote = 'upvote';
    case Downvote = 'downvote';
}
