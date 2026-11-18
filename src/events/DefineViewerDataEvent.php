<?php
namespace verbb\comments\events;

use craft\base\ElementInterface;
use craft\elements\User;

use yii\base\Event;

/**
 * Adds private data to a `viewer` response, which is never cached. `custom` becomes the response's
 * `custom`, and `commentCustom`, keyed by comment UID, becomes the `custom` of that comment's entry in
 * `comments`.
 */
class DefineViewerDataEvent extends Event
{
    // Properties
    // =========================================================================

    /** The person asking, or null for a guest. */
    public ?User $user = null;

    /** The comments in the viewer's window, keyed by their UID. */
    public array $comments = [];

    public array $custom = [];
    public array $commentCustom = [];
    public ?ElementInterface $owner = null;
    public ?int $siteId = null;
}
