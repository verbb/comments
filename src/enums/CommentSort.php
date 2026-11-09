<?php
namespace verbb\comments\enums;

enum CommentSort: string
{
    // Constants
    // =========================================================================

    case Newest = 'newest';
    case Oldest = 'oldest';
    case Top = 'top';
}
