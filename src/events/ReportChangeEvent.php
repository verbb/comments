<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;
use verbb\comments\records\Report;

use craft\elements\User;
use craft\events\CancelableEvent;

/**
 * Someone reporting a comment, or taking their report back. Before a report is made, setting `$isValid`
 * to false stops it, and the person is told it couldn't be sent.
 */
class ReportChangeEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Comment $comment;
    public int $identityId;
    /** The person reporting, or null for a guest. */
    public ?User $user = null;
    public ?string $reason = null;
    public ?string $details = null;
    /** The report, once it's saved or withdrawn. Null before it's made. */
    public ?Report $report = null;
    /** True for a new report, false when one is taken back. */
    public bool $reported = true;
}
