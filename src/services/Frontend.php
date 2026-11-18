<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\LoadMode;
use verbb\comments\enums\ModerationState;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\models\FrontendCommentView;
use verbb\comments\models\FrontendConversationView;
use verbb\comments\records\Identity as IdentityRecord;
use verbb\comments\services\Compatibility;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use Twig\Markup;
use verbb\editor\Editor;
use verbb\editor\RichText;
use verbb\editor\web\assets\editor\EditorAsset;

/**
 * The site's side of the thread: the mount point `craft.comments.render()` outputs, and the prepared
 * views of comments, authors and viewer actions that the data API and GraphQL serialise to JSON.
 */
class Frontend extends Component
{
    // Static Methods
    // =========================================================================

    public static function reportReasons(): array
    {
        $reasons = [];

        foreach (CommentsPlugin::$plugin->getSettings()->reportReasons as $reason) {
            $value = trim((string)($reason['value'] ?? ''));
            $label = trim((string)($reason['label'] ?? ''));

            $description = trim((string)($reason['description'] ?? ''));

            if ($value !== '' && $label !== '') {
                $reasons[] = [
                    'value' => $value,
                    'label' => Craft::t('comments', $label),
                    'description' => $description !== '' ? Craft::t('comments', $description) : null,
                ];
            }
        }

        return $reasons;
    }

    /**
     * Which of the mount point's reserved heights suits a thread of this many comments, so the page below
     * doesn't jump when it's drawn. Each comment takes about 6rem, up to 40rem, and a few bucketed classes
     * (`comments--reserve-0` to `-4`) cover that without an inline style, which a strict Content Security
     * Policy would refuse.
     */
    public static function reserveBucket(int $commentCount): int
    {
        return match (true) {
            $commentCount <= 0 => 0,
            $commentCount === 1 => 1,
            $commentCount <= 3 => 2,
            $commentCount <= 6 => 3,
            default => 4,
        };
    }

    public static function avatarTone(string $name): int
    {
        return $name === '' ? 0 : (int)hexdec(substr(md5(mb_strtolower(trim($name))), 0, 6)) % 8;
    }

    /**
     * `themeConfig` from each source in order, later ones winning per control and per attribute, as the
     * renderer merges it: only the ten controls, and only `class`, `title`, `aria-label` and the site's
     * own `data-*` attributes, each text, a number, true or false, or a list of classes. Anything else is
     * left out, with a warning in Craft's log.
     */
    public static function mergeThemeConfig(array $sources): array
    {
        $merged = [];
        $warn = static fn(string $message) => Craft::warning('Comments: ' . $message, __METHOD__);

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($source as $name => $config) {
                if (!in_array($name, self::THEME_CONTROLS, true)) {
                    $warn('themeConfig has no control called "' . $name . '". It covers ' . implode(', ', self::THEME_CONTROLS) . '.');

                    continue;
                }

                if (!is_array($config) || ($config !== [] && array_is_list($config))) {
                    $warn("themeConfig's $name must be an object, such as { attributes: { class: 'btn' } }.");

                    continue;
                }

                $given = $config['attributes'] ?? [];

                if (!is_array($given) || ($given !== [] && array_is_list($given))) {
                    $warn("themeConfig's $name.attributes must be an object.");

                    continue;
                }

                $attributes = $merged[$name]['attributes'] ?? [];

                foreach ($given as $attribute => $value) {
                    if (!is_string($attribute) || !preg_match(self::THEME_ATTRIBUTE_PATTERN, $attribute)) {
                        $warn("themeConfig can't set \"$attribute\" on $name. It sets class, title, aria-label and data-* attributes.");

                        continue;
                    }

                    $plain = $value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value);
                    $classes = is_array($value) && array_is_list($value) && array_filter($value, static fn(mixed $item): bool => !is_string($item)) === [];

                    if (!$plain && !$classes) {
                        $warn("themeConfig's $name \"$attribute\" must be text, a number, true or false, or a list of classes.");

                        continue;
                    }

                    $attributes[$attribute] = $value;
                }

                $resetClass = array_key_exists('resetClass', $config) && $config['resetClass'] !== null
                    ? (bool)$config['resetClass']
                    : ($merged[$name]['resetClass'] ?? null);

                // Left out when empty, which JSON would otherwise turn into a list.
                $merged[$name] = array_filter(['resetClass' => $resetClass, 'attributes' => $attributes], static fn(mixed $value): bool => $value !== null && $value !== []);
            }
        }

        return array_filter($merged, static fn(array $control): bool => $control !== []);
    }


    /**
     * `render()`'s `fields` option, custom field overrides by handle as the renderer takes them:
     * `{ handle: { input: 'my-field-input', display: 'my-field-value' } }`. Only custom element names
     * reach the mount point, since a function can't be written into the page. Anything else is left out,
     * with a warning in Craft's log.
     */
    public static function mountPointFields(mixed $fields): array
    {
        $kept = [];
        $warn = static fn(string $message) => Craft::warning('Comments: ' . $message, __METHOD__);

        if ($fields === null || $fields === []) {
            return [];
        }

        if (!is_array($fields) || array_is_list($fields)) {
            $warn("render()'s fields must be an object of field handles, such as { location: { input: 'my-location-input' } }.");

            return [];
        }

        foreach ($fields as $handle => $override) {
            if (!is_string($handle) || !preg_match(self::FIELD_HANDLE_PATTERN, $handle)) {
                $warn("render()'s fields has \"$handle\", which isn't a field handle.");

                continue;
            }

            if (!is_array($override) || ($override !== [] && array_is_list($override))) {
                $warn("render()'s fields.$handle must be an object, such as { input: 'my-location-input' }.");

                continue;
            }

            $names = [];

            foreach ($override as $kind => $name) {
                if (!in_array($kind, ['input', 'display'], true)) {
                    $warn("render()'s fields.$handle has \"$kind\". It takes input and display.");

                    continue;
                }

                if (!self::isCustomElementName($name)) {
                    $warn("render()'s fields.$handle.$kind must be a custom element's name, such as my-field-input.");

                    continue;
                }

                $names[$kind] = $name;
            }

            if ($names !== []) {
                $kept[$handle] = $names;
            }
        }

        return $kept;
    }

    /**
     * Whether `$name` can be a custom element's name: lowercase, starting with a letter, with a hyphen,
     * and not one of the names HTML keeps for itself. It's the renderer's own check, so a name kept here
     * is one it draws.
     */
    public static function isCustomElementName(mixed $name): bool
    {
        return is_string($name)
            && strlen($name) <= 100
            && preg_match(self::CUSTOM_ELEMENT_PATTERN, $name) === 1
            && !in_array($name, self::RESERVED_ELEMENT_NAMES, true);
    }


    // Constants
    // =========================================================================

    /**
     * The version of the JSON format the comments script reads. It only changes when that format changes
     * in a way an older script can't read, not with each plugin release.
     */
    public const API_VERSION = 1;
    public const MAX_VISUAL_DEPTH = 4;
    public const DEFAULT_ROOT_LIMIT = 20;
    public const MAX_ROOT_LIMIT = 50;

    /** The controls `themeConfig` reaches, as the renderer names them. */
    public const THEME_CONTROLS = [
        'primaryButton',
        'secondaryButton',
        'quietButton',
        'input',
        'textarea',
        'select',
        'checkbox',
        'radio',
        'link',
        'avatar',
    ];

    // The attributes `themeConfig` may set: classes, a title, an accessible name and a site's own `data-*`,
    // never `data-comments-*`, which the script's behaviour depends on.
    private const THEME_ATTRIBUTE_PATTERN = '/^(class|title|aria-label|data-(?!comments-)[a-z0-9]+(-[a-z0-9]+)*)$/';

    // A custom element's name as the renderer accepts one, and the hyphenated names HTML keeps for itself.
    private const CUSTOM_ELEMENT_PATTERN = '/^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/';
    private const RESERVED_ELEMENT_NAMES = ['annotation-xml', 'color-profile', 'font-face', 'font-face-src', 'font-face-uri', 'font-face-format', 'font-face-name', 'missing-glyph'];

    // A Craft field handle.
    private const FIELD_HANDLE_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_]*$/';


    // Properties
    // =========================================================================

    /**
     * Avatar URLs for this request, by user and photo. A transformed URL costs a transform-index lookup,
     * and a queued job while the transform isn't generated, so each author's is built once, not per comment.
     */
    private array $_avatarUrls = [];


    // Public Methods
    // =========================================================================

    /**
     * The mount point for an owner element's thread, which is all the HTML Comments sends for it: the
     * reserved space, the comment count as a heading and a line for visitors without JavaScript. The
     * browser fetches the thread as JSON from the data API and draws it. `$options` takes `siteId`,
     * `sort`, `limit`, `loadMode`, `themeConfig` and `fields`, and anything else is ignored.
     *
     * Nothing is output when comments are off for the element or the element isn't live, as the data
     * API wouldn't answer for it either. Who's viewing never changes the output, because a cached page
     * serves it to everyone; the private `viewer` read decides whether they get a form.
     */
    public function render(int $ownerId, array $options = []): Markup
    {
        $siteId = isset($options['siteId']) ? (int)$options['siteId'] : null;
        $sort = $this->_resolveSort(is_string($options['sort'] ?? null) ? $options['sort'] : null);
        $limit = isset($options['limit']) ? max(1, min(self::MAX_ROOT_LIMIT, (int)$options['limit'])) : self::DEFAULT_ROOT_LIMIT;
        $conversation = $this->buildMountPointView($ownerId, $siteId, $sort, $limit);

        if (!$conversation) {
            return Template::raw('');
        }

        return $this->renderMountPoint($conversation, $this->resolveLoadMode($options['loadMode'] ?? null), $options);
    }

    /**
     * The load mode a `render()` call asked for, or the **Load Comments** setting. An unknown value is
     * ignored rather than failing the page.
     */
    public function resolveLoadMode(mixed $loadMode = null): LoadMode
    {
        return (is_string($loadMode) ? LoadMode::tryFrom($loadMode) : null)
            ?? CommentsPlugin::$plugin->getSettings()->resolveLoadMode();
    }

    /**
     * The attributes of the mount point `render()` outputs. They're all data, so the page can be cached
     * by anyone, and `config` carries the `render()` options the renderer applies, such as the sort, the
     * controls' `themeConfig` and custom elements for custom fields (`fields`).
     */
    public function mountPointAttributes(FrontendConversationView $conversation, LoadMode $loadMode, array $config = []): array
    {
        // The site-wide setting first, then `render()`'s, each control merged as the renderer merges them.
        $themeConfig = self::mergeThemeConfig([
            CommentsPlugin::$plugin->getSettings()->themeConfig,
            isset($config['themeConfig']) && is_array($config['themeConfig']) ? $config['themeConfig'] : null,
        ]);

        return [
            'id' => $conversation->instanceId,
            'class' => ['comments', 'comments--reserve-' . self::reserveBucket($conversation->commentCount)],
            'aria-labelledby' => $conversation->instanceId . '-heading',
            'data-comments-root' => true,
            'data-comments-api-version' => (string)self::API_VERSION,
            'data-comments-endpoint' => UrlHelper::actionUrl('comments/api'),
            'data-comments-owner-id' => (string)$conversation->ownerId,
            'data-comments-site-id' => (string)$conversation->siteId,
            'data-comments-load-mode' => $loadMode->value,
            'data-comments-theme' => $conversation->theme,
            // Comments 3's event names alongside today's, while `compatibilityMode` is on.
            'data-comments-compatibility' => Compatibility::enabled() ?: null,
            'data-comments-config' => Json::encode(array_filter([
                'sort' => $conversation->sort,
                'limit' => $conversation->rootLimit,
                'themeConfig' => $themeConfig ?: null,
                'fields' => self::mountPointFields($config['fields'] ?? null) ?: null,
            ], static fn(mixed $value): bool => $value !== null)),
            // What the renderer shows before the thread's data arrives: the count in the header, and
            // the words for the loading line and the Show comments button, in the site's language.
            'data-comments-count' => (string)$conversation->commentCount,
            'data-comments-labels' => Json::encode([
                'comments' => Craft::t('comments', 'Comments'),
                'loading' => Craft::t('comments', 'Loading comments…'),
                'showComments' => Craft::t('comments', 'Show comments'),
                'showCommentsCount' => Craft::t('comments', 'Show comments ({count})'),
            ]),
        ];
    }

    /**
     * The mount point's markup: the reserved space, the comment count as a heading and a line for
     * visitors without JavaScript.
     */
    public function renderMountPoint(FrontendConversationView $conversation, LoadMode $loadMode, array $config = []): Markup
    {
        $heading = Html::tag('h2', Html::encode(Craft::t('comments', '{count, plural, =1{# comment} other{# comments}}', ['count' => $conversation->commentCount])), [
            'id' => $conversation->instanceId . '-heading',
            'class' => 'comments__heading',
        ]);
        $noscript = Html::tag('noscript', Html::tag('p', Html::encode(Craft::t('comments', 'Turn on JavaScript to read and post comments.')), [
            'class' => 'comments__noscript',
        ]));

        return Template::raw(Html::tag('section', $heading . $noscript, $this->mountPointAttributes($conversation, $loadMode, $config)));
    }

    /**
     * What the mount point needs to know about an owner's thread, or null when it shouldn't have one. The
     * page holds nothing about who's viewing it, so the same answer suits everyone and can be cached.
     */
    public function buildMountPointView(int $ownerId, ?int $siteId = null, ?CommentSort $sort = null, int $rootLimit = self::DEFAULT_ROOT_LIMIT): ?FrontendConversationView
    {
        $siteId ??= (int)Craft::$app->getSites()->getCurrentSite()->id;

        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        // The owners the data API answers for: a live element that isn't a comment, with comments turned
        // on for this site. Anything else would only draw a thread that can't load. Who's viewing never
        // matters here, because a cached page serves this to everyone.
        if (!$owner || $owner instanceof Comment) {
            return null;
        }

        $settings = CommentsPlugin::$plugin->getSettings();
        $conversations = CommentsPlugin::$plugin->getConversations();
        $conversation = $conversations->findForOwner($ownerId, $siteId);
        $config = $conversations->getConfigForOwner($ownerId, $siteId, $owner);

        if (!$config->enabled || !$conversations->isOwnerPublic($owner)) {
            return null;
        }

        $sort ??= $config->sortOrder;

        // There's nothing to rank by when interactions are off.
        if ($sort === CommentSort::Top && !$settings->resolveInteractionMode()->isEnabled()) {
            $sort = $settings->orderBy === 'asc' ? CommentSort::Oldest : CommentSort::Newest;
        }

        return new FrontendConversationView([
            'instanceId' => 'comments-' . $ownerId . '-' . $siteId,
            'ownerId' => $ownerId,
            'siteId' => $siteId,
            'sort' => $sort->value,
            'rootLimit' => $rootLimit,
            'theme' => $settings->resolveTheme(),
            'commentCount' => (int)($conversation->commentCount ?? 0),
        ]);
    }

    /**
     * Views of these comments on their own, without replies, keyed by comment ID, for the data API to
     * arrange into a thread. They hold only what the comment shows anyone, so no actions, moderation or
     * relative time. With `$showUnpublished`, a comment waiting for moderation or marked as spam is
     * described in full rather than as a placeholder, for the people allowed to see it. Staff badges and
     * rich bodies are looked up for all of them at once.
     */
    public function buildDetachedCommentViews(array $comments, bool $showUnpublished = false, bool $isPublic = true): array
    {
        if ($comments === []) {
            return [];
        }

        $staffIds = $this->_staffUserIds($comments);
        $this->_renderRichBodies($comments);
        CommentsPlugin::$plugin->getAvatars()->prime($comments);

        if (CommentsPlugin::$plugin->getSettings()->showsAuthorScore()) {
            CommentsPlugin::$plugin->getInteractions()->primeAuthorScores($comments);
        }

        $views = [];

        foreach ($comments as $comment) {
            $views[(int)$comment->id] = $this->_assembleCommentView($comment, $showUnpublished, $staffIds, $comments, $isPublic);
        }

        return $views;
    }

    /**
     * The decisions a moderator can make on a comment from the site, keyed off its state: the control
     * panel's, plus removing or marking spam an approved comment nobody has reported.
     */
    public function moderationDecisions(Comment $comment, ?User $viewer): array
    {
        $moderation = CommentsPlugin::$plugin->getModeration();
        $decisions = $moderation->availableDecisions($comment, $viewer);

        if ($moderation->currentState($comment) === ModerationState::Approved && $moderation->canModerateComment($viewer, $comment)) {
            $decisions = array_values(array_unique([
                ...$decisions,
                ...($moderation->canRemoveComment($viewer) ? [Moderation::DECISION_REMOVE] : []),
                Moderation::DECISION_SPAM,
            ]));
        }

        return $decisions;
    }

    /**
     * What a moderator reads beside a comment visitors can't see, or one that's been reported.
     */
    public function reviewLabel(Comment $comment): ?string
    {
        return $this->_reviewLabel($comment, $this->_publicState($comment));
    }

    /**
     * The badges a signed-in person's comments will carry on this owner, for "Posting as", in the same
     * shape as a comment author's. `Comments::EVENT_DEFINE_BADGES` handlers get a new, unsaved comment by
     * that person on the owner, with `isPublic` false, since only that person sees these.
     */
    public function viewerBadgesFor(User $user, ?ElementInterface $owner): array
    {
        $draft = new Comment([
            'userId' => (int)$user->id,
            'ownerId' => $owner?->id ? (int)$owner->id : null,
            'ownerSiteId' => $owner?->siteId ? (int)$owner->siteId : null,
            'siteId' => $owner?->siteId ? (int)$owner->siteId : null,
        ]);
        $draft->setOwner($owner, true);

        return $this->_badgesFor($draft, $this->_staffIdsForUsers([(int)$user->id]), [], false);
    }

    /**
     * A person's avatar URL at the thread's size, or null without a photo or with avatars off.
     */
    public function userAvatarUrl(?User $user): ?string
    {
        return $this->_viewerAvatarUrl($user);
    }

    /**
     * What Editor's browser module and stylesheet are published as, for loading it on intent.
     */
    public function editorAsset(): array
    {
        return $this->_editorAsset();
    }

    /**
     * Each comment's author as the thread shows them to anyone, keyed by comment ID: their public name,
     * initials, avatar and badges. GraphQL uses this so a public schema can show who wrote a comment
     * without being able to read users, which would expose the user directory. Staff badges are looked
     * up for all the comments at once.
     */
    public function publicAuthorsFor(array $comments): array
    {
        $staffIds = $this->_staffUserIds($comments);
        CommentsPlugin::$plugin->getAvatars()->prime($comments);
        $authors = [];

        foreach ($comments as $comment) {
            $name = $this->_publicAuthorName($comment) ?: Craft::t('comments', 'Guest');

            $authors[(int)$comment->id] = [
                'name' => $name,
                'initials' => Commenters::initials($name),
                'avatarUrl' => $this->_avatarUrl($comment),
                'isGuest' => !$comment->userId,
                'badges' => $this->_badgesFor($comment, $staffIds, $comments),
            ];
        }

        return $authors;
    }

    /**
     * What the current viewer can do with each comment, and how they voted on it, keyed by comment ID.
     * The thread's own buttons come from the same rules, so a headless client using GraphQL offers
     * the same actions. Votes are looked up for all the comments at once.
     */
    public function viewerStatesFor(array $comments): array
    {
        $viewer = CommentsPlugin::$plugin->getService()->getUser();
        $votes = [];

        if ($comments !== [] && CommentsPlugin::$plugin->getSettings()->resolveInteractionMode()->isEnabled()) {
            $votes = CommentsPlugin::$plugin->getInteractions()->getPresentationState($comments, $this->_currentIdentityId());
        }

        $states = [];

        foreach ($comments as $comment) {
            $actions = $this->_viewerActions($comment, $this->_publicState($comment));

            $states[(int)$comment->id] = [
                'canReply' => in_array('reply', $actions, true),
                // Authors can still edit a comment that's waiting, which the thread doesn't show them.
                'canEdit' => $comment->canEdit(),
                // The same check as the `deleteComment` mutation, which only takes an author's own comment.
                'canDelete' => $viewer instanceof User
                    && (int)$comment->userId === (int)$viewer->id
                    && !$comment->authorRemovedAt
                    && $comment->canDelete($viewer),
                'canVote' => in_array('interact', $actions, true),
                'canDownvote' => in_array('interact-down', $actions, true),
                'canReport' => in_array('report', $actions, true),
                'vote' => $votes[(int)$comment->id]['viewer'] ?? null,
            ];
        }

        return $states;
    }


    // Private Methods
    // =========================================================================

    /**
     * What a browser needs to load Editor for itself. Every form starts as the plugin's own plain
     * editor, and a rich one loads Editor when someone starts writing in it, opens a reply or edits
     * a comment. Plain authoring never loads it, except to edit a comment that was written rich.
     *
     * It's given even with Load Bundled JavaScript off, because a site that bundles the comments script
     * itself still needs to know where Editor is. The script only loads it if the page hasn't already.
     */
    private function _editorAsset(): array
    {
        $assets = Craft::$app->getAssetManager();
        $editorPath = (new EditorAsset())->sourcePath;

        return [
            'module' => $assets->getPublishedUrl($editorPath, true, 'editor.js'),
            'css' => $assets->getPublishedUrl($editorPath, true, 'editor.css'),
        ];
    }

    /**
     * Which of these comments' authors are in the groups shown with a staff badge.
     */
    private function _staffUserIds(array $comments): array
    {
        return $this->_staffIdsForUsers(array_map(static fn(Comment $comment) => (int)$comment->userId, $comments));
    }

    private function _staffIdsForUsers(array $userIds): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (!$settings->staffBadgeLabel || !$settings->staffBadgeGroups || !$userIds) {
            return [];
        }

        return array_fill_keys(array_map('intval', (new Query())
            ->select(['gu.userId'])
            ->distinct()
            ->from(['gu' => Table::USERGROUPS_USERS])
            ->innerJoin(['g' => Table::USERGROUPS], '[[g.id]] = [[gu.groupId]]')
            ->where(['g.uid' => $settings->staffBadgeGroups, 'gu.userId' => $userIds])
            ->column()), true);
    }

    /**
     * The owner and staff badges, then any a site adds through `Comments::EVENT_DEFINE_BADGES`, which
     * can badge guests too.
     */
    private function _badgesFor(Comment $comment, array $staffIds, array $comments = [], bool $isPublic = true): array
    {
        $userId = (int)$comment->userId;
        $settings = CommentsPlugin::$plugin->getSettings();
        $badges = [];

        if ($userId && $settings->ownerBadgeLabel && in_array($userId, $this->_ownerAuthorIds($comment), true)) {
            $badges[] = ['type' => 'owner', 'label' => Craft::t('comments', $settings->ownerBadgeLabel)];
        }

        if ($userId && isset($staffIds[$userId])) {
            $badges[] = ['type' => 'staff', 'label' => Craft::t('comments', (string)$settings->staffBadgeLabel)];
        }

        return CommentsPlugin::$plugin->getComments()->defineBadges($comment, $badges, $comments, $isPublic);
    }

    private function _ownerAuthorIds(Comment $comment): array
    {
        $owner = $comment->getOwner();

        return array_map('intval', match (true) {
            $owner instanceof Entry => $owner->getAuthorIds(),
            $owner instanceof User => [$owner->id],
            $owner instanceof Asset => array_filter([$owner->uploaderId]),
            $owner && method_exists($owner, 'getAuthorId') => array_filter([$owner->getAuthorId()]),
            default => [],
        });
    }

    /**
     * The name the thread shows for a comment's author. An account's name never falls back to its
     * email address.
     */
    private function _publicAuthorName(Comment $comment): ?string
    {
        return $comment->userId && ($author = $comment->getUser())
            ? CommentsHelper::publicUserName($author)
            : $comment->getAuthorName();
    }

    private function _currentIdentityId(): ?int
    {
        $identity = $this->_viewerIdentity();

        return $identity ? (int)$identity->id : null;
    }

    /**
     * Who's looking, without giving a guest an identity just for visiting.
     */
    private function _viewerIdentity(): ?IdentityRecord
    {
        // Someone a GraphQL token signs in has no session, so they're asked for the same way as an edit.
        $viewerUser = CommentsPlugin::$plugin->getService()->getUser();
        $identities = CommentsPlugin::$plugin->getIdentities();

        return $viewerUser instanceof User ? $identities->getOrCreateForUser($viewerUser) : $identities->findGuestForRequest();
    }

    /**
     * Rendering a page of rich comments together shares its lookups of attachments and mentioned
     * people, rather than making them again for each comment.
     */
    private function _renderRichBodies(array $comments): void
    {
        $groups = [];

        foreach ($comments as $comment) {
            if ($comment->isEditorContent() && $comment->bodyContent) {
                $groups[$comment->ownerId . '-' . $comment->ownerSiteId][(int)$comment->id] = $comment;
            }
        }

        foreach ($groups as $group) {
            $config = EditorContexts::forComment(reset($group));
            $assetIds = CommentsPlugin::$plugin->getCommentAssets()->getAssetIdsForComments(array_keys($group));
            $documents = [];

            foreach ($group as $id => $comment) {
                try {
                    $documents[$id] = RichText::from(Json::decode($comment->bodyContent));
                } catch (\Throwable) {
                    // Left for the comment to deal with when it's asked to render.
                }
            }

            foreach (Editor::getInstance()->getDocuments()->renderAll($documents, $config, false, $assetIds) as $id => $html) {
                $group[$id]->setRenderedBodyHtml($html);
            }
        }
    }

    /**
     * One comment as anyone can see it. A comment that's gone, or that isn't approved when unpublished
     * comments aren't asked for, only connects the replies beneath it, so it says nothing about itself.
     */
    private function _assembleCommentView(Comment $comment, bool $showUnpublished, array $staffIds, array $batch, bool $isPublic): FrontendCommentView
    {
        $state = $this->_publicState($comment);
        $visibleStates = $showUnpublished
            ? [ModerationState::Approved->value, ModerationState::Pending->value, ModerationState::Spam->value]
            : [ModerationState::Approved->value];

        if ($comment->trashed || $comment->authorRemovedAt || !in_array($state, $visibleStates, true)) {
            return new FrontendCommentView([
                'id' => (int)$comment->id,
                'uid' => (string)$comment->uid,
                'depth' => (int)$comment->depth,
                'state' => 'unavailable',
                'content' => '',
                'contentFormat' => ContentFormat::Plain->value,
                'placeholder' => true,
            ]);
        }

        $authorName = $this->_publicAuthorName($comment);

        return new FrontendCommentView([
            'id' => (int)$comment->id,
            'uid' => (string)$comment->uid,
            'depth' => (int)$comment->depth,
            'state' => $state,
            'content' => $this->_plainContent($comment),
            'contentFormat' => (string)($comment->contentFormat ?? ContentFormat::Plain->value),
            'contentHtml' => $comment->isEditorContent() ? (string)$comment->getRenderedBodyHtml() : null,
            'authorName' => $authorName,
            'authorAvatarUrl' => $this->_avatarUrl($comment),
            'authorInitials' => Commenters::initials((string)($authorName ?: Craft::t('comments', 'Guest'))),
            'avatarTone' => self::avatarTone((string)$authorName),
            'fullDate' => $comment->commentDate ? Craft::$app->getFormatter()->asDatetime($comment->commentDate, 'medium') : null,
            'commentDate' => $comment->commentDate ? DateTimeHelper::toIso8601($comment->commentDate) ?: null : null,
            'editedAt' => $comment->editedAt ? DateTimeHelper::toIso8601($comment->editedAt) ?: null : null,
            'badges' => $this->_badgesFor($comment, $staffIds, $batch, $isPublic),
            'gifUrl' => $gifUrl = $this->_gifUrl($comment),
            'gifWidth' => $gifUrl ? $comment->gifWidth : null,
            'gifHeight' => $gifUrl ? $comment->gifHeight : null,
        ]);
    }

    /**
     * What a moderator reads beside a comment visitors can't see, or one that's been reported.
     */
    private function _reviewLabel(Comment $comment, string $state): ?string
    {
        if ($state === ModerationState::Pending->value) {
            $reason = HeldReason::tryFrom((string)$comment->heldReason) ?? HeldReason::PreModeration;

            return Craft::t('comments', 'Held · {reason} · only moderators see this', ['reason' => $reason->label()]);
        }

        if ($state === ModerationState::Spam->value) {
            return Craft::t('comments', 'Spam · only moderators see this');
        }

        if ($state === ModerationState::Approved->value && ($count = $comment->getFlags()) > 0) {
            return $count === 1
                ? Craft::t('comments', 'Reported once')
                : Craft::t('comments', 'Reported {count} times', ['count' => $count]);
        }

        return null;
    }

    private function _resolveSort(?string $sort): ?CommentSort
    {
        if ($sort === null) {
            return null;
        }

        return match (strtolower($sort)) {
            'asc', 'oldest' => CommentSort::Oldest,
            'desc', 'newest' => CommentSort::Newest,
            'top' => CommentSort::Top,
            default => null,
        };
    }

    private function _publicState(Comment $comment): string
    {
        $state = $comment->moderationState ?? null;

        if ($state) {
            return $state;
        }

        return match ($comment->status) {
            Comment::STATUS_APPROVED => ModerationState::Approved->value,
            Comment::STATUS_PENDING => 'pending-verification',
            Comment::STATUS_SPAM => ModerationState::Spam->value,
            default => ModerationState::Removed->value,
        };
    }

    /**
     * GIFs are stored as URLs, so only ever hand a web address to an image tag. Comments from before GIPHY
     * was checked can hold anything.
     */
    private function _gifUrl(Comment $comment): ?string
    {
        $url = (string)$comment->gifUrl;

        return preg_match('#^https?://#i', $url) ? $url : null;
    }

    /**
     * A plain comment's text, keeping the line breaks and paragraphs its author wrote, which the template
     * turns into `<br>`s. Runs of spaces close up, and more than one blank line becomes one.
     */
    private function _plainContent(Comment $comment): string
    {
        $text = (string)($comment->bodyContent ?? $comment->comment ?? $comment->content ?? '');
        $text = str_replace(["\r\n", "\r"], "\n", strip_tags($text));
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ ?\n ?/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function _avatarUrl(Comment $comment): ?string
    {
        if (!CommentsPlugin::$plugin->getSettings()->showAvatar) {
            return null;
        }

        $author = $comment->getAuthor();
        $url = $author instanceof User ? $this->_photoUrl($author) : null;

        // A user's own photo first, then their Gravatar, or a guest's once they've confirmed their address (SD-53).
        $url ??= CommentsPlugin::$plugin->getAvatars()->urlForComment($comment);

        if ($url !== null) {
            return $url;
        }

        // Every guest and author without a photo shares the placeholder, so it's built once too.
        $placeholder = CommentsPlugin::$plugin->getSettings()->getPlaceholderAvatar();

        if (!$placeholder) {
            return null;
        }

        $key = 'placeholder:' . $placeholder->id;
        $this->_avatarUrls[$key] ??= $placeholder->getUrl(['width' => 64, 'height' => 64, 'mode' => 'fit']);

        return $this->_avatarUrls[$key];
    }

    private function _viewerAvatarUrl(?User $user): ?string
    {
        if (!$user || !CommentsPlugin::$plugin->getSettings()->showAvatar) {
            return null;
        }

        return $this->_photoUrl($user) ?? CommentsPlugin::$plugin->getAvatars()->urlForUser($user);
    }

    private function _photoUrl(User $user): ?string
    {
        if (!CommentsPlugin::$plugin->getSettings()->showAvatar || !$user->photoId) {
            return null;
        }

        // Keyed by the photo too, so a photo changed during the request isn't served from the old one.
        $key = $user->id . ':' . $user->photoId;

        if (!array_key_exists($key, $this->_avatarUrls)) {
            $this->_avatarUrls[$key] = $user->getPhoto()?->getUrl(['width' => 64, 'height' => 64, 'mode' => 'fit']);
        }

        return $this->_avatarUrls[$key];
    }

    private function _viewerActions(Comment $comment, string $state): array
    {
        if ($state !== ModerationState::Approved->value) {
            return [];
        }

        $actions = [];
        $settings = CommentsPlugin::$plugin->getSettings();
        // The same viewer `canEdit()` and `canReply()` use, including someone signed in by a GraphQL token.
        $viewer = CommentsPlugin::$plugin->getService()->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;

        if ($comment->canReply()) {
            $actions[] = 'reply';
        }

        if ($comment->canEdit()) {
            $actions[] = 'edit';
        }

        // An author deletes their own comment. Staff remove other people's through the moderation actions.
        if ($viewer && (int)$comment->userId === (int)$viewer->id && $comment->canDelete($viewer)) {
            $actions[] = 'delete';
        }

        $mode = $settings->resolveInteractionMode();

        if ($mode->isEnabled() && ($viewer || $settings->allowGuestVoting)) {
            $actions[] = 'interact';

            if ($mode->allowsDownvote()) {
                $actions[] = 'interact-down';
            }
        }

        if ($settings->allowFlagging && ($viewer || $settings->allowGuestFlagging)) {
            $actions[] = 'report';
        }

        return $actions;
    }
}
