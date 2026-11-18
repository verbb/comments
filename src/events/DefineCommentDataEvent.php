<?php
namespace verbb\comments\events;

use craft\base\ElementInterface;

use yii\base\Event;

/**
 * Adds data to each comment the data API returns, as the comment's `custom`. It runs once for a response
 * with all of its comments together, so a handler can look things up in one query rather than one per
 * comment.
 *
 * When `isPublic` is true, for `thread`, `replies`, `comment` and `structured-data`, what a handler adds
 * is cached and served to everyone, and the build runs with nobody signed in. It must never depend on who's
 * asking, and must never include a commenter's email or IP address, an ID that identifies them, or a
 * custom field value that's only collected; in Dev Mode, Comments logs a warning when it finds one. When
 * it's false, the comments are going to one person, such as their own waiting comment or a moderator's
 * held ones, and nothing is cached.
 */
class DefineCommentDataEvent extends Event
{
    // Properties
    // =========================================================================

    /** The comments in this response, keyed by their UID. */
    public array $comments = [];

    /** What to add to each comment's `custom`, keyed by comment UID. */
    public array $custom = [];

    public ?ElementInterface $owner = null;
    public ?int $siteId = null;

    /** Whether this data is cached and served to everyone. */
    public bool $isPublic = true;
}
