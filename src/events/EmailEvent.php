<?php
namespace verbb\comments\events;

use yii\base\Event;

class EmailEvent extends Event
{
    // Properties
    // =========================================================================

    public $mail;
    public $user;
    public $comment;
    public $element;

}
