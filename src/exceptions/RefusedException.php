<?php
namespace verbb\comments\exceptions;

use yii\base\UserException;

/**
 * A request Comments won't carry out, with a stable code that says why, such as `closed` or
 * `sign_in_required`, and the HTTP status the data API answers with.
 */
class RefusedException extends UserException
{
    // Public Methods
    // =========================================================================

    public function __construct(string $message, public readonly string $errorCode = 'forbidden', public readonly int $httpStatus = 403)
    {
        parent::__construct($message);
    }
}
