<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\enums\IdentityType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\RemovalReason;
use verbb\comments\enums\ReportStatus;
use verbb\comments\enums\RestrictionType;
use verbb\comments\enums\ReviewSetting;
use verbb\comments\records\Identity as IdentityRecord;
use verbb\comments\records\Restriction as RestrictionRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;

use DateTime;

/**
 * The people behind comments, for moderators deciding what to do about one of them.
 */
class Commenters extends Component
{
    // Static Methods
    // =========================================================================

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = array_map(static fn(string $word): string => mb_strtoupper(mb_substr($word, 0, 1)), array_slice(array_values(array_filter($words)), 0, 2));

        return implode('', $letters) ?: '?';
    }


    // Constants
    // =========================================================================

    public const SOURCE_ALL = 'all';
    public const SOURCE_REGISTERED = 'registered';
    public const SOURCE_GUESTS = 'guests';
    public const SOURCE_TRUSTED = 'trusted';
    public const SOURCE_HELD = 'held';
    public const SOURCE_BLOCKED = 'blocked';

    public const PAGE_SIZE = 50;

    private const SORTS = ['name', 'comments', 'waiting', 'removed', 'lastComment', 'firstSeen'];


    // Public Methods
    // =========================================================================

    /**
     * The list's sources, with how many commenters each holds.
     */
    public function getSources(): array
    {
        $sources = [
            self::SOURCE_ALL => Craft::t('comments', 'All commenters'),
            self::SOURCE_REGISTERED => Craft::t('comments', 'Registered'),
            self::SOURCE_GUESTS => Craft::t('comments', 'Guests'),
            self::SOURCE_TRUSTED => ReviewSetting::Trusted->label(),
            self::SOURCE_HELD => ReviewSetting::Held->label(),
            self::SOURCE_BLOCKED => ReviewSetting::Blocked->label(),
        ];

        $result = [];

        foreach ($sources as $key => $label) {
            $query = $this->_listQuery($key, null);
            $result[$key] = [
                'label' => $label,
                'count' => (int)(new Query())->from(['l' => $query->select(['i.id'])])->count(),
            ];
        }

        return $result;
    }

    /**
     * One page of the list, for Plugin Kit's DataTable.
     */
    public function getTableData(string $source, ?string $search, int $page, ?string $sort = null, string $direction = 'desc'): array
    {
        $query = $this->_listQuery($source, $search);
        $total = (int)(new Query())->from(['l' => (clone $query)->select(['i.id'])])->count();
        $lastPage = max(1, (int)ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $lastPage));
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'lastComment';
        $order = $direction === 'asc' ? SORT_ASC : SORT_DESC;
        $orderBy = match ($sort) {
            'name' => ['i.name' => $order],
            'comments' => ['total' => $order],
            'waiting' => ['pending' => $order],
            'removed' => ['removed' => $order],
            'firstSeen' => ['firstSeen' => $order],
            default => ['lastComment' => $order],
        };

        $rows = $query
            ->orderBy($orderBy + ['i.id' => SORT_DESC])
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->all();

        $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $settings = $this->_reviewSettings($ids);
        $lastOn = $this->_lastCommentOwners($ids);
        $viewer = Craft::$app->getUser()->getIdentity();
        $canViewEmail = $viewer && $viewer->admin;
        $photoIds = array_values(array_filter(array_map(static fn(array $row): ?int => $row['photoId'] ? (int)$row['photoId'] : null, $rows)));
        $photos = $photoIds ? Asset::find()->id($photoIds)->indexBy('id')->all() : [];

        $data = array_map(function(array $row) use ($settings, $lastOn, $canViewEmail, $photos): array {
            $id = (int)$row['id'];
            $name = $this->_displayName($row['name'], $row['username'] ?? null, $id, null, $row['fullName'] ?? null);
            $setting = $settings[$id] ?? ReviewSetting::Normal;

            return [
                'id' => $id,
                'name' => $name,
                'initials' => self::initials($name),
                'tone' => Frontend::avatarTone($name),
                'photo' => isset($photos[(int)$row['photoId']]) ? Craft::$app->getAssets()->getThumbUrl($photos[(int)$row['photoId']], 56) : null,
                'guest' => $row['type'] !== IdentityType::User->value,
                'email' => $canViewEmail ? ($row['userEmail'] ?: $row['email']) : null,
                'url' => UrlHelper::cpUrl("comments/commenters/{$id}"),
                'type' => $row['type'] === IdentityType::User->value
                    ? Craft::t('comments', 'Registered')
                    : Craft::t('comments', 'Guest'),
                'comments' => (int)$row['total'],
                'waiting' => (int)$row['pending'],
                'removed' => (int)$row['removed'],
                'reviewSetting' => $setting->value,
                'reviewSettingLabel' => $setting->label(),
                'lastComment' => $row['lastComment'] ? DateTimeHelper::toIso8601(DateTimeHelper::toDateTime($row['lastComment'])) : null,
                'lastOn' => $lastOn[$id] ?? null,
                'firstSeen' => $row['firstSeen'] ? DateTimeHelper::toIso8601(DateTimeHelper::toDateTime($row['firstSeen'])) : null,
            ];
        }, $rows);

        return ['rows' => $data, 'total' => $total];
    }

    /**
     * Decision context, per spec §27: who this is, how their comments have gone, and any restrictions.
     */
    public function getDetail(int $identityId): ?array
    {
        $identity = IdentityRecord::findOne($identityId);

        if (!$identity) {
            return null;
        }

        $user = $identity->userId ? User::find()->id((int)$identity->userId)->status(null)->one() : null;
        $viewer = Craft::$app->getUser()->getIdentity();
        $canViewEmail = $viewer && $viewer->admin;

        $states = (new Query())
            ->select(['moderationState', 'count' => 'COUNT(*)'])
            ->from('{{%comments_comments}}')
            ->where(['identityId' => $identityId])
            ->groupBy(['moderationState'])
            ->pairs();

        $dates = (new Query())
            ->select(['first' => 'MIN([[commentDate]])', 'last' => 'MAX([[commentDate]])'])
            ->from('{{%comments_comments}}')
            ->where(['identityId' => $identityId])
            ->one();

        $openFlags = (int)(new Query())
            ->from(['r' => '{{%comments_reports}}'])
            ->innerJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[r.commentId]]')
            ->where(['c.identityId' => $identityId, 'r.status' => ReportStatus::Open->value])
            ->count();

        $reportsAgainst = (int)(new Query())
            ->from(['r' => '{{%comments_reports}}'])
            ->innerJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[r.commentId]]')
            ->where(['c.identityId' => $identityId])
            ->count();

        return [
            'id' => $identityId,
            'identity' => $identity,
            'name' => $this->_displayName($identity->name, $user?->username, $identityId, $user),
            'isGuest' => $identity->type === IdentityType::Guest->value,
            // A guest with a token can be recognised when they return; one imported from Comments 3 can't.
            'isRecognisedGuest' => $identity->type === IdentityType::Guest->value && $identity->guestTokenHash !== null,
            'user' => $user,
            'email' => $canViewEmail ? ($user?->email ?? $identity->email) : null,
            'counts' => [
                'approved' => (int)($states[ModerationState::Approved->value] ?? 0),
                'pending' => (int)($states[ModerationState::Pending->value] ?? 0),
                'spam' => (int)($states[ModerationState::Spam->value] ?? 0),
                'removed' => (int)($states[ModerationState::Removed->value] ?? 0),
            ],
            'openFlags' => $openFlags,
            'reportsAgainst' => $reportsAgainst,
            'firstComment' => $dates['first'] ?? null,
            'lastComment' => $dates['last'] ?? null,
            'restrictions' => $this->_restrictionHistory($identityId),
            'reviewSetting' => CommentsPlugin::$plugin->getModeration()->getReviewSetting($identityId),
            'history' => $this->getHistory($identityId),
        ];
    }

    /**
     * The commenter's review history: settings moderators chose, comments they removed, and when
     * the commenter started. Newest first.
     */
    public function getHistory(int $identityId): array
    {
        $users = Craft::$app->getUsers();
        $items = [];

        foreach ($this->_restrictionHistory($identityId) as $restriction) {
            $items[] = [
                'date' => $restriction['dateCreated'],
                'actor' => $restriction['createdBy'],
                'text' => $restriction['isGlobal']
                    ? Craft::t('comments', 'set comments to {setting}', ['setting' => mb_strtolower($this->restrictionLabel($restriction['type']))])
                    : Craft::t('comments', 'set comments in one conversation to {setting}', ['setting' => mb_strtolower($this->restrictionLabel($restriction['type']))]),
                'element' => null,
                'detail' => null,
                'note' => $restriction['reason'],
            ];
        }

        $removals = (new Query())
            ->select(['a.dateCreated', 'a.userId', 'a.reason', 'es.title'])
            ->from(['a' => '{{%comments_moderationactions}}'])
            ->innerJoin(['c' => '{{%comments_comments}}'], '[[c.id]] = [[a.commentId]]')
            ->leftJoin(['es' => Table::ELEMENTS_SITES], '[[es.elementId]] = [[c.ownerId]] AND [[es.siteId]] = [[c.ownerSiteId]]')
            ->where(['c.identityId' => $identityId, 'a.action' => Moderation::ACTION_REMOVE])
            ->orderBy(['a.dateCreated' => SORT_DESC])
            ->limit(20)
            ->all();

        foreach ($removals as $removal) {
            $items[] = [
                'date' => $removal['dateCreated'],
                'actor' => $removal['userId'] ? $users->getUserById((int)$removal['userId'])?->getName() : null,
                'text' => Craft::t('comments', 'removed a comment on'),
                'element' => $removal['title'] ?: Craft::t('comments', '[Deleted element]'),
                'detail' => $removal['reason'] ? (RemovalReason::tryFrom((string)$removal['reason'])?->label() ?? $removal['reason']) : null,
                'note' => null,
            ];
        }

        $first = (new Query())
            ->select(['commentDate'])
            ->from('{{%comments_comments}}')
            ->where(['identityId' => $identityId, 'moderationState' => ModerationState::Approved->value])
            ->orderBy(['commentDate' => SORT_ASC])
            ->scalar();

        if ($first) {
            $items[] = [
                'date' => $first,
                'actor' => null,
                'text' => Craft::t('comments', 'First comment approved'),
                'element' => null,
                'detail' => null,
                'note' => null,
            ];
        }

        usort($items, static fn(array $a, array $b): int => DateTimeHelper::toDateTime($b['date'])?->getTimestamp() <=> DateTimeHelper::toDateTime($a['date'])?->getTimestamp());

        return $items;
    }

    public function restrictionLabel(string $type): string
    {
        return match ($type) {
            RestrictionType::Blocked->value => Craft::t('comments', 'Blocked'),
            RestrictionType::Trusted->value => Craft::t('comments', 'Trusted'),
            default => Craft::t('comments', 'Held for review'),
        };
    }


    // Private Methods
    // =========================================================================

    private function _listQuery(string $source, ?string $search): Query
    {
        $state = static fn(ModerationState $state): string => "SUM(CASE WHEN [[c.moderationState]] = '{$state->value}' THEN 1 ELSE 0 END)";

        $query = (new Query())
            ->select([
                'i.id',
                'i.type',
                'i.name',
                'i.email',
                'u.username',
                'u.fullName',
                'u.photoId',
                'userEmail' => 'u.email',
                'total' => 'COUNT([[c.id]])',
                'pending' => $state(ModerationState::Pending),
                'spam' => $state(ModerationState::Spam),
                'removed' => $state(ModerationState::Removed),
                'lastComment' => 'MAX([[c.commentDate]])',
                'firstSeen' => 'MIN([[c.commentDate]])',
            ])
            ->from(['i' => '{{%comments_identities}}'])
            ->leftJoin(['c' => '{{%comments_comments}}'], '[[c.identityId]] = [[i.id]]')
            ->leftJoin(['u' => Table::USERS], '[[u.id]] = [[i.userId]]')
            ->groupBy(['i.id', 'i.type', 'i.name', 'i.email', 'u.username', 'u.fullName', 'u.photoId', 'u.email']);

        // Voters and flaggers with no comments of their own have no decisions to make about them.
        $restricted = (new Query())
            ->select(['identityId'])
            ->from('{{%comments_restrictions}}')
            ->where(['revokedAt' => null])
            ->andWhere(['or', ['endsAt' => null], ['>', 'endsAt', Db::prepareDateForDb(new DateTime())]]);

        $query->having(['or', ['>', 'COUNT([[c.id]])', 0], ['i.id' => $restricted]]);

        $withSetting = static fn(RestrictionType $type): Query => (clone $restricted)->andWhere(['type' => $type->value, 'conversationId' => null]);

        match ($source) {
            self::SOURCE_REGISTERED => $query->where(['i.type' => IdentityType::User->value]),
            self::SOURCE_GUESTS => $query->where(['i.type' => IdentityType::Guest->value]),
            self::SOURCE_TRUSTED => $query->where(['i.id' => $withSetting(RestrictionType::Trusted)]),
            self::SOURCE_HELD => $query->where(['i.id' => $withSetting(RestrictionType::Moderated)]),
            self::SOURCE_BLOCKED => $query->where(['i.id' => $withSetting(RestrictionType::Blocked)]),
            default => null,
        };

        if ($search !== null && $search !== '') {
            $viewer = Craft::$app->getUser()->getIdentity();
            $conditions = ['or', ['like', 'i.name', $search], ['like', 'u.username', $search], ['like', 'u.fullName', $search]];

            // Searching by address would reveal who's commented to anyone who can't see addresses.
            if ($viewer && $viewer->admin) {
                $conditions[] = ['like', 'i.email', $search];
                $conditions[] = ['like', 'u.email', $search];
            }

            $query->andWhere($conditions);
        }

        return $query;
    }

    private function _reviewSettings(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $types = [];

        foreach (RestrictionRecord::find()
            ->where(['identityId' => $identityIds, 'revokedAt' => null, 'conversationId' => null])
            ->andWhere(['or', ['endsAt' => null], ['>', 'endsAt', Db::prepareDateForDb(new DateTime())]])
            ->all() as $restriction) {
            $types[(int)$restriction->identityId][] = (string)$restriction->type;
        }

        $settings = [];

        foreach ($types as $id => $identityTypes) {
            // The strictest wins if older rows overlap.
            foreach ([RestrictionType::Blocked, RestrictionType::Moderated, RestrictionType::Trusted] as $type) {
                if (in_array($type->value, $identityTypes, true)) {
                    $settings[$id] = ReviewSetting::fromRestriction($type->value);

                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * What each commenter last commented on, by title.
     */
    private function _lastCommentOwners(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $latest = (new Query())
            ->select(['identityId', 'id' => 'MAX([[id]])'])
            ->from('{{%comments_comments}}')
            ->where(['identityId' => $identityIds])
            ->groupBy(['identityId'])
            ->pairs();

        $owners = (new Query())
            ->select(['c.identityId', 'es.title'])
            ->from(['c' => '{{%comments_comments}}'])
            ->innerJoin(['es' => Table::ELEMENTS_SITES], '[[es.elementId]] = [[c.ownerId]] AND [[es.siteId]] = [[c.ownerSiteId]]')
            ->where(['c.id' => array_values($latest)])
            ->pairs();

        return array_map('strval', array_filter($owners, static fn($title): bool => $title !== null && $title !== ''));
    }

    private function _restrictionHistory(int $identityId): array
    {
        $now = new DateTime();
        $users = Craft::$app->getUsers();

        return array_map(function(RestrictionRecord $restriction) use ($now, $users): array {
            $endsAt = $restriction->endsAt ? new DateTime($restriction->endsAt) : null;
            $createdBy = $restriction->createdByUserId ? $users->getUserById((int)$restriction->createdByUserId) : null;
            $revokedBy = $restriction->revokedByUserId ? $users->getUserById((int)$restriction->revokedByUserId) : null;

            return [
                'id' => (int)$restriction->id,
                'type' => (string)$restriction->type,
                'label' => $this->restrictionLabel((string)$restriction->type),
                'reason' => $restriction->reason,
                'isGlobal' => !$restriction->conversationId,
                'isActive' => !$restriction->revokedAt && (!$endsAt || $endsAt > $now),
                'createdBy' => $createdBy?->getName(),
                'dateCreated' => $restriction->dateCreated,
                'revokedBy' => $revokedBy?->getName(),
                'revokedAt' => $restriction->revokedAt,
            ];
        }, RestrictionRecord::find()
            ->where(['identityId' => $identityId])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->all());
    }

    private function _displayName(?string $name, ?string $username, int $id, ?User $user = null, ?string $fullName = null): string
    {
        return $user?->getName() ?: ($fullName ?: ($name ?: ($username ?: Craft::t('comments', 'Guest #{id}', ['id' => $id]))));
    }
}
