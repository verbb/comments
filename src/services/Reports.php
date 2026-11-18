<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\compatibility\events\LegacyEngagementEvents;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ReportStatus;
use verbb\comments\events\ReportChangeEvent;
use verbb\comments\exceptions\RefusedException;
use verbb\comments\records\Report as ReportRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use yii\base\Event;
use yii\base\UserException;

use DateTime;
use InvalidArgumentException;

/**
 * Report creation, open-count batching, resolve/dismiss.
 */
class Reports extends Component
{
    // Constants
    // =========================================================================

    /** Comments 3 flags had no reason. */
    public const REASON_LEGACY = 'legacy';

    /** Before someone reports a comment. Setting `$isValid` to false stops it. */
    public const EVENT_BEFORE_REPORT = 'beforeReport';

    /** After someone reports a comment. */
    public const EVENT_AFTER_REPORT = 'afterReport';

    /** After someone takes back their report. Moderators resolving reports are `Moderation`'s events. */
    public const EVENT_AFTER_WITHDRAW_REPORT = 'afterWithdrawReport';


    // Properties
    // =========================================================================

    // Comments whose reports changed during this request, so counts a query loaded earlier are stale.
    private array $_changedCommentIds = [];


    // Public Methods
    // =========================================================================

    public function reasonLabel(?string $reason): string
    {
        if ($reason === self::REASON_LEGACY) {
            return Craft::t('comments', 'Legacy report');
        }

        foreach (CommentsPlugin::$plugin->getFrontend()::reportReasons() as $option) {
            if ($option['value'] === $reason) {
                return $option['label'];
            }
        }

        return Craft::t('comments', 'Other');
    }

    public function create(
        int $commentId,
        int $identityId,
        ?string $reason = null,
        ?string $details = null,
    ): ReportRecord {
        $comment = CommentsPlugin::$plugin->getComments()->getCommentById($commentId);

        if (!$comment) {
            throw new InvalidArgumentException("Comment {$commentId} was not found.");
        }

        if (!CommentsPlugin::$plugin->getIdentities()->getById($identityId)) {
            throw new InvalidArgumentException("Identity {$identityId} was not found.");
        }

        $open = ReportRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
            'status' => ReportStatus::Open->value,
        ]);

        if ($open) {
            throw new UserException(Craft::t('comments', 'You’ve already reported this comment.'));
        }

        $event = $this->_reportEvent($comment, $identityId, $reason, $details, true);

        if ($event) {
            $this->trigger(self::EVENT_BEFORE_REPORT, $event);
            LegacyEngagementEvents::report($event, true);

            if (!$event->isValid) {
                throw new RefusedException(Craft::t('comments', 'Your report couldn’t be sent.'));
            }
        }

        $record = new ReportRecord([
            'commentId' => $commentId,
            'identityId' => $identityId,
            'reason' => $reason,
            'details' => $details,
            'status' => ReportStatus::Open->value,
            'uid' => StringHelper::UUID(),
        ]);
        $record->save(false);
        $this->_changedCommentIds[$commentId] = true;
        CommentsPlugin::$plugin->getModeration()->clearQueueCounts();
        $this->_refreshFolded();

        CommentsPlugin::$plugin->getNotifications()->queueForReport($comment, $record);

        if ($event) {
            $event->report = $record;
            $this->trigger(self::EVENT_AFTER_REPORT, $event);
            LegacyEngagementEvents::report($event, false);
        }

        return $record;
    }

    public function resolve(int $reportId, ?User $actor = null, ?int $moderationActionId = null): ReportRecord
    {
        return $this->_close($reportId, ReportStatus::Resolved, $actor, $moderationActionId);
    }

    public function dismiss(int $reportId, ?User $actor = null): ReportRecord
    {
        return $this->_close($reportId, ReportStatus::Dismissed, $actor, null);
    }

    public function getOpenForComment(int $commentId): array
    {
        return ReportRecord::find()
            ->where([
                'commentId' => $commentId,
                'status' => ReportStatus::Open->value,
            ])
            ->orderBy(['dateCreated' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * Whether reports on a comment were made or settled during this request, so counts a query loaded before are stale.
     */
    public function hasChangedInRequest(int $commentId): bool
    {
        return isset($this->_changedCommentIds[$commentId]);
    }

    /** Open report counts keyed by comment ID. */
    public function getOpenCounts(array $commentIds): array
    {
        $commentIds = array_values(array_unique(array_filter(array_map('intval', $commentIds))));
        $result = array_fill_keys($commentIds, 0);

        if ($commentIds === []) {
            return $result;
        }

        $rows = (new Query())
            ->select(['commentId', 'total' => 'COUNT(*)'])
            ->from('{{%comments_reports}}')
            ->where([
                'commentId' => $commentIds,
                'status' => ReportStatus::Open->value,
            ])
            ->groupBy(['commentId'])
            ->all();

        foreach ($rows as $row) {
            $result[(int)$row['commentId']] = (int)$row['total'];
        }

        return $result;
    }

    public function getOpenForIdentity(int $commentId, int $identityId): ?ReportRecord
    {
        return ReportRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
            'status' => ReportStatus::Open->value,
        ]);
    }

    /**
     * Withdraw the caller's open Report (v3 un-flag behaviour).
     */
    public function withdrawOpen(int $commentId, int $identityId): bool
    {
        $open = $this->getOpenForIdentity($commentId, $identityId);

        if (!$open) {
            return false;
        }

        $comment = CommentsPlugin::$plugin->getComments()->getCommentById($commentId);
        $event = $comment ? $this->_reportEvent($comment, $identityId, $open->reason, $open->details, false) : null;

        if ($event) {
            $event->report = $open;
            LegacyEngagementEvents::report($event, true);
        }

        $open->status = ReportStatus::Dismissed->value;
        $open->resolvedAt = Db::prepareDateForDb(new DateTime());
        $open->save(false);
        $this->_changedCommentIds[$commentId] = true;
        CommentsPlugin::$plugin->getModeration()->clearQueueCounts();
        $this->_refreshFolded();

        if ($event) {
            $this->trigger(self::EVENT_AFTER_WITHDRAW_REPORT, $event);
            LegacyEngagementEvents::report($event, false);
        }

        return true;
    }

    /**
     * Toggle report state for an Identity (open ↔ withdrawn).
     *
     * Returns true when an open Report exists after the toggle.
     */
    public function toggle(int $commentId, int $identityId, ?string $reason = null, ?string $details = null): bool
    {
        if ($this->getOpenForIdentity($commentId, $identityId)) {
            $this->withdrawOpen($commentId, $identityId);

            return false;
        }

        $this->create($commentId, $identityId, $reason, $details);

        return true;
    }


    // Private Methods
    // =========================================================================

    /**
     * Open reports decide whether the public thread folds a comment away, while **Hide After Reports**
     * is on, so cached threads are refreshed when they change.
     */
    private function _refreshFolded(): void
    {
        if (CommentsPlugin::$plugin->getSettings()->getFlaggedCommentLimit() !== null) {
            CommentsPlugin::$plugin->getApi()->invalidateCaches();
        }
    }

    /**
     * The event for a report being made or taken back, or null when nothing is listening.
     */
    private function _reportEvent(Comment $comment, int $identityId, ?string $reason, ?string $details, bool $reported): ?ReportChangeEvent
    {
        $names = $reported ? [self::EVENT_BEFORE_REPORT, self::EVENT_AFTER_REPORT] : [self::EVENT_AFTER_WITHDRAW_REPORT];
        $legacy = $reported ? [Flags::EVENT_BEFORE_SAVE_FLAG, Flags::EVENT_AFTER_SAVE_FLAG] : [Flags::EVENT_BEFORE_DELETE_FLAG, Flags::EVENT_AFTER_DELETE_FLAG];
        $listening = array_filter($names, fn(string $name): bool => $this->hasEventHandlers($name)) !== []
            || (Compatibility::enabled() && array_filter($legacy, static fn(string $name): bool => Event::hasHandlers(Flags::class, $name)) !== []);

        if (!$listening) {
            return null;
        }

        $userId = CommentsPlugin::$plugin->getIdentities()->getById($identityId)?->userId;

        return new ReportChangeEvent([
            'comment' => $comment,
            'identityId' => $identityId,
            'user' => $userId ? User::find()->id((int)$userId)->status(null)->one() : null,
            'reason' => $reason,
            'details' => $details,
            'reported' => $reported,
        ]);
    }

    private function _close(
        int $reportId,
        ReportStatus $status,
        ?User $actor,
        ?int $moderationActionId,
    ): ReportRecord {
        $report = ReportRecord::findOne($reportId);

        if (!$report) {
            throw new InvalidArgumentException("Report {$reportId} was not found.");
        }

        if ($report->status !== ReportStatus::Open->value) {
            return $report;
        }

        $report->status = $status->value;
        $report->resolvedAt = Db::prepareDateForDb(new DateTime());
        $report->resolvedByUserId = $actor?->id;
        $report->moderationActionId = $moderationActionId;
        $report->save(false);
        $this->_changedCommentIds[(int)$report->commentId] = true;
        CommentsPlugin::$plugin->getModeration()->clearQueueCounts();
        $this->_refreshFolded();

        return $report;
    }
}
