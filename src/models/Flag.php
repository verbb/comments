<?php
namespace verbb\comments\models;

use verbb\comments\elements\Comment;

use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\User;

class Flag extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $commentId = null;
    public ?int $userId = null;


    // Public Methods
    // =========================================================================

    public function getComment(): ?Comment
    {
        if ($this->commentId) {
            return Comment::find()->id($this->commentId)->one();
        }

        return null;
    }

    /**
     * The person who reported the comment, for the GraphQL `user` field. Guests have none.
     */
    public function getUser(): ?User
    {
        if ($this->userId) {
            return User::find()->id($this->userId)->one();
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        return [
            [['id'], 'number', 'integerOnly' => true],
            [['commentId'], 'required'],
        ];
    }
}
