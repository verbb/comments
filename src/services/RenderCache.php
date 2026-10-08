<?php
namespace verbb\comments\services;

use craft\base\Component;

class RenderCache extends Component
{
    // Properties
    // =========================================================================

    public array $avatars = [];
    public array $comments = [];
    public array $elements = [];
    public array $commentIds = [];

    private array $_authorIds = [];


    // Public Methods
    // =========================================================================

    public function getAvatar($key)
    {
        return $this->avatars[$key] ?? null;
    }

    public function addAvatar($key, $value): void
    {
        $this->avatars[$key] = $value;
    }

    public function getComment($key)
    {
        return $this->comments[$key] ?? null;
    }

    public function addComment($key, $value): void
    {
        $this->comments[$key] = $value;
    }

    public function getElement($key)
    {
        return $this->elements[$key] ?? null;
    }

    public function addElement($key, $value): void
    {
        $this->elements[$key] = $value;
    }

    // Register only loaded comments, including collections populated by eager-loading replies.
    public function addComments(array $comments): void
    {
        foreach ($comments as $comment) {
            if ($comment['id'] ?? null) {
                $this->commentIds[] = (int)$comment['id'];
            }

            if ($comment['userId'] ?? null) {
                $this->_authorIds[] = (int)$comment['userId'];
            }
        }

        $this->commentIds = array_values(array_unique($this->commentIds));
        $this->_authorIds = array_values(array_unique($this->_authorIds));
    }

    public function getAuthorIds(): array
    {
        return $this->_authorIds;
    }

    public function getCommentIds(): array
    {
        return $this->commentIds;
    }

    public function setCommentIds($value): void
    {
        $this->commentIds = $value;
    }

}
