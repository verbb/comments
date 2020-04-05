<?php
namespace verbb\comments\events;

use craft\events\CancelableEvent;

class EmailEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public $mail;
    public $user;
    public $comment;
    public $element;

}
