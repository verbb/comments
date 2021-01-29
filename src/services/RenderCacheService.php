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
    public $comments = [];
    public $elements = [];
    public $commentIds = [];


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

    public function getComment($key)
    {
        return $this->comments[$key] ?? null;
    }

    public function addComment($key, $value)
    {
        $this->comments[$key] = $value;
    }

    public function getElement($key)
    {
        return $this->elements[$key] ?? null;
    }

    public function addElement($key, $value)
    {
        $this->elements[$key] = $value;
    }

    public function setCommentIds($value)
    {
        $this->commentIds = $value;
    }

    public function getCommentIds()
    {
        return $this->commentIds;
    }

}
