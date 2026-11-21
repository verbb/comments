<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\RequestProfile;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\events\DefineCommentDataEvent;
use verbb\comments\events\DefineViewerDataEvent;
use verbb\comments\exceptions\ApiException;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\exceptions\RefusedException;
use verbb\comments\exceptions\UploadRefusedException;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\ConversationConfig;
use verbb\comments\models\FrontendCommentView;
use verbb\comments\models\Settings;
use verbb\comments\records\Conversation as ConversationRecord;
use verbb\comments\records\Identity as IdentityRecord;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\Site;

use yii\base\UserException;
use yii\caching\TagDependency;
use yii\db\Connection;
use yii\web\HttpException;
use yii\web\UploadedFile;

use DateTime;
use stdClass;
use Throwable;

use verbb\editor\Editor;
use verbb\editor\enums\Capability;
use verbb\protect\models\RequestSignals;

/**
 * The data API's responses, built from the same view objects as the thread, so the JSON and the thread
 * can't disagree.
 *
 * Reads are split in two. `thread()`, `replies()`, `comment()` and `structuredData()` never look at who's
 * asking, so they're cached in Craft's data cache and by anyone downstream. `viewer()` and `edit()` carry
 * everything personal and are never cached. Writes act for the person asking and return what changed.
 */
class Api extends Component
{
    // Constants
    // =========================================================================

    /**
     * Add public data to each comment's `custom`. It's cached with the thread, so it must not depend on
     * who's asking.
     */
    public const EVENT_DEFINE_COMMENT_DATA = 'defineCommentData';

    /**
     * Add private data to a `viewer` response, which is never cached.
     */
    public const EVENT_DEFINE_VIEWER_DATA = 'defineViewerData';

    /** Invalidated whenever something a public response shows changes outside an element save. */
    public const CACHE_TAG = 'comments:api';

    public const ROOT_LIMIT_DEFAULT = 20;
    public const ROOT_LIMIT_MAX = 50;
    public const REPLY_LIMIT_DEFAULT = 20;
    public const REPLY_LIMIT_MAX = 100;
    public const REPLY_PREVIEW_LIMIT = 3;
    public const MAX_DEPTH = Frontend::MAX_VISUAL_DEPTH;
    public const MAX_COMMENTS = 200;
    public const MAX_VIEWER_UIDS = 200;
    public const STRUCTURED_DATA_LIMIT_DEFAULT = 20;
    public const PUBLIC_READS_PER_MINUTE = 120;

    /** The `moderate` actions, mapped to the moderation service's decisions. */
    public const MODERATION_ACTIONS = [
        'approve' => Moderation::DECISION_APPROVE,
        'spam' => Moderation::DECISION_SPAM,
        'not-spam' => Moderation::DECISION_NOT_SPAM,
        'remove' => Moderation::DECISION_REMOVE,
        'keep' => Moderation::DECISION_KEEP,
        'restore' => Moderation::DECISION_RESTORE,
        'hold' => 'hold',
    ];

    /**
     * Every piece of text the renderer shows, keyed by the name it uses, with the source string Craft
     * translates for the requested site. Placeholders such as `{name}` and `{count}` are left for the
     * renderer to fill in.
     */
    public const LABELS = [
        'commentCountOne' => '1 comment',
        'commentCountOther' => '{count} comments',
        'showComments' => 'Show comments',
        'showCommentsCount' => 'Show comments ({count})',
        'loading' => 'Loading comments…',
        'loadMore' => 'Load more comments',
        'empty' => 'No comments yet.',
        'beFirst' => 'Be the first to comment.',
        'closed' => 'Comments are closed.',
        'sortLabel' => 'Sort comments',
        'sortNewest' => 'Newest',
        'sortOldest' => 'Oldest',
        'sortTop' => 'Top',
        'reply' => 'Reply',
        'replyTo' => 'Reply to {name}…',
        'replyingTo' => 'Replying to {name}',
        'cancelReply' => 'Cancel reply',
        'cancel' => 'Cancel',
        'post' => 'Post',
        'posting' => 'Posting…',
        'save' => 'Save comment',
        'edit' => 'Edit',
        'edited' => 'Edited',
        'delete' => 'Delete',
        'deleteConfirm' => 'Delete this comment? This can’t be undone.',
        'like' => 'Like',
        'likes' => 'Likes',
        'upvote' => 'Upvote',
        'downvote' => 'Downvote',
        'score' => 'Score',
        'report' => 'Report…',
        'reportTitle' => 'Report this comment',
        'reportReason' => 'What’s wrong with it?',
        'reportDetails' => 'Anything else?',
        'reportDetailsHint' => 'Optional. Only moderators see this.',
        'sendReport' => 'Send report',
        'reportSent' => 'Thanks for reporting this. A moderator will take a look.',
        'reportWithdrawn' => 'Report withdrawn.',
        'close' => 'Close',
        'moreActions' => 'More actions',
        'copyLink' => 'Copy link',
        'linkCopied' => 'Link copied.',
        'showReplies' => 'Show replies',
        'hideReplies' => 'Hide replies',
        'loadMoreReplies' => 'Load more replies',
        'guest' => 'Guest',
        'commentBy' => 'Comment by {name}',
        'unavailable' => 'Comment unavailable.',
        'awaitingApproval' => 'Waiting for approval. Only you can see this until a moderator approves it.',
        'editorPlaceholder' => 'Add to the conversation…',
        'editPlaceholder' => 'Edit your comment…',
        'yourComment' => 'Your comment',
        'writeSomethingFirst' => 'Write something first.',
        'tooLong' => 'Too long by {count} characters',
        'charactersLeft' => '{count} characters left',
        'characterCount' => '{count} of {limit} characters',
        'draftSaved' => 'Draft saved',
        'draftRestored' => 'Restored your draft from {time}.',
        'discardConfirm' => 'Discard what you’ve written?',
        'discard' => 'Discard',
        'uploadInProgress' => 'Please wait for your upload to finish.',
        'joinTitle' => 'Join the conversation',
        'signIn' => 'Sign in',
        'signInToComment' => 'Sign in to comment.',
        'signInOrGuest' => 'Sign in to comment and hear about replies, or post as a guest.',
        'commentAsGuest' => 'Comment as a guest',
        'postingAs' => 'Posting as {name}',
        'yourDetails' => 'Your details',
        'yourName' => 'Your name',
        'yourEmail' => 'Your email',
        'notShownPublicly' => 'Not shown publicly',
        'replyEmails' => 'Email me when someone replies. We’ll ask you to confirm your address first.',
        'follow' => 'Follow',
        'following' => 'Following',
        'followByEmail' => 'Follow by email',
        'error' => 'Something went wrong. Please try again.',
        'tryAgain' => 'Try again',
        'moderator' => 'Moderator',
        'moderatorView' => 'Moderator view.',
        'reviewInComments' => 'Review in Comments',
        'heldCount' => '{count} held',
        'reportedCount' => '{count} reported',
        'showHeld' => 'Show held',
        'hideHeld' => 'Hide held',
        'approve' => 'Approve',
        'markSpam' => 'Mark as spam',
        'notSpam' => 'Not spam',
        'remove' => 'Remove',
        'restore' => 'Restore',
        'keep' => 'Keep',
        'spam' => 'Spam',
        'gif' => 'GIF',
        'gifAdd' => 'Add a GIF',
        'gifSearch' => 'Search GIPHY',
        'gifResults' => 'GIFs',
        'gifRemove' => 'Remove GIF',
        'gifEmpty' => 'No GIFs found.',
        'gifPoweredBy' => 'Powered by GIPHY',
        'gifUnavailable' => 'GIFs can’t be loaded right now.',
        'foldedReports' => 'Comment marked as inappropriate',
        'foldedDownvotes' => 'Comment hidden due to low rating',
        'showFolded' => 'Show comment',
        'authorScore' => 'Total votes across this user’s comments',
        'comments' => 'Comments',
        'moderation' => 'Moderation',
        'onThisPage' => 'on this page',
        'hold' => 'Hold',
        'removedNote' => 'Removed · only moderators see this',
        'chooseOption' => 'Choose…',
        // The file tray for Assets fields. The renderer fills the placeholders.
        'addFiles' => 'Add files',
        'addFile' => 'Add a file',
        'removeFile' => 'Remove {name}',
        'retryUpload' => 'Try {name} again',
        'uploading' => 'Uploading…',
        'uploaded' => '{name} uploaded.',
        'uploadFailed' => '{name} didn’t upload.',
        'fileRemoved' => '{name} removed.',
        'uploadLimits' => 'Up to {count} files, {size} each.',
        'uploadLimit' => 'Up to {size}.',
        'fileTypeNotAllowed' => '{name} can’t be added here. Choose {types}.',
        'fileTooLarge' => '{name} is larger than {size}.',
        'tooManyFiles' => 'You can add up to {count} files here.',
    ];

    /** The longest a report's details can be. */
    public const REPORT_DETAILS_MAX = 2000;

    /** Reads a minute from one address, answered from the cache or not. */
    public const CACHED_READS_PER_MINUTE = 1200;

    /** `viewer` and `protection` reads a minute from one address. */
    public const VIEWER_READS_PER_MINUTE = 600;
    public const PROTECTION_READS_PER_MINUTE = 120;
    public const SESSION_READS_PER_MINUTE = 120;
    public const MENTION_SEARCHES_PER_MINUTE = 120;
    public const UPLOADS_PER_MINUTE = 30;

    // Mint and revoke requests from a site's own server, per address.
    public const SIGNED_REQUESTS_PER_MINUTE = 600;

    // Editor's own limit on a mention search.
    public const MENTION_QUERY_MAX = 200;

    private const VERSION_KEY = 'comments:api:version';

    /** How long a write's result is kept for a repeat of its `operationId`. */
    private const REPLAY_DURATION = 600;

    private const CURSOR_VERSION = 1;
    private const CURSOR_MAX_LENGTH = 512;
    private const CACHE_DURATION = 3600;


    // Properties
    // =========================================================================

    /** The site a response is being built for, so its language is used. */
    private ?Site $_site = null;

    /** Whether caches are cleared again when the open transaction commits. */
    private bool $_invalidateOnCommit = false;


    // Public Methods
    // =========================================================================

    /**
     * The owner a request names by `ownerId` or `ownerUri`, on the site it names or the current one.
     * Anything that isn't live, or has comments turned off, is `not_found`, the same as something that
     * doesn't exist, whoever is asking.
     */
    public function resolveOwner(mixed $ownerId, mixed $ownerUri, mixed $siteId): ElementInterface
    {
        $site = $this->resolveSite($siteId);
        $elements = Craft::$app->getElements();
        $owner = null;

        if (is_numeric($ownerId) && (int)$ownerId > 0) {
            $type = $elements->getElementTypeById((int)$ownerId);

            // Only live elements of a type that can have comments; a comment can't own a thread.
            if ($type && is_subclass_of($type, ElementInterface::class) && $type !== Comment::class) {
                $owner = $type::find()->id((int)$ownerId)->siteId((int)$site->id)->one();
            }
        } elseif (is_string($ownerUri) && trim($ownerUri) !== '') {
            $uri = trim($ownerUri, " \t\n\r\0\x0B/");
            $owner = $elements->getElementByUri($uri === '' ? '__home__' : $uri, (int)$site->id, true);

            if ($owner && ($owner instanceof Comment || !CommentsPlugin::$plugin->getConversations()->isOwnerPublic($owner))) {
                $owner = null;
            }
        } else {
            throw new ApiException(Craft::t('comments', 'An owner element is required.'), 'validation_failed', 422, [
                'ownerId' => [Craft::t('comments', 'An owner element is required.')],
            ]);
        }

        // Each site has its own thread, so an element from another site never stands in for this one.
        if (!$owner || (int)$owner->siteId !== (int)$site->id || !CommentsPlugin::$plugin->getConversations()->getConfigForOwner((int)$owner->id, (int)$owner->siteId, $owner)->enabled) {
            throw $this->_notFound();
        }

        return $owner;
    }

    /**
     * The owner of a comment, by the same rules as `resolveOwner()`, for the Comments 3 form actions,
     * which find comments by ID. A comment whose owner the data API wouldn't answer for isn't found.
     */
    public function ownerOfComment(Comment $comment): ElementInterface
    {
        return $this->resolveOwner((int)$comment->ownerId, null, (int)$comment->ownerSiteId);
    }

    /**
     * Whether `$parent` can be replied to on this owner: the same owner and site, approved and not
     * removed by its author. Anything else answers as if it didn't exist.
     */
    public function assertReplyTarget(Comment $parent, ElementInterface $owner): void
    {
        if (
            (int)$parent->ownerId !== (int)$owner->id
            || (int)$parent->siteId !== (int)$owner->siteId
            || $parent->trashed
            || $parent->authorRemovedAt
            || CommentsPlugin::$plugin->getModeration()->currentState($parent) !== ModerationState::Approved
        ) {
            throw $this->_notFound();
        }
    }

    /**
     * Whether this person may edit this comment, by the data API's rules: their own, not removed,
     * within the edit window and not marked as spam. The Comments 3 form actions use it too.
     */
    public function assertEditable(Comment $comment, User $user): void
    {
        $this->_assertCanEdit($comment, $user);
    }

    public function resolveSite(mixed $siteId): Site
    {
        $sites = Craft::$app->getSites();

        if ($siteId === null || $siteId === '') {
            return $sites->getCurrentSite();
        }

        $site = is_numeric($siteId) ? $sites->getSiteById((int)$siteId) : null;

        if (!$site) {
            throw $this->_notFound();
        }

        return $site;
    }

    /**
     * A page of a thread's approved root comments with their reply previews, the conversation, the
     * editor's public settings and the labels. It never reads who's asking.
     */
    public function thread(ElementInterface $owner, mixed $sort = null, mixed $limit = null, mixed $cursor = null): array
    {
        $limit = $this->_limit($limit, self::ROOT_LIMIT_DEFAULT, self::ROOT_LIMIT_MAX);
        $config = $this->_config($owner);
        $sort = $this->_resolveSort($sort, $config);
        $conversation = CommentsPlugin::$plugin->getConversations()->findForOwner((int)$owner->id, (int)$owner->siteId);
        $after = $this->_rootCursor($cursor, $conversation, $sort, $limit);
        $cacheable = $limit === self::ROOT_LIMIT_DEFAULT;

        $data = $this->_cachedPublic(['thread', (int)$owner->id, (int)$owner->siteId, $sort->value, $limit, $after], $owner, $cacheable, function() use ($owner, $config, $conversation, $sort, $limit, $after): array {
            $page = $conversation
                ? CommentsPlugin::$plugin->getConversations()->getRootPage((int)$conversation->id, $sort, $limit, $after)
                : ['rows' => [], 'hasMore' => false];
            $rows = $this->_threadRows(array_column($page['rows'], 'id'), self::MAX_DEPTH + 1);
            $tops = array_values(array_filter(array_map(static fn(array $row): ?array => $rows[$row['id']] ?? null, $page['rows'])));
            $nodes = $this->_arrange($tops, $rows, [ModerationState::Approved->value], self::MAX_COMMENTS);
            $last = $page['rows'] ? $page['rows'][array_key_last($page['rows'])] : null;

            return [
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                'threadVersion' => $this->dataVersion(),
                'conversation' => $this->_conversationData($owner, $config, $conversation, $sort),
                'editor' => null,
                'comments' => $this->_publicComments($nodes, $owner),
                'page' => [
                    'next' => $page['hasMore'] && $conversation && $last ? $this->_encodeCursor([
                        'k' => 'roots',
                        'c' => (int)$conversation->id,
                        's' => $sort->value,
                        'l' => $limit,
                        'd' => $last['commentDate'],
                        'sc' => $last['score'],
                        'i' => $last['id'],
                    ]) : null,
                    'limit' => $limit,
                ],
                'labels' => $this->labels(),
                'locale' => $this->_currentSite()->language,
            ];
        });

        // Whether it's open depends on the time, and the editor's URLs on the request's host, so neither
        // is ever cached.
        $data['conversation'] = [...$data['conversation'], ...$this->_conversationStatus($owner, $config)];
        $data['editor'] = $this->_inSite($this->_siteFor($owner), fn(): array => $this->_publicEditor());

        return $data;
    }

    /**
     * A page of a comment's approved replies, each with its own previews down to the depth limit.
     */
    public function replies(mixed $parentUid, mixed $limit = null, mixed $cursor = null): array
    {
        $limit = $this->_limit($limit, self::REPLY_LIMIT_DEFAULT, self::REPLY_LIMIT_MAX);
        [$parent, $owner] = $this->_publicComment($parentUid, true);
        $after = $this->_repliesCursor($cursor, (int)$parent->id, $limit);

        return $this->_cachedPublic(['replies', (int)$parent->id, $limit, $after], $owner, $limit === self::REPLY_LIMIT_DEFAULT, function() use ($parent, $owner, $limit, $after): array {
            // Deep enough for the page's replies and their own replies, to count them.
            $rows = $this->_threadRows([(int)($parent->rootId ?: $parent->id)], max(self::MAX_DEPTH + 1, (int)$parent->depth + 2));
            $shown = $this->_shownIds($rows, [ModerationState::Approved->value]);
            $children = $this->_shownChildren($rows, $shown)[(int)$parent->id] ?? [];
            $count = count($children);

            if ($after !== null) {
                $children = array_values(array_filter($children, fn(array $row): bool => $this->_isAfter($row, $after)));
            }

            $tops = array_slice($children, 0, $limit);
            $nodes = $this->_arrange($tops, $rows, [ModerationState::Approved->value], self::MAX_COMMENTS, shown: $shown);
            $last = $tops ? $tops[array_key_last($tops)] : null;

            return [
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                'parentUid' => (string)$parent->uid,
                'replies' => [
                    'count' => $count,
                    'items' => $this->_publicComments($nodes, $owner),
                    'next' => count($children) > count($tops) && $last ? $this->_repliesCursorFor((int)$parent->id, $last, $limit) : null,
                ],
            ];
        });
    }

    /**
     * One approved comment in its thread, for a link to a comment that isn't on the page: its root, then
     * the replies leading down to it, each first among its siblings, with the usual previews.
     */
    public function comment(mixed $uid, mixed $id = null): array
    {
        // A permalink names a comment by its number, which finds the same comment, with the same rules.
        if (($uid === null || $uid === '') && (is_int($id) || (is_string($id) && ctype_digit($id))) && (int)$id > 0) {
            $uid = (new Query())->select(['uid'])->from(Table::ELEMENTS)->where(['id' => (int)$id])->scalar() ?: null;
        }

        [$target, $owner] = $this->_publicComment($uid, false);

        return $this->_cachedPublic(['comment', (int)$target->id], $owner, true, function() use ($target, $owner): array {
            $rows = $this->_threadRows([(int)($target->rootId ?: $target->id)], max(self::MAX_DEPTH + 1, (int)$target->depth + 1));
            $path = [];
            $id = (int)$target->id;

            // A corrupt hierarchy could loop, so nothing is visited twice.
            while ($id && isset($rows[$id]) && !isset($path[$id])) {
                $path[$id] = true;
                $id = (int)($rows[$id]['parentId'] ?? 0);
            }

            $rootId = (int)array_key_last($path);
            $nodes = $this->_arrange([$rows[$rootId]], $rows, [ModerationState::Approved->value], self::MAX_COMMENTS, $path);

            return [
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                'targetUid' => (string)$target->uid,
                // Which thread it's in, so a page can open a link in the right one without comparing
                // permalinks.
                'ownerId' => (int)$owner->id,
                'siteId' => (int)$owner->siteId,
                'comment' => $this->_publicComments($nodes, $owner)[0] ?? null,
            ];
        });
    }

    /**
     * The schema.org JSON-LD for a thread: the page as an `Article` (or the node named by `id`), its
     * comment count, and up to `limit` approved comments as `Comment` items with their replies. Authors'
     * names are left out unless `includeAuthors` is set, because this puts them straight in front of
     * search engines.
     */
    public function structuredData(ElementInterface $owner, array $options = []): array
    {
        $limit = $this->_limit($options['limit'] ?? null, self::STRUCTURED_DATA_LIMIT_DEFAULT, self::ROOT_LIMIT_MAX);
        $includeAuthors = (bool)($options['includeAuthors'] ?? false);
        $id = is_string($options['id'] ?? null) && trim($options['id']) !== '' ? trim($options['id']) : null;
        $thread = $this->thread($owner, CommentSort::Newest->value, $limit);

        $data = ['@context' => 'https://schema.org'];

        // An SEO plugin's own node already says what the page is, and a second type would make two.
        if ($id !== null) {
            $data['@id'] = $id;
        } else {
            $data['@type'] = 'Article';

            if ($url = $owner->getUrl()) {
                $data['url'] = $url;
            }
        }

        $data['commentCount'] = (int)$thread['conversation']['commentCount'];
        $data['comment'] = $this->_structuredComments($thread['comments'], $includeAuthors);

        return $data;
    }

    /**
     * Everything personal for the window a `thread` request covers: who's asking and what they can do,
     * the editor's private state, each comment's permissions and the asker's votes and reports, their
     * own comments that are waiting, and for moderators what's held, marked as spam or reported.
     *
     * A guest with no session gets a slim answer that reads nothing personal and starts nothing, with no
     * CSRF token or protection preparation until they start writing. With `$withModeration` off, as on a
     * public GraphQL schema, a moderator is answered as any other member.
     */
    public function viewer(
        ElementInterface $owner,
        mixed $sort = null,
        mixed $limit = null,
        mixed $cursor = null,
        mixed $uids = [],
        bool $slim = false,
        ?array $csrf = null,
        bool $withModeration = true,
        mixed $ids = [],
    ): array {
        return $this->_inSite($this->_siteFor($owner), function() use ($owner, $sort, $limit, $cursor, $uids, $slim, $csrf, $withModeration, $ids): array {
            $plugin = CommentsPlugin::$plugin;
            $settings = $plugin->getSettings();
            $limit = $this->_limit($limit, self::ROOT_LIMIT_DEFAULT, self::ROOT_LIMIT_MAX);
            $uids = $this->_uids($uids);
            $ids = $this->_ids($ids, count($uids));
            $config = $this->_config($owner);
            $sort = $this->_resolveSort($sort, $config);
            $conversations = $plugin->getConversations();
            $conversation = $conversations->findForOwner((int)$owner->id, (int)$owner->siteId);
            $after = $this->_rootCursor($cursor, $conversation, $sort, $limit);
            $user = $slim ? null : $this->_user();
            $identities = $plugin->getIdentities();
            $identity = match (true) {
                $slim => null,
                $user !== null => $identities->findForUser((int)$user->id),
                default => $identities->findGuestForRequest(),
            };
            $isModerator = $withModeration && $user && $plugin->getModeration()->canModerateComment($user);

            // The same window the public thread returned, then anything else this person may see in it.
            $page = $conversation
                ? $conversations->getRootPage((int)$conversation->id, $sort, $limit, $after)
                : ['rows' => [], 'hasMore' => false];
            // An author's comment marked as spam is shown to them as waiting, so a spammer learns nothing.
            $hiddenStates = [ModerationState::Pending->value, ModerationState::Spam->value];
            $extraRoots = [];

            if ($conversation && ($isModerator || $identity)) {
                $last = $page['rows'] ? $page['rows'][array_key_last($page['rows'])] : null;
                $extraRoots = $conversations->getRootsInRange(
                    (int)$conversation->id,
                    $sort,
                    $hiddenStates,
                    $after,
                    $page['hasMore'] ? $last : null,
                    self::MAX_COMMENTS,
                    $isModerator ? null : ($user ? (int)$user->id : null),
                    $isModerator || $user ? null : (int)$identity->id,
                );
            }

            $uidRows = $conversation && ($uids || $ids) ? $this->_rowsForUids((int)$conversation->id, $uids, $ids) : [];
            $rows = $this->_threadRows([
                ...array_column($page['rows'], 'id'),
                ...array_column($extraRoots, 'id'),
                ...array_column($uidRows, 'rootId'),
            ], max([self::MAX_DEPTH + 1, ...array_map(static fn(array $row): int => $row['depth'] + 1, $uidRows)]));
            $tops = array_values(array_filter(array_map(static fn(array $row): ?array => $rows[$row['id']] ?? null, $page['rows'])));
            $shown = $this->_shownIds($rows, [ModerationState::Approved->value]);
            $window = $this->_flatten($this->_arrange($tops, $rows, [ModerationState::Approved->value], self::MAX_COMMENTS, shown: $shown));

            foreach ($uidRows as $row) {
                if (isset($rows[$row['id']]) && isset($shown[$row['id']])) {
                    $window[$row['id']] = $rows[$row['id']];
                }
            }

            $isOwn = static fn(array $row): bool => $user
                ? $row['userId'] === (int)$user->id
                : ($identity && $row['userId'] === null && $row['identityId'] === (int)$identity->id);
            $canSee = static fn(array $row): bool => !$row['removed'] && (
                $row['moderationState'] === ModerationState::Approved->value
                || (($isModerator || $isOwn($row)) && in_array($row['moderationState'], $hiddenStates, true))
            );
            // Comments asked for by UID, such as a link to the asker's own waiting comment, are included
            // when they may see them, wherever they are in the thread.
            $requested = array_intersect_key($rows, array_flip(array_column($uidRows, 'id')));
            $extras = $this->_extras($window, $extraRoots, $rows, $shown, $canSee, $requested);
            $public = array_filter($window, static fn(array $row): bool => !$row['removed'] && $row['moderationState'] === ModerationState::Approved->value);
            $permissionRows = $public + $extras;

            // Elements are only loaded where a row can't answer. Comments shown privately in full, and
            // everything a moderator acts on, are loaded with their related data; the asker's own public
            // comments are only needed for their edit and delete rights, so they're loaded on their own.
            // Everything a non-moderator sees privately is their own, so their author is already known.
            $comments = $isModerator
                ? $this->_loadComments(array_keys($permissionRows), $owner)
                : $this->_loadComments([...array_keys(array_filter($public, $isOwn)), ...array_keys($extras)], $owner, $user, array_keys($extras));
            $votes = $identity && $permissionRows ? $plugin->getInteractions()->getViewerState(array_keys($permissionRows), (int)$identity->id) : [];
            $reported = $identity && $permissionRows ? $this->_openReportIds(array_keys($permissionRows), (int)$identity->id) : [];
            // A block everywhere applies before the element has a conversation too.
            $blocked = $identity && $plugin->getModeration()->isBlocked((int)$identity->id, $conversation ? (int)$conversation->id : null);
            $canComment = !$blocked && $this->_canComment($owner, $config, $user);
            $closed = $this->_isClosed($owner, $config);

            $entries = [];

            foreach ($permissionRows as $id => $row) {
                $entries[$row['uid']] = $this->_viewerCommentState($row, $comments[$id] ?? null, $user, $isModerator, $canComment, $isOwn($row), $votes[$id] ?? null, isset($reported[$id]), $blocked, $closed);
            }

            $extraComments = $this->_extraComments($extras, $comments, $rows, $shown, $owner, $user, $isModerator, $isOwn);

            // Reported comments are public, so a moderator gets them again with their state.
            if ($isModerator) {
                $reportedViews = [];

                foreach ($window as $id => $row) {
                    if (isset($comments[$id]) && $row['moderationState'] === ModerationState::Approved->value && !$row['removed'] && $comments[$id]->getFlags() > 0) {
                        $reportedViews[$id] = $row;
                    }
                }

                $extraComments = [...$extraComments, ...$this->_extraComments($reportedViews, $comments, $rows, $shown, $owner, $user, true, $isOwn, 'reported')];
            }

            $response = [
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                'threadVersion' => $this->dataVersion(),
                'viewer' => $this->_viewerData($owner, $config, $conversation, $user, $identity, $isModerator, $canComment, $blocked, $slim, $closed),
                'editor' => $this->_viewerEditor($owner, $config, $user, $identity, $canComment, $slim),
                'comments' => $entries ?: new stdClass(),
                'extraComments' => $extraComments,
                'moderation' => $isModerator ? $this->_moderationSummary($owner, $conversation) : null,
                'csrf' => $csrf,
                'custom' => new stdClass(),
            ];

            if ($this->hasEventHandlers(self::EVENT_DEFINE_VIEWER_DATA)) {
                $commentsByUid = [];

                foreach ($permissionRows as $id => $row) {
                    if (isset($comments[$id])) {
                        $commentsByUid[$row['uid']] = $comments[$id];
                    }
                }

                $event = new DefineViewerDataEvent([
                    'user' => $user,
                    'comments' => $commentsByUid,
                    'owner' => $owner,
                    'siteId' => (int)$owner->siteId,
                ]);
                $this->trigger(self::EVENT_DEFINE_VIEWER_DATA, $event);
                $response['custom'] = $event->custom ?: new stdClass();

                foreach ($event->commentCustom as $uid => $data) {
                    if (isset($entries[$uid]) && is_array($data) && $data !== []) {
                        $response['comments'][$uid]['custom'] = $data;
                    }
                }
            }

            return $this->_finish($response, $owner);
        });
    }

    /**
     * What someone needs to edit their own comment: its content, the editor's options for it and the
     * values of its custom fields, including ones that are only collected, which nobody else is shown.
     */
    public function edit(mixed $uid): array
    {
        $comment = $this->_commentByUid($uid);
        $user = $this->_user();

        if (!$user) {
            throw new ApiException(Craft::t('comments', 'Sign in to edit your comment.'), 'sign_in_required', 401);
        }

        $this->_assertCanEdit($comment, $user, true);
        $owner = $comment->getOwner();

        return $this->_inSite($this->_siteFor($owner), function() use ($comment, $owner, $user): array {
            $rich = $comment->isEditorContent();
            $document = $rich ? $comment->getEditorFormContent() : null;
            $formFields = CommentsPlugin::$plugin->getFormFields();
            // Exactly the fields it was posted with, minus any since turned Off.
            $elements = $formFields->editable($comment, $user);

            return [
                'apiVersion' => Frontend::API_VERSION,
                'status' => 'ok',
                'comment' => [
                    'uid' => (string)$comment->uid,
                    'content' => [
                        'format' => $rich ? 'rich' : 'plain',
                        'text' => (string)$comment->comment,
                        'document' => $document !== null ? Json::decode($document) : null,
                        'gif' => CommentsPlugin::$plugin->getGiphy()->forComment($comment),
                    ],
                ],
                'fields' => $formFields->inputValues($comment, $elements) ?: new stdClass(),
                'editor' => [
                    'richEditor' => $rich ? [
                        'context' => 'comments.edit',
                        'options' => EditorContexts::browserOptions('comments.edit', (int)$owner->id, (int)$owner->siteId, (int)$comment->id),
                    ] : null,
                    'fields' => $formFields->describe($elements, $comment),
                    'gifs' => CommentsPlugin::$plugin->getGiphy()->browserConfig(),
                ],
            ];
        });
    }

    /**
     * A fresh protection preparation for a new comment, a reply, or an edit when `commentUid` is given.
     * `channel` is `browser` for the renderer's form, or `headless` for a client that doesn't run the
     * passive checks.
     */
    public function protection(ElementInterface $owner, mixed $commentUid = null, mixed $channel = null): array
    {
        $commentId = null;

        if (is_string($commentUid) && $commentUid !== '') {
            $comment = $this->_commentByUid($commentUid);

            if ((int)$comment->ownerId !== (int)$owner->id) {
                throw $this->_notFound();
            }

            $commentId = (int)$comment->id;
        }

        $preparation = CommentsPlugin::$plugin->getProtection()->prepare((int)$owner->id, (int)$owner->siteId, $commentId, $channel === 'headless', $owner);

        return $this->_protectionData($preparation);
    }

    /**
     * Post a comment or a reply as the person asking.
     */
    public function create(array $body): array
    {
        $plugin = CommentsPlugin::$plugin;
        $owner = $this->resolveOwner($body['ownerId'] ?? null, $body['ownerUri'] ?? null, $body['siteId'] ?? null);

        return $this->_inSite($this->_siteFor($owner), function() use ($plugin, $owner, $body): array {
            $user = $this->_user();
            $guest = is_array($body['guest'] ?? null) ? $body['guest'] : [];
            [$format, $content] = $this->_content($body['content'] ?? null);
            $parentId = null;
            $parent = null;

            if (isset($body['parentUid']) && $body['parentUid'] !== null && $body['parentUid'] !== '') {
                $parent = $this->_commentByUid($body['parentUid']);
                $this->assertReplyTarget($parent, $owner);

                $parentId = (int)$parent->id;
            }

            $fieldValues = $body['fields'] ?? null;
            $headless = ($body['protection']['channel'] ?? null) === 'headless';

            $submission = new CommentSubmission([
                'protectionEvidence' => $plugin->getProtection()->evidence($this->_evidence($body['protection'] ?? null)),
                'protectionRequest' => $plugin->getProtection()->requestSignals(),
                'headless' => $headless,
                'ownerId' => (int)$owner->id,
                'siteId' => (int)$owner->siteId,
                'parentId' => $parentId,
                'content' => $content,
                'contentFormat' => $format,
                'name' => $user ? null : $this->_string($guest['name'] ?? null),
                'email' => $user ? null : $this->_string($guest['email'] ?? null),
                'notifyReplies' => !$user && (bool)($guest['notifyReplies'] ?? false),
                'url' => $owner->getUrl(),
                'userId' => $user ? (int)$user->id : null,
                'gif' => $plugin->getGiphy()->fromInput(is_array($body['content'] ?? null) ? ($body['content']['gif'] ?? null) : null),
            ]);

            $result = $plugin->getComments()->create($submission, function(Comment $comment) use ($plugin, $owner, $parent, $user, $fieldValues): void {
                $request = Craft::$app->getRequest();

                if (!$request->getIsConsoleRequest()) {
                    $comment->ipAddress = RequestSignals::fromRequest($request)->ipAddress;
                    $comment->userAgent = $request->getUserAgent();
                }

                // The fields described to this person in `viewer`, worked out from the same comment.
                $formFields = $plugin->getFormFields();
                $formFields->apply($comment, $fieldValues, $formFields->collectable($formFields->prospect((int)$owner->id, (int)$owner->siteId, $parent, $user), $user));
            });

            $comment = $this->_reload((int)$result->comment->id);
            $state = $result->moderationState === ModerationState::Approved ? 'approved' : 'pending';
            $plugin->getConversations()->clearCaches();
            $conversation = $plugin->getConversations()->findForOwner((int)$owner->id, (int)$owner->siteId);

            return [
                'comment' => $this->_privateComment($comment, $owner, $user, $state),
                // Spam is reported to its author as waiting, so a spammer learns nothing.
                'moderationState' => $state,
                'conversation' => ['commentCount' => (int)($conversation->commentCount ?? 0)],
                'announcement' => $state === 'approved'
                    ? Craft::t('comments', 'Comment posted.')
                    : Craft::t('comments', 'Comment submitted for moderation.'),
            ];
        });
    }

    /**
     * Change the content or custom fields of the asker's own comment.
     */
    public function update(array $body): array
    {
        $plugin = CommentsPlugin::$plugin;
        $comment = $this->_commentByUid($body['uid'] ?? null);
        $user = $this->_user();

        if (!$user) {
            throw new ApiException(Craft::t('comments', 'Sign in to edit your comment.'), 'sign_in_required', 401);
        }

        $this->_assertCanEdit($comment, $user);
        $owner = $comment->getOwner();

        return $this->_inSite($this->_siteFor($owner), function() use ($plugin, $comment, $owner, $user, $body): array {
            $comments = $plugin->getComments();
            $formFields = $plugin->getFormFields();
            $previousMentionIds = $plugin->getMentions()->getIdentityIdsForComment((int)$comment->id);
            $comment->setScenario(Comment::SCENARIO_FRONT_END);
            $comment->setAction(Comment::ACTION_SAVE);

            if (array_key_exists('content', $body)) {
                [$format, $content] = $this->_content($body['content']);
                $comments->applyBody($comment, $format, $content);

                // Without `gif`, the comment keeps its GIF, so a client that doesn't know about GIFs can't
                // drop one by editing the text. Null takes it off.
                if (is_array($body['content']) && array_key_exists('gif', $body['content'])) {
                    $plugin->getGiphy()->apply($comment, $plugin->getGiphy()->fromInput($body['content']['gif']));
                }
            }

            $comments->applyEditModerationState($comment);
            $formFields->apply($comment, $body['fields'] ?? null, $formFields->editable($comment, $user));
            $comments->savePublicUpdate(
                $comment,
                $plugin->getProtection()->evidence($this->_evidence($body['protection'] ?? null)),
                ($body['protection']['channel'] ?? null) === 'headless',
            );

            $pendingMentionIds = $comments->takePendingMentionIdentityIds();

            if ($pendingMentionIds !== null) {
                $comments->finalizeCommentMentions($comment, $previousMentionIds, $pendingMentionIds);
            }

            $comments->takePendingAssetIds();
            $pendingAssetReferences = $comments->takePendingAssetReferences();

            if ($pendingAssetReferences !== null) {
                $plugin->getCommentAssets()->syncEditedReferences($comment, $pendingAssetReferences, (int)$user->id);
            }

            $comment = $this->_reload((int)$comment->id);
            $approved = $plugin->getModeration()->currentState($comment) === ModerationState::Approved;

            return [
                'comment' => $this->_privateComment($comment, $owner, $user, $approved ? 'approved' : 'pending'),
                'moderationState' => $approved ? 'approved' : 'pending',
                'announcement' => $approved
                    ? Craft::t('comments', 'Comment updated.')
                    : Craft::t('comments', 'Comment updated, and waiting to be approved again.'),
            ];
        });
    }

    /**
     * Delete the asker's own comment. Its replies stay, under a placeholder. A moderator's removal, with
     * a reason, is `moderate()` with `remove`.
     */
    public function delete(array $body): array
    {
        $plugin = CommentsPlugin::$plugin;
        $comment = $this->_commentByUid($body['uid'] ?? null);
        $user = $this->_user();

        if (!$user) {
            throw new ApiException(Craft::t('comments', 'Sign in to delete your comment.'), 'sign_in_required', 401);
        }

        if ((int)$comment->userId !== (int)$user->id || $comment->authorRemovedAt) {
            throw new ApiException(Craft::t('comments', 'You do not have permission to delete this comment.'), 'forbidden', 403);
        }

        if (!$comment->canDelete($user)) {
            throw new ApiException(Craft::t('comments', 'This comment can no longer be changed.'), 'edit_window_passed', 403);
        }

        $comment = $plugin->getComments()->authorRemove((int)$comment->id, (int)$comment->siteId, $user);
        $plugin->getConversations()->clearCaches();
        $conversation = $comment->conversationId ? $plugin->getConversations()->getById((int)$comment->conversationId) : null;

        return [
            'uid' => (string)$comment->uid,
            'conversation' => ['commentCount' => (int)($conversation->commentCount ?? 0)],
            'announcement' => Craft::t('comments', 'Comment deleted.'),
        ];
    }

    /**
     * Set the asker's vote on a comment to `upvote`, `downvote` or nothing. Setting a state rather than
     * toggling means a repeated request changes nothing.
     */
    public function vote(array $body): array
    {
        $plugin = CommentsPlugin::$plugin;
        $settings = $plugin->getSettings();
        $mode = $settings->resolveInteractionMode();
        $user = $this->_user();
        $vote = $body['vote'] ?? null;
        $type = is_string($vote) ? InteractionType::tryFrom($vote) : null;

        if (!$mode->isEnabled()) {
            throw new ApiException(Craft::t('comments', 'Interactions are disabled.'), 'forbidden', 403);
        }

        if ($vote !== null && (!$type || !$mode->allows($type))) {
            throw new ApiException(Craft::t('comments', 'That interaction is not enabled.'), 'validation_failed', 422, [
                'vote' => [Craft::t('comments', 'That interaction is not enabled.')],
            ]);
        }

        if (!$user && !$settings->allowGuestVoting) {
            throw new ApiException(Craft::t('comments', 'Sign in to vote.'), 'sign_in_required', 401);
        }

        $comment = $this->_commentByUid($body['uid'] ?? null);

        $plugin->getComments()->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_INTERACTION, (int)$comment->siteId);
        $identity = $plugin->getIdentities()->resolveForUserOrGuest($user);

        if ($type) {
            $plugin->getInteractions()->set((int)$comment->id, (int)$identity->id, $type);
        } else {
            $plugin->getInteractions()->remove((int)$comment->id, (int)$identity->id);
        }

        $comment = $this->_reload((int)$comment->id);

        return [
            'comment' => [
                'uid' => (string)$comment->uid,
                'interaction' => ['upvotes' => $comment->getUpvotes(), 'downvotes' => $comment->getDownvotes()],
                'viewer' => $this->_commentViewer($comment, $user),
            ],
            'announcement' => Craft::t('comments', 'Interaction saved.'),
        ];
    }

    /**
     * Report a comment to the moderators, or withdraw the asker's report with `withdraw`. Reporting one
     * that's already reported changes nothing.
     */
    public function report(array $body): array
    {
        $plugin = CommentsPlugin::$plugin;
        $settings = $plugin->getSettings();
        $user = $this->_user();
        $withdraw = $this->_bool($body['withdraw'] ?? false, 'withdraw');

        if (!$settings->allowFlagging) {
            throw new ApiException(Craft::t('comments', 'Reporting isn’t allowed.'), 'forbidden', 403);
        }

        if (!$user && !$settings->allowGuestFlagging) {
            throw new ApiException(Craft::t('comments', 'Sign in to report a comment.'), 'sign_in_required', 401);
        }

        $comment = $this->_commentByUid($body['uid'] ?? null);
        $reason = $body['reason'] ?? null;
        $details = $this->_string($body['details'] ?? null);

        if ($details !== null && mb_strlen($details) > self::REPORT_DETAILS_MAX) {
            throw new ApiException(Craft::t('comments', 'Keep it to {max} characters.', ['max' => self::REPORT_DETAILS_MAX]), 'validation_failed', 422, [
                'details' => [Craft::t('comments', 'Keep it to {max} characters.', ['max' => self::REPORT_DETAILS_MAX])],
            ]);
        }

        // Anything else would show as a label nobody configured.
        if (!in_array($reason, array_column(Frontend::reportReasons(), 'value'), true)) {
            $reason = null;
        }

        $plugin->getComments()->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_REPORT, (int)$comment->siteId);
        $identity = $plugin->getIdentities()->resolveForUserOrGuest($user);
        $reports = $plugin->getReports();

        if (!$withdraw && $this->_isOwnComment($comment, $user, $identity)) {
            throw new ApiException(Craft::t('comments', 'You can’t report your own comment.'), 'forbidden', 403);
        }

        if ($withdraw) {
            $reports->withdrawOpen((int)$comment->id, (int)$identity->id);
        } elseif (!$reports->getOpenForIdentity((int)$comment->id, (int)$identity->id)) {
            $reports->create((int)$comment->id, (int)$identity->id, $reason, $details);
        }

        return [
            'comment' => [
                'uid' => (string)$comment->uid,
                'viewer' => $this->_commentViewer($this->_reload((int)$comment->id), $user),
            ],
            'announcement' => $withdraw
                ? Craft::t('comments', 'Report withdrawn.')
                : Craft::t('comments', 'Thanks for reporting this. A moderator will take a look.'),
        ];
    }

    /**
     * Follow or stop following a conversation by email. A guest confirms their address first.
     */
    public function follow(array $body): array
    {
        $plugin = CommentsPlugin::$plugin;

        if (!$plugin->getSettings()->allowsSubscriptionType(SubscriptionType::Conversation)) {
            throw new ApiException(Craft::t('comments', 'Discussion subscriptions are not allowed.'), 'forbidden', 403);
        }

        $owner = $this->resolveOwner($body['ownerId'] ?? null, $body['ownerUri'] ?? null, $body['siteId'] ?? null);
        $following = $this->_bool($body['following'] ?? true, 'following');
        $user = $this->_user();

        if ($following && !$user && !$this->_config($owner)->allowGuests) {
            throw new ApiException(Craft::t('comments', 'Must be logged in to subscribe.'), 'sign_in_required', 401);
        }

        $outcome = $plugin->getSubscriptions()->setConversationSubscription(
            (int)$owner->id,
            (int)$owner->siteId,
            $following,
            $this->_string($body['email'] ?? null),
        );
        $identity = $user ? $plugin->getIdentities()->findForUser((int)$user->id) : $plugin->getIdentities()->findGuestForRequest();
        $conversation = $plugin->getConversations()->findForOwner((int)$owner->id, (int)$owner->siteId);

        return [
            'viewer' => ['follow' => $this->_followData($owner, $this->_config($owner), $conversation, $user, $identity, $plugin->getComments()->isRequestBlocked((int)$owner->id, (int)$owner->siteId))],
            'outcome' => $outcome,
            'announcement' => match ($outcome) {
                Subscriptions::OUTCOME_FOLLOWED => Craft::t('comments', 'You’re following this conversation.'),
                Subscriptions::OUTCOME_CONFIRMING => Craft::t('comments', 'Check your email to confirm your subscription.'),
                default => Craft::t('comments', 'You’re no longer following this conversation.'),
            },
        ];
    }

    /**
     * A moderator's decision on a comment from the site.
     */
    public function moderate(array $body): array
    {
        $plugin = CommentsPlugin::$plugin;
        $moderation = $plugin->getModeration();
        $user = $this->_user();
        $action = $body['action'] ?? null;

        if (!$user) {
            throw new ApiException(Craft::t('comments', 'Sign in to moderate comments.'), 'sign_in_required', 401);
        }

        if (!is_string($action) || !isset(self::MODERATION_ACTIONS[$action])) {
            throw new ApiException(Craft::t('comments', 'Unknown moderation action.'), 'validation_failed', 422, [
                'action' => [Craft::t('comments', 'Unknown moderation action.')],
            ]);
        }

        $comment = $this->_commentByUid($body['uid'] ?? null, true);

        if (!$moderation->canModerateComment($user, $comment)) {
            throw new ApiException(Craft::t('comments', 'You are not permitted to moderate this comment.'), 'forbidden', 403);
        }

        $decision = self::MODERATION_ACTIONS[$action];
        $state = $moderation->currentState($comment);
        $allowed = $plugin->getFrontend()->moderationDecisions($comment, $user);

        // Holding takes a published comment back for review. From Removed it would be a way to restore
        // without the trash permission, as would any other decision there.
        if ($state === ModerationState::Approved) {
            $allowed[] = 'hold';
        }

        if ((in_array($decision, [Moderation::DECISION_REMOVE, Moderation::DECISION_RESTORE], true) || $state === ModerationState::Removed) && !$moderation->canRemoveComment($user)) {
            throw new ApiException(Craft::t('comments', 'You are not permitted to remove this comment.'), 'forbidden', 403);
        }

        // A real decision that doesn't fit the comment as it is now means someone else got there first.
        if (!in_array($decision, $allowed, true)) {
            throw new ApiException(Craft::t('comments', 'This comment has changed since it was loaded.'), 'conflict', 409);
        }

        $reason = $this->_string($body['reason'] ?? null);
        $note = $this->_string($body['note'] ?? null);

        $comment = $decision === 'hold'
            ? $moderation->setState($comment, ModerationState::Pending, $user)
            : $moderation->decide($comment, $decision, $user, $reason, $note);
        $comment = $this->_reload((int)$comment->id);
        $owner = $comment->getOwner();

        return [
            'comment' => $owner ? $this->_moderatedComment($comment, $owner, $user) : ['uid' => (string)$comment->uid],
            'announcement' => $decision === 'hold'
                ? Craft::t('comments', 'Comment held for moderation.')
                : $moderation->decisionMessage($decision),
        ];
    }

    /**
     * Who's signed in, with the CSRF token for cookie sign-ins, for a front end about to write that
     * hasn't had one from `viewer` or `protection`, such as a guest's first vote or a sibling subdomain
     * (tier 2). Asking is what starts a guest's session. With an owner, `viewer` is the same as the
     * `viewer` read's for that thread; without one, it says who's signed in and what the site allows in
     * general, and each thread's `viewer` read has the final say.
     */
    public function session(?ElementInterface $owner, ?array $csrf): array
    {
        $plugin = CommentsPlugin::$plugin;
        $user = $this->_user();
        $identities = $plugin->getIdentities();
        $identity = $user ? $identities->findForUser((int)$user->id) : $identities->findGuestForRequest();

        if ($owner) {
            $viewer = $this->_inSite($this->_siteFor($owner), function() use ($plugin, $owner, $user, $identity): array {
                $config = $this->_config($owner);
                $conversation = $plugin->getConversations()->findForOwner((int)$owner->id, (int)$owner->siteId);
                $isModerator = $user && $plugin->getModeration()->canModerateComment($user);
                // A block everywhere applies before the element has a conversation too.
                $blocked = $identity && $plugin->getModeration()->isBlocked((int)$identity->id, $conversation ? (int)$conversation->id : null);
                $canComment = !$blocked && $this->_canComment($owner, $config, $user);

                return $this->_viewerData($owner, $config, $conversation, $user, $identity, (bool)$isModerator, $canComment, (bool)$blocked, false, $this->_isClosed($owner, $config));
            });
        } else {
            $settings = $plugin->getSettings();
            $frontend = $plugin->getFrontend();
            $name = $user ? CommentsHelper::publicUserName($user) : null;

            $viewer = [
                'isGuest' => !$user,
                'hasSession' => true,
                'name' => $name,
                'initials' => $name ? Commenters::initials($name) : '',
                'avatarUrl' => $frontend->userAvatarUrl($user),
                'avatarTone' => Frontend::avatarTone((string)$name),
                'badges' => [],
                'canComment' => $user !== null || $settings->allowGuest,
                'canVote' => $settings->resolveInteractionMode()->isEnabled() && ($user || $settings->allowGuestVoting),
                'canReport' => $settings->allowFlagging && ($user || $settings->allowGuestFlagging),
                'isModerator' => $user && $plugin->getModeration()->canModerateComment($user),
                'blocked' => false,
                'loginUrl' => $this->_loginUrl($user),
                'follow' => ['available' => false, 'following' => false, 'needsEmail' => !$user && !$identity?->verifiedAt],
            ];
        }

        return [
            'apiVersion' => Frontend::API_VERSION,
            'status' => 'ok',
            'viewer' => $viewer,
            'csrf' => $csrf,
        ];
    }

    /**
     * Editor's mention search for a member, through the data API's sign-in tiers, with exactly the
     * checks Editor's own endpoint makes: the context, its scope and the member's access to it.
     */
    public function mentions(mixed $context, mixed $scope, mixed $query): array
    {
        $user = $this->_user();

        if (!$user) {
            throw new ApiException(Craft::t('comments', 'Sign in to mention someone.'), 'sign_in_required', 401);
        }

        if (!is_string($query) || mb_strlen(trim($query)) > self::MENTION_QUERY_MAX) {
            throw new ApiException(Craft::t('comments', 'The search is too long.'), 'invalid_request', 400);
        }

        $editor = Editor::getInstance();
        $config = $editor->getEditorConfigs()->resolveRequestConfig($context, $scope ?? [], $user, Capability::Mentions);

        return [
            'apiVersion' => Frontend::API_VERSION,
            'status' => 'ok',
            'results' => $editor->getMentions()->search(trim($query), $config),
        ];
    }

    /**
     * An upload for a member's Editor attachment or inline image, through the data API's sign-in tiers,
     * with exactly Editor's checks for the context, then Comments' upload rules (`UploadPolicy`), which
     * Comments' contexts apply wherever an upload comes from. The file waits in the member's temporary
     * upload folder until their comment saves, as with Editor's own endpoint.
     *
     * Guests can't upload to Editor, whichever tier they come from, so a file can only ever be
     * attached by the person who uploaded it.
     */
    public function upload(?UploadedFile $file, array $params): array
    {
        $user = $this->_user();

        // Assets custom fields upload here with their handle, under the same sign-in tiers.
        if (($params['field'] ?? null) !== null && $params['field'] !== '') {
            return $this->_fieldUpload($file, $params, $user);
        }

        if (!$user) {
            throw new ApiException(Craft::t('comments', 'Sign in to upload files.'), 'sign_in_required', 401);
        }

        if (!$file) {
            throw new ApiException(Craft::t('comments', 'No file was uploaded.'), 'validation_failed', 422, [
                'file' => [Craft::t('comments', 'No file was uploaded.')],
            ]);
        }

        $kind = $params['kind'] ?? 'inlineImage';

        if (!is_string($kind)) {
            throw new ApiException(Craft::t('comments', 'This kind of upload isn’t supported.'), 'invalid_request', 400);
        }

        $editor = Editor::getInstance();
        $config = $editor->getEditorConfigs()->resolveRequestConfig($params['context'] ?? null, $params['scope'] ?? [], $user, Capability::Upload);

        return [
            'apiVersion' => Frontend::API_VERSION,
            'status' => 'ok',
            'asset' => $editor->getAssets()->upload($file, $config, $kind),
        ];
    }

    /**
     * Carry out a write once per `operationId`. The result is remembered for ten minutes against the
     * person making it and the kind of write, and a repeat returns it with `true` for replayed instead
     * of acting twice, which covers double clicks, retries after a dropped connection and two copies of
     * the script. REST and GraphQL each keep their own, because their answers differ in shape. Failures
     * aren't remembered, so a retry after one, such as with fresh protection, is carried out.
     */
    public function once(string $name, mixed $operationId, callable $write, string $transport = 'rest'): array
    {
        if (!is_string($operationId) || !preg_match('/^[A-Za-z0-9_-]{8,128}$/', $operationId)) {
            throw new ApiException(Craft::t('comments', 'Every change needs an operation ID.'), 'validation_failed', 422, [
                'operationId' => [Craft::t('comments', 'Every change needs an operation ID.')],
            ]);
        }

        $key = 'comments:api:operation:' . hash('sha256', implode('|', [$this->_actorKey(), $transport, $name, $operationId]));
        $cache = Craft::$app->getCache();
        $mutex = Craft::$app->getMutex();

        // Two copies of the same write arriving together wait for each other rather than both acting.
        $locked = $mutex->acquire($key, 10);

        try {
            $replay = $cache->get($key);

            if (is_array($replay) && array_key_exists('result', $replay)) {
                return [$replay['result'], true];
            }

            $result = $write();
            $cache->set($key, ['result' => $result], self::REPLAY_DURATION);

            return [$result, false];
        } finally {
            if ($locked) {
                $mutex->release($key);
            }
        }
    }

    /**
     * One comment as the data API describes it to the person asking: in the state they see it in, with
     * their permissions and, for a moderator, what they can decide unless `$withModeration` is off.
     * GraphQL's `commentsData` gives this.
     */
    public function commentData(Comment $comment, bool $withModeration = true): array
    {
        $data = $this->commentDataFor([$comment], $withModeration);

        if (!isset($data[(int)$comment->id])) {
            throw $this->_notFound();
        }

        return $data[(int)$comment->id];
    }

    /**
     * `commentData()` for many comments at once, keyed by comment ID, with the asker, their votes and
     * reports, parents, badges and site data looked up once for all of them rather than per comment.
     * Comments whose owner can't be found are left out.
     */
    public function commentDataFor(array $comments, bool $withModeration = true): array
    {
        $user = $this->_user();
        $moderation = CommentsPlugin::$plugin->getModeration();
        $states = [];

        foreach ($comments as $comment) {
            $state = $moderation->currentState($comment);
            $removed = $comment->authorRemovedAt || $comment->trashed || $state === ModerationState::Removed;

            $states[(int)$comment->id] = match (true) {
                $withModeration && $user && $moderation->canModerateComment($user, $comment) => match (true) {
                    $removed => 'removed',
                    $state === ModerationState::Spam => 'spam',
                    $state === ModerationState::Pending => 'held',
                    $comment->getFlags() > 0 => 'reported',
                    default => 'approved',
                },
                $removed => 'placeholder',
                $state === ModerationState::Approved => 'approved',
                default => 'pending',
            };
        }

        return $this->_privateComments($comments, $user, $states, $withModeration);
    }

    /**
     * The renderer's text in the language of the site the response is for.
     */
    public function labels(): array
    {
        $language = $this->_currentSite()->language;
        $labels = [];

        foreach (self::LABELS as $key => $source) {
            $labels[$key] = Craft::t('comments', $source, [], $language);
        }

        return $labels;
    }

    /**
     * Refuse a visitor's public reads beyond the limits, keyed by their address alone so the limit never
     * depends on cookies. Reads that have to be built count against `PUBLIC_READS_PER_MINUTE`, and every
     * read, including ones answered from the cache, against a looser `CACHED_READS_PER_MINUTE`, so a
     * site's own server rendering many pages from one address isn't refused while the cache answers it.
     * Behind a proxy, the address comes from `trustedHosts`.
     */
    public function limitPublicRead(bool $built = false): void
    {
        $this->rateLimit($built ? 'built' : 'reads', $built ? self::PUBLIC_READS_PER_MINUTE : self::CACHED_READS_PER_MINUTE);
    }

    /**
     * A per-address limit of `$limit` requests a minute for one kind of request. IPv6 addresses are
     * counted by their /64, which is what one visitor usually has.
     */
    public function rateLimit(string $bucket, int $limit): void
    {
        $ipAddress = RequestSignals::fromRequest(Craft::$app->getRequest())->ipAddress;

        if (!$ipAddress) {
            return;
        }

        $binary = @inet_pton($ipAddress);

        if ($binary !== false && strlen($binary) === 16) {
            $ipAddress = bin2hex(substr($binary, 0, 8)) . '::/64';
        }

        $cache = Craft::$app->getCache();
        $key = 'comments:api:rate:' . $bucket . ':' . hash('sha256', $ipAddress) . ':' . intdiv(DateTimeHelper::currentTimeStamp(), 60);
        $count = (int)$cache->get($key) + 1;
        $cache->set($key, $count, 60);

        if ($count > $limit) {
            throw new ApiException(Craft::t('comments', 'Please wait before trying again.'), 'rate_limited', 429, retryAfter: 60 - DateTimeHelper::currentTimeStamp() % 60);
        }
    }

    /**
     * Forget every cached public response, for changes that aren't element saves, such as votes, and
     * move the data version on. Inside a database transaction it happens again once the outermost one
     * commits, because a read in between could cache data from before the change.
     */
    public function invalidateCaches(): void
    {
        $this->_invalidateNow();
        $db = Craft::$app->getDb();

        if (!$db->getTransaction() || $this->_invalidateOnCommit) {
            return;
        }

        $this->_invalidateOnCommit = true;
        $committed = null;
        $rolledBack = null;
        $done = function() use ($db, &$committed, &$rolledBack): void {
            $db->off(Connection::EVENT_COMMIT_TRANSACTION, $committed);
            $db->off(Connection::EVENT_ROLLBACK_TRANSACTION, $rolledBack);
            $this->_invalidateOnCommit = false;
        };
        $committed = function() use ($done): void {
            $done();
            $this->_invalidateNow();
        };
        $rolledBack = $done;
        $db->on(Connection::EVENT_COMMIT_TRANSACTION, $committed);
        $db->on(Connection::EVENT_ROLLBACK_TRANSACTION, $rolledBack);
    }

    /**
     * Turn an exception from anywhere in Comments into the data API's error, with its code and status.
     */
    public function errorFor(Throwable $e): ApiException
    {
        return match (true) {
            $e instanceof ApiException => $e,
            $e instanceof ProtectionException => new ApiException($e->getMessage(), $e->errorCode, $e->httpStatus, retryAfter: $e->retryAfter),
            $e instanceof RefusedException => new ApiException($e->getMessage(), $e->errorCode, $e->httpStatus),
            $e instanceof CommentValidationException => new ApiException($e->getMessage(), 'validation_failed', 422, $this->_fieldErrors($e->fieldErrors)),
            $e instanceof UploadRefusedException => new ApiException($e->getMessage(), $e->errorCode, 422),
            $e instanceof HttpException => match (true) {
                $e->statusCode === 401 => new ApiException($e->getMessage(), 'sign_in_required', 401),
                $e->statusCode === 403 => new ApiException($e->getMessage(), 'forbidden', 403),
                $e->statusCode === 404 => $this->_notFound(),
                $e->statusCode === 405 => new ApiException($e->getMessage(), 'method_not_allowed', 405),
                $e->statusCode === 429 => new ApiException($e->getMessage(), 'rate_limited', 429),
                $e->statusCode >= 500 => new ApiException(Craft::t('comments', 'Something went wrong. Please try again.'), 'server_error', 500),
                default => new ApiException($e->getMessage(), 'invalid_request', 400),
            },
            $e instanceof UserException => new ApiException($e->getMessage(), 'validation_failed', 422),
            default => new ApiException(Craft::t('comments', 'Something went wrong. Please try again.'), 'server_error', 500),
        };
    }

    /**
     * The JSON for an error, as every `comments/api` response and GraphQL's extensions give it.
     */
    public function errorData(ApiException $error): array
    {
        return [
            'apiVersion' => Frontend::API_VERSION,
            'status' => 'error',
            'errors' => [[
                'category' => $this->_errorCategory($error->errorCode),
                'code' => $error->errorCode,
                'message' => $error->getMessage(),
                'fieldErrors' => $error->fieldErrors ?: new stdClass(),
                'recoverable' => in_array($error->errorCode, ['validation_failed', 'protection_required', 'protection_expired', 'rate_limited', 'csrf_failed', 'token_expired', 'session_expired', 'conflict', 'protection_unavailable'], true),
                'retryAfter' => $error->retryAfter,
            ]],
        ];
    }


    // Private Methods
    // =========================================================================

    /**
     * Who's making a write, for remembering its result. A guest is known by their CSRF cookie, which
     * every browser write needs and which stays the same while their first comment gives them a guest
     * cookie, then by their session or guest cookie, and last by their address.
     */
    private function _actorKey(): string
    {
        $user = $this->_user();

        if ($user) {
            return 'user:' . $user->id;
        }

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return 'console';
        }

        // A guest on another site is known by their session token, and never by a cookie.
        $signIn = CommentsPlugin::$plugin->getSignIn()->current();

        if ($signIn && $signIn->usesHeaderToken()) {
            return $signIn->guestSecret !== null
                ? 'guest:' . hash('sha256', $signIn->guestSecret)
                : 'ip:' . hash('sha256', (string)$request->getUserIP());
        }

        $csrf = $request->enableCsrfCookie ? $request->getCookies()->getValue($request->csrfParam) : null;

        if (is_string($csrf) && $csrf !== '') {
            return 'csrf:' . hash('sha256', $csrf);
        }

        $session = Craft::$app->getSession();

        if ($session->getIsActive() || $session->getHasSessionId()) {
            return 'session:' . hash('sha256', (string)$session->getId());
        }

        $guestToken = CommentsPlugin::$plugin->getIdentities()->guestTokenFromRequest();

        if ($guestToken !== null) {
            return 'guest:' . hash('sha256', $guestToken);
        }

        return 'ip:' . hash('sha256', (string)$request->getUserIP());
    }

    /**
     * Where a guest signs in to comment as a member: Craft's own sign-in page, unless the guest is on
     * another site (a `cross-origin-public` request), where signing in to Craft wouldn't sign them in
     * on the site they're reading. That site signs its members in itself, with member tokens.
     */
    private function _loginUrl(?User $user): ?string
    {
        $loginPath = Craft::$app->getConfig()->getGeneral()->getLoginPath();

        if ($user || !is_string($loginPath) || $loginPath === '' || CommentsPlugin::$plugin->getSignIn()->current()?->profile === RequestProfile::CrossOriginPublic) {
            return null;
        }

        return UrlHelper::siteUrl($loginPath);
    }

    private function _notFound(): ApiException
    {
        return new ApiException(Craft::t('comments', 'This can’t be shown.'), 'not_found', 404);
    }

    private function _user(): ?User
    {
        $user = CommentsPlugin::$plugin->getService()->getUser();

        return $user instanceof User ? $user : null;
    }

    private function _config(ElementInterface $owner): ConversationConfig
    {
        return CommentsPlugin::$plugin->getConversations()->getConfigForOwner((int)$owner->id, (int)$owner->siteId, $owner);
    }

    private function _siteFor(?ElementInterface $owner): Site
    {
        return ($owner ? Craft::$app->getSites()->getSiteById((int)$owner->siteId) : null) ?? Craft::$app->getSites()->getCurrentSite();
    }

    private function _currentSite(): Site
    {
        return $this->_site ?? Craft::$app->getSites()->getCurrentSite();
    }

    /**
     * Build a response in the language of the site it's for, so labels, dates and relative times match
     * the thread's page even when the action URL belongs to another site.
     */
    private function _inSite(Site $site, callable $build): mixed
    {
        $app = Craft::$app;
        $previousSite = $this->_site;
        $previousLanguage = $app->language;
        $this->_site = $site;
        $switch = $site->language !== $previousLanguage;

        if ($switch) {
            $this->_setLanguage($site->language);
        }

        try {
            return $build();
        } finally {
            $this->_site = $previousSite;

            if ($switch) {
                $this->_setLanguage($previousLanguage);
            }
        }
    }

    /**
     * Craft creates its locale and formatter once, for the language it starts with, so they're made
     * again for the new one.
     */
    private function _setLanguage(string $language): void
    {
        $app = Craft::$app;
        $app->language = $language;
        $app->set('locale', fn() => Craft::$app->getI18n()->getLocaleById(Craft::$app->language));
        $app->set('formattingLocale', fn() => Craft::$app->getI18n()->getLocaleById(Craft::$app->language));
        $app->set('formatter', fn() => Craft::$app->getFormattingLocale()->getFormatter());
    }

    /**
     * A public response from Craft's data cache, built once between the saves that change it. It's keyed
     * only by its normalised parameters, the site, the URLs it's built on and the settings, so nothing a
     * visitor sends can make a variant. Anything that depends on the time or the request, such as relative
     * times and permalinks, is filled in on the way out.
     *
     * It isn't cached at all while Craft works out `@web` from the request, because then a forged host
     * header could be built into a response served to everyone, or when `$cacheable` is off, as for page
     * sizes other than the default, which would otherwise multiply the variants.
     *
     * Building one counts against the visitor's rate limit for uncached reads, and runs as nobody, so an
     * event handler can't make cached data depend on who happened to ask first.
     */
    private function _cachedPublic(array $key, ElementInterface $owner, bool $cacheable, callable $build): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $site = $this->_siteFor($owner);
        $make = function() use ($site, $build): array {
            $this->limitPublicRead(true);

            return $this->_asNobody(fn() => $this->_inSite($site, $build));
        };

        if (!$cacheable || $this->isHostDependent()) {
            $data = $make();
        } else {
            $cacheKey = 'comments:api:' . hash('sha256', Json::encode([
                $key,
                (int)$owner->siteId,
                Frontend::API_VERSION,
                Craft::getAlias('@web'),
                $site->getBaseUrl(),
                $settings->getAttributes(),
                // Changes to the Comment field layout, such as a field's handle or label, and to which fields
                // are shown, which `config/comments.php` can set without project config.
                Craft::$app->getInfo()->configVersion,
                CommentsPlugin::$plugin->getFormFields()->settingsHash(),
            ]));

            $data = Craft::$app->getCache()->getOrSet($cacheKey, $make, self::CACHE_DURATION, new TagDependency(['tags' => [
                self::CACHE_TAG,
                sprintf('element::%s::*', Comment::class),
                sprintf('element::%s::*', User::class),
                sprintf('element::%s', $owner->id),
            ]]));
        }

        return $this->_inSite($site, fn() => $this->_finish($data, $owner));
    }

    /**
     * Whether this browser has anything Comments could recognise it by: Craft's session, its sign-in
     * cookie, or a guest's cookie. Without any, `viewer` reads nothing personal and starts nothing.
     */
    public function hasSession(): bool
    {
        $request = Craft::$app->getRequest();

        if (!$request instanceof \craft\web\Request) {
            return false;
        }

        // A request from another site is recognised by its token alone: a member token, or a guest's
        // session token once they've started writing.
        $signIn = CommentsPlugin::$plugin->getSignIn()->current();

        if ($signIn && $signIn->usesHeaderToken()) {
            return $signIn->user !== null || $signIn->guestSecret !== null;
        }

        $cookies = $request->getCookies();
        $user = Craft::$app->getUser();

        return Craft::$app->getSession()->getHasSessionId()
            || ($user instanceof \craft\web\User && $user->enableAutoLogin && $cookies->has($user->identityCookie['name'] ?? '_identity'))
            || $cookies->has(Identities::GUEST_COOKIE_NAME);
    }

    /**
     * Whether Craft works out the site's URLs from the request's own host, which `X-Forwarded-Host` can
     * change. Nothing built then can be shared between visitors.
     */
    public function isHostDependent(): bool
    {
        $request = Craft::$app->getRequest();

        return $request instanceof \craft\web\Request && $request->isWebAliasSetDynamically;
    }

    /**
     * A stamp that changes whenever cached public data does. A thread carries the stamp it was built
     * with as `threadVersion`, and `viewer` gives the current one under the same name, so a client can tell
     * when a CDN or the browser served it an older thread, such as just after its own comment.
     */
    public function dataVersion(): string
    {
        $cache = Craft::$app->getCache();
        $version = $cache->get(self::VERSION_KEY);

        if (!is_string($version)) {
            $version = $this->_newVersion();
            $cache->set(self::VERSION_KEY, $version);
        }

        return $version;
    }

    /**
     * Rows for these threads, deep enough for what a response can show: `$depth` levels, which also
     * counts the replies of the deepest comments shown. A thread whose hidden comments reach that depth
     * may have visible replies deeper down that make them placeholders, so it's loaded in full.
     */
    private function _threadRows(array $rootIds, int $depth): array
    {
        $conversations = CommentsPlugin::$plugin->getConversations();
        $rows = $conversations->getThreadRows($rootIds, $depth);
        $full = [];

        foreach ($rows as $row) {
            if ($row['depth'] === $depth && ($row['removed'] || $row['moderationState'] !== ModerationState::Approved->value)) {
                $full[$row['rootId']] = true;
            }
        }

        if ($full !== []) {
            $rows = array_filter($rows, static fn(array $row): bool => !isset($full[$row['rootId']])) + $conversations->getThreadRows(array_keys($full));
        }

        return $rows;
    }

    /**
     * Run a build with the person asking hidden, then restore them.
     */
    private function _asNobody(callable $build): mixed
    {
        $userComponent = Craft::$app->getUser();
        $identity = $userComponent->getIdentity(false);

        if (!$identity) {
            return $build();
        }

        $userComponent->setIdentity(null);

        try {
            return $build();
        } finally {
            $userComponent->setIdentity($identity);
        }
    }

    private function _invalidateNow(): void
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::CACHE_TAG);
        $cache->set(self::VERSION_KEY, $this->_newVersion());
    }

    private function _newVersion(): string
    {
        return base_convert((string)(int)(microtime(true) * 1000), 10, 36) . bin2hex(random_bytes(2));
    }

    private function _limit(mixed $value, int $default, int $max): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value) || (int)$value != $value || (int)$value < 1 || (int)$value > $max) {
            throw new ApiException(Craft::t('comments', 'Ask for between 1 and {max} at a time.', ['max' => $max]), 'limit_exceeded', 400);
        }

        return (int)$value;
    }

    /**
     * The sort asked for, or the conversation's own. Anything unknown is the default, so it can't make a
     * new cache variant, and top falls back when there's nothing to rank by.
     */
    private function _resolveSort(mixed $sort, ConversationConfig $config): CommentSort
    {
        $resolved = (is_string($sort) ? CommentSort::tryFrom(strtolower($sort)) : null) ?? $config->sortOrder;

        if ($resolved === CommentSort::Top && !CommentsPlugin::$plugin->getSettings()->resolveInteractionMode()->isEnabled()) {
            return CommentsPlugin::$plugin->getSettings()->orderBy === 'asc' ? CommentSort::Oldest : CommentSort::Newest;
        }

        return $resolved;
    }

    private function _uids(mixed $uids): array
    {
        if ($uids === null || $uids === '' || $uids === []) {
            return [];
        }

        $uids = is_array($uids) ? $uids : explode(',', (string)$uids);

        if (count($uids) > self::MAX_VIEWER_UIDS) {
            throw new ApiException(Craft::t('comments', 'Ask for between 1 and {max} at a time.', ['max' => self::MAX_VIEWER_UIDS]), 'limit_exceeded', 400);
        }

        return array_values(array_unique(array_filter($uids, static fn(mixed $uid): bool => is_string($uid) && preg_match('/^[0-9a-f-]{36}$/i', $uid))));
    }

    /**
     * Comment numbers asked for alongside UIDs, such as from a permalink's `#comment-123`, sharing the
     * same limit.
     */
    private function _ids(mixed $ids, int $uidCount = 0): array
    {
        if ($ids === null || $ids === '' || $ids === []) {
            return [];
        }

        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);

        if (count($ids) + $uidCount > self::MAX_VIEWER_UIDS) {
            throw new ApiException(Craft::t('comments', 'Ask for between 1 and {max} at a time.', ['max' => self::MAX_VIEWER_UIDS]), 'limit_exceeded', 400);
        }

        $valid = array_filter($ids, static fn(mixed $id): bool => (is_int($id) || (is_string($id) && ctype_digit($id))) && (int)$id > 0);

        return array_values(array_unique(array_map('intval', $valid)));
    }

    private function _rowsForUids(int $conversationId, array $uids, array $ids = []): array
    {
        return array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'rootId' => (int)$row['rootId'], 'depth' => (int)$row['depth']], (new Query())
            ->select(['c.id', 'c.rootId', 'c.depth'])
            ->from(['c' => '{{%comments_comments}}'])
            ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[c.id]]')
            ->where(['c.conversationId' => $conversationId])
            ->andWhere(['or', ['e.uid' => $uids ?: null], ['c.id' => $ids ?: null]])
            ->all());
    }

    /**
     * A cursor is the last row's sort key, signed with Craft's security key so nobody can make one that
     * reaches rows they couldn't page to, and encoded so it's opaque.
     */
    private function _encodeCursor(array $payload): string
    {
        $signed = Craft::$app->getSecurity()->hashData(Json::encode(['v' => self::CURSOR_VERSION, ...$payload]));

        return rtrim(strtr(base64_encode($signed), '+/', '-_'), '=');
    }

    private function _decodeCursor(mixed $cursor): array
    {
        $invalid = new ApiException(Craft::t('comments', 'That page link isn’t valid any more. Load the comments again.'), 'invalid_cursor', 400);

        if (!is_string($cursor) || $cursor === '' || strlen($cursor) > self::CURSOR_MAX_LENGTH) {
            throw $invalid;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $json = $decoded !== false ? Craft::$app->getSecurity()->validateData($decoded) : false;
        $data = is_string($json) ? Json::decodeIfJson($json) : null;

        if (!is_array($data) || ($data['v'] ?? null) !== self::CURSOR_VERSION) {
            throw $invalid;
        }

        return $data;
    }

    /**
     * A page's cursor only continues the list it came from, at the page size it came from.
     */
    private function _rootCursor(mixed $cursor, ?ConversationRecord $conversation, CommentSort $sort, int $limit): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $data = $this->_decodeCursor($cursor);

        // A cursor only continues the list it came from.
        if (!$conversation || ($data['k'] ?? null) !== 'roots' || (int)($data['c'] ?? 0) !== (int)$conversation->id || ($data['s'] ?? null) !== $sort->value || (int)($data['l'] ?? 0) !== $limit || !isset($data['d'], $data['i'])) {
            throw new ApiException(Craft::t('comments', 'That page link isn’t valid any more. Load the comments again.'), 'invalid_cursor', 400);
        }

        return ['id' => (int)$data['i'], 'commentDate' => (string)$data['d'], 'score' => (int)($data['sc'] ?? 0)];
    }

    /**
     * A cursor from a page of replies only continues at that page's size. One from a preview, which
     * isn't a page, can start a page of any size.
     */
    private function _repliesCursor(mixed $cursor, int $parentId, int $limit): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $data = $this->_decodeCursor($cursor);

        if (($data['k'] ?? null) !== 'replies' || (int)($data['p'] ?? 0) !== $parentId || (isset($data['l']) && (int)$data['l'] !== $limit)) {
            throw new ApiException(Craft::t('comments', 'That page link isn’t valid any more. Load the comments again.'), 'invalid_cursor', 400);
        }

        // A cursor with no row starts from the first reply.
        return isset($data['d'], $data['i']) ? ['id' => (int)$data['i'], 'commentDate' => (string)$data['d']] : null;
    }

    private function _repliesCursorFor(int $parentId, ?array $lastRow, ?int $limit = null): string
    {
        return $this->_encodeCursor(array_filter([
            'k' => 'replies',
            'p' => $parentId,
            'l' => $limit,
            'd' => $lastRow['commentDate'] ?? null,
            'i' => $lastRow['id'] ?? null,
        ], static fn(mixed $value): bool => $value !== null));
    }

    /**
     * Whether a reply comes after a cursor's row in the order replies are shown.
     */
    private function _isAfter(array $row, array $after): bool
    {
        $order = [(string)$row['commentDate'], (int)$row['id']] <=> [(string)$after['commentDate'], (int)$after['id']];

        return $this->_replySort() === CommentSort::Oldest ? $order > 0 : $order < 0;
    }

    private function _replySort(): CommentSort
    {
        return CommentsPlugin::$plugin->getSettings()->orderBy === 'asc' ? CommentSort::Oldest : CommentSort::Newest;
    }

    /**
     * Which rows a thread shows: those in `$states`, and hidden ones holding a reply that is, which stand
     * in as placeholders so the replies beneath stay connected.
     */
    private function _shownIds(array $rows, array $states): array
    {
        $shown = [];

        foreach ($rows as $id => $row) {
            if (!$row['removed'] && in_array($row['moderationState'], $states, true)) {
                $shown[$id] = true;
            }
        }

        foreach (array_keys($shown) as $id) {
            $parentId = $rows[$id]['parentId'];
            $walked = [];

            while ($parentId && isset($rows[$parentId]) && !isset($walked[$parentId])) {
                $walked[$parentId] = true;
                $shown[$parentId] ??= true;
                $parentId = $rows[$parentId]['parentId'];
            }
        }

        return $shown;
    }

    /**
     * The shown children of every row, in the order replies are listed.
     */
    private function _shownChildren(array $rows, array $shown): array
    {
        $children = [];

        foreach ($rows as $id => $row) {
            if ($row['parentId'] !== null && isset($shown[$id])) {
                $children[$row['parentId']][] = $row;
            }
        }

        $direction = $this->_replySort() === CommentSort::Oldest ? 1 : -1;

        foreach ($children as &$list) {
            usort($list, static fn(array $a, array $b): int => $direction * ([(string)$a['commentDate'], $a['id']] <=> [(string)$b['commentDate'], $b['id']]));
        }

        return $children;
    }

    /**
     * Arrange rows into the nested shape a response returns. Starting from `$tops`, each comment above
     * the depth limit gets its first replies as a preview, level by level, until the response holds
     * `$budget` comments, and a comment whose replies don't all fit gets a cursor for the rest. `$path`
     * pins the replies leading to a linked comment first among their siblings, at any depth.
     */
    private function _arrange(array $tops, array $rows, array $states, int $budget, array $path = [], ?array $shown = null): array
    {
        $shown ??= $this->_shownIds($rows, $states);
        $children = $this->_shownChildren($rows, $shown);
        $tops = array_slice(array_values(array_filter($tops, static fn(array $row): bool => isset($shown[$row['id']]))), 0, max(0, $budget));
        $budget -= count($tops);
        $plan = [];
        $level = array_column($tops, 'id');

        // Breadth first, so a large page shares the budget across its comments rather than spending it
        // all on the first one's replies.
        while ($level !== []) {
            $nextLevel = [];

            foreach ($level as $id) {
                $kids = $children[$id] ?? [];
                $pinned = array_values(array_filter($kids, static fn(array $row): bool => isset($path[$row['id']])));
                $take = $rows[$id]['depth'] < self::MAX_DEPTH ? self::REPLY_PREVIEW_LIMIT : 0;

                if ($pinned) {
                    $kids = [...$pinned, ...array_values(array_filter($kids, static fn(array $row): bool => !isset($path[$row['id']])))];
                    $take = max($take, 1);
                }

                $items = array_slice($kids, 0, max(0, min($take, $budget)));
                $budget -= count($items);
                $count = count($children[$id] ?? []);

                $plan[$id] = [
                    'count' => $count,
                    'items' => array_column($items, 'id'),
                    // A pinned reply breaks the order, so the rest are loaded from the start.
                    'next' => $count > count($items) ? $this->_repliesCursorFor($id, $items && !$pinned ? $items[array_key_last($items)] : null) : null,
                ];
                array_push($nextLevel, ...array_column($items, 'id'));
            }

            $level = $nextLevel;
        }

        $build = function(int $id) use (&$build, $rows, $plan): array {
            $row = $rows[$id];
            $parent = $row['parentId'] !== null ? ($rows[$row['parentId']] ?? null) : null;

            return [
                'id' => $id,
                'uid' => $row['uid'],
                'parentUid' => $parent['uid'] ?? null,
                'depth' => $row['depth'],
                'placeholder' => $row['removed'] || $row['moderationState'] !== ModerationState::Approved->value,
                'row' => $row,
                'count' => $plan[$id]['count'] ?? 0,
                'items' => array_map($build, $plan[$id]['items'] ?? []),
                'next' => $plan[$id]['next'] ?? null,
            ];
        };

        return array_map($build, array_column($tops, 'id'));
    }

    /**
     * Every row in arranged nodes, keyed by ID.
     */
    private function _flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[$node['id']] = $node['row'];
            $flat += $this->_flatten($node['items']);
        }

        return $flat;
    }

    /**
     * Comments a viewer may see in the window that the public data leaves out: placeholders that are
     * really their own waiting comment or one a moderator can see, roots in the window's range that
     * aren't public, and their hidden replies beneath anything in the window, level by level.
     */
    private function _extras(array $window, array $extraRoots, array $rows, array $shown, callable $canSee, array $requested = []): array
    {
        $extras = [];

        foreach ($requested as $id => $row) {
            if (!isset($shown[$id]) && $canSee($row)) {
                $extras[$id] = $row;
            }
        }

        foreach ($window as $id => $row) {
            if ($row['moderationState'] !== ModerationState::Approved->value && $canSee($row)) {
                $extras[$id] = $row;
            }
        }

        foreach ($extraRoots as $root) {
            if (isset($rows[$root['id']]) && $canSee($rows[$root['id']])) {
                $extras[$root['id']] = $rows[$root['id']];
            }
        }

        $parents = array_fill_keys(array_keys($window + $extras), true);
        $byParent = [];

        foreach ($rows as $id => $row) {
            if ($row['parentId'] !== null && !isset($shown[$id]) && $canSee($row)) {
                $byParent[$row['parentId']][] = $row;
            }
        }

        while ($parents !== [] && count($extras) < self::MAX_COMMENTS) {
            $found = [];

            foreach (array_keys($parents) as $parentId) {
                foreach ($byParent[$parentId] ?? [] as $row) {
                    if (!isset($extras[$row['id']]) && count($extras) < self::MAX_COMMENTS) {
                        $extras[$row['id']] = $row;
                        $found[$row['id']] = true;
                    }
                }
            }

            $parents = $found;
        }

        return $extras;
    }

    /**
     * Load comments with what their views need, keyed by ID. With `$author`, they're all theirs, so the
     * batch lookups of authors, owners and report counts are skipped and only the vote totals of
     * `$totalsFor` are counted, for the ones that are drawn.
     */
    private function _loadComments(array $ids, ElementInterface $owner, ?User $author = null, array $totalsFor = []): array
    {
        if ($ids === []) {
            return [];
        }

        $query = Comment::find()->id($ids)->status(null)->siteId('*')->indexBy('id');

        if ($author) {
            $query->withRelatedData(false);
        }

        $comments = $query->all();
        $totals = $author && $totalsFor ? CommentsPlugin::$plugin->getInteractions()->getTotals($totalsFor) : [];

        foreach ($comments as $id => $comment) {
            if ($author && (int)$comment->userId === (int)$author->id) {
                $comment->setUser($author, true);
            }

            if ((int)$comment->ownerId === (int)$owner->id) {
                $comment->setOwner($owner, true);
            }

            if (isset($totals[$id])) {
                $comment->setInteractionTotals($totals[$id]);
            }
        }

        return $comments;
    }

    private function _reload(int $id): Comment
    {
        $comment = Comment::find()->id($id)->status(null)->siteId('*')->trashed(null)->one();

        if (!$comment) {
            throw $this->_notFound();
        }

        return $comment;
    }

    /**
     * A comment by its UID, which must belong to a live owner with comments on. The comment's own state
     * is for the caller to check.
     */
    private function _commentByUid(mixed $uid, bool $includeTrashed = false): Comment
    {
        if (!is_string($uid) || !preg_match('/^[0-9a-f-]{36}$/i', $uid)) {
            throw $this->_notFound();
        }

        $comment = Comment::find()->uid($uid)->status(null)->siteId('*')->trashed($includeTrashed ? null : false)->one();
        $owner = $comment?->getOwner();

        if (!$comment || !$owner || !CommentsPlugin::$plugin->getConversations()->isOwnerPublic($owner) || !$this->_config($owner)->enabled) {
            throw $this->_notFound();
        }

        return $comment;
    }

    /**
     * A comment a public read may start from: one the thread shows, or with `$asParent`, one standing in
     * as a placeholder for the replies beneath it.
     */
    private function _publicComment(mixed $uid, bool $asParent): array
    {
        $comment = $this->_commentByUid($uid, $asParent);
        $owner = $this->resolveOwner((int)$comment->ownerId, null, (int)$comment->ownerSiteId);
        $public = !$comment->trashed && !$comment->authorRemovedAt && $comment->moderationState === ModerationState::Approved->value;

        if (!$public && !$asParent) {
            throw $this->_notFound();
        }

        if (!$public) {
            $rows = $this->_threadRows([(int)($comment->rootId ?: $comment->id)], (int)$comment->depth + 1);

            if (!isset($this->_shownIds($rows, [ModerationState::Approved->value])[(int)$comment->id])) {
                throw $this->_notFound();
            }
        }

        return [$comment, $owner];
    }

    /**
     * Arranged nodes as public comment data, with the rest of their elements loaded at once.
     */
    private function _publicComments(array $nodes, ElementInterface $owner): array
    {
        $flat = $this->_flatten($nodes);
        $ids = array_keys(array_filter($flat, static fn(array $row): bool => !$row['removed'] && $row['moderationState'] === ModerationState::Approved->value));
        $comments = $this->_loadComments($ids, $owner);
        $views = CommentsPlugin::$plugin->getFrontend()->buildDetachedCommentViews(array_values($comments));
        $custom = $this->_customData($comments, $owner);
        $ownerUrl = $owner->getUrl();

        $build = function(array $node) use (&$build, $views, $comments, $custom, $ownerUrl): array {
            $view = $node['placeholder'] ? null : ($views[$node['id']] ?? null);
            $data = $view && isset($comments[$node['id']])
                ? $this->_commentData($view, $comments[$node['id']], $ownerUrl, $node['parentUid'], 'approved')
                : $this->_placeholderData($node);
            $data['custom'] = $custom[$node['uid']] ?? new stdClass();
            $data['replies'] = [
                'count' => $node['count'],
                'items' => array_map($build, $node['items']),
                'next' => $node['next'],
            ];

            return $data;
        };

        return array_map($build, $nodes);
    }

    /**
     * One comment as the data API describes it to anyone, apart from its `custom` and `replies`.
     */
    private function _commentData(FrontendCommentView $view, Comment $comment, ?string $ownerUrl, ?string $parentUid, string $state): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $rich = $comment->isEditorContent();
        $mode = $settings->resolveInteractionMode();

        return [
            'uid' => $view->uid,
            'parentUid' => $parentUid,
            'depth' => $view->depth,
            'state' => $state,
            'content' => [
                'format' => $rich ? 'rich' : 'plain',
                // Sanitised by Editor when it was rendered, and the only HTML the renderer inserts.
                'html' => $rich ? $view->contentHtml : null,
                'text' => $rich ? trim((string)$comment->comment) : $view->content,
                'gifUrl' => $view->gifUrl,
                'gifWidth' => $view->gifWidth,
                'gifHeight' => $view->gifHeight,
            ],
            'author' => [
                'name' => $view->authorName ?: Craft::t('comments', 'Guest'),
                'initials' => $view->authorInitials,
                'avatarUrl' => $view->authorAvatarUrl,
                'avatarTone' => $view->avatarTone,
                'isGuest' => !$comment->userId,
                'badges' => $view->badges,
                // Net votes across the author's approved comments, while **Show Author Score** is on.
                'score' => $settings->showsAuthorScore() && $comment->userId ? $comment->getAuthorScore() : null,
            ],
            // `reports` or `downvotes` when the thread folds the comment away, as Comments 3 hid it.
            'folded' => $comment->getFoldReason(),
            'createdAt' => $view->commentDate,
            'editedAt' => $view->editedAt,
            'display' => [
                'timeAgo' => null,
                'fullDate' => $view->fullDate,
            ],
            // The owner's URL is added as the response leaves, as it can depend on the request's host.
            'permalink' => '#comment-' . $view->id,
            'fields' => CommentsPlugin::$plugin->getFormFields()->shownFields($comment),
            'interaction' => $mode->isEnabled() ? [
                'upvotes' => $comment->getUpvotes(),
                'downvotes' => $comment->getDownvotes(),
            ] : null,
        ];
    }

    /**
     * Stands in for a hidden comment with visible replies, saying nothing about it or why it's hidden.
     */
    private function _placeholderData(array $node): array
    {
        return [
            'uid' => $node['uid'],
            'parentUid' => $node['parentUid'],
            'depth' => $node['depth'],
            'state' => 'placeholder',
            'content' => null,
            'author' => null,
            'folded' => null,
            'createdAt' => null,
            'editedAt' => null,
            'display' => null,
            'permalink' => null,
            'fields' => [],
            'interaction' => null,
        ];
    }

    /**
     * What `EVENT_DEFINE_COMMENT_DATA` handlers add to these comments, keyed by UID. Anything that isn't
     * a non-empty array is left out.
     */
    private function _customData(array $comments, ElementInterface $owner, bool $isPublic = true): array
    {
        if ($comments === [] || !$this->hasEventHandlers(self::EVENT_DEFINE_COMMENT_DATA)) {
            return [];
        }

        $byUid = [];

        foreach ($comments as $comment) {
            $byUid[(string)$comment->uid] = $comment;
        }

        $event = new DefineCommentDataEvent([
            'comments' => $byUid,
            'owner' => $owner,
            'siteId' => (int)$owner->siteId,
            'isPublic' => $isPublic,
        ]);
        $this->trigger(self::EVENT_DEFINE_COMMENT_DATA, $event);
        $custom = array_filter(
            array_intersect_key($event->custom, $byUid),
            static fn(mixed $data): bool => is_array($data) && $data !== [],
        );

        if ($isPublic && Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->_warnAboutPrivateData($custom, $byUid);
        }

        return $custom;
    }

    /**
     * In Dev Mode, say when a handler put a commenter's address, IP address or user ID into data that's
     * cached and served to everyone.
     */
    private function _warnAboutPrivateData(array $custom, array $comments): void
    {
        foreach ($custom as $uid => $data) {
            $comment = $comments[$uid] ?? null;
            $json = (string)json_encode($data);
            $private = array_filter([
                $comment?->getAuthorEmail(),
                $comment?->ipAddress,
                // Custom field values that are only collected.
                ...($comment ? CommentsPlugin::$plugin->getFormFields()->privateValues($comment) : []),
            ], static fn(mixed $value): bool => is_string($value) && $value !== '' && str_contains($json, $value));

            if ($private || preg_match('/"(userId|email|ipAddress|identityId)"\\s*:/', $json)) {
                Craft::warning('A `' . self::EVENT_DEFINE_COMMENT_DATA . '` handler added private details to the public data of comment ' . $uid . '. That data is cached and served to everyone.', __METHOD__);
            }
        }
    }

    /**
     * What's worked out as a response leaves rather than kept with it: relative times in its site's
     * language, which a cached one would get wrong, and permalinks on the owner's URL, which can depend
     * on the request's host.
     */
    private function _finish(array $data, ?ElementInterface $owner): array
    {
        $showTimeAgo = CommentsPlugin::$plugin->getSettings()->showTimeAgo;
        $ownerUrl = (string)($owner?->getUrl() ?? '');
        $now = new DateTime();
        $fill = function(array $comment) use (&$fill, $now, $showTimeAgo, $ownerUrl): array {
            if ($showTimeAgo && is_array($comment['display'] ?? null) && !empty($comment['createdAt']) && ($date = DateTimeHelper::toDateTime($comment['createdAt']))) {
                $comment['display']['timeAgo'] = CommentsHelper::humanDurationFromInterval($now->diff($date));
            }

            if (is_string($comment['permalink'] ?? null) && str_starts_with($comment['permalink'], '#')) {
                $comment['permalink'] = $ownerUrl . $comment['permalink'];
            }

            if (isset($comment['replies']['items'])) {
                $comment['replies']['items'] = array_map($fill, $comment['replies']['items']);
            }

            return $comment;
        };

        foreach (['comments', 'extraComments'] as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                $data[$key] = array_map($fill, $data[$key]);
            }
        }

        if (isset($data['comment']) && is_array($data['comment'])) {
            $data['comment'] = $fill($data['comment']);
        }

        if (isset($data['replies']['items'])) {
            $data['replies']['items'] = array_map($fill, $data['replies']['items']);
        }

        return $data;
    }

    /**
     * Whether a conversation takes new comments now. Closing after a number of days depends on the
     * time, so it's never cached.
     */
    private function _conversationStatus(ElementInterface $owner, ConversationConfig $config): array
    {
        $closed = $config->closed || CommentsPlugin::$plugin->getComments()->checkExpired($owner);

        return [
            'status' => $closed ? 'closed' : 'open',
            'closedNotice' => $closed ? Craft::t('comments', 'Comments are closed.', [], $this->_siteFor($owner)->language) : null,
        ];
    }

    private function _conversationData(ElementInterface $owner, ConversationConfig $config, ?ConversationRecord $conversation, CommentSort $sort): array
    {
        $plugin = CommentsPlugin::$plugin;
        $settings = $plugin->getSettings();
        $mode = $settings->resolveInteractionMode();
        $closed = $config->closed || $plugin->getComments()->checkExpired($owner);

        return [
            'uid' => $conversation ? (string)$conversation->uid : null,
            'ownerId' => (int)$owner->id,
            'siteId' => (int)$owner->siteId,
            'status' => $closed ? 'closed' : 'open',
            'closedNotice' => $closed ? Craft::t('comments', 'Comments are closed.') : null,
            'commentCount' => (int)($conversation->commentCount ?? 0),
            'rootCount' => $conversation ? $plugin->getConversations()->countRoots((int)$conversation->id) : 0,
            'sort' => $sort->value,
            'sorts' => array_values(array_filter([
                CommentSort::Newest->value,
                CommentSort::Oldest->value,
                $mode->isEnabled() ? CommentSort::Top->value : null,
            ])),
            'interactionMode' => $mode->value,
            'maxLength' => $settings->securityMaxLength ? (int)$settings->securityMaxLength : null,
            'guestPolicy' => [
                'allowed' => $config->allowGuests,
                'nameAndEmail' => match (true) {
                    (bool)$settings->guestRequireEmailName => 'required',
                    (bool)$settings->guestShowEmailName => 'optional',
                    default => 'hidden',
                },
                'canVote' => $mode->isEnabled() && $settings->allowGuestVoting,
                'canReport' => $settings->allowFlagging && $settings->allowGuestFlagging,
            ],
            'followAvailable' => $settings->allowsSubscriptionType(SubscriptionType::Conversation),
            'showAvatars' => (bool)$settings->showAvatar,
            'reportReasons' => $settings->allowFlagging ? Frontend::reportReasons() : [],
        ];
    }

    /**
     * What anyone needs to draw the editor before knowing who's writing.
     */
    private function _publicEditor(): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $rich = $settings->isRichAuthoringEnabled();
        $asset = $rich ? CommentsPlugin::$plugin->getFrontend()->editorAsset() : null;

        return [
            'authoring' => $rich ? 'rich' : 'plain',
            'maxLength' => $settings->securityMaxLength ? (int)$settings->securityMaxLength : null,
            'richEditor' => $asset ? [
                'context' => 'comments.reply',
                'module' => $asset['module'],
                'css' => $asset['css'],
            ] : null,
        ];
    }

    private function _viewerData(
        ElementInterface $owner,
        ConversationConfig $config,
        ?ConversationRecord $conversation,
        ?User $user,
        ?IdentityRecord $identity,
        bool $isModerator,
        bool $canComment,
        bool $blocked,
        bool $slim,
        bool $closed = false,
    ): array {
        $plugin = CommentsPlugin::$plugin;
        $settings = $plugin->getSettings();
        $frontend = $plugin->getFrontend();
        $mode = $settings->resolveInteractionMode();
        $name = $user ? CommentsHelper::publicUserName($user) : null;

        return [
            'isGuest' => !$user,
            'hasSession' => !$slim,
            'name' => $name,
            'initials' => $name ? Commenters::initials($name) : '',
            'avatarUrl' => $frontend->userAvatarUrl($user),
            'avatarTone' => Frontend::avatarTone((string)$name),
            'badges' => $user ? $frontend->viewerBadgesFor($user, $owner) : [],
            'canComment' => $canComment,
            'canVote' => !$blocked && !$closed && $mode->isEnabled() && ($user || $settings->allowGuestVoting),
            'canReport' => !$blocked && $settings->allowFlagging && ($user || $settings->allowGuestFlagging),
            'isModerator' => $isModerator,
            'blocked' => $blocked,
            'loginUrl' => $this->_loginUrl($user),
            'follow' => $this->_followData($owner, $config, $conversation, $user, $identity, $blocked),
        ];
    }

    private function _followData(ElementInterface $owner, ConversationConfig $config, ?ConversationRecord $conversation, ?User $user, ?IdentityRecord $identity, bool $blocked = false): array
    {
        $plugin = CommentsPlugin::$plugin;
        // A closed conversation can still be followed, but not by someone blocked from it (SD-45).
        $available = !$blocked && $plugin->getSettings()->allowsSubscriptionType(SubscriptionType::Conversation) && ($user || $config->allowGuests);

        return [
            'available' => $available,
            'following' => $available && $conversation && $identity && $plugin->getSubscriptions()->isSubscribed((int)$conversation->id, (int)$identity->id, null, SubscriptionType::Conversation),
            'needsEmail' => !$user && !$identity?->verifiedAt,
        ];
    }

    private function _viewerEditor(ElementInterface $owner, ConversationConfig $config, ?User $user, ?IdentityRecord $identity, bool $canComment, bool $slim): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $rich = $settings->isRichAuthoringEnabled();
        $protection = null;

        // A slim guest asks for this once they start writing, so reading never starts a session.
        if (!$slim && $canComment) {
            try {
                $protection = $this->_protectionData(CommentsPlugin::$plugin->getProtection()->prepare((int)$owner->id, (int)$owner->siteId, owner: $owner));
            } catch (ProtectionException) {
                // Posting says what's wrong, and the rest of the response is still useful.
            }
        }

        return [
            // The renderer adds the form it's for, such as `-create` or `-reply-<uid>`. Per person, so a
            // shared computer never offers one visitor another's draft.
            'draftKey' => 'comments-' . $owner->id . '-' . $owner->siteId . '-' . ($user ? $user->id : 'guest'),
            'guest' => [
                'show' => !$user && $config->allowGuests && ($settings->guestShowEmailName || $settings->guestRequireEmailName),
                'required' => !$user && (bool)$settings->guestRequireEmailName,
                'name' => $user ? null : $identity?->name,
                'email' => $user ? null : $identity?->email,
                'offerReplyEmails' => !$user && $settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_REPLY),
            ],
            'fields' => $this->_formFieldDescriptions($owner, $user),
            'protection' => $protection,
            'richEditor' => $rich && $canComment ? [
                'context' => 'comments.reply',
                'options' => EditorContexts::browserOptions('comments.reply', (int)$owner->id, (int)$owner->siteId),
            ] : null,
            // GIPHY's public web key, only for someone who can comment (SD-52).
            'gifs' => $canComment ? CommentsPlugin::$plugin->getGiphy()->browserConfig() : null,
        ];
    }

    private function _protectionData(mixed $preparation): array
    {
        $manifest = $preparation->browserManifest();

        return [
            'version' => 1,
            'manifest' => $manifest->jsonSerialize(),
            'expiresAt' => $manifest->expiresAt,
        ];
    }

    private function _moderationSummary(ElementInterface $owner, ?ConversationRecord $conversation): array
    {
        $held = 0;
        $reported = 0;

        if ($conversation) {
            $counts = (new Query())
                ->select([
                    'held' => "SUM(CASE WHEN [[c.moderationState]] = 'pending' THEN 1 ELSE 0 END)",
                    'reported' => "SUM(CASE WHEN [[c.moderationState]] = 'approved' AND EXISTS (SELECT 1 FROM {{%comments_reports}} r WHERE [[r.commentId]] = [[c.id]] AND [[r.status]] = 'open') THEN 1 ELSE 0 END)",
                ])
                ->from(['c' => '{{%comments_comments}}'])
                ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[c.id]]')
                ->where(['c.conversationId' => (int)$conversation->id, 'c.authorRemovedAt' => null, 'e.dateDeleted' => null])
                ->one();
            $held = (int)($counts['held'] ?? 0);
            $reported = (int)($counts['reported'] ?? 0);
        }

        return [
            'heldCount' => $held,
            'reportedCount' => $reported,
            'reviewUrl' => UrlHelper::cpUrl('comments', ['ownerId' => $owner->id, 'source' => $held ? 'needs-review' : null]),
        ];
    }

    /**
     * What the asker can do with one comment, worked out from its row where possible so a page of
     * comments needs no elements loaded, and from the element for their own edit and delete rights.
     */
    /**
     * Someone blocked from the discussion can't vote or report, and a closed one takes no votes, though
     * harmful content can still be reported (SD-45).
     */
    private function _viewerCommentState(array $row, ?Comment $comment, ?User $user, bool $isModerator, bool $canComment, bool $isOwn, mixed $vote, bool $reported, bool $blocked = false, bool $closed = false): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $mode = $settings->resolveInteractionMode();
        $approved = !$row['removed'] && $row['moderationState'] === ModerationState::Approved->value;
        $maxDepth = is_numeric($settings->maxReplyDepth) ? (int)$settings->maxReplyDepth : null;

        $state = [
            'canReply' => $approved && $canComment && ($maxDepth === null || $row['depth'] < $maxDepth),
            // An author's comment marked as spam is reported like one waiting, so nothing gives it away.
            'canEdit' => $isOwn && $user && $comment && ($comment->canEdit() || (
                $row['moderationState'] === ModerationState::Spam->value && !$row['removed'] && $comment->isWithinAuthorWindow($user) && $comment->isDiscussionOpenForEdits()
            )),
            'canDelete' => $isOwn && $user && $comment && !$comment->authorRemovedAt && $comment->canDelete($user),
            'canVote' => $approved && !$blocked && !$closed && $mode->isEnabled() && ($user || $settings->allowGuestVoting),
            'canDownvote' => $approved && !$blocked && !$closed && $mode->allowsDownvote() && ($user || $settings->allowGuestVoting),
            'canReport' => $approved && !$blocked && !$isOwn && $settings->allowFlagging && ($user || $settings->allowGuestFlagging),
            'vote' => is_object($vote) ? (string)$vote->type : (is_string($vote) ? $vote : null),
            'reported' => $reported,
            'isAuthor' => $isOwn,
            'moderation' => $isModerator && $comment && $user ? $this->_moderationData($comment, $user) : null,
            'custom' => new stdClass(),
        ];

        return $state;
    }

    /**
     * The viewer state of one comment after a write, worked out the same way as `viewer` does.
     */
    private function _commentViewer(Comment $comment, ?User $user): array
    {
        return $this->_commentViewers([$comment], $user)[(int)$comment->id];
    }

    /**
     * Whether a conversation is closed now, by hand or by its owner's age.
     */
    private function _isClosed(ElementInterface $owner, ConversationConfig $config): bool
    {
        return $config->closed || CommentsPlugin::$plugin->getComments()->checkExpired($owner);
    }

    /**
     * Whether this person can post here. A guest's answer comes from the settings alone, so it doesn't
     * depend on anything a request carries.
     */
    private function _canComment(ElementInterface $owner, ConversationConfig $config, ?User $user): bool
    {
        if ($user) {
            return CommentsPlugin::$plugin->getConversations()->canComment($owner);
        }

        return $config->isCommentingEnabled() && $config->allowGuests && !CommentsPlugin::$plugin->getComments()->checkExpired($owner);
    }

    private function _moderationData(Comment $comment, User $user): array
    {
        $frontend = CommentsPlugin::$plugin->getFrontend();
        $decisions = $frontend->moderationDecisions($comment, $user);
        $actions = array_values(array_filter(array_map(static fn(string $decision): ?string => array_search($decision, self::MODERATION_ACTIONS, true) ?: null, $decisions)));

        return [
            'reviewLabel' => $frontend->reviewLabel($comment),
            'openReportCount' => $comment->getFlags(),
            'actions' => $actions,
        ];
    }

    /**
     * Comments shown privately in full, flat and each with its parent's UID, for the renderer to place.
     */
    private function _extraComments(array $rowsById, array $comments, array $rows, array $shown, ElementInterface $owner, ?User $user, bool $isModerator, callable $isOwn, ?string $forceState = null): array
    {
        $loaded = array_values(array_intersect_key($comments, $rowsById));

        if ($loaded === []) {
            return [];
        }

        $views = CommentsPlugin::$plugin->getFrontend()->buildDetachedCommentViews($loaded, true, false);
        $custom = $this->_customData(array_intersect_key($comments, $rowsById), $owner, false);
        $children = $this->_shownChildren($rows, $shown);
        $ownerUrl = $owner->getUrl();
        $items = [];

        foreach ($rowsById as $id => $row) {
            if (!isset($comments[$id], $views[$id])) {
                continue;
            }

            $state = $forceState ?? match (true) {
                $isOwn($row) || !$isModerator => 'pending',
                $row['moderationState'] === ModerationState::Spam->value => 'spam',
                default => 'held',
            };
            $parent = $row['parentId'] !== null ? ($rows[$row['parentId']] ?? null) : null;
            $data = $this->_commentData($views[$id], $comments[$id], $ownerUrl, $parent['uid'] ?? null, $state);
            $data['custom'] = $custom[$row['uid']] ?? new stdClass();
            $count = count($children[$id] ?? []);
            $data['replies'] = ['count' => $count, 'items' => [], 'next' => $count ? $this->_repliesCursorFor($id, null) : null];

            if ($isModerator && $user) {
                $data['moderation'] = $this->_moderationData($comments[$id], $user);
            }

            $items[] = $data;
        }

        return $items;
    }

    /**
     * A comment written or edited by its author, with the state they see and their own permissions.
     */
    private function _privateComment(Comment $comment, ElementInterface $owner, ?User $user, string $state): array
    {
        return $this->_privateComments([$comment], $user, [(int)$comment->id => $state])[(int)$comment->id];
    }

    /**
     * A comment after a moderator's decision, in the state they now see it in.
     */
    private function _moderatedComment(Comment $comment, ElementInterface $owner, User $user): array
    {
        return $this->commentDataFor([$comment])[(int)$comment->id];
    }

    /**
     * Comments for the person asking, each in the state given for it in `$states`, keyed by comment ID:
     * the public shape with their own `viewer` entry and, in a moderator's states (`held`, `spam`,
     * `reported` and `removed`), `moderation`. A `placeholder` says nothing about the comment, and
     * `removed` gives a moderator only its UID and what they can do.
     */
    private function _privateComments(array $comments, ?User $user, array $states, bool $withModeration = true): array
    {
        $plugin = CommentsPlugin::$plugin;
        $byId = [];
        $groups = [];

        foreach ($comments as $comment) {
            $owner = $comment->getOwner();

            if ($owner && isset($states[(int)$comment->id])) {
                $byId[(int)$comment->id] = $comment;
                $groups[$owner->id . ':' . $owner->siteId][(int)$comment->id] = $comment;
            }
        }

        if ($byId === []) {
            return [];
        }

        $viewers = $this->_commentViewers(array_values($byId), $user, $withModeration);
        $parentIds = array_values(array_unique(array_filter(array_map(static fn(Comment $comment): int => (int)$comment->parentId, $byId))));
        $parentUids = $parentIds ? (new Query())->select(['uid', 'id'])->from(Table::ELEMENTS)->where(['id' => $parentIds])->indexBy('id')->column() : [];
        $moderator = $withModeration && $user && $plugin->getModeration()->canModerateComment($user);
        $data = [];

        foreach ($groups as $group) {
            $owner = reset($group)->getOwner();

            $data += $this->_inSite($this->_siteFor($owner), function() use ($group, $owner, $states, $viewers, $parentUids, $moderator, $user): array {
                $views = CommentsPlugin::$plugin->getFrontend()->buildDetachedCommentViews(array_values($group), true, false);
                $custom = $this->_customData($group, $owner, false);
                $items = [];

                foreach ($group as $id => $comment) {
                    $state = $states[$id];
                    $parentUid = $comment->parentId ? ($parentUids[(int)$comment->parentId] ?? null) : null;
                    $moderation = $moderator && $user && in_array($state, ['held', 'spam', 'reported', 'removed', 'approved'], true) && CommentsPlugin::$plugin->getModeration()->canModerateComment($user, $comment)
                        ? $this->_moderationData($comment, $user)
                        : null;

                    if ($state === 'removed') {
                        $items[$id] = ['uid' => (string)$comment->uid, 'state' => 'removed', 'moderation' => $moderation];

                        continue;
                    }

                    $item = $state === 'placeholder' || !isset($views[$id])
                        ? $this->_placeholderData(['uid' => (string)$comment->uid, 'parentUid' => $parentUid, 'depth' => (int)$comment->depth])
                        : $this->_commentData($views[$id], $comment, null, $parentUid, $state);
                    $item['custom'] = $state === 'placeholder' ? new stdClass() : ($custom[(string)$comment->uid] ?? new stdClass());
                    $item['replies'] = ['count' => 0, 'items' => [], 'next' => null];
                    $item['viewer'] = $viewers[$id];

                    if ($moderation !== null) {
                        $item['moderation'] = $moderation;
                    }

                    $items[$id] = $this->_finish(['comment' => $item], $owner)['comment'];
                }

                return $items;
            });
        }

        return $data;
    }

    /**
     * The asker's `viewer` entries for these comments, keyed by comment ID, worked out as `viewer` does,
     * with their votes, reports and restrictions looked up once for all of them.
     */
    private function _commentViewers(array $comments, ?User $user, bool $withModeration = true): array
    {
        $plugin = CommentsPlugin::$plugin;
        $identity = $user ? $plugin->getIdentities()->findForUser((int)$user->id) : $plugin->getIdentities()->findGuestForRequest();
        $ids = array_map(static fn(Comment $comment): int => (int)$comment->id, $comments);
        $votes = $identity ? $plugin->getInteractions()->getViewerState($ids, (int)$identity->id) : [];
        $reported = $identity ? $this->_openReportIds($ids, (int)$identity->id) : [];
        $isModerator = $withModeration && $user && $plugin->getModeration()->canModerateComment($user);
        $threads = [];
        $viewers = [];

        foreach ($comments as $comment) {
            $owner = $comment->getOwner();
            $conversationId = $comment->conversationId ? (int)$comment->conversationId : 0;
            $key = ($owner?->id ?? 0) . ':' . $conversationId;

            if (!isset($threads[$key])) {
                $blocked = $identity && $plugin->getModeration()->isBlocked((int)$identity->id, $conversationId ?: null);
                $threads[$key] = [
                    'blocked' => $blocked,
                    'closed' => !$owner || $this->_isClosed($owner, $this->_config($owner)),
                    'canComment' => $owner && !$blocked && $this->_canComment($owner, $this->_config($owner), $user),
                ];
            }

            $row = [
                'id' => (int)$comment->id,
                'uid' => (string)$comment->uid,
                'depth' => (int)$comment->depth,
                'moderationState' => (string)$comment->moderationState,
                'removed' => (bool)$comment->authorRemovedAt || (bool)$comment->trashed,
                'userId' => $comment->userId ? (int)$comment->userId : null,
                'identityId' => $comment->identityId ? (int)$comment->identityId : null,
            ];

            $viewers[(int)$comment->id] = $this->_viewerCommentState(
                $row,
                $comment,
                $user,
                (bool)$isModerator,
                $threads[$key]['canComment'],
                $this->_isOwnComment($comment, $user, $identity),
                $votes[(int)$comment->id] ?? null,
                isset($reported[(int)$comment->id]),
                $threads[$key]['blocked'],
                $threads[$key]['closed'],
            );
        }

        return $viewers;
    }

    /**
     * An author can edit their own comment within the edit window. One marked as spam looks to its
     * author like one waiting for approval, so it can be opened for editing like one, and saving it
     * fails with a message that doesn't say why.
     */
    private function _assertCanEdit(Comment $comment, User $user, bool $reading = false): void
    {
        if ((int)$comment->userId !== (int)$user->id || $comment->authorRemovedAt || $comment->trashed) {
            throw new ApiException(Craft::t('comments', 'You do not have permission to edit this comment.'), 'forbidden', 403);
        }

        if (!$comment->isWithinAuthorWindow($user)) {
            throw new ApiException(Craft::t('comments', 'This comment can no longer be changed.'), 'edit_window_passed', 403);
        }

        $spam = CommentsPlugin::$plugin->getModeration()->currentState($comment) === ModerationState::Spam;

        // The same answer as any comment in a closed discussion, so a spam one isn't given away.
        if ($spam && !$comment->isDiscussionOpenForEdits()) {
            throw new ApiException(Craft::t('comments', 'You do not have permission to edit this comment.'), 'forbidden', 403);
        }

        if ($spam && !$reading) {
            throw new ApiException(Craft::t('comments', 'This comment can’t be changed right now.'), 'forbidden', 403);
        }

        if (!$spam && !$comment->canEdit()) {
            throw new ApiException(Craft::t('comments', 'You do not have permission to edit this comment.'), 'forbidden', 403);
        }
    }

    /**
     * A write's `content`, as `{ format: "plain", text }` or `{ format: "rich", document }`.
     */
    private function _content(mixed $content): array
    {
        $invalid = fn(string $message): ApiException => new ApiException($message, 'validation_failed', 422, ['content' => [$message]]);

        if (!is_array($content)) {
            throw $invalid(Craft::t('comments', 'Comment content cannot be empty.'));
        }

        if (($content['format'] ?? 'plain') === 'rich') {
            $document = $content['document'] ?? null;

            if (!is_array($document) && !is_string($document)) {
                throw $invalid(Craft::t('comments', 'Invalid rich comment content.'));
            }

            return [ContentFormat::Rich, is_string($document) ? $document : Json::encode($document)];
        }

        if (($content['format'] ?? 'plain') !== 'plain' || !is_string($content['text'] ?? null)) {
            throw $invalid(Craft::t('comments', 'Comment content cannot be empty.'));
        }

        return [ContentFormat::Plain, $content['text']];
    }

    private function _evidence(mixed $protection): mixed
    {
        if (!is_array($protection)) {
            return [];
        }

        unset($protection['channel']);

        return $protection;
    }

    /**
     * A true or false sent as JSON or a form value. Anything else, such as the string `"false"` that
     * PHP would read as true, or `"maybe"`, is refused rather than guessed at.
     */
    private function _bool(mixed $value, string $name): bool
    {
        $bool = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (!is_bool($bool) || is_array($value)) {
            throw new ApiException(Craft::t('comments', 'Send true or false.'), 'validation_failed', 422, [$name => [Craft::t('comments', 'Send true or false.')]]);
        }

        return $bool;
    }

    private function _isOwnComment(Comment $comment, ?User $user, ?IdentityRecord $identity): bool
    {
        return $user
            ? (int)$comment->userId === (int)$user->id
            : ($identity && !$comment->userId && (int)$comment->identityId === (int)$identity->id);
    }

    private function _string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * The custom fields this person may fill in on a new comment and on a reply, in the field layout's
     * order, after conditions and the replies rule. It depends on who's asking, so it's only in `viewer`.
     */
    private function _formFieldDescriptions(ElementInterface $owner, ?User $user): array
    {
        $formFields = CommentsPlugin::$plugin->getFormFields();
        $prospect = $formFields->prospect((int)$owner->id, (int)$owner->siteId, null, $user);

        return [
            'comment' => $formFields->describe($formFields->collectable($prospect, $user), $prospect),
            'reply' => $formFields->describe($formFields->collectable($prospect, $user, true), $prospect),
        ];
    }

    /**
     * An upload for an Assets custom field, for a new comment (`ownerId` or `ownerUri`, `siteId`, and
     * `parentUid` for a reply) or the asker's own comment (`commentUid`). The field has to be one this
     * person can fill in there. A guest can upload to a field they're offered, into their own folder.
     */
    private function _fieldUpload(?UploadedFile $file, array $params, ?User $user): array
    {
        $plugin = CommentsPlugin::$plugin;
        $formFields = $plugin->getFormFields();
        $identities = $plugin->getIdentities();
        $handle = $params['field'];

        if (!is_string($handle)) {
            throw new ApiException(Craft::t('comments', 'This field can’t be filled in here.'), 'invalid_request', 400);
        }

        if (is_string($params['commentUid'] ?? null) && $params['commentUid'] !== '') {
            if (!$user) {
                throw new ApiException(Craft::t('comments', 'Sign in to edit your comment.'), 'sign_in_required', 401);
            }

            $comment = $this->_commentByUid($params['commentUid']);
            $this->_assertCanEdit($comment, $user);
            $elements = $formFields->editable($comment, $user);
        } else {
            $owner = $this->resolveOwner($params['ownerId'] ?? null, $params['ownerUri'] ?? null, $params['siteId'] ?? null);
            $config = $this->_config($owner);
            $identity = $user ? $identities->findForUser((int)$user->id) : $identities->findGuestForRequest();
            $conversation = $plugin->getConversations()->findForOwner((int)$owner->id, (int)$owner->siteId);

            if ($identity && $plugin->getModeration()->isBlocked((int)$identity->id, $conversation ? (int)$conversation->id : null)) {
                throw new ApiException(Craft::t('comments', 'You can’t take part in this discussion.'), 'blocked', 403);
            }

            if (!$this->_canComment($owner, $config, $user)) {
                throw $user || $this->_isClosed($owner, $config)
                    ? new ApiException(Craft::t('comments', 'Comments are closed for this element.'), $this->_isClosed($owner, $config) ? 'closed' : 'forbidden', 403)
                    : new ApiException(Craft::t('comments', 'Must be logged in to comment.'), 'sign_in_required', 401);
            }

            $parent = null;

            if (is_string($params['parentUid'] ?? null) && $params['parentUid'] !== '') {
                $parent = $this->_commentByUid($params['parentUid']);

                if ((int)$parent->ownerId !== (int)$owner->id || $parent->authorRemovedAt || $plugin->getModeration()->currentState($parent) !== ModerationState::Approved) {
                    throw $this->_notFound();
                }
            }

            $elements = $formFields->collectable($formFields->prospect((int)$owner->id, (int)$owner->siteId, $parent, $user), $user);

            // A guest's upload is checked like their comment will be, with its own preparation, since
            // anything else would let anyone store files without the form's protection.
            if (!$user && in_array($handle, array_map(static fn($element): string => $element->getField()->handle, $elements), true)) {
                $protection = $params['protection'] ?? null;
                $protection = is_string($protection) ? Json::decodeIfJson($protection) : $protection;
                $plugin->getProtection()->checkUpload(
                    (int)$owner->id,
                    (int)$owner->siteId,
                    $plugin->getProtection()->evidence($this->_evidence($protection)),
                    is_array($protection) && ($protection['channel'] ?? null) === 'headless',
                );
            }
        }

        return [
            'apiVersion' => Frontend::API_VERSION,
            'status' => 'ok',
            // A guest's first upload is where their identity, and their cookie or session token, begin,
            // once it has passed every check.
            ...$formFields->upload($file, $handle, $elements, $user, static fn() => $user ? $identities->getOrCreateForUser($user) : $identities->resolveForUserOrGuest(null)),
        ];
    }

    private function _openReportIds(array $commentIds, int $identityId): array
    {
        return array_fill_keys(array_map('intval', (new Query())
            ->select(['commentId'])
            ->from('{{%comments_reports}}')
            ->where(['commentId' => $commentIds, 'identityId' => $identityId, 'status' => 'open'])
            ->column()), true);
    }

    /**
     * Thread data as schema.org `Comment` items. A placeholder isn't a comment, so its replies move up to
     * stand in its place.
     */
    private function _structuredComments(array $comments, bool $includeAuthors): array
    {
        $items = [];

        foreach ($comments as $comment) {
            $replies = $this->_structuredComments($comment['replies']['items'] ?? [], $includeAuthors);

            if (($comment['state'] ?? null) !== 'approved') {
                array_push($items, ...$replies);

                continue;
            }

            $item = [
                '@type' => 'Comment',
                'datePublished' => $comment['createdAt'],
                'text' => $comment['content']['text'],
                'url' => $comment['permalink'],
            ];

            if ($includeAuthors) {
                $item['author'] = ['@type' => 'Person', 'name' => $comment['author']['name']];
            }

            if (is_array($comment['interaction'] ?? null)) {
                $item['upvoteCount'] = (int)$comment['interaction']['upvotes'];
            }

            if ($replies) {
                $item['comment'] = $replies;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Field errors keyed the way the data API names them: `content`, `guest.name`, `guest.email` and
     * `fields.<handle>` for custom fields.
     */
    private function _fieldErrors(array $errors): array
    {
        $mapped = [];

        foreach ($errors as $key => $messages) {
            $name = preg_match('/^fields\[(.+)\]$/', (string)$key, $matches) ? $matches[1] : (string)$key;
            $name = match ($name) {
                'comment', 'bodyContent', 'content' => 'content',
                'name' => 'guest.name',
                'email' => 'guest.email',
                default => Craft::$app->getFields()->getFieldByHandle($name) ? 'fields.' . $name : $name,
            };
            $mapped[$name] = [...($mapped[$name] ?? []), ...array_values((array)$messages)];
        }

        return $mapped;
    }

    private function _errorCategory(string $code): string
    {
        return match ($code) {
            'validation_failed', 'file_type_not_allowed', 'file_too_large', 'too_many_files' => 'validation',
            'protection_required', 'protection_expired', 'challenge_failed', 'possible_spam', 'protection_unavailable' => 'protection',
            'rate_limited' => 'rate',
            'sign_in_required', 'token_expired', 'token_revoked', 'session_expired', 'origin_not_allowed', 'csrf_failed', 'signature_invalid', 'user_not_eligible' => 'auth',
            'forbidden', 'blocked', 'closed', 'edit_window_passed' => 'policy',
            'not_found', 'conflict' => 'state',
            'server_error' => 'server',
            default => 'request',
        };
    }
}
