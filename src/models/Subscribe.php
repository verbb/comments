<?php
namespace verbb\comments\models;

use verbb\comments\elements\Comment;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;

class Subscribe extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $ownerId = null;
    public ?int $ownerSiteId = null;
    public ?int $userId = null;
    public ?int $commentId = null;
    public bool $subscribed = false;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [['id'], 'number', 'integerOnly' => true],
            [['userId'], 'required', 'message' => Craft::t('comments', 'You must be logged in to change your settings.')],
            [['ownerId'], 'required'],
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