<?php
namespace verbb\comments\errors;

use yii\base\Exception;

class VoteNotFoundException extends Exception
{
    // Public Methods
    // =========================================================================

    public function getName(): string
    {
        return 'Vote not found';
    }
}
