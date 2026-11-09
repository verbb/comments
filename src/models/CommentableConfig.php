<?php
namespace verbb\comments\models;

use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ModerationMode;

use craft\base\Model;

/**
 * Nullable source-level comment configuration. Null properties inherit global Settings.
 */
class CommentableConfig extends Model
{
    // Properties
    // =========================================================================

    public ?bool $enabled = null;
    public ?ModerationMode $moderationMode = null;
    public ?bool $allowGuests = null;
    public ?CommentSort $sortOrder = null;
}
