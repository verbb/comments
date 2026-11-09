<?php
namespace verbb\comments\models;

use craft\base\Model;

/**
 * Transient viewer overlay for canonical frontend partials — no Identity IDs or credentials.
 */
class FrontendViewerView extends Model
{
    // Properties
    // =========================================================================

    public int $contractVersion = 1;
    public bool $isLoggedIn = false;
    public bool $canComment = false;
    public bool $showSubscribe = false;
    public bool $subscribeNeedsEmail = false;
    public bool $isSubscribed = false;
    public ?string $avatarUrl = null;
    public ?string $name = null;
    public string $initials = '';
    public int $avatarTone = 0;
    /** The badge their comments will carry, such as Author or Staff. */
    public ?string $badge = null;
    public ?string $loginUrl = null;
    public bool $allowsGuests = false;

    /** Viewer holds a moderation capability, so unmoderated Comments are visible and actionable inline. */
    public bool $isModerator = false;
}
