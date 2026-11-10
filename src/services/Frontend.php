<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\CommentStatus;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\models\FrontendCommentView;
use verbb\comments\models\FrontendEditorView;
use verbb\comments\models\FrontendConversationView;
use verbb\comments\models\FrontendEnvelope;
use verbb\comments\models\FrontendViewerView;
use verbb\comments\models\Settings;
use verbb\comments\records\Identity as IdentityRecord;
use verbb\comments\web\twig\FrontendTemplateLoader;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\fields\BaseOptionsField;
use craft\fields\Checkboxes;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\SingleOptionFieldData;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Lightswitch;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\Url;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use DateTimeInterface;

use Twig\Markup;
use verbb\editor\Editor;
use verbb\editor\RichText;
use verbb\editor\web\assets\editor\EditorAsset;

/**
 * Builds canonical frontend presentation views from v4 Conversation reads.
 * Twig partials consume these transient models — not Craft elements or Settings.
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

    public static function avatarTone(string $name): int
    {
        return $name === '' ? 0 : (int)hexdec(substr(md5(mb_strtolower(trim($name))), 0, 6)) % 8;
    }


    // Constants
    // =========================================================================

    public const CONTRACT_VERSION = 1;
    public const REPLY_PREVIEW_LIMIT = 3;
    public const MAX_VISUAL_DEPTH = 4;
    public const MAX_REPLIES_PER_REQUEST = 500;

    public const FLASH_NOTICE = 'comments.notice';
    public const FLASH_ERROR = 'comments.error';

    // The URL parameters that move around the comments on a page.
    private const PAGE_PARAMS = ['comment', 'commentsCompose', 'commentsFlag', 'commentsReplyLimit', 'commentsRootsNext', 'commentsSort', 'commentsLoad'];


    // Properties
    // =========================================================================

    /** The public fields shown on a comment's meta line, per setting. */
    private array $_metaFields = [];

    /**
     * Avatar URLs for this request, by user and photo. A transformed URL costs a transform-index lookup,
     * and a queued job while the transform isn't generated, so each author's is built once, not per comment.
     */
    private array $_avatarUrls = [];


    // Public Methods
    // =========================================================================

    /**
     * Render the canonical frontend Conversation shell for an owner element.
     */
    public function render(int $ownerId, array $criteria = []): Markup
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $request = Craft::$app->getRequest();

        // Without JavaScript, moving around the comments is done with the page's URL.
        $query = $request->getIsSiteRequest() ? $request->getQueryParams() : [];
        $pageState = array_intersect_key($query, array_flip(self::PAGE_PARAMS));

        $siteId = isset($criteria['siteId']) ? (int)$criteria['siteId'] : null;
        // The template's `sort` is the default. A sort the visitor picked from the no-JavaScript sort
        // links is in the URL and wins, or those links would do nothing on a page that sets one.
        $sort = $this->_resolveSort(is_string($query['commentsSort'] ?? null) ? $query['commentsSort'] : null)
            ?? $this->_resolveSort(is_string($criteria['sort'] ?? null) ? $criteria['sort'] : null);
        $rootLimit = isset($criteria['limit']) ? max(1, (int)$criteria['limit']) : 20;
        $sharedCache = (bool)($criteria['sharedCache'] ?? false);

        // A URL asking for something specific has to get it, and the browser can't be relied on to fetch it.
        $lazy = (bool)($criteria['lazy'] ?? $settings->lazyLoad) && $pageState === [];

        // A shared page can't vary by comment, so the browser asks for the thread itself from the URL's hash.
        $targetId = !$sharedCache ? (int)($query['comment'] ?? 0) : 0;
        $rootsNext = !$sharedCache && is_string($query['commentsRootsNext'] ?? null) ? $query['commentsRootsNext'] : null;
        $replyLimit = !$sharedCache ? (int)($query['commentsReplyLimit'] ?? 0) : 0;

        $conversation = $this->buildConversationView(
            $ownerId,
            $siteId,
            $sort,
            $lazy ? 0 : $rootLimit,
            $rootsNext,
            $sharedCache || $lazy,
            $targetId ?: null,
            $replyLimit,
        );

        if (!$conversation) {
            return Template::raw('');
        }

        $conversation->lazy = $lazy;
        $conversation->rootLimit = $rootLimit;

        $viewer = $this->buildViewerView($conversation);
        $editor = $this->buildEditorView($conversation);
        $reportTargetId = null;

        if (!$sharedCache && !$lazy && $targetId) {
            $this->_openRequestedForm($conversation, $targetId, (string)($query['commentsCompose'] ?? ''));

            if (!empty($query['commentsFlag']) && in_array('report', $this->_findInThread($conversation, $targetId)?->actions ?? [], true)) {
                $reportTargetId = $targetId;
            }
        }

        $flash = [];

        // Left for the page after a form posted without JavaScript. A shared page can't show anyone's.
        if (!$sharedCache && !$lazy && $request->getIsSiteRequest()) {
            $session = Craft::$app->getSession();
            $flash = array_filter([
                'error' => $session->getFlash(self::FLASH_ERROR),
                'notice' => $session->getFlash(self::FLASH_NOTICE),
            ]);
        }

        $html = $this->renderPartial('conversation', [
            'contractVersion' => self::CONTRACT_VERSION,
            'instance' => $conversation->instanceId,
            'conversation' => $conversation,
            'viewer' => $viewer,
            'editor' => $editor,
            'theme' => $conversation->theme,
            'apiBase' => $this->getApiUrl(''),
            'sharedCache' => $sharedCache || $lazy,
            'reportReasons' => self::reportReasons(),
            'reportTargetId' => $reportTargetId,
            // Only rich authoring needs Editor, and only once someone starts writing.
            'editorAsset' => CommentsPlugin::$plugin->getSettings()->isRichAuthoringEnabled() ? $this->_editorAsset() : null,
            'flash' => $flash,
        ]);

        return Template::raw($html);
    }

    /**
     * The plugin's own frontend templates. A site's overrides are found ahead of these.
     */
    public function getTemplatePath(): string
    {
        return Craft::getAlias('@verbb/comments/templates/frontend');
    }

    /**
     * Where a site keeps the frontend templates it has changed, if it has any.
     */
    public function getTemplateOverridePath(): ?string
    {
        $folder = CommentsPlugin::$plugin->getSettings()->templateFolderOverride;

        if (!$folder) {
            return null;
        }

        $path = Craft::$app->getPath()->getSiteTemplatesPath() . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'frontend';

        return is_dir($path) ? $path : null;
    }

    /**
     * An action URL for the browser runtime, such as `actions/comments/frontend-api/create`. With no path,
     * it's the base the script adds each endpoint to. Action URLs are built on the current site's base
     * URL, so they keep its language, and every request names its `siteId` as well.
     */
    public function getApiUrl(string $path = '', array $params = []): string
    {
        return UrlHelper::actionUrl(rtrim('comments/frontend-api/' . ltrim($path, '/'), '/'), $params);
    }

    public function renderPartial(string $partial, array $context = []): string
    {
        $view = Craft::$app->getView();
        $twig = $view->getTwig();
        $oldLoader = $twig->getLoader();
        $oldTemplatesPath = $view->getTemplatesPath();
        $overridePath = $this->getTemplateOverridePath();
        $view->setTemplatesPath($this->getTemplatePath());

        if ($overridePath) {
            $twig->setLoader(new FrontendTemplateLoader($oldLoader, $view, $overridePath));
        }

        try {
            return $view->renderTemplate($partial, $context);
        } finally {
            $twig->setLoader($oldLoader);
            $view->setTemplatesPath($oldTemplatesPath);
        }
    }

    public function renderFragment(string $type, string $mode, string $resourceUid, string $partial, array $context = []): array
    {
        return [
            'type' => $type,
            'mode' => $mode,
            'resourceUid' => $resourceUid,
            'html' => $this->renderPartial($partial, $context),
        ];
    }

    public function viewerControlFragments(
        FrontendConversationView $conversation,
        FrontendViewerView $viewer,
        FrontendEditorView $editor,
    ): array {
        $context = [
            'contractVersion' => self::CONTRACT_VERSION,
            'instance' => $conversation->instanceId,
            'conversation' => $conversation,
            'viewer' => $viewer,
            'editor' => $editor,
            'theme' => $conversation->theme,
            'apiBase' => $this->getApiUrl(''),
            'sharedCache' => false,
            'reportReasons' => self::reportReasons(),
        ];

        return [
            $this->renderFragment('conversationHeader', 'replace', $conversation->conversationUid, '_conversation/header', $context),
            $this->renderFragment('rootList', 'replace', $conversation->conversationUid, '_conversation/list', $context),
            $this->renderFragment('viewerControls', 'replace', $conversation->conversationUid, '_editor/region', $context),
            // Holds a CSRF token, so it isn't in a page cached for everyone.
            $this->renderFragment('reportDialog', 'replace', $conversation->conversationUid, '_report/form', $context),
        ];
    }

    public function conversationReadFragments(FrontendConversationView $conversation, FrontendViewerView $viewer, FrontendEditorView $editor): array
    {
        $context = [
            'contractVersion' => self::CONTRACT_VERSION,
            'instance' => $conversation->instanceId,
            'conversation' => $conversation,
            'viewer' => $viewer,
            'editor' => $editor,
            'theme' => $conversation->theme,
            'apiBase' => $this->getApiUrl(''),
        ];

        return [
            $this->renderFragment('conversationHeader', 'replace', $conversation->conversationUid, '_conversation/header', $context),
            $this->renderFragment('rootList', 'replace', $conversation->conversationUid, '_conversation/list', $context),
        ];
    }

    public function buildEnvelope(string $status, array $fragments = [], array $options = []): array
    {
        $envelope = $status === 'ok'
            ? FrontendEnvelope::ok($fragments, $options)
            : FrontendEnvelope::error($options['errors'] ?? [], $status);

        return $envelope->toArray();
    }

    public function buildConversationView(
        int $ownerId,
        ?int $siteId = null,
        ?CommentSort $sort = null,
        int $rootLimit = 20,
        ?string $rootsNext = null,
        bool $sharedCache = false,
        ?int $targetCommentId = null,
        int $replyLimit = 0,
    ): ?FrontendConversationView {
        $siteId ??= (int)Craft::$app->getSites()->getCurrentSite()->id;
        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if (!$owner) {
            return null;
        }

        $settings = CommentsPlugin::$plugin->getSettings();
        $conversations = CommentsPlugin::$plugin->getConversations();
        $conversation = $conversations->findForOwner($ownerId, $siteId);
        $config = $conversations->getConfigForOwner($ownerId, $siteId);

        // Disabled means there's no Comments experience at all, even with existing comments.
        if (!$config->enabled) {
            return null;
        }

        // Someone who can already see an unpublished element can see its comments too.
        if (!$conversations->isOwnerPublic($owner) && (
            !($viewer = Craft::$app->getUser()->getIdentity())
            || $sharedCache
            || !Craft::$app->getElements()->canView($owner, $viewer)
        )) {
            return null;
        }

        // Empty Conversation rendering creates no records — only build when one exists or comments can be posted.
        $availability = $settings->commentingAvailable($owner);
        $canComment = (bool)$availability['permission'];

        if (!$conversation && !$canComment) {
            return null;
        }

        // Stays the same between requests, so what a browser fetches later can find its place on the page.
        $conversationUid = $conversation
            ? (string)$conversation->uid
            : "new-{$ownerId}-{$siteId}";

        $sort ??= $config->sortOrder;

        // There's nothing to rank by when interactions are off.
        if ($sort === CommentSort::Top && !$settings->resolveInteractionMode()->isEnabled()) {
            $sort = $settings->orderBy === 'asc' ? CommentSort::Oldest : CommentSort::Newest;
        }

        $instanceId = 'comments-' . $ownerId . '-' . $siteId;

        $viewerUser = Craft::$app->getUser()->getIdentity();

        // Moderation is inherently private, so shared-cache shells always render the public view
        // and pick up moderator affordances during the viewer-controls hydration pass.
        $isModerator = !$sharedCache
            && CommentsPlugin::$plugin->getModeration()->canModerateComment($viewerUser);

        $roots = [];
        $next = null;

        // A limit of nothing means the browser's fetching them later, when it needs them.
        if ($conversation && $rootLimit > 0) {
            $page = $conversations->getRootComments(
                (int)$conversation->id,
                $sort,
                $rootLimit,
                $rootsNext,
                $this->_visibleStatesFor($isModerator),
            );
            $next = $page->next;
            $roots = $this->buildCommentViewsForComments($page->comments, 0, !$sharedCache, $isModerator);

            // The linked comment's thread goes first, unless this is a later page of the list.
            if ($targetCommentId && !$rootsNext && ($thread = $this->buildThreadView($targetCommentId, $ownerId, $siteId, $isModerator, $replyLimit))) {
                $roots = [$thread, ...array_values(array_filter($roots, static fn(FrontendCommentView $root): bool => $root->id !== $thread->id))];
            }
        }

        $viewerIdentity = !$sharedCache ? $this->_viewerIdentity() : null;

        $subscribed = $conversation && $viewerIdentity
            ? CommentsPlugin::$plugin->getSubscriptions()->isSubscribed(
                (int)$conversation->id,
                (int)$viewerIdentity->id,
                null,
                SubscriptionType::Conversation,
            )
            : false;

        $heldCount = 0;
        $reportedCount = 0;

        if ($isModerator && $conversation) {
            $heldCount = (int)Comment::find()->status(null)->conversationId((int)$conversation->id)->moderationState(ModerationState::Pending->value)->count();
            $reportedCount = (int)Comment::find()->status(null)->conversationId((int)$conversation->id)->moderationState(ModerationState::Approved->value)->hasOpenReports(true)->count();
        }

        return new FrontendConversationView([
            'contractVersion' => self::CONTRACT_VERSION,
            'instanceId' => $instanceId,
            'heldCount' => $heldCount,
            'reportedCount' => $reportedCount,
            'reviewUrl' => $isModerator ? UrlHelper::cpUrl('comments', ['ownerId' => $ownerId, 'source' => $heldCount ? 'needs-review' : null]) : null,
            'conversationUid' => $conversationUid,
            'ownerId' => $ownerId,
            'siteId' => $siteId,
            'sort' => $sort->value,
            'theme' => $settings->resolveEditorTheme(),
            'roots' => $roots,
            'rootsNext' => $next,
            'rootCount' => count($roots),
            'commentCount' => (int)($conversation->commentCount ?? 0),
            'canComment' => $canComment,
            'closedNotice' => in_array($availability['reason'], [CommentStatus::ManuallyClosed['reason'], CommentStatus::Expired['reason']], true)
                ? Craft::t('comments', 'Comments are closed.')
                : null,
            'allowsTopSort' => $settings->resolveInteractionMode()->isEnabled(),
            'guestNotice' => !$viewerUser && !$settings->allowGuest
                ? ($settings->guestNotice ?: Craft::t('comments', 'Must be logged in to comment.'))
                : null,
            'showSubscribe' => $settings->allowsSubscriptionType(SubscriptionType::Conversation) && ($viewerUser || $config->allowGuests),
            'subscribeNeedsEmail' => !$viewerUser && !$viewerIdentity?->verifiedAt,
            'isSubscribed' => $subscribed,
            'isModerator' => $isModerator,
            'interactionMode' => $settings->resolveInteractionMode()->value,
            'saveAction' => UrlHelper::actionUrl('comments/comments/save'),
            'apiSaveAction' => $this->getApiUrl('create'),
            'subscribeAction' => UrlHelper::actionUrl('comments/comments/subscribe'),
            'voteAction' => UrlHelper::actionUrl('comments/comments/vote'),
            'interactionAction' => $this->getApiUrl('interaction'),
            'moderateAction' => $this->getApiUrl('moderate'),
            'removeAction' => $this->getApiUrl('remove'),
            'reportAction' => $this->getApiUrl('report'),
            'pageParams' => array_diff_key($this->_pageParams(), ['commentsSort' => true]),
            'rootsNextHref' => $next ? $this->_pageHref(['commentsRootsNext' => $next, 'commentsSort' => $sort->value]) : '',
            'showCommentsHref' => $this->_pageHref(['commentsLoad' => 1]),
            'cancelHref' => $this->_pageHref(),
            'flagAction' => UrlHelper::actionUrl('comments/comments/flag'),
            'operationId' => StringHelper::UUID(),
        ]);
    }

    public function buildViewerView(FrontendConversationView $conversation): FrontendViewerView
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $viewerUser = Craft::$app->getUser()->getIdentity();

        $name = $viewerUser ? CommentsHelper::publicUserName($viewerUser) : null;
        $loginPath = Craft::$app->getConfig()->getGeneral()->getLoginPath();

        return new FrontendViewerView([
            'contractVersion' => self::CONTRACT_VERSION,
            'name' => $name,
            'initials' => $name ? Commenters::initials($name) : '',
            'avatarTone' => self::avatarTone((string)$name),
            'badge' => $viewerUser ? $this->_viewerBadge($viewerUser, $conversation) : null,
            'loginUrl' => !$viewerUser && is_string($loginPath) && $loginPath !== '' ? UrlHelper::siteUrl($loginPath) : null,
            'allowsGuests' => (bool)$settings->allowGuest,
            'isLoggedIn' => (bool)$viewerUser,
            'canComment' => $conversation->canComment,
            'showSubscribe' => $conversation->showSubscribe,
            'subscribeNeedsEmail' => $conversation->subscribeNeedsEmail,
            'isSubscribed' => $conversation->isSubscribed,
            'isModerator' => $conversation->isModerator,
            'avatarUrl' => $this->_viewerAvatarUrl($viewerUser),
        ]);
    }

    public function buildEditorView(
        FrontendConversationView $conversation,
        string $mode = 'create',
        ?Comment $targetComment = null,
    ): FrontendEditorView {
        $settings = CommentsPlugin::$plugin->getSettings();
        $viewerUser = Craft::$app->getUser()->getIdentity();
        // A comment written while rich authoring was on keeps its formatting when it's edited later.
        $richAuthoring = $settings->isRichAuthoringEnabled()
            || ($mode === 'edit' && $targetComment?->isEditorContent());
        $guestIdentity = $viewerUser ? null : CommentsPlugin::$plugin->getIdentities()->findGuestForRequest();
        $publicFields = $this->_publicFieldViews($mode === 'edit' ? $targetComment : null);

        // The script posts to the frontend API's action; forms without JavaScript keep conversation.saveAction.
        $saveAction = $conversation->apiSaveAction ?: $conversation->saveAction;

        $editorContext = match ($mode) {
            'edit' => 'comments.edit',
            default => 'comments.reply',
        };

        $editor = new FrontendEditorView([
            'contractVersion' => self::CONTRACT_VERSION,
            'mode' => $mode,
            'operationId' => StringHelper::UUID(),
            'saveAction' => $saveAction,
            'legacySaveAction' => $conversation->saveAction,
            'ownerId' => $conversation->ownerId,
            'siteId' => $conversation->siteId,
            'contentFormat' => $richAuthoring ? ContentFormat::Editor->value : ContentFormat::Plain->value,
            'richAuthoring' => $richAuthoring,
            'editorAsset' => $richAuthoring ? $this->_editorAsset() : null,
            'editorContext' => $editorContext,
            'showGuestFields' => !$viewerUser && $settings->allowGuest && ($settings->guestShowEmailName || $settings->guestRequireEmailName),
            'guestRequireEmailName' => (bool)$settings->guestRequireEmailName,
            'offerReplyEmails' => !$viewerUser && $settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_REPLY),
            'guestName' => $guestIdentity?->name,
            'guestEmail' => $guestIdentity?->email,
            'publicFields' => $publicFields,
            'hasFileInputs' => in_array('file', array_column($publicFields, 'type'), true),
            'redirectTemplate' => $this->_pagePath() . $this->_pageHref(['comment' => '__ID__'], 'comment-__ID__'),
            'cancelHref' => $conversation->cancelHref,
        ]);

        if ($mode === 'reply' && $targetComment) {
            $editor->parentCommentId = (int)$targetComment->id;
            $author = $targetComment->userId ? $targetComment->getUser() : null;
            $editor->parentAuthorName = $author
                ? CommentsHelper::publicUserName($author)
                : ($targetComment->getAuthorName() ?: Craft::t('comments', 'Guest'));
            // "Reply to Alex…" reads better with a first name, when the account has one to give.
            $editor->parentAuthorShortName = $author && $author->firstName && $editor->parentAuthorName !== Craft::t('comments', 'Member')
                ? $author->firstName
                : $editor->parentAuthorName;
        }

        if ($mode === 'edit' && $targetComment) {
            $editor->editCommentId = (int)$targetComment->id;
            $editor->initialPlainContent = (string)($targetComment->comment ?? '');

            if ($targetComment->isEditorContent()) {
                $editor->initialEditorContent = $targetComment->getEditorFormContent();
            }
        }

        // Per person, so a shared computer never offers one visitor another's draft.
        $editor->draftKey = implode(':', [
            'comments',
            $conversation->siteId,
            $conversation->ownerId,
            $mode,
            $targetComment ? (int)$targetComment->id : 'new',
            $viewerUser ? (int)$viewerUser->id : 'guest',
        ]);
        $editor->maxLength = $settings->securityMaxLength ? (int)$settings->securityMaxLength : null;

        $editor->redirectTemplate = str_replace('__ID__', '{id}', $editor->redirectTemplate);
        $this->_restorePostedValues($editor);

        return $editor;
    }

    public function buildCommentViewPublic(
        Comment $comment,
        int $depth = 0,
        bool $includeViewerActions = true,
        ?bool $isModerator = null,
    ): FrontendCommentView {
        return $this->buildCommentViewsForComments([$comment], $depth, $includeViewerActions, $isModerator)[0]
            ?? $this->_assembleCommentView($comment, $depth, [], 0, false, $includeViewerActions, false, null);
    }

    public function buildCommentViewsForComments(
        array $comments,
        int $depth,
        bool $includeViewerActions = true,
        ?bool $isModerator = null,
    ): array {
        if ($comments === []) {
            return [];
        }

        $isModerator ??= $includeViewerActions
            && CommentsPlugin::$plugin->getModeration()->canModerateComment(Craft::$app->getUser()->getIdentity());

        $parentIds = array_map(static fn(Comment $comment): int => (int)$comment->id, $comments);

        // Interaction totals and the viewer's own state are fetched once per level rather than
        // per comment — this is on the hot render path and must not become an N+1.
        $interactionState = [];
        $mode = CommentsPlugin::$plugin->getSettings()->resolveInteractionMode();

        if ($includeViewerActions && $mode->isEnabled()) {
            $interactionState = CommentsPlugin::$plugin->getInteractions()->getPresentationState(
                $parentIds,
                $this->_currentIdentityId(),
            );
        }

        $settings = CommentsPlugin::$plugin->getSettings();
        $sort = $settings->orderBy === 'asc' ? CommentSort::Oldest : CommentSort::Newest;
        $staffIds = $this->_staffUserIds($comments);
        $this->_renderRichBodies($comments);

        // Beyond the visual limit replies are only counted, and load on request instead.
        $childMaps = CommentsPlugin::$plugin->getConversations()->getReplyPreviewMap(
            $parentIds,
            $sort,
            $depth < self::MAX_VISUAL_DEPTH ? self::REPLY_PREVIEW_LIMIT : 0,
            $this->_visibleStatesFor($isModerator),
        );

        $views = [];

        foreach ($comments as $comment) {
            $preview = $childMaps[(int)$comment->id] ?? [
                'replies' => [],
                'total' => 0,
                'hasMore' => false,
            ];
            $childViews = $this->buildCommentViewsForComments(
                $preview['replies'],
                $depth + 1,
                $includeViewerActions,
                $isModerator,
            );
            $views[] = $this->_assembleCommentView(
                $comment,
                $depth,
                $childViews,
                (int)$preview['total'],
                (bool)$preview['hasMore'],
                $includeViewerActions,
                $isModerator,
                $interactionState[(int)$comment->id] ?? null,
                $staffIds,
            );
        }

        return $views;
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
        $authors = [];

        foreach ($comments as $comment) {
            $name = $this->_publicAuthorName($comment) ?: Craft::t('comments', 'Guest');

            $authors[(int)$comment->id] = [
                'name' => $name,
                'initials' => Commenters::initials($name),
                'avatarUrl' => $this->_avatarUrl($comment),
                'isGuest' => !$comment->userId,
                'badges' => $this->_badgesFor($comment, $staffIds),
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

    /**
     * A comment with the thread it belongs to: its root at the top, then only the replies leading down
     * to it alongside the usual previews. Replies are loaded from there as normal.
     *
     * Returns nothing for a comment the viewer can't see, the same as one that doesn't exist.
     */
    public function buildThreadView(int $commentId, int $ownerId, int $siteId, bool $isModerator, int $replyLimit = 0): ?FrontendCommentView
    {
        $target = Comment::find()->id($commentId)->ownerId($ownerId)->ownerSiteId($siteId)->status(null)->siteId('*')->one();

        if (
            !$target
            || $target->authorRemovedAt
            || !in_array($this->_publicState($target), $this->_visibleStatesFor($isModerator) ?? [ModerationState::Approved->value], true)
        ) {
            return null;
        }

        $path = CommentsPlugin::$plugin->getConversations()->getThreadPath($target);
        $view = null;

        foreach (array_reverse($path) as $comment) {
            $parentView = $this->buildCommentViewsForComments([$comment], (int)$comment->depth, true, $isModerator)[0];

            // Asked to show more of this comment's replies than the usual preview.
            if (!$view && $replyLimit > 0) {
                $settings = CommentsPlugin::$plugin->getSettings();
                $preview = CommentsPlugin::$plugin->getConversations()->getReplyPreviewMap(
                    [(int)$comment->id],
                    $settings->orderBy === 'asc' ? CommentSort::Oldest : CommentSort::Newest,
                    min($replyLimit, self::MAX_REPLIES_PER_REQUEST),
                    $this->_visibleStatesFor($isModerator),
                )[(int)$comment->id];

                $parentView->replies = $this->buildCommentViewsForComments($preview['replies'], (int)$comment->depth + 1, true, $isModerator);
                $parentView->hasMoreReplies = $preview['hasMore'];
            }

            if ($view) {
                $siblings = array_values(array_filter($parentView->replies, static fn(FrontendCommentView $reply): bool => $reply->id !== $view->id));
                $parentView->replies = [$view, ...$siblings];
                $parentView->hasMoreReplies = $parentView->replyCount > count($parentView->replies);
            }

            $view = $parentView;
        }

        return $view;
    }


    // Private Methods
    // =========================================================================

    /**
     * What a browser needs to load Editor for itself. Every form starts as the plugin's own plain
     * editor, and a rich one loads Editor when someone starts writing in it, opens a reply or edits
     * a comment. Plain authoring never loads it, except to edit a comment that was written rich.
     *
     * It's given even with Load Bundled JavaScript off, because a site that bundles the runtime itself
     * still needs to know where Editor is. The runtime only loads it if the page hasn't already.
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
     * Open a reply or edit form in place for someone without JavaScript, if they're allowed that action.
     */
    private function _openRequestedForm(FrontendConversationView $conversation, int $targetId, string $mode): void
    {
        $node = $this->_findInThread($conversation, $targetId);

        if (!$node || !in_array($mode, ['reply', 'edit'], true) || !in_array($mode, $node->actions, true)) {
            return;
        }

        $target = CommentsPlugin::$plugin->getComments()->getCommentById($targetId);

        if ($target) {
            $node->contextualEditor = $this->buildEditorView($conversation, $mode, $target);
        }
    }

    /**
     * The linked comment is always the first reply at every level of its pinned thread.
     */
    private function _findInThread(FrontendConversationView $conversation, int $targetId): ?FrontendCommentView
    {
        $node = $conversation->roots[0] ?? null;

        while ($node && $node->id !== $targetId) {
            $node = $node->replies[0] ?? null;
        }

        return $node;
    }

    /**
     * A link back to this page, relative so that it works wherever the fragment holding it ends up. It
     * keeps whatever else is in the URL, like a site's own pagination.
     */
    private function _pageHref(array $params = [], ?string $fragment = null): string
    {
        $query = http_build_query($this->_pageParams() + array_filter($params, static fn($value) => $value !== null) + $this->_pageSort());

        return '?' . $query . ($fragment ? "#{$fragment}" : '');
    }

    private function _pageParams(): array
    {
        $request = Craft::$app->getRequest();

        if (!$request->getIsSiteRequest()) {
            return [];
        }

        return array_diff_key($request->getQueryParams(), array_flip([...self::PAGE_PARAMS, Craft::$app->getConfig()->getGeneral()->pathParam]));
    }

    /**
     * The order someone chose stays as they move around the page without JavaScript.
     */
    private function _pageSort(): array
    {
        $request = Craft::$app->getRequest();
        $sort = $request->getIsSiteRequest() ? $request->getQueryParam('commentsSort') : null;

        return is_string($sort) && $sort !== '' ? ['commentsSort' => $sort] : [];
    }

    private function _pagePath(): string
    {
        $request = Craft::$app->getRequest();

        return $request->getIsConsoleRequest() ? '' : ltrim($request->getPathInfo(), '/');
    }

    /**
     * When a form posted without JavaScript comes back with an error, nothing typed into it is lost.
     */
    private function _restorePostedValues(FrontendEditorView $editor): void
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest() || !$request->getIsPost() || $request->getAcceptsJson()) {
            return;
        }

        if (
            (int)$request->getBodyParam('parentId') !== (int)$editor->parentCommentId
            || (int)$request->getBodyParam('commentId') !== (int)$editor->editCommentId
        ) {
            return;
        }

        $editor->initialPlainContent = (string)$request->getBodyParam('fields.comment', $editor->initialPlainContent ?? '');
        $editor->guestName = (string)$request->getBodyParam('fields.name', $editor->guestName ?? '');
        $editor->guestEmail = (string)$request->getBodyParam('fields.email', $editor->guestEmail ?? '');

        foreach ($editor->publicFields as &$field) {
            $posted = $request->getBodyParam('fields.' . $field['handle']);

            if ($posted !== null && $field['type'] !== 'file') {
                $field['values'] = array_map('strval', (array)$posted);
            }
        }
    }

    /**
     * The fields selected under Public Fields, in plain terms a form can draw. Field types that can't be
     * filled in with a plain input are left out.
     */
    private function _publicFieldViews(?Comment $comment): array
    {
        $allowed = CommentsPlugin::$plugin->getSettings()->publicFields;
        $layout = Craft::$app->getFields()->getLayoutByType(Comment::class);
        $views = [];

        if (!$allowed) {
            return [];
        }

        foreach ($layout->getTabs() as $tab) {
            foreach ($tab->getElements() as $element) {
                if (!$element instanceof CustomField || !in_array($element->attribute(), $allowed, true)) {
                    continue;
                }

                $field = $element->getField();
                [$type, $multiple] = $this->_publicFieldType($field);

                if ($type === null) {
                    continue;
                }

                $value = $comment?->getFieldValue($field->handle);

                $views[] = [
                    'handle' => $field->handle,
                    'label' => $element->label() ?? $field->name,
                    'instructions' => $element->instructions ?: ($field->instructions ?: null),
                    'required' => $element->required,
                    'type' => $type,
                    'multiple' => $multiple,
                    'options' => $this->_publicFieldOptions($field),
                    'values' => $type === 'file' ? [] : $this->_publicFieldValues($value),
                ];
            }
        }

        return $views;
    }

    /**
     * Short public field values for a comment's meta line, such as "I race · Rotorua". Only fields a
     * visitor fills in publicly, and only kinds that read as a phrase: never addresses or files.
     */
    private function _metaLine(Comment $comment): array
    {
        $allowed = CommentsPlugin::$plugin->getSettings()->publicFields;
        $key = implode(',', $allowed);

        if (!isset($this->_metaFields[$key])) {
            $this->_metaFields[$key] = [];
            $layout = $allowed ? Craft::$app->getFields()->getLayoutByType(Comment::class) : null;

            foreach ($layout?->getCustomFieldElements() ?? [] as $element) {
                $field = $element->getField();

                if (in_array($field->handle, $allowed, true) && (
                    ($field instanceof PlainText && !$field->multiline)
                    || $field instanceof Number
                    || $field instanceof Dropdown
                    || $field instanceof RadioButtons
                    || $field instanceof MultiSelect
                )) {
                    $this->_metaFields[$key][] = $field;
                }
            }
        }

        $line = [];

        foreach ($this->_metaFields[$key] as $field) {
            $value = $comment->getFieldValue($field->handle);
            $text = match (true) {
                $value instanceof SingleOptionFieldData => (string)$value->label,
                $value instanceof MultiOptionsFieldData => implode(', ', array_map(static fn($option): string => (string)$option->label, iterator_to_array($value))),
                is_scalar($value) => trim((string)$value),
                default => '',
            };

            if ($text !== '') {
                $line[] = StringHelper::truncate($text, 40);
            }
        }

        return $line;
    }

    private function _publicFieldType(FieldInterface $field): array
    {
        return match (true) {
            $field instanceof PlainText => [$field->multiline ? 'textarea' : 'text', false],
            $field instanceof Number => ['number', false],
            $field instanceof Email => ['email', false],
            $field instanceof Url => ['url', false],
            $field instanceof Date => ['date', false],
            $field instanceof Dropdown => ['select', false],
            $field instanceof MultiSelect => ['select', true],
            $field instanceof RadioButtons => ['radio', false],
            $field instanceof Checkboxes => ['checkboxes', true],
            $field instanceof Lightswitch => ['lightswitch', false],
            $field instanceof Assets => ['file', !$field->maxRelations || $field->maxRelations > 1],
            default => [null, false],
        };
    }

    private function _publicFieldOptions(FieldInterface $field): array
    {
        if (!$field instanceof BaseOptionsField) {
            return [];
        }

        $options = [];

        foreach ($field->options as $option) {
            // Option groups only have a heading, so their options are listed plainly.
            if (isset($option['value'])) {
                $options[] = ['label' => (string)$option['label'], 'value' => (string)$option['value']];
            }
        }

        return $options;
    }

    private function _publicFieldValues(mixed $value): array
    {
        if ($value === null || $value === '' || $value === false) {
            return [];
        }

        if ($value instanceof MultiOptionsFieldData) {
            return array_map(static fn($option): string => (string)$option->value, iterator_to_array($value));
        }

        if ($value instanceof SingleOptionFieldData) {
            return [(string)$value->value];
        }

        if ($value instanceof DateTimeInterface) {
            return [$value->format('Y-m-d')];
        }

        return $value === true ? ['1'] : [(string)$value];
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

    private function _badgesFor(Comment $comment, array $staffIds): array
    {
        $userId = (int)$comment->userId;

        if (!$userId) {
            return [];
        }

        $settings = CommentsPlugin::$plugin->getSettings();
        $badges = [];

        if ($settings->ownerBadgeLabel && in_array($userId, $this->_ownerAuthorIds($comment), true)) {
            $badges[] = ['type' => 'owner', 'label' => Craft::t('comments', $settings->ownerBadgeLabel)];
        }

        if (isset($staffIds[$userId])) {
            $badges[] = ['type' => 'staff', 'label' => Craft::t('comments', (string)$settings->staffBadgeLabel)];
        }

        return $badges;
    }

    /**
     * The badge a signed-in viewer's comments will carry here, for "Posting as".
     */
    private function _viewerBadge(User $user, FrontendConversationView $conversation): ?string
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $owner = Craft::$app->getElements()->getElementById($conversation->ownerId, null, $conversation->siteId);

        if ($owner && $settings->ownerBadgeLabel) {
            $authorIds = array_map('intval', match (true) {
                $owner instanceof Entry => $owner->getAuthorIds(),
                $owner instanceof User => [$owner->id],
                $owner instanceof Asset => array_filter([$owner->uploaderId]),
                default => [],
            });

            if (in_array((int)$user->id, $authorIds, true)) {
                return Craft::t('comments', $settings->ownerBadgeLabel);
            }
        }

        if ($settings->staffBadgeLabel && isset($this->_staffIdsForUsers([(int)$user->id])[(int)$user->id])) {
            return Craft::t('comments', (string)$settings->staffBadgeLabel);
        }

        return null;
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

    /**
     * Moderators see unmoderated Comments inline so they can act without leaving the page.
     *
     * Null means public visibility (approved only).
     */
    private function _visibleStatesFor(bool $isModerator): ?array
    {
        if (!$isModerator) {
            return null;
        }

        return [
            ModerationState::Approved->value,
            ModerationState::Pending->value,
            ModerationState::Spam->value,
        ];
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

    private function _assembleCommentView(
        Comment $comment,
        int $depth,
        array $replies,
        int $replyCount,
        bool $hasMoreReplies,
        bool $includeViewerActions = true,
        bool $isModerator = false,
        ?array $interactionState = null,
        array $staffIds = [],
    ): FrontendCommentView {
        $settings = CommentsPlugin::$plugin->getSettings();
        $state = $this->_publicState($comment);

        // Only here to connect the replies beneath it, so it says nothing about itself or why it's hidden.
        if (
            $comment->trashed
            || $comment->authorRemovedAt
            || !in_array($state, $this->_visibleStatesFor($isModerator) ?? [ModerationState::Approved->value], true)
        ) {
            return new FrontendCommentView([
                'contractVersion' => self::CONTRACT_VERSION,
                'id' => (int)$comment->id,
                'uid' => (string)$comment->uid,
                'depth' => $depth,
                'visualDepth' => min($depth, self::MAX_VISUAL_DEPTH),
                'state' => 'unavailable',
                'content' => '',
                'contentFormat' => ContentFormat::Plain->value,
                'permalink' => '#comment-' . $comment->id,
                'replies' => $replies,
                'replyCount' => $replyCount,
                'hasMoreReplies' => $hasMoreReplies,
                'hrefs' => $this->_commentHrefs($comment, count($replies)),
                'placeholder' => true,
            ]);
        }

        $contentHtml = null;

        if ($comment->isEditorContent()) {
            $contentHtml = (string)$comment->getRenderedBodyHtml();
        }

        $authorName = $this->_publicAuthorName($comment);

        return new FrontendCommentView([
            'contractVersion' => self::CONTRACT_VERSION,
            'id' => (int)$comment->id,
            'uid' => (string)$comment->uid,
            'depth' => $depth,
            'visualDepth' => min($depth, self::MAX_VISUAL_DEPTH),
            'state' => $state,
            'content' => $this->_plainContent($comment),
            'contentFormat' => (string)($comment->contentFormat ?? ContentFormat::Plain->value),
            'contentHtml' => $contentHtml,
            'authorName' => $authorName,
            'authorAvatarUrl' => $this->_avatarUrl($comment),
            'authorInitials' => Commenters::initials((string)($authorName ?: Craft::t('comments', 'Guest'))),
            'avatarTone' => self::avatarTone((string)$authorName),
            'fullDate' => $comment->commentDate ? Craft::$app->getFormatter()->asDatetime($comment->commentDate, 'medium') : null,
            'metaLine' => $this->_metaLine($comment),
            'reviewLabel' => $includeViewerActions && $isModerator ? $this->_reviewLabel($comment, $state) : null,
            'reported' => $includeViewerActions && $isModerator && $state === ModerationState::Approved->value && $comment->getFlags() > 0,
            'commentDate' => $comment->commentDate
                ? DateTimeHelper::toIso8601($comment->commentDate) ?: null
                : null,
            // Relative times would go stale in a shared cache.
            'timeAgo' => $includeViewerActions && $settings->showTimeAgo ? ($comment->getTimeAgo() ?: null) : null,
            'editedAt' => $comment->editedAt ? DateTimeHelper::toIso8601($comment->editedAt) ?: null : null,
            'badges' => $this->_badgesFor($comment, $staffIds),
            'gifUrl' => $this->_gifUrl($comment),
            'permalink' => '#comment-' . $comment->id,
            'replies' => $replies,
            'replyCount' => $replyCount,
            'hasMoreReplies' => $hasMoreReplies,
            'hrefs' => $this->_commentHrefs($comment, count($replies)),
            'actions' => $includeViewerActions ? $this->_viewerActions($comment, $state) : [],
            'interaction' => $includeViewerActions ? $this->_interactionView($state, $interactionState) : null,
            'moderationActions' => $includeViewerActions && $isModerator
                ? $this->_moderationActions($comment, $state)
                : [],
            'moderatorOnly' => $state !== ModerationState::Approved->value,
        ]);
    }

    private function _commentHrefs(Comment $comment, int $shownReplies): array
    {
        $id = (int)$comment->id;
        $anchor = "comment-{$id}";

        return [
            'reply' => $this->_pageHref(['comment' => $id, 'commentsCompose' => 'reply'], $anchor),
            'edit' => $this->_pageHref(['comment' => $id, 'commentsCompose' => 'edit'], $anchor),
            'flag' => $this->_pageHref(['comment' => $id, 'commentsFlag' => 1], $anchor),
            'replies' => $this->_pageHref(['comment' => $id, 'commentsReplyLimit' => $shownReplies + 20], $anchor),
        ];
    }

    private function _interactionView(string $commentState, ?array $state): ?array
    {
        $mode = CommentsPlugin::$plugin->getSettings()->resolveInteractionMode();

        // Unmoderated Comments are only visible to moderators, who shouldn't be voting on them.
        if (!$mode->isEnabled() || $commentState !== ModerationState::Approved->value) {
            return null;
        }

        $state ??= ['upvotes' => 0, 'downvotes' => 0, 'score' => 0, 'viewer' => null];

        return [
            'mode' => $mode->value,
            'allowsDownvote' => $mode->allowsDownvote(),
            'usesScore' => $mode->usesScore(),
            'upvotes' => (int)$state['upvotes'],
            'downvotes' => (int)$state['downvotes'],
            'score' => (int)$state['score'],
            'viewer' => $state['viewer'],
        ];
    }

    /**
     * Inline moderation, keyed off the Comment's current state: the same decisions as the control panel,
     * plus Remove and Spam on any approved comment.
     */
    private function _moderationActions(Comment $comment, string $state): array
    {
        $viewer = Craft::$app->getUser()->getIdentity();
        $moderation = CommentsPlugin::$plugin->getModeration();
        $decisions = $moderation->availableDecisions($comment, $viewer);

        if ($state === ModerationState::Approved->value && $decisions === [] && $moderation->canModerateComment($viewer, $comment)) {
            $decisions = $moderation->canRemoveComment($viewer)
                ? [Moderation::DECISION_REMOVE, Moderation::DECISION_SPAM]
                : [Moderation::DECISION_SPAM];
        }

        return array_map(static fn(string $decision): string => 'moderate-' . $decision, $decisions);
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
     * Historical GIFs are stored as URLs, so only ever hand a web address to an image tag.
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
        $url = $author instanceof User ? $this->_viewerAvatarUrl($author) : null;

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

        if (!$user->photoId) {
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

        // Staff remove other people's comments through the moderation actions.
        if ($viewer && (int)$comment->userId === (int)$viewer->id && $comment->canDelete($viewer)) {
            $actions[] = 'remove';
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
