<?php
namespace verbb\comments\services;

/**
 * Comments 3's `Subscribe` service, kept only so listeners written for its events still resolve. Comments 4
 * has no such service: in compatibility mode these events still fire, as class-level events, with
 * Comments 3's `SubscribeEvent`. See `compatibility/events/LegacyEngagementEvents`, and use
 * the events on `Subscriptions` instead.
 *
 * @deprecated in 4.0.0. Removed when compatibility mode is.
 */
final class Subscribe
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_SUBSCRIBE = 'beforeSaveSubscribe';
    public const EVENT_AFTER_SAVE_SUBSCRIBE = 'afterSaveSubscribe';
    public const EVENT_BEFORE_DELETE_SUBSCRIBE = 'beforeDeleteSubscribe';
    public const EVENT_AFTER_DELETE_SUBSCRIBE = 'afterDeleteSubscribe';
}
