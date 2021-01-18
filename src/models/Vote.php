<?php
namespace verbb\comments\models;

use verbb\comments\elements\Comment;
use verbb\comments\records\Vote as VoteRecord;

use Craft;
use craft\base\Model;

class Vote extends Model
{
    // Public Properties
    // =========================================================================

    public $id;
    public $commentId;
    public $userId;
    public $sessionId;
    public $lastIp;
    public $upvote;
    public $downvote;


    // Public Methods
    // =========================================================================

    public function rules()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser) {
            $targetAttribute = ['userId', 'commentId', 'upvote', 'downvote'];
        } else {
            $targetAttribute = ['sessionId', 'commentId', 'upvote', 'downvote'];
        }

        return [
            [['id'], 'number', 'integerOnly' => true],
            [['commentId'], 'required'],
            [
                'commentId',
                'unique',
                'targetAttribute' => $targetAttribute,
                'targetClass' => VoteRecord::class,
                'message' => Craft::t('comments', 'You can only vote on a comment once.')
            ]
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