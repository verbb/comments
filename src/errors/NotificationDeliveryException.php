<?php
namespace verbb\comments\errors;

use yii\base\Exception;

/**
 * Thrown once every recipient has been tried, so a queue job shows as failed rather than succeeding.
 */
class NotificationDeliveryException extends Exception
{
    // Public Methods
    // =========================================================================

    public function getName(): string
    {
        return 'Notification delivery failed';
    }
}
