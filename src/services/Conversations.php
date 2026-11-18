<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ModerationMode;
use verbb\comments\enums\ModerationState;
use verbb\comments\fields\CommentsField;
use verbb\comments\models\CommentableConfig;
use verbb\comments\models\CommentPage;
use verbb\comments\models\ConversationConfig;
use verbb\comments\models\ConversationSummary;
use verbb\comments\records\Comment as CommentRecord;
use verbb\comments\records\Conversation as ConversationRecord;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;

use yii\db\Exception as DbException;
use yii\db\Expression;
use yii\db\IntegrityException;

use DateTime;
use Throwable;

/**
 * Conversation resolution, lazy create, aggregates and root/reply queries.
 */
class Conversations extends Component
{
    // Constants
    // =========================================================================

    public const MAX_ROOTS_PER_PAGE = 50;


    // Properties
    // =========================================================================

    /** Request-local lookup cache. */
    private array $byOwnerCache = [];

    private array $byIdCache = [];

    /** Request-local resolved config cache. */
    private array $configCache = [];

    /** Request-local answers to whether the current person can comment on an owner. */
    private array $commentingCache = [];


    // Public Methods
    // =========================================================================

    public function getById(int $id): ?ConversationRecord
    {
        if (array_key_exists($id, $this->byIdCache)) {
            return $this->byIdCache[$id];
        }

        $conversation = ConversationRecord::findOne($id);
        $this->byIdCache[$id] = $conversation;

        return $conversation;
    }

    public function findForOwner(int $ownerId, int $siteId): ?ConversationRecord
    {
        $key = $this->_ownerKey($ownerId, $siteId);

        if (array_key_exists($key, $this->byOwnerCache)) {
            return $this->byOwnerCache[$key];
        }

        $conversation = ConversationRecord::findOne([
            'ownerId' => $ownerId,
            'siteId' => $siteId,
        ]);
        $this->byOwnerCache[$key] = $conversation;

        if ($conversation) {
            $this->byIdCache[(int)$conversation->id] = $conversation;
        }

        return $conversation;
    }

    /**
     * Lazy-create Conversation on first write. Do not call merely to render an empty widget.
     */
    public function getOrCreateForOwner(int $ownerId, int $siteId): ConversationRecord
    {
        $existing = $this->findForOwner($ownerId, $siteId);

        if ($existing) {
            return $existing;
        }

        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if (!$owner) {
            throw new \InvalidArgumentException("Owner element {$ownerId} was not found on site {$siteId}.");
        }

        $conversation = new ConversationRecord([
            'ownerId' => $ownerId,
            'siteId' => $siteId,
            'closed' => false,
            'commentCount' => 0,
        ]);

        try {
            $conversation->save(false);
        } catch (IntegrityException) {
            // Unique (ownerId, siteId) race — reload the winner.
            $conversation = ConversationRecord::findOne([
                'ownerId' => $ownerId,
                'siteId' => $siteId,
            ]);

            if (!$conversation) {
                throw new DbException('Failed to materialize Conversation after unique-index race.');
            }
        }

        $key = $this->_ownerKey($ownerId, $siteId);
        $this->byOwnerCache[$key] = $conversation;
        $this->byIdCache[(int)$conversation->id] = $conversation;

        return $conversation;
    }

    public function getSummary(int $conversationId): ?ConversationSummary
    {
        $conversation = $this->getById($conversationId);

        if (!$conversation || $conversation->ownerId === null || $conversation->siteId === null) {
            return null;
        }

        return $this->_mapSummary($conversation);
    }

    public function getSummaryForOwner(int $ownerId, int $siteId): ?ConversationSummary
    {
        $conversation = $this->findForOwner($ownerId, $siteId);

        if (!$conversation) {
            return null;
        }

        return $this->_mapSummary($conversation);
    }

    /**
     * Resolved runtime config: global Settings → Commentable source/field → Conversation overrides.
     */
    public function getConfigForOwner(int $ownerId, int $siteId, ?ElementInterface $owner = null): ConversationConfig
    {
        $key = $this->_ownerKey($ownerId, $siteId);

        if (isset($this->configCache[$key])) {
            return $this->configCache[$key];
        }

        $settings = CommentsPlugin::$plugin->getSettings();

        // A caller that has already loaded the owner saves loading it again.
        if (!$owner || (int)$owner->id !== $ownerId || (int)$owner->siteId !== $siteId) {
            $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);
        }

        $commentable = $this->resolveCommentableConfig($owner);
        $conversation = $this->findForOwner($ownerId, $siteId);

        $globalModeration = $settings->requireModeration
            ? ModerationMode::Pre
            : ModerationMode::Post;
        $globalSort = $settings->orderBy === 'asc'
            ? CommentSort::Oldest
            : CommentSort::Newest;

        // Source policy supplies the default, and the Comments field can opt an individual owner in or out.
        $enabled = $commentable->enabled ?? true;

        // Closed stops new comments but keeps existing ones visible, unlike disabled.
        // A Comments field set to Closed counts too, before the owner has been saved since, as an upgraded
        // Comments 3 entry may not have been.
        $closed = $settings->closed || ($conversation && $conversation->closed) || $commentable->closed;

        $moderationMode = $commentable->moderationMode ?? $globalModeration;

        if ($conversation && $conversation->moderationMode) {
            $mode = ModerationMode::tryFrom((string)$conversation->moderationMode);

            if ($mode) {
                $moderationMode = $mode;
            }
        }

        if ($moderationMode === ModerationMode::Disabled) {
            $enabled = false;
        }

        $allowGuests = $commentable->allowGuests ?? (bool)$settings->allowGuest;

        if ($conversation && $conversation->allowGuests !== null) {
            $allowGuests = (bool)$conversation->allowGuests;
        }

        $sortOrder = $commentable->sortOrder ?? $globalSort;

        if ($conversation && $conversation->sortOrder) {
            $sort = CommentSort::tryFrom((string)$conversation->sortOrder);

            if ($sort) {
                $sortOrder = $sort;
            }
        }

        $config = new ConversationConfig([
            'ownerId' => $ownerId,
            'siteId' => $siteId,
            'conversationId' => $conversation ? (int)$conversation->id : null,
            'enabled' => $enabled,
            'closed' => $closed,
            'moderationMode' => $moderationMode,
            'allowGuests' => $allowGuests,
            'sortOrder' => $sortOrder,
        ]);

        $this->configCache[$key] = $config;

        return $config;
    }

    /**
     * Source-level config from project-config-backed permission settings plus any Comments field
     * value on the owner. Null values inherit the global setting.
     */
    public function resolveCommentableConfig(?ElementInterface $owner): CommentableConfig
    {
        $config = new CommentableConfig();

        if (!$owner) {
            return $config;
        }

        $config->enabled = CommentsPlugin::$plugin->getComments()->checkPermissions($owner);
        $enabledOverride = null;

        foreach ($owner->getFieldValues() as $handle => $value) {
            $field = Craft::$app->getFields()->getFieldByHandle($handle);

            if (!$field instanceof CommentsField) {
                continue;
            }

            $fieldOverride = CommentsField::enabledOverride($value);

            if (CommentsField::isClosedValue($value)) {
                $config->closed = true;
            }

            // Multiple fields are unnecessary but possible. A disable always wins so an
            // overlooked duplicate cannot accidentally expose a closed conversation.
            if ($fieldOverride === false) {
                $enabledOverride = false;

                break;
            }

            if ($fieldOverride === true) {
                $enabledOverride = true;
            }
        }

        if ($enabledOverride !== null) {
            $config->enabled = $enabledOverride;
        }

        return $config;
    }

    /**
     * Whether anyone can see this element, which leaves out drafts, revisions and anything disabled or
     * not yet live. Asked of the element's own query, because statuses differ by element type.
     */
    public function isOwnerPublic(ElementInterface $owner): bool
    {
        return $owner::find()->id($owner->id)->siteId($owner->siteId)->exists();
    }

    /**
     * Stop or resume new comments on an element, leaving existing ones in place.
     */
    public function setClosed(int $ownerId, int $siteId, bool $closed): void
    {
        $conversation = $closed
            ? $this->getOrCreateForOwner($ownerId, $siteId)
            : $this->findForOwner($ownerId, $siteId);

        if (!$conversation || (bool)$conversation->closed === $closed) {
            return;
        }

        $conversation->closed = $closed;
        $conversation->save(false);
        $this->clearCaches();
        CommentsPlugin::$plugin->getApi()->invalidateCaches();
    }

    /**
     * Canonical definition of "a Comment that counts towards a Conversation's aggregates".
     *
     * Approved, not author-removed, and not sitting in the trash. Aggregate rebuilds and the
     * drift check in Diagnostics both build on this so the two can never disagree about what
     * the correct count is.
     */
    public function publicCommentQuery(int $conversationId): Query
    {
        return (new Query())
            ->from(['c' => '{{%comments_comments}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.id]]')
            ->where([
                'c.conversationId' => $conversationId,
                'c.moderationState' => ModerationState::Approved->value,
            ])
            ->andWhere(['c.authorRemovedAt' => null])
            // Soft-deleted Comments keep their row and moderation state, so they must be
            // excluded here or trashing a Comment would leave the count overstated.
            ->andWhere(['e.dateDeleted' => null]);
    }

    /**
     * Rebuild commentCount / lastActivityAt from approved public Comments.
     */
    public function rebuildAggregates(int $conversationId): void
    {
        $row = $this->publicCommentQuery($conversationId)
            ->select([
                'commentCount' => 'COUNT(*)',
                'lastActivityAt' => 'MAX([[c.commentDate]])',
            ])
            ->one();

        Craft::$app->getDb()->createCommand()
            ->update('{{%comments_conversations}}', [
                'commentCount' => (int)($row['commentCount'] ?? 0),
                'lastActivityAt' => $row['lastActivityAt'] ?? null,
            ], ['id' => $conversationId])
            ->execute();

        $this->_forget($conversationId);
    }

    /**
     * Reattach replies whose parent was permanently deleted without going through the element, which
     * is what Craft's garbage collection does when a trashed comment's retention period ends.
     *
     * A reply is never lost with its parent. It joins the root of its thread, or starts a new thread
     * if the root went too, and whatever is beneath it follows.
     *
     * Returns the number of replies reattached.
     */
    public function repairDetachedReplies(): int
    {
        $db = Craft::$app->getDb();

        $detached = (new Query())
            ->select(['id', 'rootId'])
            ->from('{{%comments_comments}}')
            ->where(['parentId' => null])
            ->andWhere(['or', ['rootId' => null], '[[rootId]] <> [[id]]', ['>', 'depth', 0]])
            ->all();

        foreach ($detached as $row) {
            $id = (int)$row['id'];
            $rootId = $row['rootId'] !== null ? (int)$row['rootId'] : null;

            $db->transaction(function() use ($db, $id, $rootId) {
                $db->createCommand()->update('{{%comments_comments}}', [
                    'parentId' => $rootId,
                    'rootId' => $rootId ?? $id,
                    'depth' => $rootId !== null ? 1 : 0,
                ], ['id' => $id], [], false)->execute();

                $this->_realignDescendants($id, $rootId ?? $id, $rootId !== null ? 1 : 0);
            });
        }

        return count($detached);
    }

    /**
     * Move a complete root thread to another owner while preserving its hierarchy.
     */
    public function reassignThread(
        Comment $comment,
        ElementInterface $owner,
        int $siteId,
        ?int $previousConversationId = null,
    ): void {
        if (!$comment->id) {
            throw new \InvalidArgumentException('A saved Comment is required.');
        }

        $targetConversation = $this->getOrCreateForOwner((int)$owner->id, $siteId);
        $targetConversationId = (int)$targetConversation->id;
        $previousConversationId ??= $comment->conversationId ? (int)$comment->conversationId : null;
        $rootId = (int)($comment->rootId ?: $comment->id);

        CommentRecord::updateAll([
            'ownerId' => (int)$owner->id,
            'ownerSiteId' => $siteId,
            'conversationId' => $targetConversationId,
        ], [
            'or',
            ['id' => $rootId],
            ['rootId' => $rootId],
        ]);

        $comment->ownerId = (int)$owner->id;
        $comment->ownerSiteId = $siteId;
        $comment->conversationId = $targetConversationId;
        $comment->setOwner($owner);

        if ($previousConversationId && $previousConversationId !== $targetConversationId) {
            $this->rebuildAggregates($previousConversationId);
        }

        $this->rebuildAggregates($targetConversationId);
    }

    /**
     * Increment aggregates after an approved Comment is created.
     */
    public function recordApprovedCommentActivity(int $conversationId, DateTime $commentDate): void
    {
        $db = Craft::$app->getDb();
        $db->createCommand()
            ->update('{{%comments_conversations}}', [
                'commentCount' => new \yii\db\Expression('[[commentCount]] + 1'),
                'lastActivityAt' => Db::prepareDateForDb($commentDate),
            ], ['id' => $conversationId])
            ->execute();

        $this->_forget($conversationId);
    }

    /**
     * Public root Comments for a Conversation with keyset continuation (`next`).
     */
    public function getRootComments(
        int $conversationId,
        CommentSort $sort = CommentSort::Newest,
        int $limit = 20,
        ?string $next = null,
        ?array $states = null,
    ): CommentPage {
        $limit = max(1, min($limit, self::MAX_ROOTS_PER_PAGE));

        if ($sort === CommentSort::Top) {
            return $this->_getTopRootComments($conversationId, $limit, $next, $states);
        }

        $ids = $this->_queryRootIds($conversationId, $sort, $limit + 1, $next, $states);
        $hasMore = count($ids) > $limit;

        if ($hasMore) {
            $ids = array_slice($ids, 0, $limit);
        }

        // Preserve keyset order from the ID query.
        $ordered = $this->_hydrate($ids);

        $continuation = null;

        if ($hasMore && $ordered !== []) {
            $last = $ordered[array_key_last($ordered)];
            $continuation = $this->_encodeContinuation(
                $conversationId,
                $sort,
                $last->commentDate ?? new DateTime(),
                (int)$last->id,
            );
        }

        return new CommentPage($ordered, $continuation);
    }

    /**
     * A page of root comments for the data API, ordered by a key that's different for every comment, so
     * pages never overlap or reorder: the date then the ID for newest and oldest, and the score then the
     * ID for top. Each row has the comment's `id`, `commentDate` and `score`, and `$after` is the last row
     * of the page before. A root counts when its state is in `$states` (approved when null) or it holds a
     * reply that does, the same as the thread.
     */
    public function getRootPage(int $conversationId, CommentSort $sort, int $limit, ?array $after = null, ?array $states = null): array
    {
        $query = $this->_rootIdQuery($conversationId, $states)
            ->addSelect(['c.commentDate'])
            ->limit($limit + 1);
        $this->_applyRootOrder($query, $conversationId, $sort, $after);
        $rows = $this->_normalizeRootRows($query->all());

        return [
            'rows' => array_slice($rows, 0, $limit),
            'hasMore' => count($rows) > $limit,
        ];
    }

    /**
     * How many root comments a conversation shows: those in `$states`, or holding a reply that is.
     */
    public function countRoots(int $conversationId, ?array $states = null): int
    {
        return (int)$this->_rootIdQuery($conversationId, $states)->count();
    }

    /**
     * Root comments in `$states` that sort between the end of the previous page (`$after`) and the last
     * row of this one (`$until`, or the end of the list when null), for showing comments the public page
     * leaves out beside the ones it has. `$userId` or `$identityId` limits them to one author's comments.
     */
    public function getRootsInRange(
        int $conversationId,
        CommentSort $sort,
        array $states,
        ?array $after,
        ?array $until,
        int $limit,
        ?int $userId = null,
        ?int $identityId = null,
    ): array {
        $query = (new Query())
            ->select(['c.id', 'c.commentDate'])
            ->from(['c' => '{{%comments_comments}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.id]]')
            ->where([
                'c.conversationId' => $conversationId,
                'c.parentId' => null,
                'c.moderationState' => $states,
                'c.authorRemovedAt' => null,
                'e.dateDeleted' => null,
            ])
            ->limit($limit);

        if ($userId !== null || $identityId !== null) {
            $query->andWhere(['or', $userId !== null ? ['c.userId' => $userId] : '0=1', $identityId !== null ? ['and', ['c.userId' => null], ['c.identityId' => $identityId]] : '0=1']);
        }

        $this->_applyRootOrder($query, $conversationId, $sort, $after, $until);

        return $this->_normalizeRootRows($query->all());
    }

    /**
     * Every comment in these threads, or those no deeper than `$maxDepth`, as the light rows the data API
     * arranges a page from: enough to work out what's visible, where it sits and who wrote it, without
     * loading any elements.
     */
    public function getThreadRows(array $rootIds, ?int $maxDepth = null): array
    {
        $rootIds = array_values(array_unique(array_filter(array_map('intval', $rootIds))));

        if ($rootIds === []) {
            return [];
        }

        $rows = [];

        $query = $this->_hierarchyRows()
            ->addSelect(['c.commentDate', 'c.depth', 'c.userId', 'c.identityId', 'e.uid'])
            ->where(['c.rootId' => $rootIds]);

        if ($maxDepth !== null) {
            $query->andWhere(['<=', 'c.depth', $maxDepth]);
        }

        foreach ($query->all() as $row) {
            $rows[(int)$row['id']] = [
                'id' => (int)$row['id'],
                'uid' => (string)$row['uid'],
                'parentId' => $row['parentId'] !== null ? (int)$row['parentId'] : null,
                'rootId' => (int)$row['rootId'],
                'depth' => (int)$row['depth'],
                'moderationState' => (string)$row['moderationState'],
                'removed' => $row['authorRemovedAt'] !== null || $row['dateDeleted'] !== null,
                'commentDate' => (string)$row['commentDate'],
                'userId' => $row['userId'] !== null ? (int)$row['userId'] : null,
                'identityId' => $row['identityId'] !== null ? (int)$row['identityId'] : null,
            ];
        }

        return $rows;
    }

    /**
     * The thread a comment sits in, from its root down to the comment itself.
     */
    public function getThreadPath(Comment $comment): array
    {
        $parents = [];

        foreach ((new Query())
            ->select(['id', 'parentId'])
            ->from('{{%comments_comments}}')
            ->where(['rootId' => $comment->rootId ?: $comment->id])
            ->all() as $row) {
            $parents[(int)$row['id']] = $row['parentId'] !== null ? (int)$row['parentId'] : null;
        }

        $path = [(int)$comment->id];
        $parentId = $parents[(int)$comment->id] ?? null;

        // A corrupt hierarchy could loop, so nothing is ever visited twice.
        while ($parentId !== null && !in_array($parentId, $path, true)) {
            array_unshift($path, $parentId);
            $parentId = $parents[$parentId] ?? null;
        }

        return $this->_hydrate($path);
    }

    /**
     * Direct replies under a parent Comment, with placeholders for any that hold visible replies.
     */
    public function getReplies(
        int $parentId,
        CommentSort $sort = CommentSort::Newest,
        int $limit = 100,
        ?array $states = null,
    ): array {
        $limit = max(1, min($limit, 200));
        $rows = $this->_visibleChildren([$parentId], $states)[$parentId] ?? [];
        $ids = array_map(
            static fn(array $row): int => (int)$row['id'],
            array_slice($this->_sortRows($rows, $sort), 0, $limit),
        );

        return $this->_hydrate($ids);
    }

    /**
     * Batched reply previews for many parent comments in two lightweight queries plus one element fetch.
     *
     * A preview limit of zero only counts replies, for parents too deep to render theirs inline.
     */
    public function getReplyPreviewMap(
        array $parentIds,
        CommentSort $sort = CommentSort::Newest,
        int $previewLimit = 3,
        ?array $states = null,
    ): array {
        $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds))));
        $previewLimit = max(0, $previewLimit);

        if ($parentIds === []) {
            return [];
        }

        $grouped = $this->_visibleChildren($parentIds, $states);
        $selectedIds = [];
        $previewIds = [];

        foreach ($parentIds as $parentId) {
            $slice = array_slice($this->_sortRows($grouped[$parentId] ?? [], $sort), 0, $previewLimit);
            $previewIds[$parentId] = array_map(static fn(array $row): int => (int)$row['id'], $slice);
            array_push($selectedIds, ...$previewIds[$parentId]);
        }

        $commentsById = [];

        foreach ($this->_hydrate($selectedIds) as $comment) {
            $commentsById[(int)$comment->id] = $comment;
        }

        $result = [];

        foreach ($parentIds as $parentId) {
            $total = count($grouped[$parentId] ?? []);

            $result[$parentId] = [
                'replies' => array_values(array_filter(array_map(
                    static fn(int $id) => $commentsById[$id] ?? null,
                    $previewIds[$parentId],
                ))),
                'total' => $total,
                'hasMore' => $total > $previewLimit,
            ];
        }

        return $result;
    }

    /**
     * Whether the current person can comment on an owner. Every comment's Reply asks this, and with
     * **Maximum Comments Per User** set it counts their comments, so it's worked out once per owner and
     * person in a request rather than once per comment.
     */
    public function canComment(?ElementInterface $owner): bool
    {
        if (!$owner || !isset($owner->id, $owner->siteId)) {
            return false;
        }

        $user = CommentsPlugin::$plugin->getService()->getUser();
        $key = $this->_ownerKey((int)$owner->id, (int)$owner->siteId) . ':' . ($user->id ?? 'guest');

        return $this->commentingCache[$key] ??= CommentsPlugin::$plugin->getSettings()->canComment($owner);
    }

    /**
     * Forget who can comment where, as a saved comment can change how many someone has.
     */
    public function clearCommentingCache(): void
    {
        $this->commentingCache = [];
    }

    /** Clear request-local caches (tests / long-running workers). */
    public function clearCaches(): void
    {
        $this->byOwnerCache = [];
        $this->byIdCache = [];
        $this->configCache = [];
        $this->commentingCache = [];
    }


    // Private Methods
    // =========================================================================

    private function _forget(int $conversationId): void
    {
        unset($this->byIdCache[$conversationId]);

        foreach ($this->byOwnerCache as $key => $cached) {
            if ($cached && (int)$cached->id === $conversationId) {
                unset($this->byOwnerCache[$key]);
            }
        }
    }

    private function _realignDescendants(int $parentId, int $rootId, int $parentDepth): void
    {
        $db = Craft::$app->getDb();
        $parentIds = [$parentId];
        $depth = $parentDepth + 1;

        while ($parentIds !== []) {
            $children = array_map('intval', (new Query())
                ->select(['id'])
                ->from('{{%comments_comments}}')
                ->where(['parentId' => $parentIds])
                ->column());

            if ($children !== []) {
                $db->createCommand()->update('{{%comments_comments}}', [
                    'rootId' => $rootId,
                    'depth' => $depth,
                ], ['id' => $children], [], false)->execute();
            }

            $parentIds = $children;
            $depth++;
        }
    }

    /**
     * Votes keep moving a comment's position, so a keyset has nothing stable to continue from. Top
     * pages by offset instead, and the frontend skips any comment that has already been rendered.
     */
    private function _getTopRootComments(int $conversationId, int $limit, ?string $next, ?array $states): CommentPage
    {
        $offset = $this->_decodeTopOffset($next, $conversationId);

        $scores = (new Query())
            ->select([
                'commentId' => 'i.commentId',
                'score' => "SUM(CASE WHEN [[i.type]] = 'upvote' THEN 1 WHEN [[i.type]] = 'downvote' THEN -1 ELSE 0 END)",
            ])
            ->from(['i' => '{{%comments_interactions}}'])
            ->innerJoin(['ic' => '{{%comments_comments}}'], '[[ic.id]] = [[i.commentId]]')
            ->where(['ic.conversationId' => $conversationId, 'ic.parentId' => null])
            ->groupBy(['i.commentId']);

        $ids = array_map('intval', $this->_rootIdQuery($conversationId, $states)
            ->leftJoin(['scores' => $scores], '[[scores.commentId]] = [[c.id]]')
            ->orderBy([
                new Expression('COALESCE([[scores.score]], 0) DESC'),
                'c.commentDate' => SORT_DESC,
                'c.id' => SORT_DESC,
            ])
            ->offset($offset)
            ->limit($limit + 1)
            ->column());

        $hasMore = count($ids) > $limit;

        return new CommentPage(
            $this->_hydrate(array_slice($ids, 0, $limit)),
            $hasMore ? $this->_encodeTopOffset($conversationId, $offset + $limit) : null,
        );
    }

    /**
     * Load Comments in the given order, including any in the trash that are standing in as placeholders.
     */
    private function _hydrate(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $byId = [];

        foreach (CommentsPlugin::$plugin->getComments()->fetch([
            'id' => $ids,
            'status' => null,
            'siteId' => '*',
            'trashed' => null,
        ])->all() as $comment) {
            $byId[(int)$comment->id] = $comment;
        }

        return array_values(array_filter(array_map(static fn(int $id) => $byId[$id] ?? null, $ids)));
    }

    /**
     * The replies a viewer can reach under each parent. Removing a comment doesn't remove the
     * conversation beneath it, so a hidden reply stays as a placeholder while it holds visible ones.
     */
    private function _visibleChildren(array $parentIds, ?array $states): array
    {
        $visibleStates = $this->_visibleStates($states);

        $rows = $this->_hierarchyRows()
            ->addSelect(['c.commentDate'])
            ->where(['c.parentId' => $parentIds])
            ->all();

        $hiddenRootIds = [];

        foreach ($rows as $row) {
            if (!$this->_isRowVisible($row, $visibleStates)) {
                $hiddenRootIds[(int)$row['rootId']] = true;
            }
        }

        $placeholderIds = $this->_placeholderIds(array_keys($hiddenRootIds), $visibleStates);
        $grouped = [];

        foreach ($rows as $row) {
            if ($this->_isRowVisible($row, $visibleStates) || isset($placeholderIds[(int)$row['id']])) {
                $grouped[(int)$row['parentId']][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * Hidden comments in these threads with a visible reply somewhere beneath them.
     */
    private function _placeholderIds(array $rootIds, array $visibleStates): array
    {
        if ($rootIds === []) {
            return [];
        }

        $rows = [];

        foreach ($this->_hierarchyRows()->where(['c.rootId' => $rootIds])->all() as $row) {
            $rows[(int)$row['id']] = $row;
        }

        $placeholders = [];
        $walked = [];

        foreach ($rows as $row) {
            if (!$this->_isRowVisible($row, $visibleStates)) {
                continue;
            }

            $parentId = (int)$row['parentId'];

            while ($parentId && isset($rows[$parentId]) && !isset($walked[$parentId])) {
                $walked[$parentId] = true;

                if (!$this->_isRowVisible($rows[$parentId], $visibleStates)) {
                    $placeholders[$parentId] = true;
                }

                $parentId = (int)$rows[$parentId]['parentId'];
            }
        }

        return $placeholders;
    }

    private function _hierarchyRows(): Query
    {
        return (new Query())
            ->select(['c.id', 'c.parentId', 'c.rootId', 'c.moderationState', 'c.authorRemovedAt', 'e.dateDeleted'])
            ->from(['c' => '{{%comments_comments}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.id]]');
    }

    private function _isRowVisible(array $row, array $visibleStates): bool
    {
        return in_array($row['moderationState'], $visibleStates, true)
            && $row['authorRemovedAt'] === null
            && $row['dateDeleted'] === null;
    }

    private function _sortRows(array $rows, CommentSort $sort): array
    {
        $direction = $sort === CommentSort::Newest ? -1 : 1;

        usort($rows, static fn(array $a, array $b): int => $direction * (
            [(string)$a['commentDate'], (int)$a['id']] <=> [(string)$b['commentDate'], (int)$b['id']]
        ));

        return $rows;
    }

    /**
     * Normalise a requested moderation-state filter.
     *
     * Public reads pass null and only ever see Approved. Moderator reads pass an explicit
     * list (e.g. Approved + Pending) so unmoderated Comments are actionable inline.
     */
    private function _visibleStates(?array $states): array
    {
        if ($states === null || $states === []) {
            return [ModerationState::Approved->value];
        }

        $values = [];

        foreach ($states as $state) {
            $value = $state instanceof ModerationState ? $state->value : (string)$state;

            if (ModerationState::tryFrom($value) !== null) {
                $values[$value] = true;
            }
        }

        return $values === [] ? [ModerationState::Approved->value] : array_keys($values);
    }

    private function _queryRootIds(
        int $conversationId,
        CommentSort $sort,
        int $limit,
        ?string $next,
        ?array $states = null,
    ): array {
        $descending = $sort === CommentSort::Newest;
        $order = $descending
            ? ['c.commentDate' => SORT_DESC, 'c.id' => SORT_DESC]
            : ['c.commentDate' => SORT_ASC, 'c.id' => SORT_ASC];

        $query = $this->_rootIdQuery($conversationId, $states)
            ->orderBy($order)
            ->limit($limit);

        $keyset = $this->_decodeContinuation($next, $conversationId, $sort);

        if ($keyset !== null) {
            if ($descending) {
                $query->andWhere([
                    'or',
                    ['<', 'c.commentDate', $keyset['date']],
                    [
                        'and',
                        ['c.commentDate' => $keyset['date']],
                        ['<', 'c.id', $keyset['id']],
                    ],
                ]);
            } else {
                $query->andWhere([
                    'or',
                    ['>', 'c.commentDate', $keyset['date']],
                    [
                        'and',
                        ['c.commentDate' => $keyset['date']],
                        ['>', 'c.id', $keyset['id']],
                    ],
                ]);
            }
        }

        return array_map('intval', $query->column());
    }

    /**
     * Filtered here rather than after loading, so a page of hidden roots can't end pagination early.
     */
    private function _rootIdQuery(int $conversationId, ?array $states): Query
    {
        $visibleStates = $this->_visibleStates($states);

        $visibleReply = (new Query())
            ->from(['r' => '{{%comments_comments}}'])
            ->innerJoin(['re' => '{{%elements}}'], '[[re.id]] = [[r.id]]')
            ->where('[[r.rootId]] = [[c.id]] AND [[r.id]] <> [[c.id]]')
            ->andWhere(['r.moderationState' => $visibleStates, 'r.authorRemovedAt' => null, 're.dateDeleted' => null]);

        return (new Query())
            ->select(['c.id'])
            ->from(['c' => '{{%comments_comments}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.id]]')
            ->where(['c.conversationId' => $conversationId, 'c.parentId' => null])
            ->andWhere(['or',
                ['c.moderationState' => $visibleStates, 'c.authorRemovedAt' => null, 'e.dateDeleted' => null],
                ['exists', $visibleReply],
            ]);
    }

    /**
     * Orders a root query by its sort's unique key and starts it after `$after`, both being rows with a
     * `commentDate`, `id` and, for top, `score`. `$until` stops it at a row, inclusive.
     */
    private function _applyRootOrder(Query $query, int $conversationId, CommentSort $sort, ?array $after, ?array $until = null): void
    {
        if ($sort === CommentSort::Top) {
            $score = 'COALESCE([[scores.score]], 0)';
            $query
                ->addSelect(['score' => new Expression($score)])
                ->leftJoin(['scores' => $this->_rootScoreQuery($conversationId)], '[[scores.commentId]] = [[c.id]]')
                ->orderBy([new Expression($score . ' DESC'), 'c.id' => SORT_DESC]);

            if ($after !== null) {
                $query->andWhere(['or',
                    new Expression($score . ' < :afterScore', [':afterScore' => (int)$after['score']]),
                    ['and', new Expression($score . ' = :afterScoreTie', [':afterScoreTie' => (int)$after['score']]), ['<', 'c.id', (int)$after['id']]],
                ]);
            }

            if ($until !== null) {
                $query->andWhere(['or',
                    new Expression($score . ' > :untilScore', [':untilScore' => (int)$until['score']]),
                    ['and', new Expression($score . ' = :untilScoreTie', [':untilScoreTie' => (int)$until['score']]), ['>=', 'c.id', (int)$until['id']]],
                ]);
            }

            return;
        }

        $descending = $sort === CommentSort::Newest;
        $direction = $descending ? SORT_DESC : SORT_ASC;
        $query->orderBy(['c.commentDate' => $direction, 'c.id' => $direction]);

        foreach (['after' => $after, 'until' => $until] as $bound => $row) {
            if ($row === null) {
                continue;
            }

            // Past the last row is later for oldest and earlier for newest; up to a row is the reverse.
            $beyond = ($bound === 'after') === $descending ? '<' : '>';
            $date = Db::prepareDateForDb(DateTimeHelper::toDateTime($row['commentDate']) ?: new DateTime());
            $query->andWhere(['or',
                [$beyond, 'c.commentDate', $date],
                ['and', ['c.commentDate' => $date], [$bound === 'after' ? $beyond : $beyond . '=', 'c.id', (int)$row['id']]],
            ]);
        }
    }

    /**
     * Each root comment's score, its upvotes less its downvotes.
     */
    private function _rootScoreQuery(int $conversationId): Query
    {
        return (new Query())
            ->select([
                'commentId' => 'i.commentId',
                'score' => "SUM(CASE WHEN [[i.type]] = 'upvote' THEN 1 WHEN [[i.type]] = 'downvote' THEN -1 ELSE 0 END)",
            ])
            ->from(['i' => '{{%comments_interactions}}'])
            ->innerJoin(['ic' => '{{%comments_comments}}'], '[[ic.id]] = [[i.commentId]]')
            ->where(['ic.conversationId' => $conversationId, 'ic.parentId' => null])
            ->groupBy(['i.commentId']);
    }

    private function _normalizeRootRows(array $rows): array
    {
        return array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'commentDate' => (string)$row['commentDate'],
            'score' => (int)($row['score'] ?? 0),
        ], $rows);
    }

    private function _encodeTopOffset(int $conversationId, int $offset): string
    {
        $payload = json_encode(['c' => $conversationId, 's' => CommentSort::Top->value, 'o' => $offset], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function _decodeTopOffset(?string $next, int $conversationId): int
    {
        $json = $next ? base64_decode(strtr($next, '-_', '+/'), true) : false;
        $data = $json ? json_decode($json, true) : null;

        if (!is_array($data) || (int)($data['c'] ?? 0) !== $conversationId || ($data['s'] ?? '') !== CommentSort::Top->value) {
            return 0;
        }

        return max(0, (int)($data['o'] ?? 0));
    }

    private function _encodeContinuation(
        int $conversationId,
        CommentSort $sort,
        DateTime $commentDate,
        int $id,
    ): string {
        // Private continuation payload — not a public CursorCodec architecture.
        $payload = json_encode([
            'c' => $conversationId,
            's' => $sort->value,
            'd' => $commentDate->format(DATE_ATOM),
            'i' => $id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function _decodeContinuation(?string $next, int $conversationId, CommentSort $sort): ?array
    {
        if ($next === null || $next === '') {
            return null;
        }

        $padded = strtr($next, '-_', '+/');
        $pad = strlen($padded) % 4;

        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        try {
            $json = base64_decode($padded, true);

            if ($json === false) {
                return null;
            }

            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (
            (int)($data['c'] ?? 0) !== $conversationId
            || ($data['s'] ?? '') !== $sort->value
            || empty($data['d'])
            || empty($data['i'])
        ) {
            return null;
        }

        $date = DateTimeHelper::toDateTime($data['d']);

        if (!$date) {
            return null;
        }

        return [
            'date' => Db::prepareDateForDb($date),
            'id' => (int)$data['i'],
        ];
    }

    private function _mapSummary(ConversationRecord $conversation): ConversationSummary
    {
        $sort = null;

        if ($conversation->sortOrder) {
            $sort = CommentSort::tryFrom((string)$conversation->sortOrder);
        }

        $mode = null;

        if ($conversation->moderationMode) {
            $mode = ModerationMode::tryFrom((string)$conversation->moderationMode);
        }

        $lastActivity = $conversation->lastActivityAt
            ? DateTimeHelper::toDateTime($conversation->lastActivityAt)
            : null;

        return new ConversationSummary(
            conversationId: (int)$conversation->id,
            ownerId: (int)$conversation->ownerId,
            siteId: (int)$conversation->siteId,
            commentCount: (int)$conversation->commentCount,
            lastActivityAt: $lastActivity ?: null,
            closed: (bool)$conversation->closed,
            moderationMode: $mode,
            allowGuests: $conversation->allowGuests !== null ? (bool)$conversation->allowGuests : null,
            sortOrder: $sort,
        );
    }

    private function _ownerKey(int $ownerId, int $siteId): string
    {
        return $ownerId . ':' . $siteId;
    }
}
