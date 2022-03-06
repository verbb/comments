<?php
namespace verbb\comments\enums;

/**
 * The CommentStatus class is an abstract class that defines all the possible states for commenting on an element.
 * (There is one 'available' case and several 'unavailable' cases.)
 *
 * This class is a poor man's version of an enum copied from P&T's basic pattern in Craft CMS,
 * since PHP does not have support for native enumerations.
 */
abstract class CommentStatus
{
    // Constants
    // =========================================================================

    public const Allowed = ['permission' => true, 'reason' => 'Allowed'];
    public const Expired = ['permission' => false, 'reason' => 'Expired'];
    public const ManuallyClosed = ['permission' => false, 'reason' => 'ManuallyClosed'];
    public const NoGuests = ['permission' => false, 'reason' => 'NoGuests'];
    public const TooManyComments = ['permission' => false, 'reason' => 'TooManyComments'];
    public const Unpermitted = ['permission' => false, 'reason' => 'Unpermitted'];
    public const UserBanned = ['permission' => false, 'reason' => 'UserBanned'];
}