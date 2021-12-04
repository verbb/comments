<?php
/**
 */

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
    const Allowed           = array('permission' => true,  'reason' => 'Allowed');
    const Expired           = array('permission' => false, 'reason' => 'Expired');
    const ManuallyClosed    = array('permission' => false, 'reason' => 'ManuallyClosed');
    const Unpermitted       = array('permission' => false, 'reason' => 'Unpermitted');
    const NoGuests          = array('permission' => false, 'reason' => 'NoGuests');
    const TooManyComments   = array('permission' => false, 'reason' => 'TooManyComments');
    const UserBanned        = array('permission' => false, 'reason' => 'UserBanned');
}