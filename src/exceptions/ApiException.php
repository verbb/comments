<?php
namespace verbb\comments\exceptions;

use yii\base\UserException;

/**
 * An error the data API returns as JSON, with its stable code, HTTP status and any field errors keyed
 * by field (`content`, `guest.name`, `fields.<handle>`).
 */
class ApiException extends UserException
{
    // Public Methods
    // =========================================================================

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 400,
        public readonly array $fieldErrors = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
