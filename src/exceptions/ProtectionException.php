<?php
namespace verbb\comments\exceptions;

use yii\base\UserException;

class ProtectionException extends UserException
{
    // Public Methods
    // =========================================================================

    /**
     * `$errorCode` is the data API's code for the refusal: `protection_required`, `protection_expired`,
     * `challenge_failed`, `possible_spam`, `rate_limited` or `protection_unavailable`.
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly ?int $retryAfter = null,
        public readonly string $errorCode = 'protection_required',
    ) {
        parent::__construct($message);
    }
}
