<?php
namespace verbb\comments\events;

use verbb\comments\elements\Comment;

use craft\events\CancelableEvent;

class EmailEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public Comment $comment;
    public mixed $element;
    public mixed $mail;
    public mixed $user;

}
