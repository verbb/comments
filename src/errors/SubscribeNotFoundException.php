<?php
namespace verbb\comments\errors;

use yii\base\Exception;

class SubscribeNotFoundException extends Exception
{
    // Public Methods
    // =========================================================================

    public function getName(): string
    {
        return 'Subscribe not found';
    }
}
