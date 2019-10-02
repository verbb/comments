<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\events\SubscribeEvent;
use verbb\comments\errors\SubscribeNotFoundException;
use verbb\comments\models\Subscribe as SubscribeModel;
use verbb\comments\records\Subscribe as SubscribeRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;

class SubscribeService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_SUBSCRIBE = 'beforeSaveSubscribe';
    const EVENT_AFTER_SAVE_SUBSCRIBE = 'afterSaveSubscribe';
    const EVENT_BEFORE_DELETE_SUBSCRIBE = 'beforeDeleteSubscribe';
    const EVENT_AFTER_DELETE_SUBSCRIBE = 'afterDeleteSubscribe';


    // Public Methods
    // =========================================================================

    public function getAllSubscribers($ownerId, $ownerSiteId, $commentId)
    {
        $items = [];

        // First, find all subscribers that are subscribing to the element overall
        $results = $this->_createSubscribeQuery()
            ->where(['ownerId' => $ownerId, 'ownerSiteId' => $ownerSiteId, 'commentId' => $commentId, 'subscribed' => true])
            ->all();

        foreach ($results as $result) {
            $items[] = new SubscribeModel($result);
        }

        return $items;
    }

    public function getSubscribe($ownerId, $ownerSiteId, $userId, $commentId = null)
    {
        $result = $this->_createSubscribeQuery()
            ->where(['ownerId' => $ownerId, 'ownerSiteId' => $ownerSiteId, 'userId' => $userId, 'commentId' => $commentId])
            ->one();

        return $result ? new SubscribeModel($result) : null;
    }

    public function hasSubscribed($ownerId, $ownerSiteId, $userId, $commentId = null)
    {
        $settings = Comments::$plugin->getSettings();

        // Check if subscribed globally to the element, or to a particular comment
        $hasSubscribed = $this->_createSubscribeQuery()
            ->where([
                'ownerId' => $ownerId,
                'ownerSiteId' => $ownerSiteId,
                'userId' => $userId,
                'commentId' => $commentId,
            ])
            ->one();

        if ($hasSubscribed) {
            return (bool)$hasSubscribed['subscribed'];
        } else if ($commentId && $settings->notificationSubscribeDefault) {
            // If not specifically subscribed, check if we're checking against a comment. 
            // If its the own users' they're automatically subscribed to replies on their own comments
            $comment = Comments::$plugin->comments->getCommentById($commentId, $ownerSiteId);

            if ($comment && $comment->userId == $userId) {
                return true;
            }
        }

        return false;
    }

    public function toggleSubscribe(SubscribeModel $subscribe, bool $runValidation = true): bool
    {
        $settings = Comments::$plugin->getSettings();

        $subscribed = !$subscribe->subscribed;

        // Make sure to check if null - that means there's no records. We want to check if
        // we're toggling on our own comment, and if so, we're unsubscribing, because by default
        // you subscribe to your own comment thread.
        if (is_null($subscribe->subscribed) && $subscribe->commentId && $settings->notificationSubscribeDefault) {
            $comment = Comments::$plugin->comments->getCommentById($subscribe->commentId, $subscribe->ownerSiteId);

            if ($comment && $comment->userId == $subscribe->userId) {
                $subscribed = false;
            }
        }

        $subscribe->subscribed = $subscribed;

        return $this->saveSubscribe($subscribe, $runValidation);
    }

    public function saveSubscribe(SubscribeModel $subscribe, bool $runValidation = true): bool
    {
        $isNewSubscribe = !$subscribe->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_SUBSCRIBE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_SUBSCRIBE, new SubscribeEvent([
                'subscribe' => $subscribe,
                'isNew' => $isNewSubscribe,
            ]));
        }

        if ($runValidation && !$subscribe->validate()) {
            Craft::info('Subscribe not saved due to validation error.', __METHOD__);
            return false;
        }

        $subscribeRecord = $this->_getSubscribeRecordById($subscribe->id);

        $subscribeRecord->ownerId = $subscribe->ownerId;
        $subscribeRecord->ownerSiteId = $subscribe->ownerSiteId;
        $subscribeRecord->userId = $subscribe->userId;
        $subscribeRecord->commentId = $subscribe->commentId;
        $subscribeRecord->subscribed = $subscribe->subscribed;

        // Save the record
        $subscribeRecord->save(false);

        // Now that we have a ID, save it on the model
        if ($isNewSubscribe) {
            $subscribe->id = $subscribeRecord->id;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_SUBSCRIBE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_SUBSCRIBE, new SubscribeEvent([
                'subscribe' => $subscribe,
                'isNew' => $isNewSubscribe,
            ]));
        }

        return true;
    }

    public function deleteSubscribeById(int $subscribeId): bool
    {
        $subscribe = $this->getSubscribeById($subscribeId);

        if (!$subscribe) {
            return false;
        }

        return $this->deleteSubscribe($subscribe);
    }

    public function deleteSubscribe(SubscribeModel $subscribe): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_SUBSCRIBE)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_SUBSCRIBE, new SubscribeEvent([
                'subscribe' => $subscribe,
            ]));
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%comments_subscribe}}', ['id' => $subscribe->id])
            ->execute();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_SUBSCRIBE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_SUBSCRIBE, new SubscribeEvent([
                'subscribe' => $subscribe,
            ]));
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _getSubscribeRecordById(int $subscribeId = null): SubscribeRecord
    {
        if ($subscribeId !== null) {
            $subscribeRecord = SubscribeRecord::findOne($subscribeId);

            if (!$subscribeRecord) {
                throw new SubscribeNotFoundException("No subscribe exists with the ID '{$subscribeId}'");
            }
        } else {
            $subscribeRecord = new SubscribeRecord();
        }

        return $subscribeRecord;
    }

    private function _createSubscribeQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'ownerId',
                'ownerSiteId',
                'userId',
                'commentId',
                'subscribed',
            ])
            ->from(['{{%comments_subscribe}}']);
    }

}
