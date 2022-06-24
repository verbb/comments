<?php
namespace verbb\comments\enums;

/**
 * The CommentStatus class is an abstract class that defines all of the possible states for commenting on an element.
 * (There is one 'available' case and several different 'unavailable' cases.)
 *
 * This class is a poor man's version of an enum copied from P&T's basic pattern in Craft CMS,
 * since PHP does not have support for native enumerations.
 */
abstract class CommentStatus
{
    const Allowed = ['permission' => true, 'reason' => 'Allowed'];
    const Expired = ['permission' => false, 'reason' => 'Expired'];
    const ManuallyClosed = ['permission' => false, 'reason' => 'ManuallyClosed'];
    const Unpermitted = ['permission' => false, 'reason' => 'Unpermitted'];
    const NoGuests = ['permission' => false, 'reason' => 'NoGuests'];
    const TooManyComments = ['permission' => false, 'reason' => 'TooManyComments'];
    const UserBanned = ['permission' => false, 'reason' => 'UserBanned'];
}