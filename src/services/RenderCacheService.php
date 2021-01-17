<?php
namespace verbb\comments\services;

use verbb\comments\Comments;

use Craft;
use craft\base\Component;
use craft\db\Query;

class RenderCacheService extends Component
{
    // Properties
    // =========================================================================

    public $avatars = [];


    // Public Methods
    // =========================================================================

    public function getAvatar($key)
    {
        return $this->avatars[$key] ?? null;
    }

    public function addAvatar($key, $value)
    {
        $this->avatars[$key] = $value;
    }

}
