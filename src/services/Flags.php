<?php
namespace verbb\comments\services;

/**
 * Comments 3's `Flags` service, kept only so listeners written for its events still resolve. Comments 4
 * has no such service: in compatibility mode these events still fire, as class-level events, with
 * Comments 3's `FlagEvent`. See `compatibility/events/LegacyEngagementEvents`, and use
 * the events on `Reports` instead.
 *
 * @deprecated in 4.0.0. Removed when compatibility mode is.
 */
final class Flags
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_FLAG = 'beforeSaveFlag';
    public const EVENT_AFTER_SAVE_FLAG = 'afterSaveFlag';
    public const EVENT_BEFORE_DELETE_FLAG = 'beforeDeleteFlag';
    public const EVENT_AFTER_DELETE_FLAG = 'afterDeleteFlag';
}
