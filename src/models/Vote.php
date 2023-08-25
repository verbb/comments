<?php
namespace verbb\comments\models;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\records\Vote as VoteRecord;

use Craft;
use craft\base\Model;
use craft\base\ElementInterface;
use craft\elements\User;

class Vote extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $commentId = null;
    public ?int $userId = null;
    public ?string $sessionId = null;
    public ?string $lastIp = null;
    public ?int $upvote = null;
    public ?int $downvote = null;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        $currentUser = Comments::$plugin->getService()->getUser();

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
                'message' => Craft::t('comments', 'You can only vote on a comment once.'),
            ],
        ];
    }

    public function getComment(): ?Comment
    {
        if ($this->commentId) {
            return Comment::find()->id($this->commentId)->one();
        }

        return null;
    }


    public function getUser(): ?User
    {
        if ($this->userId) {
            return User::find()->id($this->userId)->one();
        }

        return null;
    }

}