<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\events\FlagEvent;
use verbb\comments\errors\FlagNotFoundException;
use verbb\comments\models\Flag as FlagModel;
use verbb\comments\records\Flag as FlagRecord;

use Craft;
use craft\base\Component;
use craft\base\MemoizableArray;
use craft\helpers\ArrayHelper;
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

    protected $sessionName = 'comments_flag';

    private $_flags;


    // Public Methods
    // =========================================================================

    public function __serialize()
    {
        $vars = get_object_vars($this);
        unset($vars['_flags']);
        return $vars;
    }

    public function getFlagByCommentId(int $commentId)
    {
        return $this->_flags($commentId)->firstWhere('commentId', $commentId);
    }

    public function getFlagByUser(int $commentId, $userId)
    {
        // Try and fetch flags for a user, if not, use their sessionId
        $flags = $this->_flags($commentId);
        $criteria = ['commentId' => $commentId];

        if ($userId) {
            $criteria['userId'] = $userId;
        } else {
            $criteria['sessionId'] = $this->_getSessionId();
        }

        if ($items = ArrayHelper::whereMultiple($flags, $criteria)) {
            return reset($items);
        }

        return null;
    }

    public function getFlagsByCommentId(int $commentId)
    {
        return count($this->_flags($commentId)->where('commentId', $commentId));
    }

    public function hasFlagged($comment, $user)
    {
        // Try and fetch flags for a user, if not, use their sessionId
        $flags = $this->_flags($comment->id);
        $criteria = ['commentId' => $comment->id];

        if ($user && $user->id) {
            $criteria['userId'] = $user->id;
        } else {
            $criteria['sessionId'] = $this->_getSessionId();
        }

        return (bool)ArrayHelper::whereMultiple($flags, $criteria);
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
        $settings = Comments::$plugin->getSettings();

        $isNewFlag = !$flag->id;

        if ($isNewFlag) {
            $result = $this->saveFlag($flag, $runValidation);

            if ($result && $settings->notificationFlaggedEnabled) {
                Comments::$plugin->comments->sendFlagNotificationEmail($flag->getComment());
            }
        } else {
            $result = $this->deleteFlag($flag);
        }

        return $result;
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
        $flagRecord->sessionId = $this->_getSessionId();

        if (Craft::$app->getConfig()->getGeneral()->storeUserIps) {
            $flagRecord->lastIp = Craft::$app->getRequest()->userIP;
        }

        // Save the record
        $flagRecord->save(false);

        // Now that we have a ID, save it on the model
        if ($isNewFlag) {
            $flag->id = $flagRecord->id;
        }

        // Clear caches
        $this->_flags = null;

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

        // Clear caches
        $this->_flags = null;

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_FLAG)) {
            $this->trigger(self::EVENT_AFTER_DELETE_FLAG, new FlagEvent([
                'flag' => $flag,
            ]));
        }

        return true;
    }

    public function generateSessionId(): string
    {
        return md5(uniqid(mt_rand(), true));
    }


    // Private Methods
    // =========================================================================

    private function _flags($commentId = null)
    {
        if ($this->_flags === null) {
            $flags = [];

            $memoize = true;
            $query = $this->_createFlagsQuery();

            // Check to see if we've set a collection of comments for rendering.
            // We limit the flags to only the flags for these comments, rather than the entire table
            if ($commentIds = Comments::$plugin->getRenderCache()->getCommentIds()) {
                $query->where(['commentId' => $commentIds]);
            } else if ($commentId) {
                $query->where(['commentId' => $commentId]);
                $memoize = false;
            }

            foreach ($query->all() as $result) {
                $flags[] = new FlagModel($result);
            }

            if ($memoize) {
                $this->_flags = new MemoizableArray($flags);
            } else {
                return $flags;
            }
        }

        return $this->_flags;
    }

    private function _getSessionId()
    {
        $session = Craft::$app->getSession();
        $sessionId = $session[$this->sessionName];

        if (!$sessionId) {
            $sessionId = $this->generateSessionId();
            $session->set($this->sessionName, $sessionId);
        }

        return $sessionId;
    }

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
