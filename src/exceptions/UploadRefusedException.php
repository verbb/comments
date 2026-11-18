<?php
namespace verbb\comments\exceptions;

use yii\web\BadRequestHttpException;

/**
 * An upload Comments won't accept, with the data API's code for why: `file_type_not_allowed`,
 * `file_too_large` or `too_many_files`. It's a bad request to anything that calls Editor's upload
 * service directly, as Editor's own refusals are.
 */
class UploadRefusedException extends BadRequestHttpException
{
    // Public Methods
    // =========================================================================

    public function __construct(string $message, public readonly string $errorCode = 'file_type_not_allowed')
    {
        parent::__construct($message);
    }
}
