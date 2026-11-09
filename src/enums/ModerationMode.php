<?php
namespace verbb\comments\enums;

/**
 * Per-conversation (or override) moderation policy.
 *
 * Pre = pre-moderate (comments wait for approval before public visibility).
 */
enum ModerationMode: string
{
    // Constants
    // =========================================================================

    case Disabled = 'disabled';
    case Post = 'post';
    case Pre = 'pre';
}
