<?php
namespace verbb\comments\exceptions;

use yii\base\UserException;

class ProtectionException extends UserException
{
    // Public Methods
    // =========================================================================

    public function __construct(string $message, public readonly int $httpStatus = 422, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
