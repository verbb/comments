<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ReportStatus;
use verbb\comments\records\Report as ReportRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\StringHelper;

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

        CommentsPlugin::$plugin->getNotifications()->queueForReport($comment, $record);

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

        $open->status = ReportStatus::Dismissed->value;
        $open->resolvedAt = Db::prepareDateForDb(new DateTime());
        $open->save(false);
        $this->_changedCommentIds[$commentId] = true;
        CommentsPlugin::$plugin->getModeration()->clearQueueCounts();

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

        return $report;
    }
}
