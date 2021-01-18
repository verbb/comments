<?php
namespace verbb\comments\models;

use verbb\comments\elements\Comment;
use verbb\comments\records\Subscribe as SubscribeRecord;

use Craft;
use craft\base\Model;
use craft\validators\UniqueValidator;

class Subscribe extends Model
{
    // Public Properties
    // =========================================================================

    public $id;
    public $ownerId;
    public $ownerSiteId;
    public $userId;
    public $commentId;
    public $subscribed;


    // Public Methods
    // =========================================================================

    public function rules()
    {
        return [
            [['id'], 'number', 'integerOnly' => true],
            [['userId'], 'required', 'message' => Craft::t('comments', 'You must be logged in to change your settings.')],
            [['ownerId'], 'required'],
        ];
    }

    public function getComment()
    {
        if ($this->commentId) {
            return Comment::find()->id($this->commentId)->one();
        }

        return null;
    }

}