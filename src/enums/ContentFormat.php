<?php
namespace verbb\comments\enums;

/**
 * Canonical Comment body formats for Comments 4.
 *
 * v3 bodies migrate as plain.
 */
enum ContentFormat: string
{
    // Constants
    // =========================================================================

    case Plain = 'plain';
    case Rich = 'rich';
}
