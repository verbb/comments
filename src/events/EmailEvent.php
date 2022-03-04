<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;

use craft\events\CancelableEvent;

class EmailEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public mixed $mail;
    public mixed $user;
    public Comment $comment;
    public mixed $element;

}
