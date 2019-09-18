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

    public function getSubscribe($ownerId, $ownerSiteId, $userId)
    {
        $result = $this->_createSubscribeQuery()
            ->where(['ownerId' => $ownerId, 'ownerSiteId' => $ownerSiteId, 'userId' => $userId])
            ->one();

        return $result ? new SubscribeModel($result) : null;
    }

    public function hasSubscribed($ownerId, $ownerSiteId, $userId)
    {
        $settings = Comments::$plugin->getSettings();

        // Check for any database records, but if none, make sure to check the global
        $hasSubscribed = $this->_createSubscribeQuery()
            ->where([
                'ownerId' => $ownerId,
                'ownerSiteId' => $ownerSiteId,
                'userId' => $userId,
            ])
            ->one();

        if ($hasSubscribed) {
            return $hasSubscribed['subscribed'];
        } else if (!$settings->notificationReplyEnabled) {
            return false;
        }

        return true;
    }

    public function toggleSubscribe(SubscribeModel $subscribe, bool $runValidation = true): bool
    {
        $settings = Comments::$plugin->getSettings();

        $subscribed = !$subscribe->subscribed;

        // Make sure to check if null - that means there's no records, and we need to check the global
        // setting first to get the initial state - then toggle that.
        if (is_null($subscribe->subscribed)) {
            if ($settings->notificationReplyEnabled) {
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
                'subscribed',
            ])
            ->from(['{{%comments_subscribe}}']);
    }

}
