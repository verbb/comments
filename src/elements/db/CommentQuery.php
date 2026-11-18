<?php
namespace verbb\comments\elements\db;

use verbb\comments\Comments;
use verbb\comments\compatibility\elements\LegacyCommentQuery;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ContentFormat;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\StringHelper;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\Db;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Site;

use DateTime;
use Exception;

class CommentQuery extends ElementQuery
{
    // Traits
    // =========================================================================

    use LegacyCommentQuery;


    // Properties
    // =========================================================================

    public mixed $ownerId = null;
    public mixed $ownerSiteId = null;
    public mixed $userId = null;
    public mixed $name = null;
    public mixed $email = null;
    public mixed $comment = null;
    public mixed $url = null;
    public mixed $ipAddress = null;
    public mixed $userAgent = null;
    public mixed $commentDate = null;
    public array|string|null $status = null;

    public mixed $ownerType = null;
    public mixed $ownerSectionId = null;
    public mixed $ownerSection = null;
    public mixed $ownerEntryTypeId = null;
    public mixed $ownerEntryType = null;
    public mixed $isFlagged = null;
    public mixed $hasOpenReports = null;

    // Comments 4 query params (adjacency hierarchy; no Craft Structure is involved).
    public mixed $conversationId = null;
    public mixed $identityId = null;
    public mixed $moderationState = null;
    public mixed $rootId = null;
    public mixed $parentId = null;

    /**
     * Whether a search may match custom fields that comments don't show publicly. GraphQL on a schema
     * that can't moderate turns this off, so a search can't find a comment by a value only moderators see.
     */
    public bool $withPrivateFields = true;

    /**
     * Whether a search leaves out commenters' email addresses even for someone who can see them, as
     * Comments 3's parameter of the same name did, such as for a public search an admin may also use.
     * Anyone who can't see addresses never searches them, whatever this is.
     */
    public bool $searchTextAndNameOnly = false;

    /**
     * Whether loading comments also loads their report counts, vote totals, authors and owners for all
     * of them at once, which a page of comments needs. A caller that needs none of it, or sets them
     * itself, turns it off to save the queries.
     */
    public bool $withRelatedData = true;


    // Public Methods
    // =========================================================================

    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = [Comment::STATUS_APPROVED];
        }

        parent::__construct($elementType, $config);
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'ownerSection':
                $this->ownerSection($value);

                break;
            case 'ownerEntryType':
                $this->ownerEntryType($value);

                break;
            default:
                parent::__set($name, $value);
        }
    }

    public function ownerType($value): static
    {
        $this->ownerType = $value;

        return $this;
    }

    public function owner(ElementInterface $owner): static
    {
        $this->ownerId = $owner->id;
        $this->siteId = $owner->siteId;

        return $this;
    }

    public function ownerId($value): static
    {
        $this->ownerId = $value;

        return $this;
    }

    public function ownerSiteId($value): static
    {
        $this->ownerSiteId = $value;

        if ($value && strtolower($value) !== ':empty:') {
            // A block will never exist in a site that is different from its ownerSiteId,
            // so let's set the siteId param here too.
            $this->siteId = (int)$value;
        }

        return $this;
    }

    public function ownerSite($value): static
    {
        if ($value instanceof Site) {
            $this->ownerSiteId($value->id);
        } else {
            $site = Craft::$app->getSites()->getSiteByHandle($value);

            if (!$site) {
                throw new Exception('Invalid site handle: ' . $value);
            }

            $this->ownerSiteId($site->id);
        }

        return $this;
    }

    public function ownerSectionId($value): static
    {
        $this->ownerSectionId = $value;

        return $this;
    }

    public function ownerSection(mixed $value): static
    {
        if ($value instanceof Section) {
            $this->ownerSectionId = $value->id;
        } elseif ($value !== null) {
            $this->ownerSectionId = (new Query())
                ->select(['id'])
                ->from([Table::SECTIONS])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->ownerSectionId = null;
        }

        return $this;
    }

    public function ownerEntryTypeId($value): static
    {
        $this->ownerEntryTypeId = $value;

        return $this;
    }

    public function ownerEntryType(mixed $value): static
    {
        if ($value instanceof EntryType) {
            $this->ownerEntryTypeId = $value->id;
        } elseif ($value !== null) {
            $this->ownerEntryTypeId = (new Query())
                ->select(['id'])
                ->from([Table::ENTRY_TYPES])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->ownerEntryTypeId = null;
        }

        return $this;
    }

    public function userId($value): static
    {
        $this->userId = $value;

        return $this;
    }

    public function name($value): static
    {
        $this->name = $value;

        return $this;
    }

    public function email($value): static
    {
        $this->email = $value;

        return $this;
    }

    public function comment($value): static
    {
        $this->comment = $value;

        return $this;
    }

    public function url($value): static
    {
        $this->url = $value;

        return $this;
    }

    public function ipAddress($value): static
    {
        $this->ipAddress = $value;

        return $this;
    }

    public function userAgent($value): static
    {
        $this->userAgent = $value;

        return $this;
    }

    public function commentDate($value): static
    {
        $this->commentDate = $value;

        return $this;
    }

    public function isFlagged($value): static
    {
        $this->isFlagged = $value;

        return $this;
    }

    public function hasOpenReports($value): static
    {
        $this->hasOpenReports = $value;

        return $this;
    }

    public function conversationId($value): static
    {
        $this->conversationId = $value;

        return $this;
    }

    public function identityId($value): static
    {
        $this->identityId = $value;

        return $this;
    }

    public function moderationState($value): static
    {
        $this->moderationState = $value;

        return $this;
    }

    /**
     * Leaves commenters' email addresses out of a search, even for someone who can see them.
     */
    public function searchTextAndNameOnly(bool $value = true): static
    {
        $this->searchTextAndNameOnly = $value;

        return $this;
    }

    public function rootId($value): static
    {
        $this->rootId = $value;

        return $this;
    }

    public function withRelatedData(bool $value = true): static
    {
        $this->withRelatedData = $value;

        return $this;
    }

    /**
     * Narrows the results to the direct replies of these comments. Pass `:empty:` for top-level comments.
     */
    public function parentId($value): static
    {
        $this->parentId = $value;

        return $this;
    }

    /**
     * Comments 2 stored emoji as shortcodes, and a database without 4-byte support still does (see
     * `StringHelper::emojiForDb()`). Turning them back as each comment loads means every surface
     * reads emoji: templates, the editor, GraphQL and the API. A rich comment's document stores
     * emoji as JSON escapes instead, so it's left as it is.
     */
    public function createElement(array $row): ElementInterface
    {
        if (isset($row['comment']) && is_string($row['comment'])) {
            $row['comment'] = StringHelper::shortcodesToEmoji($row['comment']);
        }

        if (isset($row['bodyContent']) && is_string($row['bodyContent']) && ($row['contentFormat'] ?? null) !== ContentFormat::Rich->value) {
            $row['bodyContent'] = StringHelper::shortcodesToEmoji($row['bodyContent']);
        }

        return parent::createElement($row);
    }

    public function populate($rows): array
    {
        $results = parent::populate($rows);

        $comments = array_values(array_filter($results, static fn(mixed $result): bool => $result instanceof Comment));
        $commentIds = array_map(static fn(Comment $comment): int => (int)$comment->id, $comments);

        if ($commentIds === [] || !$this->withRelatedData) {
            return $results;
        }

        $reportCounts = Comments::$plugin->getReports()->getOpenCounts($commentIds);
        $interactionTotals = Comments::$plugin->getInteractions()->getTotals($commentIds);
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn(Comment $comment): ?int => $comment->userId ? (int)$comment->userId : null,
            $comments,
        ))));
        $users = $userIds === []
            ? []
            : User::find()->id($userIds)->status(null)->indexBy('id')->all();

        // Every comment shows its author's photo, so load them together rather than one query each.
        // Only users with a photo are passed, so a page without any costs nothing.
        $usersWithPhotos = array_values(array_filter($users, static fn(User $user): bool => (bool)$user->photoId));

        if ($usersWithPhotos !== []) {
            Craft::$app->getElements()->eagerLoadElements(User::class, $usersWithPhotos, ['photo']);
        }

        $ownerRows = (new Query())
            ->select(['id', 'type'])
            ->from([Table::ELEMENTS])
            ->where(['id' => array_values(array_unique(array_filter(array_map(
                static fn(Comment $comment): ?int => $comment->ownerId ? (int)$comment->ownerId : null,
                $comments,
            ))))])
            ->all();
        $ownerTypes = [];

        foreach ($ownerRows as $row) {
            $ownerTypes[(int)$row['id']] = (string)$row['type'];
        }

        $ownerGroups = [];

        foreach ($comments as $comment) {
            if ($comment->ownerId && isset($ownerTypes[(int)$comment->ownerId])) {
                $key = $ownerTypes[(int)$comment->ownerId] . ':' . (int)$comment->ownerSiteId;
                $ownerGroups[$key]['type'] = $ownerTypes[(int)$comment->ownerId];
                $ownerGroups[$key]['siteId'] = (int)$comment->ownerSiteId;
                $ownerGroups[$key]['ids'][] = (int)$comment->ownerId;
            }
        }

        $owners = [];

        foreach ($ownerGroups as $group) {
            $type = $group['type'];

            // The plugin that provides this element type may be disabled or gone.
            if (!is_a($type, ElementInterface::class, true)) {
                continue;
            }

            $groupOwners = $type::find()
                ->id(array_values(array_unique($group['ids'])))
                ->siteId($group['siteId'])
                ->status(null)
                ->indexBy('id')
                ->all();

            foreach ($groupOwners as $owner) {
                $owners[$type . ':' . $group['siteId'] . ':' . $owner->id] = $owner;
            }
        }

        foreach ($comments as $comment) {
            $id = (int)$comment->id;
            $comment->setOpenReportCount($reportCounts[$id] ?? 0);
            $comment->setInteractionTotals($interactionTotals[$id] ?? []);

            if ($comment->userId) {
                $comment->setUser($users[(int)$comment->userId] ?? null, true);
            }

            if ($comment->ownerId) {
                $type = $ownerTypes[(int)$comment->ownerId] ?? '';
                $key = $type . ':' . (int)$comment->ownerSiteId . ':' . (int)$comment->ownerId;
                $comment->setOwner($owners[$key] ?? null, true);
            }
        }

        return $results;
    }


    // Protected Methods
    // =========================================================================

    protected function beforePrepare(): bool
    {
        // Comments 3's structure parameters, answered from each reply's parent and depth.
        $this->applyLegacyStructureParams();

        $this->joinElementTable('comments_comments');

        $select = [
            'comments_comments.id',
            'comments_comments.ownerId',
            'comments_comments.ownerSiteId',
            'comments_comments.userId',
            'comments_comments.status',
            'comments_comments.name',
            'comments_comments.email',
            'comments_comments.url',
            'comments_comments.comment',
            'comments_comments.gifUrl',
            'comments_comments.ipAddress',
            'comments_comments.userAgent',
            'comments_comments.commentDate',
        ];

        // v4 columns exist only after the prepare migration.
        $db = Craft::$app->getDb();
        $hasV4 = $db->columnExists('{{%comments_comments}}', 'conversationId');

        if ($hasV4) {
            $select = array_merge($select, [
                'comments_comments.conversationId',
                'comments_comments.identityId',
                'comments_comments.parentId',
                'comments_comments.rootId',
                'comments_comments.depth',
                'comments_comments.moderationState',
                'comments_comments.contentFormat',
                // Alias required: bare `content` collides with elements_sites.content JSON.
                'comments_comments.content AS bodyContent',
                'comments_comments.authorRemovedAt',
                'comments_comments.editedAt',
                'comments_comments.heldReason',
                'comments_comments.protection',
            ]);
        }

        // Which custom fields each comment was posted with, once `m400000_000009_form_fields` has run.
        // Added by `m400000_000012_gifs_avatars`, so a site part-way through the upgrade can still read comments.
        if ($hasV4 && $db->columnExists('{{%comments_comments}}', 'gifWidth')) {
            $select[] = 'comments_comments.gifWidth';
            $select[] = 'comments_comments.gifHeight';
        }

        if ($hasV4 && $db->columnExists('{{%comments_comments}}', 'postedFields')) {
            $select[] = 'comments_comments.postedFields';
        }

        $this->query->select($select);

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.ownerId', $this->ownerId));
        }

        if ($this->ownerSiteId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.ownerSiteId', $this->ownerSiteId));
        }

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.userId', $this->userId));
        }

        if ($hasV4 && $this->conversationId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.conversationId', $this->conversationId));
        }

        if ($hasV4 && $this->identityId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.identityId', $this->identityId));
        }

        if ($hasV4 && $this->moderationState) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.moderationState', $this->moderationState));
        }

        if ($hasV4 && $this->rootId) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.rootId', $this->rootId));
        }

        if ($hasV4 && $this->parentId !== null) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.parentId', $this->parentId));
        }

        if ($this->name) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.name', $this->name));
        }

        if ($this->email) {
            // D1: filtering by address is as admin-only as the address itself. Anyone else gets no
            // matches, so the filter can't confirm who has commented.
            if (!CommentsHelper::canViewAddresses()) {
                return false;
            }

            $this->subQuery->andWhere(Db::parseParam('comments_comments.email', $this->email));
        }

        // Craft's search index holds each comment's author address (`authorEmail`) and its custom fields,
        // and a search matches any of them. For anyone who can't see addresses, a comment must also match
        // on something else, so typing part of an address finds nothing, and the same goes for custom
        // fields a request can't read.
        $privateFieldIds = $this->search ? $this->_privateFieldIds() : [];

        $withoutAddresses = $this->searchTextAndNameOnly || !CommentsHelper::canViewAddresses();

        if ($this->search && ($withoutAddresses || $privateFieldIds)) {
            $searchQuery = Craft::$app->getSearch()->createDbQuery($this->search, $this);

            if ($searchQuery !== false) {
                $searchQuery->select(['elementId']);

                if ($withoutAddresses) {
                    $searchQuery->andWhere(['not', ['attribute' => Comment::SEARCH_ATTRIBUTE_EMAIL]]);
                }

                if ($privateFieldIds) {
                    $searchQuery->andWhere(['not', ['fieldId' => $privateFieldIds]]);
                }

                $this->subQuery->andWhere(['elements.id' => $searchQuery]);
            }
        }

        if ($this->comment) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.comment', $this->comment));
        }

        if ($this->url) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.url', $this->url));
        }

        if ($this->ipAddress) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.ipAddress', $this->ipAddress));
        }

        if ($this->userAgent) {
            $this->subQuery->andWhere(Db::parseParam('comments_comments.userAgent', $this->userAgent));
        }

        if ($this->commentDate) {
            $this->subQuery->andWhere(Db::parseDateParam('comments_comments.commentDate', $this->commentDate));
        }

        if ($this->isFlagged !== null) {
            $reportsQuery = '(SELECT DISTINCT commentId FROM {{%comments_reports}} WHERE [[status]] = \'open\')';

            if ($this->isFlagged) {
                $this->subQuery->innerJoin(
                    "{$reportsQuery} comments_open_reports_legacy_alias",
                    '[[comments_comments.id]] = [[comments_open_reports_legacy_alias.commentId]]',
                );
            } else {
                $this->subQuery->leftJoin(
                    "{$reportsQuery} comments_open_reports_legacy_alias",
                    '[[comments_comments.id]] = [[comments_open_reports_legacy_alias.commentId]]',
                );
                $this->subQuery->andWhere(['comments_open_reports_legacy_alias.commentId' => null]);
            }
        }

        if ($this->hasOpenReports !== null) {
            $reportsAlias = 'comments_open_reports';
            $reportsQuery = '(SELECT DISTINCT commentId FROM {{%comments_reports}} WHERE [[status]] = \'open\')';

            if ($this->hasOpenReports) {
                $this->subQuery->innerJoin(
                    "{$reportsQuery} {$reportsAlias}",
                    "[[comments_comments.id]] = [[$reportsAlias.commentId]]",
                );
            } else {
                $this->subQuery->leftJoin(
                    "{$reportsQuery} {$reportsAlias}",
                    "[[comments_comments.id]] = [[$reportsAlias.commentId]]",
                );
                $this->subQuery->andWhere(["{$reportsAlias}.commentId" => null]);
            }
        }

        if ($this->ownerType) {
            $this->subQuery->innerJoin('{{%elements}} ownerElements', '[[comments_comments.ownerId]] = [[ownerElements.id]]');
            $this->subQuery->andWhere(Db::parseParam('ownerElements.type', $this->ownerType));
        }

        if ($this->ownerSectionId || $this->ownerEntryTypeId) {
            $this->subQuery->innerJoin('{{%entries}} ownerEntries', '[[comments_comments.ownerId]] = [[ownerEntries.id]]');
        }

        if ($this->ownerSectionId) {
            $this->subQuery->andWhere(Db::parseParam('ownerEntries.sectionId', $this->ownerSectionId));
        }

        if ($this->ownerEntryTypeId) {
            $this->subQuery->andWhere(Db::parseParam('ownerEntries.typeId', $this->ownerEntryTypeId));
        }

        if ($this->_orderByVotes()) {
            $subQuery = (new Query())
                ->select(["COALESCE(SUM(CASE WHEN [[comments_interactions.type]] = 'upvote' THEN 1 WHEN [[comments_interactions.type]] = 'downvote' THEN -1 ELSE 0 END), 0)"])
                ->from('{{%comments_interactions}} comments_interactions')
                ->where('[[comments_interactions.commentId]] = [[elements.id]]');

            $this->subQuery->addSelect(['voteCount' => $subQuery]);
        }

        if ($this->_orderByFlagged()) {
            $subQuery = (new Query())
                ->select(['COUNT(*)'])
                ->from('{{%comments_reports}} comments_reports')
                ->where('[[comments_reports.commentId]] = [[elements.id]]')
                ->andWhere(['comments_reports.status' => 'open']);

            $this->subQuery->addSelect(['flagCount' => $subQuery]);
        }

        return parent::beforePrepare();
    }

    protected function afterPrepare(): bool
    {
        $this->restoreLegacyStructureParams();

        return parent::afterPrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            // A comment its author deleted keeps its state in case of a restore, but isn't public.
            Comment::STATUS_APPROVED => [
                'comments_comments.status' => Comment::STATUS_APPROVED,
                'comments_comments.authorRemovedAt' => null,
            ],
            Comment::STATUS_PENDING => [
                'comments_comments.status' => Comment::STATUS_PENDING,
            ],
            Comment::STATUS_SPAM => [
                'comments_comments.status' => Comment::STATUS_SPAM,
            ],
            Comment::STATUS_REMOVED => [
                'comments_comments.status' => Comment::STATUS_REMOVED,
            ],
            Comment::STATUS_AUTHOR_REMOVED => ['not', ['comments_comments.authorRemovedAt' => null]],
            // Comments 3's `trashed`, in compatibility mode.
            default => $this->legacyStatusCondition($status) ?? parent::statusCondition($status),
        };
    }


    // Private Methods
    // =========================================================================

    private function _orderByVotes(): bool|string
    {
        if ($this->orderBy) {
            if (is_string($this->orderBy)) {
                return strstr($this->orderBy, 'voteCount');
            }

            if (is_array($this->orderBy)) {
                return isset($this->orderBy['voteCount']);
            }
        }

        return false;
    }

    private function _orderByFlagged(): bool|string
    {
        if ($this->orderBy) {
            if (is_string($this->orderBy)) {
                return strstr($this->orderBy, 'flagCount');
            }

            if (is_array($this->orderBy)) {
                return isset($this->orderBy['flagCount']);
            }
        }

        return false;
    }

    /**
     * The IDs of the custom fields a search mustn't match on, when `withPrivateFields` is off: all of them,
     * because a field shown now can still hold values entered while it was only collected.
     */
    private function _privateFieldIds(): array
    {
        if ($this->withPrivateFields) {
            return [];
        }

        return array_map(static fn($field): int => (int)$field->id, Craft::$app->getFields()->getLayoutByType(Comment::class)->getCustomFields());
    }
}
