<?php
namespace verbb\comments\enums;

enum RestrictionType: string
{
    // Constants
    // =========================================================================

    case Blocked = 'blocked';
    case Moderated = 'moderated';
    case Trusted = 'trusted';
}
