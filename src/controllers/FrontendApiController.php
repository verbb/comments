<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Conversations;
use verbb\comments\services\Frontend;
use verbb\comments\services\Moderation;
use verbb\comments\services\Protection;
use verbb\comments\services\Subscriptions;

use Craft;
use craft\helpers\StringHelper;
use craft\web\Controller;

use yii\base\UserException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FrontendApiController extends Controller
{
    // Properties
    // =========================================================================

    protected array|bool|int $allowAnonymous = [
        'conversation',
        'roots',
        'replies',
        'viewer-controls',
        'editor',
        'protection',
        'create',
        'subscription',
        'thread',
    ];


    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        $settings = Comments::$plugin->getSettings();

        if ($settings->allowGuestFlagging) {
            $this->allowAnonymous['report'] = 1;
        }

        if ($settings->allowGuestVoting) {
            $this->allowAnonymous['interaction'] = 1;
        }

        if (!parent::beforeAction($action)) {
            return false;
        }

        // Envelope responses are always JSON regardless of Accept negotiation.
        $this->response->format = Response::FORMAT_JSON;
        // These responses include viewer state or single-use preparation evidence, and they're fragments
        // of a page rather than pages in their own right, so search engines shouldn't list them.
        $this->response->getHeaders()
            ->set('Cache-Control', 'private, no-store')
            ->set('X-Robots-Tag', 'noindex');

        return true;
    }

    public function actionConversation(): Response
    {
        $ownerId = (int)$this->request->getRequiredParam('ownerId');
        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $frontend = Comments::$plugin->getFrontend();
        $conversation = $frontend->buildConversationView(
            $ownerId,
            $siteId,
            $this->_resolveSortParam(),
            $this->_resolveRootLimit(),
        );

        if (!$conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $viewer = $frontend->buildViewerView($conversation);
        $editor = $frontend->buildEditorView($conversation);

        return $this->asJson($frontend->buildEnvelope('ok', $frontend->conversationReadFragments($conversation, $viewer, $editor), [
            'resource' => $conversation->conversationUid,
            'rootsNext' => $conversation->rootsNext,
        ]));
    }

    public function actionRoots(): Response
    {
        $ownerId = (int)$this->request->getRequiredParam('ownerId');
        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $cursor = $this->request->getParam('cursor');
        $frontend = Comments::$plugin->getFrontend();
        $conversation = $frontend->buildConversationView(
            $ownerId,
            $siteId,
            $this->_resolveSortParam(),
            $this->_resolveRootLimit(),
            is_string($cursor) ? $cursor : null,
        );

        if (!$conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $viewer = $frontend->buildViewerView($conversation);
        $editor = $frontend->buildEditorView($conversation);
        $context = [
            'contractVersion' => $frontend::CONTRACT_VERSION,
            'instance' => $conversation->instanceId,
            'conversation' => $conversation,
            'viewer' => $viewer,
            'editor' => $editor,
            'theme' => $conversation->theme,
            'apiBase' => $frontend->getApiUrl(''),
        ];

        return $this->asJson($frontend->buildEnvelope('ok', [
            $frontend->renderFragment('rootList', 'append', $conversation->conversationUid, '_conversation/list', $context),
        ], [
            'rootsNext' => $conversation->rootsNext,
        ]));
    }

    public function actionReplies(): Response
    {
        $commentUid = (string)$this->request->getRequiredParam('commentUid');
        // A trashed comment can still be standing in as a placeholder for the replies beneath it.
        $comment = Comment::find()->uid($commentUid)->status(null)->trashed(null)->siteId('*')->one();

        if (!$comment) {
            throw new NotFoundHttpException('Comment not found.');
        }

        $frontend = Comments::$plugin->getFrontend();
        $settings = Comments::$plugin->getSettings();
        $isModerator = Comments::$plugin->getModeration()
            ->canModerateComment(Craft::$app->getUser()->getIdentity());

        // Each request returns everything up to the limit, because the fragment replaces the whole list.
        $limit = max(1, min((int)($this->request->getParam('limit') ?: 20), Frontend::MAX_REPLIES_PER_REQUEST));

        $preview = Comments::$plugin->getConversations()->getReplyPreviewMap(
            [(int)$comment->id],
            $settings->orderBy === 'asc' ? CommentSort::Oldest : CommentSort::Newest,
            $limit,
            $isModerator
                ? [ModerationState::Approved->value, ModerationState::Pending->value, ModerationState::Spam->value]
                : null,
        )[(int)$comment->id];

        $replyViews = $frontend->buildCommentViewsForComments(
            $preview['replies'],
            (int)($comment->depth ?? 0) + 1,
        );

        $parentViews = $frontend->buildCommentViewsForComments([$comment], (int)($comment->depth ?? 0));
        $commentView = $parentViews[0];
        $commentView->replies = $replyViews;
        $commentView->replyCount = $preview['total'];
        $commentView->hasMoreReplies = $preview['hasMore'];

        $conversation = $frontend->buildConversationView((int)$comment->ownerId, (int)$comment->siteId);
        $context = [
            'contractVersion' => $frontend::CONTRACT_VERSION,
            'instance' => $conversation?->instanceId ?? ('comments-' . $comment->ownerId),
            'conversation' => $conversation,
            'viewer' => $conversation ? $frontend->buildViewerView($conversation) : null,
            'editor' => $conversation ? $frontend->buildEditorView($conversation) : null,
            'theme' => $conversation?->theme ?? 'light',
            'comment' => $commentView,
        ];

        return $this->asJson($frontend->buildEnvelope('ok', [
            $frontend->renderFragment('replies', 'replace', (string)$comment->uid, '_comment/replies', $context),
        ]));
    }

    public function actionViewerControls(): Response
    {
        $this->response->headers->set('Cache-Control', 'private, no-store');

        $ownerId = (int)$this->request->getRequiredParam('ownerId');
        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $frontend = Comments::$plugin->getFrontend();
        // Hydrate private viewer state (actions, subscribe, editor) onto shared-cache shells.
        $conversation = $frontend->buildConversationView($ownerId, $siteId, $this->_resolveSortParam(), $this->_resolveRootLimit(), null, false);

        if (!$conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $viewer = $frontend->buildViewerView($conversation);
        $editor = $frontend->buildEditorView($conversation);

        return $this->asJson($frontend->buildEnvelope(
            'ok',
            $frontend->viewerControlFragments($conversation, $viewer, $editor),
        ));
    }

    public function actionEditor(): Response
    {
        $this->response->headers->set('Cache-Control', 'private, no-store');

        $mode = (string)$this->request->getRequiredParam('mode');

        if (!in_array($mode, ['create', 'reply', 'edit'], true)) {
            return $this->_apiError(Craft::t('comments', 'Invalid editor mode.'));
        }

        $ownerId = (int)$this->request->getRequiredParam('ownerId');
        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $frontend = Comments::$plugin->getFrontend();
        $conversation = $frontend->buildConversationView($ownerId, $siteId);

        if (!$conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $targetComment = null;
        $resourceUid = $conversation->conversationUid;

        if ($mode !== 'create') {
            $commentUid = (string)$this->request->getRequiredParam('commentUid');
            $targetComment = Comment::find()->uid($commentUid)->status(null)->one();

            if (!$targetComment || (int)$targetComment->ownerId !== $ownerId) {
                throw new NotFoundHttpException('Comment not found.');
            }

            // The edit form holds the comment's full text, whatever state it's in.
            if ($mode === 'edit' && !$targetComment->canEdit()) {
                throw new NotFoundHttpException('Comment not found.');
            }

            $resourceUid = (string)$targetComment->uid;
        }

        $viewer = $frontend->buildViewerView($conversation);
        $editor = $frontend->buildEditorView($conversation, $mode, $targetComment);
        $context = [
            'contractVersion' => $frontend::CONTRACT_VERSION,
            'instance' => $conversation->instanceId,
            'conversation' => $conversation,
            'viewer' => $viewer,
            'editor' => $editor,
            'theme' => $conversation->theme,
            'form' => $mode,
        ];

        return $this->asJson($frontend->buildEnvelope('ok', [
            $frontend->renderFragment('editor', 'replace', $resourceUid, '_editor/form', $context),
        ], [
            'resource' => $resourceUid,
        ]));
    }

    public function actionProtection(): Response
    {
        $this->response->getHeaders()->set('Cache-Control', 'private, no-store');
        $this->_allowGraphqlOrigins();

        // A browser asking whether it may send a cross-origin request gets only the headers above.
        if ($this->request->getIsOptions()) {
            $this->response->format = Response::FORMAT_RAW;
            $this->response->data = '';

            return $this->response;
        }

        $ownerId = (int)$this->request->getRequiredParam('ownerId');
        $siteId = (int)$this->request->getParam('siteId', Craft::$app->getSites()->getCurrentSite()->id);
        $commentId = (int)$this->request->getParam('commentId', 0);
        $headless = $this->request->getParam('channel', 'headless') === 'headless';

        return $this->asJson(Comments::$plugin->getProtection()->prepare($ownerId, $siteId, $commentId ?: null, $headless));
    }

    public function actionCreate(): Response
    {
        $this->requirePostRequest();

        $frontend = Comments::$plugin->getFrontend();
        $operationId = (string)$this->request->getBodyParam('commentsOperationId', '');
        $commentId = (int)$this->request->getBodyParam('commentId', 0);

        try {
            if ($commentId) {
                $comment = Comments::$plugin->getComments()->updateFromPost($commentId);
                $message = $comment->moderationState === ModerationState::Approved->value
                    ? Craft::t('comments', 'Comment updated.')
                    : Craft::t('comments', 'Comment updated, and waiting to be approved again.');
                $mutation = 'updated';
            } else {
                $result = Comments::$plugin->getComments()->createFromPost();
                $comment = $result->comment;
                $message = $result->moderationState === ModerationState::Approved
                    ? Craft::t('comments', 'Comment posted.')
                    : Craft::t('comments', 'Comment submitted for moderation.');
                $mutation = 'created';
            }
        } catch (UserException $e) {
            if ($e instanceof \verbb\comments\exceptions\ProtectionException) {
                $this->response->setStatusCode($e->httpStatus);

                if ($e->retryAfter !== null) {
                    $this->response->getHeaders()->set('Retry-After', (string)$e->retryAfter);
                }
            }

            return $this->asJson($frontend->buildEnvelope('error', [], [
                'errors' => [[
                    'category' => 'validation',
                    'code' => 'validation_failed',
                    'message' => $e->getMessage(),
                    'fieldErrors' => $e instanceof CommentValidationException ? $e->fieldErrors : [],
                    'recoverable' => true,
                ]],
            ]));
        }

        return $this->asJson($this->_saveResponseEnvelope($frontend, $comment, $operationId, $message, $mutation));
    }

    public function actionRemove(): Response
    {
        $this->requirePostRequest();

        $frontend = Comments::$plugin->getFrontend();
        $commentId = (int)$this->request->getRequiredBodyParam('commentId');

        try {
            $comment = Comments::$plugin->getComments()->authorRemove($commentId);
        } catch (UserException $e) {
            return $this->_apiError($e->getMessage());
        }

        $envelope = $frontend->buildEnvelope('ok', [[
            'type' => 'comment',
            'mode' => 'remove',
            'resourceUid' => (string)$comment->uid,
            'html' => '',
        ]], [
            'announcement' => Craft::t('comments', 'Comment deleted.'),
            'resource' => (string)$comment->uid,
        ]);

        return $this->_formResponse($envelope) ?? $this->asJson($envelope);
    }

    public function actionInteraction(): Response
    {
        $this->requirePostRequest();

        $commentId = (int)$this->request->getRequiredBodyParam('commentId');
        $mode = Comments::$plugin->getSettings()->resolveInteractionMode();

        if (!$mode->isEnabled()) {
            return $this->_apiError(Craft::t('comments', 'Interactions are disabled.'));
        }

        // `type` is canonical; `upvote` is the v3 boolean kept working for legacy posts.
        $typeParam = $this->request->getBodyParam('type');
        $type = is_string($typeParam) ? InteractionType::tryFrom($typeParam) : null;
        $type ??= (bool)$this->request->getBodyParam('upvote', true)
            ? InteractionType::Upvote
            : InteractionType::Downvote;

        if (!$mode->allows($type)) {
            return $this->_apiError(Craft::t('comments', 'That interaction is not enabled.'));
        }

        try {
            $comment = Comments::$plugin->getComments()->requireEngagementTarget($commentId, Protection::ENGAGEMENT_INTERACTION);
            $identity = Comments::$plugin->getIdentities()->resolveForCurrentRequest();
            Comments::$plugin->getInteractions()->toggle($commentId, (int)$identity->id, $type);
        } catch (UserException $e) {
            return $this->_apiError($e);
        }

        return $this->asJson(Comments::$plugin->getFrontend()->buildEnvelope(
            'ok',
            // Replace the row so the viewer sees updated counts and pressed state.
            $this->_commentFragments($comment),
            ['announcement' => Craft::t('comments', 'Interaction saved.')],
        ));
    }

    public function actionModerate(): Response
    {
        $this->requirePostRequest();

        $commentId = (int)$this->request->getRequiredBodyParam('commentId');
        $action = (string)$this->request->getRequiredBodyParam('moderationAction');
        $reason = $this->request->getBodyParam('reason');

        $comment = Comment::find()->id($commentId)->status(null)->one();

        if (!$comment) {
            throw new NotFoundHttpException('Comment not found.');
        }

        $moderation = Comments::$plugin->getModeration();
        $actor = Craft::$app->getUser()->getIdentity();
        $decision = str_starts_with($action, 'moderate-') ? substr($action, 9) : $action;

        if (!$moderation->canModerateComment($actor, $comment)) {
            return $this->_apiError(Craft::t('comments', 'You are not permitted to moderate this comment.'));
        }

        // The same decisions as the control panel, plus removing or marking spam an approved comment
        // nobody has reported, which the site's menu offers.
        $allowed = $moderation->availableDecisions($comment, $actor);

        if ($moderation->currentState($comment) === ModerationState::Approved) {
            $allowed = array_unique([...$allowed, Moderation::DECISION_SPAM, ...($moderation->canRemoveComment($actor) ? [Moderation::DECISION_REMOVE] : [])]);
        }

        if (!in_array($decision, $allowed, true)) {
            return $this->_apiError($decision === Moderation::DECISION_REMOVE
                ? Craft::t('comments', 'You are not permitted to remove this comment.')
                : Craft::t('comments', 'Unknown moderation action.'));
        }

        $reason = is_string($reason) && $reason !== '' ? $reason : null;

        try {
            $comment = $moderation->decide($comment, $decision, $actor, $reason);
        } catch (UserException $e) {
            return $this->_apiError($e->getMessage());
        }

        $envelope = Comments::$plugin->getFrontend()->buildEnvelope(
            'ok',
            $this->_commentFragments($comment),
            [
                'announcement' => $moderation->decisionMessage($decision),
                'resource' => (string)$comment->uid,
            ],
        );

        return $this->_formResponse($envelope) ?? $this->asJson($envelope);
    }

    public function actionReport(): Response
    {
        $this->requirePostRequest();

        if (!Comments::$plugin->getSettings()->allowFlagging) {
            return $this->_apiError(Craft::t('comments', 'Reporting isn’t allowed.'));
        }

        $commentId = (int)$this->request->getRequiredBodyParam('commentId');
        $reason = $this->request->getBodyParam('reason');
        $details = $this->request->getBodyParam('details');

        // Anything else would show as a label nobody configured.
        if (!in_array($reason, array_column(Comments::$plugin->getFrontend()::reportReasons(), 'value'), true)) {
            $reason = null;
        }

        try {
            Comments::$plugin->getComments()->requireEngagementTarget($commentId, Protection::ENGAGEMENT_REPORT);
            $identity = Comments::$plugin->getIdentities()->resolveForCurrentRequest();
            $flagged = Comments::$plugin->getReports()->toggle(
                $commentId,
                (int)$identity->id,
                is_string($reason) && $reason !== '' ? $reason : null,
                is_string($details) && $details !== '' ? $details : null,
            );
        } catch (UserException $e) {
            return $this->_apiError($e);
        }

        $envelope = Comments::$plugin->getFrontend()->buildEnvelope('ok', [], [
            'announcement' => $flagged
                ? Craft::t('comments', 'Thanks for reporting this. A moderator will take a look.')
                : Craft::t('comments', 'Report withdrawn.'),
        ]);

        return $this->_formResponse($envelope) ?? $this->asJson($envelope);
    }

    public function actionSubscription(): Response
    {
        $this->requirePostRequest();

        $settings = Comments::$plugin->getSettings();

        if (!$settings->allowsSubscriptionType(SubscriptionType::Conversation)) {
            return $this->_apiError(Craft::t('comments', 'Discussion subscriptions are not allowed.'));
        }

        $ownerId = (int)$this->request->getRequiredBodyParam('ownerId');
        $siteId = (int)$this->request->getRequiredBodyParam('siteId');
        $desired = (bool)$this->request->getBodyParam('subscribed', true);
        $frontend = Comments::$plugin->getFrontend();

        try {
            $outcome = Comments::$plugin->getSubscriptions()->setConversationSubscription(
                $ownerId,
                $siteId,
                $desired,
                $this->request->getBodyParam('email'),
            );
        } catch (UserException $e) {
            return $this->_apiError($e);
        }

        $conversation = $frontend->buildConversationView($ownerId, $siteId, $this->_resolveSortParam());
        $fragments = [];

        // Redrawn rather than toggled in the browser, because a guest may still be waiting to confirm.
        if ($conversation) {
            $viewer = $frontend->buildViewerView($conversation);
            $fragments[] = $frontend->renderFragment('conversationHeader', 'replace', $conversation->conversationUid, '_conversation/header', [
                'contractVersion' => $frontend::CONTRACT_VERSION,
                'instance' => $conversation->instanceId,
                'conversation' => $conversation,
                'viewer' => $viewer,
            ]);
        }

        return $this->asJson($frontend->buildEnvelope('ok', $fragments, [
            'announcement' => match ($outcome) {
                Subscriptions::OUTCOME_SUBSCRIBED => Craft::t('comments', 'You’re following this conversation.'),
                Subscriptions::OUTCOME_CONFIRMING => Craft::t('comments', 'Check your email to confirm your subscription.'),
                default => Craft::t('comments', 'You’re no longer following this conversation.'),
            },
            'state' => ['subscribed' => $outcome === Subscriptions::OUTCOME_SUBSCRIBED],
        ]));
    }

    /**
     * The thread around one comment, for a link to one that isn't on the page. A comment the viewer
     * can't see gets the same empty answer as one that doesn't exist.
     */
    public function actionThread(): Response
    {
        $ownerId = (int)$this->request->getRequiredParam('ownerId');
        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $commentId = (int)$this->request->getRequiredParam('commentId');
        $frontend = Comments::$plugin->getFrontend();
        // Only needed for its context, so it doesn't need to load a page of comments as well.
        $conversation = $frontend->buildConversationView($ownerId, $siteId, null, 1);
        $isModerator = Comments::$plugin->getModeration()->canModerateComment(Craft::$app->getUser()->getIdentity());
        $thread = $conversation ? $frontend->buildThreadView($commentId, $ownerId, $siteId, $isModerator) : null;

        if (!$thread) {
            return $this->asJson($frontend->buildEnvelope('ok'));
        }

        return $this->asJson($frontend->buildEnvelope('ok', [
            $frontend->renderFragment('thread', 'replace', $thread->uid, '_comment/comment', [
                'contractVersion' => $frontend::CONTRACT_VERSION,
                'instance' => $conversation->instanceId,
                'conversation' => $conversation,
                'viewer' => $frontend->buildViewerView($conversation),
                'editor' => $frontend->buildEditorView($conversation),
                'theme' => $conversation->theme,
                'comment' => $thread,
            ]),
        ]));
    }


    // Private Methods
    // =========================================================================

    private function _saveResponseEnvelope(
        \verbb\comments\services\Frontend $frontend,
        Comment $comment,
        string $operationId,
        string $message,
        string $mutation,
    ): array {
        $conversation = $frontend->buildConversationView((int)$comment->ownerId, (int)$comment->siteId, $this->_resolveSortParam());

        if (!$conversation) {
            return $frontend->buildEnvelope('ok', [], [
                'operationId' => $operationId,
                'announcement' => $message,
            ]);
        }

        $viewer = $frontend->buildViewerView($conversation);
        $editor = $frontend->buildEditorView($conversation);
        $commentView = $frontend->buildCommentViewPublic($comment, (int)($comment->depth ?? 0));
        $context = [
            'contractVersion' => $frontend::CONTRACT_VERSION,
            'instance' => $conversation->instanceId,
            'conversation' => $conversation,
            'viewer' => $viewer,
            'editor' => $editor,
            'theme' => $conversation->theme,
            'comment' => $commentView,
        ];

        // Something awaiting moderation isn't public yet, so the visitor gets told rather than shown it.
        $isVisible = !$commentView->placeholder;
        $fragments = [];
        $parentId = (int)($comment->parentId ?? 0);

        if ($mutation === 'updated') {
            $fragments[] = $frontend->renderFragment('comment', 'replace', (string)$comment->uid, '_comment/comment', $context);
            $fragments[] = $frontend->renderFragment('editor', 'replace', (string)$comment->uid, '_editor/empty', $context);
        } elseif ($parentId > 0) {
            $parent = Comment::find()->id($parentId)->status(null)->one();

            if ($parent) {
                $parentView = $frontend->buildCommentViewPublic($parent, max(0, (int)($parent->depth ?? 0)));

                // The preview might not reach a new reply, and whoever wrote it needs to see it.
                if ($isVisible && !in_array($commentView->id, array_column($parentView->replies, 'id'), true)) {
                    $parentView->replies = [$commentView, ...$parentView->replies];
                    $parentView->replyCount = max($parentView->replyCount, count($parentView->replies));
                    $parentView->hasMoreReplies = $parentView->replyCount > count($parentView->replies);
                }

                $parentContext = [...$context, 'comment' => $parentView];
                $fragments[] = $frontend->renderFragment('comment', 'replace', (string)$parent->uid, '_comment/comment', $parentContext);
                $fragments[] = $frontend->renderFragment('editor', 'replace', (string)$parent->uid, '_editor/empty', $parentContext);
            }
        } else {
            if ($isVisible) {
                $fragments[] = $frontend->renderFragment('comment', 'insert', (string)$comment->uid, '_comment/comment', $context);
            }

            $fragments[] = $frontend->renderFragment('editor', 'replace', $conversation->conversationUid, '_editor/form', [...$context, 'form' => 'create']);
        }

        // The count in the heading has probably changed, and a first comment has only just made a conversation.
        $fragments[] = $frontend->renderFragment('conversationHeader', 'replace', $conversation->conversationUid, '_conversation/header', $context);

        return $frontend->buildEnvelope('ok', $fragments, [
            'operationId' => $operationId,
            'announcement' => $message,
            'resource' => $isVisible ? (string)$comment->uid : null,
        ]);
    }

    /**
     * Render a single Comment row for in-place replacement after a state or interaction change.
     */
    private function _commentFragments(Comment $comment): array
    {
        $frontend = Comments::$plugin->getFrontend();
        $conversation = $frontend->buildConversationView((int)$comment->ownerId, (int)$comment->siteId);

        if (!$conversation) {
            return [];
        }

        return [
            $frontend->renderFragment('comment', 'replace', (string)$comment->uid, '_comment/comment', [
                'contractVersion' => $frontend::CONTRACT_VERSION,
                'instance' => $conversation->instanceId,
                'conversation' => $conversation,
                'viewer' => $frontend->buildViewerView($conversation),
                'editor' => $frontend->buildEditorView($conversation),
                'theme' => $conversation->theme,
                'comment' => $frontend->buildCommentViewPublic($comment, (int)($comment->depth ?? 0)),
            ]),
        ];
    }

    /**
     * A form posted without JavaScript lands on this page again, rather than on a page of JSON.
     */
    private function _formResponse(array $envelope): ?Response
    {
        if ($this->request->getAcceptsJson() || !$this->request->getIsPost()) {
            return null;
        }

        $session = Craft::$app->getSession();

        if (($envelope['status'] ?? 'ok') === 'ok') {
            $session->setFlash(Frontend::FLASH_NOTICE, (string)($envelope['announcement'] ?? ''));
        } else {
            $session->setFlash(Frontend::FLASH_ERROR, (string)($envelope['errors'][0]['message'] ?? Craft::t('comments', 'That didn’t work. Please try again.')));
        }

        return $this->redirect($this->request->getReferrer() ?: '/');
    }

    /**
     * Answer cross-origin requests as Craft's GraphQL endpoint does, following `allowedGraphqlOrigins`,
     * and never more widely. A headless front end on another origin can call GraphQL, but its
     * `createComment` and `saveComment` mutations need evidence from here, so without this they'd
     * fail whenever protection is on.
     *
     * This exposes nothing a server-side script couldn't already fetch, since the evidence is only
     * anti-spam preparation and grants no permission. With the default `*`, browsers don't send
     * cookies, so the preparation is a guest's. Credentials are only allowed for an origin the site
     * lists, as GraphQL allows them. With GraphQL turned off or `allowedGraphqlOrigins` set to `false`,
     * nothing is added, and a CORS filter a site has attached to this action keeps its own headers.
     */
    private function _allowGraphqlOrigins(): void
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $allowedOrigins = $generalConfig->allowedGraphqlOrigins;

        if (!$generalConfig->enableGql || $allowedOrigins === false) {
            return;
        }

        $headers = $this->response->getHeaders();
        $headers->setDefault('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Craft-Authorization, X-Craft-Token');

        if (!is_array($allowedOrigins)) {
            $headers->setDefault('Access-Control-Allow-Origin', '*');

            return;
        }

        $headers->add('Vary', 'Origin');

        foreach (StringHelper::split((string)$this->request->getOrigin()) as $origin) {
            if (in_array($origin, $allowedOrigins, true)) {
                $headers->setDefault('Access-Control-Allow-Origin', $origin);
                $headers->setDefault('Access-Control-Allow-Credentials', 'true');

                return;
            }
        }
    }

    private function _apiError(string|UserException $error): Response
    {
        $message = $error instanceof UserException ? $error->getMessage() : $error;

        if ($error instanceof ProtectionException) {
            $this->response->setStatusCode($error->httpStatus);

            if ($error->retryAfter !== null) {
                $this->response->getHeaders()->set('Retry-After', (string)$error->retryAfter);
            }
        }

        $envelope = Comments::$plugin->getFrontend()->buildEnvelope('error', [], [
            'errors' => [[
                'category' => 'policy',
                'code' => 'request_rejected',
                'message' => $message,
                'recoverable' => false,
            ]],
        ]);

        return $this->_formResponse($envelope) ?? $this->asJson($envelope);
    }

    private function _resolveRootLimit(): int
    {
        return max(1, min((int)($this->request->getParam('limit') ?: 20), Conversations::MAX_ROOTS_PER_PAGE));
    }

    private function _resolveSortParam(): ?CommentSort
    {
        $sortParam = $this->request->getParam('sort') ?? $this->request->getParam('commentsSort');

        return match ($sortParam) {
            'oldest' => CommentSort::Oldest,
            'newest' => CommentSort::Newest,
            'top' => CommentSort::Top,
            default => null,
        };
    }
}
