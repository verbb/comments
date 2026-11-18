<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\IdentityType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\RemovalReason;
use verbb\comments\enums\ReportStatus;
use verbb\comments\enums\RestrictionType;
use verbb\comments\enums\ReviewSetting;
use verbb\comments\events\ModerationEvent;
use verbb\comments\records\Comment as CommentRecord;
use verbb\comments\records\ModerationAction as ModerationActionRecord;
use verbb\comments\records\CommenterNote as CommenterNoteRecord;
use verbb\comments\records\ModeratorNote as ModeratorNoteRecord;
use verbb\comments\records\Restriction as RestrictionRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\records\Element as ElementRecord;

use yii\base\UserException;

use DateTime;
use InvalidArgumentException;

/**
 * Comment moderation lifecycle, action history, notes and participation restrictions.
 */
class Moderation extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_AFTER_CHANGE_STATE = 'afterChangeState';

    public const ACTOR_USER = 'user';
    public const ACTOR_SYSTEM = 'system';

    public const ACTION_APPROVE = 'approve';
    public const ACTION_REMOVE = 'remove';
    public const ACTION_RESTORE = 'restore';
    public const ACTION_MARK_SPAM = 'markSpam';
    public const ACTION_MARK_NOT_SPAM = 'markNotSpam';
    public const ACTION_HOLD = 'hold';
    public const ACTION_MODERATOR_EDIT = 'moderatorEdit';
    public const ACTION_AUTHOR_EDIT = 'authorEdit';
    public const ACTION_IMPORT = 'import';

    public const DECISION_APPROVE = 'approve';
    public const DECISION_SPAM = 'spam';
    public const DECISION_NOT_SPAM = 'not-spam';
    public const DECISION_REMOVE = 'remove';
    public const DECISION_RESTORE = 'restore';
    public const DECISION_KEEP = 'keep';

    public const DECISIONS = [
        self::DECISION_APPROVE,
        self::DECISION_SPAM,
        self::DECISION_NOT_SPAM,
        self::DECISION_REMOVE,
        self::DECISION_RESTORE,
        self::DECISION_KEEP,
    ];


    // Properties
    // =========================================================================

    private ?int $_lastActionId = null;

    /** Worked out once per request, as the nav and the index both ask. */
    private ?array $_queueCounts = null;


    // Public Methods
    // =========================================================================

    public function approve(
        Comment|int $comment,
        ?User $actor = null,
        ?string $reason = null,
        ?string $expectedVersion = null,
    ): Comment {
        return $this->_runMutation($comment, Moderation::ACTION_APPROVE, $actor, $reason, $expectedVersion);
    }

    public function remove(
        Comment|int $comment,
        ?User $actor = null,
        ?string $reason = null,
        ?string $expectedVersion = null,
    ): Comment {
        return $this->_runMutation($comment, Moderation::ACTION_REMOVE, $actor, $reason, $expectedVersion);
    }

    public function restore(
        Comment|int $comment,
        ?User $actor = null,
        ?string $reason = null,
        ?string $expectedVersion = null,
    ): Comment {
        return $this->_runMutation($comment, Moderation::ACTION_RESTORE, $actor, $reason, $expectedVersion);
    }

    public function markSpam(
        Comment|int $comment,
        ?User $actor = null,
        ?string $reason = null,
        ?string $expectedVersion = null,
    ): Comment {
        return $this->_runMutation($comment, Moderation::ACTION_MARK_SPAM, $actor, $reason, $expectedVersion);
    }

    public function markNotSpam(
        Comment|int $comment,
        ?User $actor = null,
        ?string $reason = null,
        ?string $expectedVersion = null,
    ): Comment {
        return $this->_runMutation($comment, Moderation::ACTION_MARK_NOT_SPAM, $actor, $reason, $expectedVersion);
    }

    /**
     * One moderator decision, as offered on the comment's page, the entry panel, the index and the site.
     * Keep and Remove also close the comment's open reports, so it leaves the Reported source.
     */
    public function decide(
        Comment|int $comment,
        string $decision,
        ?User $actor = null,
        ?string $reason = null,
        ?string $note = null,
    ): Comment {
        $comment = $this->_resolveComment($comment);
        $reports = CommentsPlugin::$plugin->getReports();

        if ($decision === self::DECISION_REMOVE) {
            $reason = $reason !== null && $reason !== '' ? $reason : null;

            if ($reason !== null && !RemovalReason::tryFrom($reason)) {
                throw new UserException(Craft::t('comments', 'Choose a reason for removing this comment.'));
            }
        } else {
            $reason = null;
        }

        $this->_lastActionId = null;

        $comment = match ($decision) {
            self::DECISION_APPROVE => $this->approve($comment, $actor),
            self::DECISION_SPAM => $this->markSpam($comment, $actor),
            self::DECISION_NOT_SPAM => $this->markNotSpam($comment, $actor),
            self::DECISION_REMOVE => $this->remove($comment, $actor, $reason),
            self::DECISION_RESTORE => $this->restore($comment, $actor),
            self::DECISION_KEEP => $comment,
            default => throw new UserException(Craft::t('comments', 'Invalid moderation decision.')),
        };

        if (in_array($decision, [self::DECISION_KEEP, self::DECISION_REMOVE, self::DECISION_SPAM], true)) {
            foreach ($reports->getOpenForComment((int)$comment->id) as $report) {
                if ($decision === self::DECISION_KEEP) {
                    $reports->dismiss((int)$report->id, $actor);
                } else {
                    $reports->resolve((int)$report->id, $actor, $this->_lastActionId);
                }
            }

            Craft::$app->getElements()->invalidateCachesForElement($comment);
        }

        $note = trim((string)$note);

        if ($note !== '') {
            $this->addNote($comment, $note, $actor);
        }

        return $comment;
    }

    /**
     * The decisions that make sense for a comment as it stands, for someone who can make them.
     */
    public function availableDecisions(Comment $comment, ?User $actor): array
    {
        if (!$this->canModerateComment($actor, $comment)) {
            return [];
        }

        $canRemove = $this->canRemoveComment($actor);
        $state = $this->currentState($comment);
        $decisions = match ($state) {
            ModerationState::Pending => [self::DECISION_APPROVE, self::DECISION_SPAM, self::DECISION_REMOVE],
            ModerationState::Spam => [self::DECISION_NOT_SPAM],
            ModerationState::Removed => [self::DECISION_RESTORE],
            ModerationState::Approved => $this->hasOpenReports($comment) ? [self::DECISION_KEEP, self::DECISION_REMOVE] : [],
        };

        return array_values(array_filter($decisions, static fn(string $decision): bool => $canRemove || !in_array($decision, [self::DECISION_REMOVE, self::DECISION_RESTORE], true)));
    }

    public function decisionLabel(string $decision): string
    {
        return match ($decision) {
            self::DECISION_APPROVE => Craft::t('comments', 'Approve'),
            self::DECISION_SPAM => Craft::t('comments', 'Spam'),
            self::DECISION_NOT_SPAM => Craft::t('comments', 'Not spam'),
            self::DECISION_REMOVE => Craft::t('comments', 'Remove'),
            self::DECISION_RESTORE => Craft::t('comments', 'Restore'),
            self::DECISION_KEEP => Craft::t('comments', 'Keep'),
            default => $decision,
        };
    }

    public function decisionMessage(string $decision): string
    {
        return match ($decision) {
            self::DECISION_APPROVE => Craft::t('comments', 'Comment approved.'),
            self::DECISION_SPAM => Craft::t('comments', 'Comment marked as spam.'),
            self::DECISION_NOT_SPAM => Craft::t('comments', 'Comment returned to Needs review.'),
            self::DECISION_REMOVE => Craft::t('comments', 'Comment removed.'),
            self::DECISION_RESTORE => Craft::t('comments', 'Comment restored.'),
            self::DECISION_KEEP => Craft::t('comments', 'Reports dismissed. The comment stays up.'),
            default => Craft::t('comments', 'Comment updated.'),
        };
    }

    /**
     * What's waiting for a moderator, for the nav badge and the index's Moderation sources.
     */
    public function getQueueCounts(): array
    {
        if ($this->_queueCounts !== null) {
            return $this->_queueCounts;
        }

        $pending = (int)(new Query())
            ->from(['c' => CommentRecord::tableName()])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.id]]')
            ->where(['c.moderationState' => ModerationState::Pending->value, 'e.dateDeleted' => null])
            ->count();

        $reported = (int)(new Query())
            ->from(['c' => CommentRecord::tableName()])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.id]]')
            ->where(['c.moderationState' => ModerationState::Approved->value, 'e.dateDeleted' => null])
            ->andWhere(['exists', (new Query())->from(['r' => '{{%comments_reports}}'])->where('[[r.commentId]] = [[c.id]]')->andWhere(['r.status' => ReportStatus::Open->value])])
            ->count();

        return $this->_queueCounts = ['pending' => $pending, 'reported' => $reported];
    }

    /**
     * Forget the counts once something changes what's waiting.
     */
    public function clearQueueCounts(): void
    {
        $this->_queueCounts = null;
    }

    public function hasOpenReports(Comment $comment): bool
    {
        // A comment from a query already has its count, which saves a query per comment on a moderator's page.
        return $comment->getFlags() > 0;
    }

    public function setState(Comment|int $comment, ModerationState $state, ?User $actor = null): Comment
    {
        $comment = $this->_resolveComment($comment);
        $currentState = $this->currentState($comment);

        if ($currentState === $state) {
            return $comment;
        }

        $action = match ($state) {
            ModerationState::Approved => $currentState === ModerationState::Removed
                ? self::ACTION_RESTORE
                : self::ACTION_APPROVE,
            ModerationState::Pending => $currentState === ModerationState::Spam
                ? self::ACTION_MARK_NOT_SPAM
                : self::ACTION_HOLD,
            ModerationState::Spam => self::ACTION_MARK_SPAM,
            ModerationState::Removed => self::ACTION_REMOVE,
        };

        return $this->_runMutation($comment, $action, $actor, null, null);
    }

    public function addNote(Comment|int $comment, string $note, ?User $actor = null): ModeratorNoteRecord
    {
        $comment = $this->_resolveComment($comment);
        $note = trim($note);

        if ($note === '') {
            throw new UserException(Craft::t('comments', 'Internal note cannot be empty.'));
        }

        $record = new ModeratorNoteRecord([
            'commentId' => (int)$comment->id,
            'userId' => $actor?->id,
            'note' => $note,
        ]);
        $record->save(false);

        return $record;
    }

    public function getNotes(int $commentId): array
    {
        return ModeratorNoteRecord::find()
            ->where(['commentId' => $commentId])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->all();
    }

    /**
     * A moderator's note about a commenter, for their Activity tab.
     */
    public function addCommenterNote(int $identityId, string $note, ?User $actor = null): CommenterNoteRecord
    {
        $note = trim($note);

        if ($note === '') {
            throw new UserException(Craft::t('comments', 'Internal note cannot be empty.'));
        }

        if (!CommentsPlugin::$plugin->getIdentities()->getById($identityId)) {
            throw new InvalidArgumentException("Identity {$identityId} was not found.");
        }

        $record = new CommenterNoteRecord([
            'identityId' => $identityId,
            'userId' => $actor?->id,
            'note' => $note,
        ]);
        $record->save(false);

        return $record;
    }

    /**
     * @return CommenterNoteRecord[] Newest first.
     */
    public function getCommenterNotes(int $identityId): array
    {
        return CommenterNoteRecord::find()
            ->where(['identityId' => $identityId])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->all();
    }

    public function blockIdentity(
        int $identityId,
        ?int $conversationId = null,
        ?User $actor = null,
        ?string $reason = null,
        ?DateTime $endsAt = null,
    ): RestrictionRecord {
        return $this->_createRestriction(
            $identityId,
            RestrictionType::Blocked,
            $conversationId,
            $actor,
            $reason,
            $endsAt,
        );
    }

    public function moderateIdentity(
        int $identityId,
        ?int $conversationId = null,
        ?User $actor = null,
        ?string $reason = null,
        ?DateTime $endsAt = null,
    ): RestrictionRecord {
        return $this->_createRestriction(
            $identityId,
            RestrictionType::Moderated,
            $conversationId,
            $actor,
            $reason,
            $endsAt,
        );
    }

    public function revokeRestriction(int $restrictionId, ?User $actor = null): RestrictionRecord
    {
        $restriction = RestrictionRecord::findOne($restrictionId);

        if (!$restriction) {
            throw new InvalidArgumentException("Restriction {$restrictionId} was not found.");
        }

        if ($restriction->revokedAt) {
            return $restriction;
        }

        $restriction->revokedAt = Db::prepareDateForDb(new DateTime());
        $restriction->revokedByUserId = $actor?->id;
        $restriction->save(false);

        return $restriction;
    }

    /**
     * A commenter has one review setting at a time across the site. Setting one replaces whatever
     * site-wide setting they had; restrictions limited to one conversation are left alone.
     */
    public function setReviewSetting(int $identityId, ReviewSetting $setting, ?User $actor = null, ?string $note = null): ?RestrictionRecord
    {
        if (!CommentsPlugin::$plugin->getIdentities()->getById($identityId)) {
            throw new InvalidArgumentException("Identity {$identityId} was not found.");
        }

        $note = trim((string)$note) ?: null;

        return Craft::$app->getDb()->transaction(function() use ($identityId, $setting, $actor, $note): ?RestrictionRecord {
            foreach ($this->getActiveRestrictions($identityId) as $restriction) {
                if (!$restriction->conversationId) {
                    $this->revokeRestriction((int)$restriction->id, $actor);
                }
            }

            $type = $setting->restrictionType();

            // Normal is only the end of the other settings, with nowhere of its own for a note, so the note
            // is kept as a note about the commenter.
            if (!$type) {
                if ($note !== null) {
                    $this->addCommenterNote($identityId, $note, $actor);
                }

                return null;
            }

            return $this->_createRestriction($identityId, $type, null, $actor, $note, null);
        });
    }

    public function getReviewSetting(int $identityId): ReviewSetting
    {
        $active = array_filter($this->getActiveRestrictions($identityId), static fn(RestrictionRecord $restriction): bool => !$restriction->conversationId);
        $types = array_map(static fn(RestrictionRecord $restriction): string => (string)$restriction->type, $active);

        // Rows from before settings replaced each other may overlap. The strictest wins.
        foreach ([RestrictionType::Blocked, RestrictionType::Moderated, RestrictionType::Trusted] as $type) {
            if (in_array($type->value, $types, true)) {
                return ReviewSetting::fromRestriction($type->value);
            }
        }

        return ReviewSetting::Normal;
    }

    /**
     * Trusted commenters skip pre-moderation. A block or hold, here or on the conversation, still wins.
     */
    public function isTrusted(int $identityId, ?int $conversationId = null): bool
    {
        $types = array_map(static fn(RestrictionRecord $restriction): string => (string)$restriction->type, $this->getActiveRestrictions($identityId, $conversationId));

        return in_array(RestrictionType::Trusted->value, $types, true)
            && !in_array(RestrictionType::Blocked->value, $types, true)
            && !in_array(RestrictionType::Moderated->value, $types, true);
    }

    /**
     * Active (non-revoked, non-expired) restrictions for an Identity.
     */
    public function getActiveRestrictions(int $identityId, ?int $conversationId = null): array
    {
        $query = RestrictionRecord::find()
            ->where([
                'identityId' => $identityId,
                'revokedAt' => null,
            ])
            ->andWhere([
                'or',
                ['endsAt' => null],
                ['>', 'endsAt', Db::prepareDateForDb(new DateTime())],
            ]);

        if ($conversationId !== null) {
            $query->andWhere([
                'or',
                ['conversationId' => null],
                ['conversationId' => $conversationId],
            ]);
        }

        return $query->all();
    }

    public function isBlocked(int $identityId, ?int $conversationId = null): bool
    {
        foreach ($this->getActiveRestrictions($identityId, $conversationId) as $restriction) {
            if ($restriction->type === RestrictionType::Blocked->value) {
                return true;
            }
        }

        return false;
    }

    public function isModerated(int $identityId, ?int $conversationId = null): bool
    {
        foreach ($this->getActiveRestrictions($identityId, $conversationId) as $restriction) {
            if ($restriction->type === RestrictionType::Moderated->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any of a commenter's comments is approved, not counting any in the trash.
     */
    public function hasApprovedComment(int $identityId): bool
    {
        return (new Query())
            ->from(['comments' => '{{%comments_comments}}'])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[comments.id]]')
            ->where([
                'comments.identityId' => $identityId,
                'comments.moderationState' => ModerationState::Approved->value,
                'elements.dateDeleted' => null,
            ])
            ->exists();
    }

    /**
     * Deletes spam older than **Delete Spam After** for good, a batch at a time, when Craft collects
     * garbage. Spam with replies stays until they've gone. Returns how many were deleted.
     */
    public function deleteExpiredSpam(int $limit = 100): int
    {
        $days = CommentsPlugin::$plugin->getSettings()->spamRetentionDays;

        if (!is_numeric($days) || (int)$days < 1) {
            return 0;
        }

        $cutoff = (new DateTime())->modify('-' . (int)$days . ' days');
        $ids = (new Query())
            ->select(['comments.id'])
            ->from(['comments' => '{{%comments_comments}}'])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[comments.id]]')
            ->where(['comments.moderationState' => ModerationState::Spam->value])
            ->andWhere(['<', 'elements.dateUpdated', Db::prepareDateForDb($cutoff)])
            ->andWhere(['not exists', (new Query())->from(['replies' => '{{%comments_comments}}'])->where('[[replies.parentId]] = [[comments.id]]')])
            ->orderBy(['elements.dateUpdated' => SORT_ASC])
            ->limit($limit)
            ->column();
        $deleted = 0;

        foreach ($ids as $id) {
            $comment = Comment::find()->id((int)$id)->status(null)->trashed(null)->site('*')->unique()->one();

            if ($comment && Craft::$app->getElements()->deleteElement($comment, true)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function getActions(int $commentId): array
    {
        return ModerationActionRecord::find()
            ->where(['commentId' => $commentId])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->all();
    }

    /**
     * Whether this comment has been public before, so it has already notified everyone it needs to.
     */
    public function wasPreviouslyPublic(Comment $comment): bool
    {
        return ModerationActionRecord::find()
            ->where(['commentId' => $comment->id, 'fromState' => ModerationState::Approved->value])
            ->exists();
    }

    public function currentState(Comment $comment): ModerationState
    {
        if ($comment->moderationState) {
            $state = ModerationState::tryFrom($comment->moderationState);

            if ($state) {
                return $state;
            }
        }

        return match ($comment->status) {
            Comment::STATUS_APPROVED => ModerationState::Approved,
            Comment::STATUS_PENDING => ModerationState::Pending,
            Comment::STATUS_SPAM => ModerationState::Spam,
            Comment::STATUS_REMOVED => ModerationState::Removed,
            default => ModerationState::Pending,
        };
    }

    /**
     * Record the state change an author edit caused. The edit has already been saved.
     */
    public function recordAuthorEdit(Comment $comment, ModerationState $fromState): void
    {
        $toState = $this->currentState($comment);
        // The author who edited it, which for GraphQL can be a user signed in only by a token.
        $actor = CommentsPlugin::$plugin->getService()->getUser();

        $this->_recordAction(
            commentId: (int)$comment->id,
            action: self::ACTION_AUTHOR_EDIT,
            fromState: $fromState,
            toState: $toState,
            actor: $actor instanceof User ? $actor : null,
            reason: null,
        );

        if ($comment->conversationId && $fromState->isPubliclyVisible() && !$toState->isPubliclyVisible()) {
            CommentsPlugin::$plugin->getConversations()->rebuildAggregates((int)$comment->conversationId);
        }
    }

    /**
     * Lazy detail panel payloads keyed by panel name.
     */
    public function getDetailPanel(int $commentId, string $panel): array
    {
        return match ($panel) {
            'history' => $this->_detailHistoryPanel($commentId),
            'notes' => $this->_detailNotesPanel($commentId),
            'reports' => $this->_detailReportsPanel($commentId),
            default => throw new InvalidArgumentException("Unknown detail panel: {$panel}"),
        };
    }

    /**
     * Whether an actor may moderate Comments through any surface (CP or public UI).
     *
     * Moderators must never be able to moderate their own Comment through the public
     * surface — self-approval would bypass the configured moderation queue.
     */
    public function canModerateComment(?User $actor, ?Comment $comment = null): bool
    {
        // A member signed in by a member token only moderates where its origin allows it.
        if (!$actor || !CommentsPlugin::$plugin->getSignIn()->allowsModeration($actor)) {
            return false;
        }

        if (!$actor->admin && !$actor->can('comments-moderate')) {
            return false;
        }

        if ($comment && (int)$comment->userId === (int)$actor->id) {
            return false;
        }

        return true;
    }

    public function canRemoveComment(?User $actor): bool
    {
        return $actor && CommentsPlugin::$plugin->getSignIn()->allowsModeration($actor) && ($actor->admin || $actor->can('comments-trash'));
    }


    // Private Methods
    // =========================================================================

    private function _runMutation(
        Comment|int $comment,
        string $action,
        ?User $actor,
        ?string $reason,
        ?string $expectedVersion,
    ): Comment {
        $comment = $this->_resolveComment($comment);
        $this->_assertExpectedVersion($comment, $expectedVersion);

        $result = match ($action) {
            self::ACTION_APPROVE => $this->_transition($comment, ModerationState::Approved, $action, $actor, $reason),
            self::ACTION_REMOVE => $this->_transition($comment, ModerationState::Removed, $action, $actor, $reason),
            self::ACTION_RESTORE => $this->_transition($comment, ModerationState::Approved, $action, $actor, $reason),
            self::ACTION_MARK_SPAM => $this->_transition($comment, ModerationState::Spam, $action, $actor, $reason, HeldReason::tryFrom((string)$comment->heldReason) ?? HeldReason::Moderator),
            self::ACTION_MARK_NOT_SPAM => $this->_transition($comment, ModerationState::Pending, $action, $actor, $reason, HeldReason::tryFrom((string)$comment->heldReason) ?? HeldReason::Moderator),
            self::ACTION_HOLD => $this->_transition($comment, ModerationState::Pending, $action, $actor, $reason, HeldReason::Moderator),
            default => throw new InvalidArgumentException("Unknown action: {$action}"),
        };

        if (in_array($action, [self::ACTION_MARK_SPAM, self::ACTION_MARK_NOT_SPAM], true)) {
            CommentsPlugin::$plugin->getProtection()->feedback($result, $action === self::ACTION_MARK_SPAM);
        }

        return $result;
    }

    private function _transition(
        Comment|int $comment,
        ModerationState $toState,
        string $action,
        ?User $actor,
        ?string $reason,
        ?HeldReason $heldReason = null,
    ): Comment {
        $comment = $this->_resolveComment($comment);
        $fromState = $this->currentState($comment);
        // Only a waiting or spam comment has a reason for it.
        $heldReason = in_array($toState, [ModerationState::Pending, ModerationState::Spam], true) ? ($heldReason ?? HeldReason::Moderator) : null;

        if ($fromState === $toState && $action !== self::ACTION_RESTORE) {
            return $comment;
        }

        $this->_assertTransitionAllowed($fromState, $toState, $action);

        $wasPublic = $fromState->isPubliclyVisible();
        $willBePublic = $toState->isPubliclyVisible();
        $now = new DateTime();

        Craft::$app->getDb()->transaction(function() use (
            $comment,
            $fromState,
            $toState,
            $action,
            $actor,
            $reason,
            $now,
            $heldReason,
        ) {
            $legacyStatus = match ($toState) {
                ModerationState::Approved => Comment::STATUS_APPROVED,
                ModerationState::Pending => Comment::STATUS_PENDING,
                ModerationState::Spam => Comment::STATUS_SPAM,
                ModerationState::Removed => Comment::STATUS_REMOVED,
            };

            CommentRecord::updateAll([
                'moderationState' => $toState->value,
                'status' => $legacyStatus,
                'heldReason' => $heldReason?->value,
            ], ['id' => $comment->id]);

            // Bump element dateUpdated so expectedVersion tokens change after mutations.
            ElementRecord::updateAll([
                'dateUpdated' => Db::prepareDateForDb($now),
            ], ['id' => $comment->id]);

            Craft::$app->getElements()->invalidateCachesForElement($comment);

            $this->_lastActionId = (int)$this->_recordAction(
                commentId: (int)$comment->id,
                action: $action,
                fromState: $fromState,
                toState: $toState,
                actor: $actor,
                reason: $reason,
            )->id;
        });

        $comment->moderationState = $toState->value;
        $comment->heldReason = $heldReason?->value;
        $this->_queueCounts = null;
        $comment->status = match ($toState) {
            ModerationState::Approved => Comment::STATUS_APPROVED,
            ModerationState::Pending => Comment::STATUS_PENDING,
            ModerationState::Spam => Comment::STATUS_SPAM,
            ModerationState::Removed => Comment::STATUS_REMOVED,
        };
        $comment->dateUpdated = $now;

        if ($comment->conversationId) {
            // A rebuild rather than an increment, because the comment being approved may be older
            // than the latest activity, which shouldn't move backwards.
            if ($wasPublic !== $willBePublic) {
                CommentsPlugin::$plugin->getConversations()->rebuildAggregates((int)$comment->conversationId);
            }
        }

        // Notifications after commit.
        CommentsPlugin::$plugin->getNotifications()->queueAfterModeration(
            $comment,
            $fromState,
            $toState,
            $action,
            $actor?->id,
        );

        // Changing state doesn't save the element, so the data API's cached responses are cleared here,
        // after the change is committed.
        CommentsPlugin::$plugin->getApi()->invalidateCaches();

        // Changing state doesn't save the element, so its save events don't fire for this.
        if ($this->hasEventHandlers(self::EVENT_AFTER_CHANGE_STATE)) {
            $this->trigger(self::EVENT_AFTER_CHANGE_STATE, new ModerationEvent([
                'comment' => $comment,
                'previousState' => $fromState,
                'state' => $toState,
                'actor' => $actor,
            ]));
        }

        return $comment;
    }

    private function _assertTransitionAllowed(
        ModerationState $from,
        ModerationState $to,
        string $action,
    ): void {
        $allowed = match ($action) {
            self::ACTION_APPROVE => in_array($from, [ModerationState::Pending, ModerationState::Removed, ModerationState::Spam], true),
            self::ACTION_REMOVE => in_array($from, [ModerationState::Pending, ModerationState::Approved, ModerationState::Spam], true),
            self::ACTION_RESTORE => $from === ModerationState::Removed,
            self::ACTION_MARK_SPAM => in_array($from, [ModerationState::Pending, ModerationState::Approved, ModerationState::Removed], true),
            self::ACTION_MARK_NOT_SPAM => $from === ModerationState::Spam,
            self::ACTION_HOLD => in_array($from, [ModerationState::Approved, ModerationState::Removed], true),
            default => false,
        };

        if (!$allowed) {
            throw new UserException(Craft::t(
                'comments',
                'Cannot {action} a comment in state “{state}”.',
                ['action' => $action, 'state' => $from->value],
            ));
        }
    }

    private function _recordAction(
        int $commentId,
        string $action,
        ?ModerationState $fromState,
        ?ModerationState $toState,
        ?User $actor,
        ?string $reason,
    ): ModerationActionRecord {
        $record = new ModerationActionRecord([
            'commentId' => $commentId,
            'action' => $action,
            'fromState' => $fromState?->value,
            'toState' => $toState?->value,
            'userId' => $actor?->id,
            'actorType' => $actor ? self::ACTOR_USER : self::ACTOR_SYSTEM,
            'reason' => $reason,
            'uid' => StringHelper::UUID(),
        ]);
        $record->save(false);

        return $record;
    }

    private function _createRestriction(
        int $identityId,
        RestrictionType $type,
        ?int $conversationId,
        ?User $actor,
        ?string $reason,
        ?DateTime $endsAt,
    ): RestrictionRecord {
        if (!CommentsPlugin::$plugin->getIdentities()->getById($identityId)) {
            throw new InvalidArgumentException("Identity {$identityId} was not found.");
        }

        $record = new RestrictionRecord([
            'identityId' => $identityId,
            'conversationId' => $conversationId,
            'type' => $type->value,
            'reason' => $reason,
            'createdByUserId' => $actor?->id,
            'endsAt' => $endsAt ? Db::prepareDateForDb($endsAt) : null,
            'uid' => StringHelper::UUID(),
        ]);
        $record->save(false);

        return $record;
    }

    private function _resolveComment(Comment|int $comment): Comment
    {
        if ($comment instanceof Comment) {
            return $comment;
        }

        $resolved = CommentsPlugin::$plugin->getComments()->getCommentById($comment);

        if (!$resolved) {
            throw new InvalidArgumentException("Comment {$comment} was not found.");
        }

        return $resolved;
    }

    private function _commentVersion(Comment $comment): string
    {
        $date = $comment->dateUpdated ?? $comment->dateCreated ?? $comment->commentDate;
        $iso = $this->_iso8601String($date) ?? '';
        $actionCount = (int)ModerationActionRecord::find()
            ->where(['commentId' => $comment->id])
            ->count();

        // Action count bumps the token on every successful mutation, even within the same second.
        return "{$iso}.{$actionCount}";
    }

    private function _iso8601String(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $formatted = DateTimeHelper::toIso8601($date);

        return is_string($formatted) && $formatted !== '' ? $formatted : null;
    }

    private function _assertExpectedVersion(Comment $comment, ?string $expectedVersion): void
    {
        if ($expectedVersion === null || $expectedVersion === '') {
            return;
        }

        if ($this->_commentVersion($comment) !== $expectedVersion) {
            throw new UserException(Craft::t(
                'comments',
                'This comment was updated elsewhere. Refresh and try again.',
            ));
        }
    }

    private function _actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_APPROVE => Craft::t('comments', 'Approved'),
            self::ACTION_REMOVE => Craft::t('comments', 'Removed'),
            self::ACTION_RESTORE => Craft::t('comments', 'Restored'),
            self::ACTION_MARK_SPAM => Craft::t('comments', 'Marked as spam'),
            self::ACTION_MARK_NOT_SPAM => Craft::t('comments', 'Marked as not spam'),
            self::ACTION_HOLD => Craft::t('comments', 'Held for moderation'),
            self::ACTION_MODERATOR_EDIT => Craft::t('comments', 'Edited by a moderator'),
            self::ACTION_AUTHOR_EDIT => Craft::t('comments', 'Edited by its author'),
            self::ACTION_IMPORT => Craft::t('comments', 'Imported from Comments 3'),
            default => $action,
        };
    }

    private function _stateLabel(?string $state): ?string
    {
        return match ($state) {
            null => null,
            ModerationState::Approved->value => Craft::t('comments', 'Approved'),
            ModerationState::Pending->value => Craft::t('comments', 'Pending'),
            ModerationState::Spam->value => Craft::t('comments', 'Spam'),
            ModerationState::Removed->value => Craft::t('comments', 'Removed'),
            default => $state,
        };
    }

    private function _detailHistoryPanel(int $commentId): array
    {
        $items = [];

        foreach ($this->getActions($commentId) as $action) {
            $actorName = null;
            $actorUrl = null;

            if ($action->userId) {
                $user = Craft::$app->getUsers()->getUserById((int)$action->userId);
                $actorName = $user?->getName();
                $actorUrl = $user?->getCpEditUrl();
            }

            $items[] = [
                'action' => (string)$action->action,
                'actionLabel' => $this->_actionLabel((string)$action->action),
                'fromState' => $action->fromState,
                'fromLabel' => $this->_stateLabel($action->fromState),
                'toState' => $action->toState,
                'toLabel' => $this->_stateLabel($action->toState),
                'reason' => $action->reason,
                'reasonLabel' => $action->reason ? (RemovalReason::tryFrom((string)$action->reason)?->label() ?? $action->reason) : null,
                'actorName' => $actorName,
                'actorUrl' => $actorUrl,
                'dateCreated' => $action->dateCreated
                    ? DateTimeHelper::toIso8601(DateTimeHelper::toDateTime($action->dateCreated))
                    : null,
            ];
        }

        return ['items' => $items];
    }

    private function _detailNotesPanel(int $commentId): array
    {
        $items = [];

        foreach ($this->getNotes($commentId) as $note) {
            $authorName = null;

            if ($note->userId) {
                $user = Craft::$app->getUsers()->getUserById((int)$note->userId);
                $authorName = $user?->getName();
            }

            $items[] = [
                'id' => (int)$note->id,
                'userId' => $note->userId ? (int)$note->userId : null,
                'note' => (string)$note->note,
                'authorName' => $authorName,
                'dateCreated' => $note->dateCreated
                    ? DateTimeHelper::toIso8601(DateTimeHelper::toDateTime($note->dateCreated))
                    : null,
            ];
        }

        return ['items' => $items];
    }

    private function _detailReportsPanel(int $commentId): array
    {
        $rows = (new Query())
            ->select(['r.*', 'reporterName' => 'i.name', 'u.fullName', 'u.username'])
            ->from(['r' => '{{%comments_reports}}'])
            ->leftJoin(['i' => '{{%comments_identities}}'], '[[i.id]] = [[r.identityId]]')
            ->leftJoin(['u' => Table::USERS], '[[u.id]] = [[i.userId]]')
            ->where(['r.commentId' => $commentId])
            ->orderBy(['r.dateCreated' => SORT_DESC, 'r.id' => SORT_DESC])
            ->all();

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row['id'],
                // Who reported it: a person's account name first, as on the Commenters screen.
                'identityId' => $row['identityId'] ? (int)$row['identityId'] : null,
                'reporterName' => $row['fullName'] ?: ($row['reporterName'] ?: ($row['username'] ?: null)),
                'status' => (string)$row['status'],
                'reason' => $row['reason'],
                'details' => $row['details'],
                'dateCreated' => $row['dateCreated']
                    ? DateTimeHelper::toIso8601(DateTimeHelper::toDateTime($row['dateCreated']))
                    : null,
            ];
        }

        return ['items' => $items];
    }
}
