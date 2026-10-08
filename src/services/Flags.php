<?php
namespace verbb\comments\services;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\events\FlagEvent;
use verbb\comments\errors\FlagNotFoundException;
use verbb\comments\models\Flag as FlagModel;
use verbb\comments\records\Flag as FlagRecord;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\db\Query;

use yii\base\Event;

class Flags extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_FLAG = 'beforeSaveFlag';
    public const EVENT_AFTER_SAVE_FLAG = 'afterSaveFlag';
    public const EVENT_BEFORE_DELETE_FLAG = 'beforeDeleteFlag';
    public const EVENT_AFTER_DELETE_FLAG = 'afterDeleteFlag';


    // Properties
    // =========================================================================
    
    protected string $sessionName = 'comments_flag';

    private array $_flagCounts = [];
    private array $_viewerFlags = [];


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        foreach ([Comment::EVENT_AFTER_DELETE, Comment::EVENT_AFTER_RESTORE] as $event) {
            Event::on(Comment::class, $event, function(): void {
                $this->_invalidateFlagCaches();
            });
        }
    }

    public function getFlagByUser(int $commentId, $userId)
    {
        $flags = $this->_getViewerFlags($commentId, $userId);

        return $flags ? reset($flags) : null;
    }

    public function getFlagsByCommentId(int $commentId): int
    {
        if (!array_key_exists($commentId, $this->_flagCounts)) {
            $commentIds = $this->_getUncachedCommentIds($commentId, $this->_flagCounts);
            $rows = (new Query())
                ->select(['commentId', 'total' => 'COUNT(*)'])
                ->from('{{%comments_flags}}')
                ->where(['commentId' => $commentIds])
                ->groupBy('commentId')
                ->all();

            foreach ($commentIds as $id) {
                $this->_flagCounts[$id] = 0;
            }

            foreach ($rows as $row) {
                $this->_flagCounts[$row['commentId']] = (int)$row['total'];
            }
        }

        return $this->_flagCounts[$commentId];
    }

    public function hasFlagged($comment, $user): bool
    {
        return (bool)$this->_getViewerFlags($comment->id, $user->id ?? null);
    }

    public function isOverFlagThreshold($comment): bool
    {
        $threshold = Comments::$plugin->getSettings()->flaggedCommentLimit;
        $flags = $this->getFlagsByCommentId($comment->id);

        return $flags >= $threshold;
    }

    public function toggleFlag(FlagModel $flag, bool $runValidation = true): bool
    {
        $settings = Comments::$plugin->getSettings();

        $isNewFlag = !$flag->id;

        if ($isNewFlag) {
            $result = $this->saveFlag($flag, $runValidation);

            if ($result && $settings->notificationFlaggedEnabled) {
                Comments::$plugin->getComments()->sendNotificationEmail('flag', $flag->getComment());
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
            Comments::info('Flag not saved due to validation error.');
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

        $this->_invalidateFlagCaches();

        // Now that we have an ID, save it on the model
        if ($isNewFlag) {
            $flag->id = $flagRecord->id;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_FLAG)) {
            $this->trigger(self::EVENT_AFTER_SAVE_FLAG, new FlagEvent([
                'flag' => $flag,
                'isNew' => $isNewFlag,
            ]));
        }

        return true;
    }

    public function deleteFlag(FlagModel $flag): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_FLAG)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_FLAG, new FlagEvent([
                'flag' => $flag,
            ]));
        }

        Db::delete('{{%comments_flags}}', ['id' => $flag->id]);

        $this->_invalidateFlagCaches();

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

    private function _getViewerFlags(int $commentId, $userId): array
    {
        $identity = $userId ? ['userId' => $userId] : ['sessionId' => $this->_getSessionId()];
        $key = key($identity) . ':' . reset($identity);
        $cached = $this->_viewerFlags[$key] ?? [];

        if (!array_key_exists($commentId, $cached)) {
            $commentIds = $this->_getUncachedCommentIds($commentId, $cached);
            $rows = $this->_createFlagsQuery()
                ->where(['commentId' => $commentIds])
                ->andWhere($identity)
                ->all();

            foreach ($commentIds as $id) {
                $cached[$id] = [];
            }

            foreach ($rows as $row) {
                $cached[$row['commentId']][] = new FlagModel($row);
            }

            $this->_viewerFlags[$key] = $cached;
        }

        return $cached[$commentId];
    }

    private function _getUncachedCommentIds(int $commentId, array $cached): array
    {
        return array_values(array_diff(array_unique(array_merge(
            Comments::$plugin->getRenderCache()->getCommentIds(),
            [$commentId],
        )), array_keys($cached)));
    }

    private function _invalidateFlagCaches(): void
    {
        $this->_flagCounts = [];
        $this->_viewerFlags = [];
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

    private function _getFlagRecordById(int $flagId = null): ?FlagRecord
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
