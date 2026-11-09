<?php
namespace verbb\comments\enums;

enum ModerationState: string
{
    // Constants
    // =========================================================================

    case Pending = 'pending';
    case Approved = 'approved';
    case Removed = 'removed';
    case Spam = 'spam';


    // Public Methods
    // =========================================================================

    public function isPubliclyVisible(): bool
    {
        return $this === self::Approved;
    }
}
