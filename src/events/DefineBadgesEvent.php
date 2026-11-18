<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;

use yii\base\Event;

/**
 * Adds badges, such as "Verified buyer", to a comment's author. Each badge is a `type` that a site can
 * style and a `label` that's shown.
 *
 * Badges are public. When `isPublic` is true, as it is for the thread and every public data API read,
 * they're cached and served to everyone, and the build runs with nobody signed in, so a handler must
 * never make them depend on who's looking or reveal anything private about a commenter.
 *
 * The `viewer` read also asks for the badges a signed-in person's next comment will carry, for the
 * form's "Posting as" line. `comment` is then a new, unsaved comment by them on the element, and
 * `isPublic` is false.
 */
class DefineBadgesEvent extends Event
{
    // Properties
    // =========================================================================

    public ?Comment $comment = null;

    /** The badges so far, each `['type' => …, 'label' => …]`. */
    public array $badges = [];

    /** Every comment drawn alongside this one, so a handler can look them all up at once. */
    public array $comments = [];

    /** Whether these badges are shown to everyone and cached, rather than only to the person asking. */
    public bool $isPublic = true;
}
