<?php
namespace verbb\comments\models;

use verbb\comments\elements\Comment;

use craft\base\ElementInterface;
use craft\base\Model;

class Flag extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $commentId = null;
    public ?int $userId = null;
    public ?int $sessionId = null;
    public ?string $lastIp = null;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [['id'], 'number', 'integerOnly' => true],
            [['commentId'], 'required'],
        ];
    }

    public function getComment(): ?Comment
    {
        if ($this->commentId) {
            return Comment::find()->id($this->commentId)->one();
        }

        return null;
    }

}