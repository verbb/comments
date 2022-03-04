<?php
namespace verbb\comments\errors;

use yii\base\Exception;

class FlagNotFoundException extends Exception
{
    // Public Methods
    // =========================================================================

    public function getName(): string
    {
        return 'Flag not found';
    }
}
