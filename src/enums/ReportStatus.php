<?php
namespace verbb\comments\enums;

enum ReportStatus: string
{
    // Constants
    // =========================================================================

    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
