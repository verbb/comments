<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\events\FlagEvent;
use verbb\comments\errors\FlagNotFoundException;
use verbb\comments\models\Flag as FlagModel;
use verbb\comments\records\Flag as FlagRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;

class FlagsService extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_FLAG = 'beforeSaveFlag';
    const EVENT_AFTER_SAVE_FLAG = 'afterSaveFlag';
    const EVENT_BEFORE_DELETE_FLAG = 'beforeDeleteFlag';
    const EVENT_AFTER_DELETE_FLAG = 'afterDeleteFlag';


    // Properties
    // =========================================================================

    private $_flagsById;


    // Public Methods
    // =========================================================================

    public function getFlagByCommentId(int $commentId)
    {
        $result = $this->_createFlagsQuery()
            ->where(['commentId' => $commentId])
            ->one();

        return $result ? new FlagModel($result) : null;
    }

    public function getFlagByUser(int $commentId, $userId)
    {
        $result = $this->_createFlagsQuery()
            ->where(['commentId' => $commentId, 'userId' => $userId])
            ->one();

        return $result ? new FlagModel($result) : null;
    }

    public function getFlagsByCommentId(int $commentId)
    {
        return $this->_createFlagsQuery()
            ->where(['commentId' => $commentId])
            ->count();
    }

    public function hasFlagged($comment, $user)
    {
        return $this->_createFlagsQuery()
            ->where(['commentId' => $comment->id, 'userId' => $user->id])
            ->exists();
    }

    public function isOverFlagThreshold($comment)
    {
        $threshold = Comments::$plugin->getSettings()->flaggedCommentLimit;
        $flags = $this->getFlagsByCommentId($comment->id);

        if ($flags >= $threshold) {
            return true;
        }

        return false;
    }

    public function toggleFlag(FlagModel $flag, bool $runValidation = true): bool
    {
        $isNewFlag = !$flag->id;

        if ($isNewFlag) {
            return $this->saveFlag($flag, $runValidation);
        } else {
            return $this->deleteFlag($flag);
        }
    }

    public function saveFlag(FlagModel $flag, bool $runValidation = true): bool
    {
        $isNewFlag = !$flag->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_FLAG)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_FLAG, new FlagEvent([
                'flag' => $flag,
                'isNew' => $isNewFlag,
            ]));
        }

        if ($runValidation && !$flag->validate()) {
            Craft::info('Flag not saved due to validation error.', __METHOD__);
            return false;
        }

        $flagRecord = $this->_getFlagRecordById($flag->id);

        $flagRecord->commentId = $flag->commentId;
        $flagRecord->userId = $flag->userId;

        // Save the record
        $flagRecord->save(false);

        // Now that we have a ID, save it on the model
        if ($isNewFlag) {
            $flag->id = $flagRecord->id;
        }

        // Might as well update our cache of the model while we have it.
        $this->_flagsById[$flag->id] = $flag;

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_FLAG)) {
            $this->trigger(self::EVENT_AFTER_SAVE_FLAG, new FlagEvent([
                'flag' => $flag,
                'isNew' => $isNewFlag,
            ]));
        }

        return true;
    }

    public function deleteFlagById(int $flagId): bool
    {
        $flag = $this->getFlagById($flagId);

        if (!$flag) {
            return false;
        }

        return $this->deleteFlag($flag);
    }

    public function deleteFlag(FlagModel $flag): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_FLAG)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_FLAG, new FlagEvent([
                'flag' => $flag,
            ]));
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%comments_flags}}', ['id' => $flag->id])
            ->execute();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_FLAG)) {
            $this->trigger(self::EVENT_AFTER_DELETE_FLAG, new FlagEvent([
                'flag' => $flag,
            ]));
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _getFlagRecordById(int $flagId = null): FlagRecord
    {
        if ($flagId !== null) {
            $flagRecord = FlagRecord::findOne($flagId);

            if (!$flagRecord) {
                throw new FlagNotFoundException("No flag exists with the ID '{$flagId}'");
            }
        } else {
            $flagRecord = new FlagRecord();
        }

        return $flagRecord;
    }

    private function _createFlagsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'commentId',
                'userId',
            ])
            ->from(['{{%comments_flags}}']);
    }

}
