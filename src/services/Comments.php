<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\errors\NotificationDeliveryException;
use verbb\comments\events\EmailEvent;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\fieldlayoutelements\CommentsField as CommentsFieldElement;
use verbb\comments\models\CommentResult;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\ConversationConfig;
use verbb\comments\models\Settings;
use verbb\comments\records\Comment as CommentRecord;
use verbb\comments\records\Identity as IdentityRecord;
use verbb\comments\web\assets\frontend\FrontEndAsset;
use verbb\comments\web\assets\frontend\FrontEndCssAsset;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\events\FieldEvent;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\web\View;

use yii\base\UserException;
use yii\helpers\Markdown;
use yii\validators\EmailValidator;

use DateTime;
use Exception;
use Throwable;

use Twig\Markup;
use verbb\editor\web\assets\editor\EditorAsset;

class Comments extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SEND_REPLY_EMAIL = 'beforeSendReplyEmail';
    public const EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL = 'beforeSendModeratorApprovedEmail';
    public const EVENT_BEFORE_SEND_SUBSCRIBE_EMAIL = 'beforeSendSubscribeEmail';
    public const EVENT_BEFORE_SEND_STAFF_EMAIL = 'beforeSendStaffEmail';

    public const CONFIG_FIELDLAYOUT_KEY = 'comments.comments.fieldLayouts';

    // The system message for each staff notification, when the moderator group is emailed. These keep
    // Comments 3's keys, so a site's customised wording and translations carry over.
    private const STAFF_EMAIL_MESSAGES = [
        Notifications::TYPE_ADMIN => 'comments_admin_notification',
        Notifications::TYPE_MODERATOR => 'comments_moderator_notification',
        Notifications::TYPE_MODERATOR_EDIT => 'comments_moderator_edit_notification',
        Notifications::TYPE_FLAG => 'comments_flag_notification',
    ];


    // Properties
    // =========================================================================

    /** How many emails failed in the notification being sent. */
    private int $_deliveryFailures = 0;

    /** Mention IDs resolved during the latest applyBodyFromRequest() call. */
    private ?array $_pendingMentionIdentityIds = null;

    /** Asset IDs resolved during the latest applyBodyFromRequest() call. */
    private ?array $_pendingAssetIds = null;

    private ?array $_pendingAssetReferences = null;


    // Public Methods
    // =========================================================================

    public function getCommentById(int $id, $siteId = null): ?Comment
    {
        // A comment only exists on the site it was made on, which needn't be the current one.
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getElements()->getElementById($id, Comment::class, $siteId ?? '*');
    }

    public function fetch($criteria = null): CommentQuery
    {
        $query = Comment::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * Canonical Comment create — Conversation/Identity, hierarchy, content, moderation,
     * persistence, subscriptions and notifications.
     *
     * The optional callback lets an HTTP transport apply request metadata and custom-field
     * values before the element is validated and written. Every caller supplies Protect
     * evidence appropriate to its browser or headless plan.
     */
    public function create(CommentSubmission $submission, ?callable $prepareComment = null): CommentResult
    {
        if (!$submission->validate()) {
            throw new UserException(
                Craft::t('comments', 'Could not create comment.') . ' ' . Json::encode($submission->getErrors())
            );
        }

        // A name and address are for guests. A signed-in person's comment is shown with their account,
        // so whatever came with it, from GraphQL or a crafted form, isn't kept.
        if ($submission->userId) {
            $submission->name = null;
            $submission->email = null;
        }

        $normalized = $this->normalizeSubmissionContent($submission);
        $submission->contentFormat = $normalized['contentFormat'];
        $submission->content = $normalized['bodyContent'];

        if ($submission->content === '' && $normalized['plainText'] === '') {
            throw CommentValidationException::fromErrors(['comment' => [Craft::t('comments', 'Comment content cannot be empty.')]]);
        }

        $plainText = $normalized['plainText'];
        $mentionIdentityIds = $normalized['mentionIdentityIds'] ?? [];
        $assetIds = $normalized['assetIds'] ?? [];
        $assetReferences = $normalized['assetReferences'] ?? [];

        $conversations = CommentsPlugin::$plugin->getConversations();
        $identities = CommentsPlugin::$plugin->getIdentities();
        $moderation = CommentsPlugin::$plugin->getModeration();
        $settings = CommentsPlugin::$plugin->getSettings();

        if ($submission->conversationId) {
            $existingConversation = $conversations->getById($submission->conversationId);

            if (!$existingConversation) {
                throw new UserException(Craft::t('comments', 'Conversation not found.'));
            }

            $ownerId = (int)$existingConversation->ownerId;
            $siteId = (int)$existingConversation->siteId;
        } elseif ($submission->hasOwnerTarget()) {
            $ownerId = (int)$submission->ownerId;
            $siteId = (int)$submission->siteId;
            $existingConversation = $conversations->findForOwner($ownerId, $siteId);
        } else {
            throw new UserException(Craft::t('comments', 'An owner element is required.'));
        }

        $config = $conversations->getConfigForOwner($ownerId, $siteId);

        if (!$config->isCommentingEnabled()) {
            throw new UserException(Craft::t('comments', 'Comments are closed for this element.'));
        }

        // Auto-closing depends on the element rather than its stored settings.
        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if ($owner && $this->checkExpired($owner)) {
            throw new UserException(Craft::t('comments', 'Comments are closed for this element.'));
        }

        if (!$submission->userId && !$config->allowGuests) {
            throw new UserException(Craft::t('comments', 'Must be logged in to comment.'));
        }

        if (!$submission->userId && $settings->guestRequireEmailName) {
            if (!trim((string)$submission->name)) {
                throw CommentValidationException::fromErrors(['name' => [Craft::t('comments', 'Name is required.')]]);
            }

            if (!trim((string)$submission->email)) {
                throw CommentValidationException::fromErrors(['email' => [Craft::t('comments', 'Email is required.')]]);
            }
        }

        if ($submission->userId && $settings->maxUserComments) {
            $commentCount = Comment::find()
                ->ownerId($ownerId)
                ->ownerSiteId($siteId)
                ->userId($submission->userId)
                ->status([Comment::STATUS_APPROVED, Comment::STATUS_PENDING])
                ->count();

            if ($commentCount >= (int)$settings->maxUserComments) {
                throw new UserException(Craft::t('comments', 'Comment limit reached for this element.'));
            }
        }

        // Checked again when saving, but a known blocked author shouldn't reach a spam provider first.
        $knownIdentity = $submission->userId ? $identities->findForUser($submission->userId) : null;

        if ($knownIdentity && $moderation->isBlocked((int)$knownIdentity->id, $existingConversation ? (int)$existingConversation->id : null)) {
            throw new UserException(Craft::t('comments', 'You are not permitted to comment in this discussion.'));
        }

        $moderationState = $this->_resolveInitialModerationState($config);
        $commentDate = new DateTime();

        $comment = new Comment([
            'ownerId' => $ownerId,
            'ownerSiteId' => $siteId,
            'siteId' => $siteId,
            'userId' => $submission->userId,
            'name' => $submission->name,
            'email' => $submission->email,
            // Page URL the comment was made on — Comment metadata, not Identity.url.
            'url' => $submission->url,
            'comment' => $plainText,
            'status' => $this->_legacyStatus($moderationState),
            'commentDate' => $commentDate,
            'moderationState' => $moderationState->value,
            'heldReason' => $moderationState === ModerationState::Pending ? HeldReason::PreModeration->value : null,
            'contentFormat' => $submission->contentFormat->value,
            'bodyContent' => $submission->content,
            'skipAfterSaveSideEffects' => true,
        ]);
        $comment->setScenario($prepareComment ? Comment::SCENARIO_FRONT_END : Comment::SCENARIO_LIVE);
        $comment->setAction(Comment::ACTION_SAVE);

        if ($prepareComment) {
            $prepareComment($comment);
        }

        if (!$comment->validate()) {
            throw CommentValidationException::fromErrors($comment->getErrors());
        }

        // Spam providers are outside the transaction, so a slow response doesn't hold database locks.
        // Nothing is written until this has passed.
        CommentsPlugin::$plugin->getProtection()->check($comment, $submission->protectionEvidence, $submission->protectionRequest, $submission->headless);

        if (!$submission->userId && $submission->email && !(new EmailValidator())->validate($submission->email)) {
            throw CommentValidationException::fromErrors(['email' => [Craft::t('comments', 'Enter a valid email address.')]]);
        }

        // Behind Protect so registered emails can't be probed without hitting rate limits.
        if (!$submission->userId && $submission->email && User::find()->email(Db::escapeParam($submission->email))->status(null)->exists()) {
            throw new UserException(Craft::t('comments', 'Unauthorized.'));
        }

        try {
            $result = Craft::$app->getDb()->transaction(function() use ($submission, $comment, $commentDate, $existingConversation, $ownerId, $siteId, $mentionIdentityIds, $settings, $conversations, $moderation) {
                $conversation = $existingConversation ?? $conversations->getOrCreateForOwner($ownerId, $siteId);
                $identity = $this->_resolveSubmissionIdentity($submission);

                if ($moderation->isBlocked((int)$identity->id, (int)$conversation->id)) {
                    throw new UserException(Craft::t('comments', 'You are not permitted to comment in this discussion.'));
                }

                [$parentId, $rootId, $depth] = $this->_resolveHierarchy(
                    (int)$conversation->id,
                    $submission->parentId,
                );

                if (is_numeric($settings->maxReplyDepth) && $depth > (int)$settings->maxReplyDepth) {
                    throw new UserException(Craft::t('comments', 'The maximum reply depth has been reached.'));
                }

                $this->_applyReviewSetting($comment, (int)$identity->id, (int)$conversation->id);

                $comment->conversationId = (int)$conversation->id;
                $comment->identityId = (int)$identity->id;
                $comment->parentId = $parentId;
                $comment->rootId = $rootId;
                $comment->depth = $depth;

                if (!Craft::$app->getElements()->saveElement($comment, false, false, false)) {
                    throw new UserException(
                        Craft::t('comments', 'Could not save comment.') . ' ' . Json::encode($comment->getErrors())
                    );
                }

                // The bypass is only for the canonical create transaction. Do not let the returned
                // element silently suppress side effects if it is edited again in the same request.
                $comment->skipAfterSaveSideEffects = false;

                if ($mentionIdentityIds !== []) {
                    CommentsPlugin::$plugin->getMentions()->syncIdentityIds((int)$comment->id, $mentionIdentityIds);
                }

                // Roots reference themselves; set after we know the element id.
                if ($rootId === null) {
                    CommentRecord::updateAll(
                        ['rootId' => $comment->id],
                        ['id' => $comment->id],
                    );
                    $comment->rootId = (int)$comment->id;
                }

                $moderationState = ModerationState::from((string)$comment->moderationState);

                if ($moderationState === ModerationState::Approved) {
                    $conversations->recordApprovedCommentActivity((int)$conversation->id, $commentDate);
                }

                return new CommentResult($comment, $moderationState);
            });

        } catch (Throwable $e) {
            // Records created inside a rejected attempt have been rolled back. Discard
            // request caches too so a fresh attempt never reuses non-existent IDs.
            CommentsPlugin::$plugin->getConversations()->clearCaches();
            CommentsPlugin::$plugin->getIdentities()->clearCaches();

            throw $e;
        }

        // File moves can be remote too, so they follow the commit. The upload stays temporary if the
        // comment failed to save, and the comment is undone if the move fails, so a retry is safe either way.
        if ($assetReferences !== []) {
            try {
                $assetIds = CommentsPlugin::$plugin->getCommentAssets()->finalizeReferences(
                    $assetReferences,
                    'comments.reply',
                    (int)$submission->userId,
                );
                CommentsPlugin::$plugin->getCommentAssets()->syncAssetIds((int)$result->comment->id, $assetIds);
            } catch (Throwable $e) {
                Craft::$app->getElements()->deleteElement($result->comment, true);

                throw $e;
            }
        }

        // Skipped while saving above, so the control panel can find this comment.
        Craft::$app->getSearch()->indexElementAttributes($result->comment);

        CommentsPlugin::$plugin->getSubscriptions()->applyDefaultForComment($result->comment);

        if ($submission->notifyReplies && !$submission->userId) {
            $identity = $identities->getById((int)$result->comment->identityId);

            if ($identity && !$identity->verifiedAt) {
                CommentsPlugin::$plugin->getSubscriptions()->requestConfirmation($identity, null, $siteId);
            }
        }

        $moderation->clearQueueCounts();

        // Notifications after commit — never inside the write transaction (no Outbox).
        CommentsPlugin::$plugin->getNotifications()->queueForNewComment(
            $result->comment,
            $result->moderationState,
        );

        if ($result->moderationState === ModerationState::Approved && $mentionIdentityIds !== []) {
            CommentsPlugin::$plugin->getNotifications()->queueMentionNotifications(
                $result->comment,
                CommentsPlugin::$plugin->getMentions()->newlyAddedIdentityIds(
                    [],
                    $mentionIdentityIds,
                    $result->comment->identityId ? (int)$result->comment->identityId : null,
                ),
            );
        }

        return $result;
    }

    /**
     * Create and validate a comment from the current public form request.
     */
    public function createFromPost(): CommentResult
    {
        return $this->create(
            $this->buildSubmissionFromPost(),
            static function(Comment $comment): void {
                $request = Craft::$app->getRequest();
                $comment->ipAddress = \verbb\protect\helpers\Ip::clientIp($request);
                $comment->userAgent = $request->getUserAgent();
                $comment->setPublicFieldValuesFromRequest();
            },
        );
    }

    public function normalizeSubmissionContent(CommentSubmission $submission): array
    {
        if ($submission->contentFormat === ContentFormat::Editor) {
            if (!CommentsPlugin::$plugin->getSettings()->isRichAuthoringEnabled()) {
                throw new UserException(Craft::t('comments', 'Rich comment authoring is not enabled.'));
            }

            return $this->normalizeEditorPayload(
                $submission->content,
                'comments.reply',
                $submission->userId,
                $submission->ownerId,
                $submission->siteId,
            );
        }

        $plain = $this->_normalizePlainContent($submission->content);

        return [
            'contentFormat' => ContentFormat::Plain,
            'bodyContent' => $plain,
            'plainText' => $plain,
            'mentionIdentityIds' => [],
            'assetIds' => [],
            'assetReferences' => [],
        ];
    }

    public function normalizeEditorPayload(
        string $payload,
        string $contextHandle,
        ?int $actorUserId = null,
        ?int $ownerId = null,
        ?int $siteId = null,
    ): array {
        try {
            $decoded = Json::decode($payload);
        } catch (\Throwable) {
            throw new UserException(Craft::t('comments', 'Invalid rich comment content.'));
        }

        if (!is_array($decoded)) {
            throw new UserException(Craft::t('comments', 'Invalid rich comment content.'));
        }

        $documents = \verbb\editor\Editor::getInstance()->getDocuments();
        // Keep Editor feature policy aligned with current Comments settings (tests + CP toggles).
        \verbb\comments\helpers\EditorContexts::refresh();
        $config = \verbb\editor\Editor::getInstance()->getEditorConfigs()->requireConfigByHandle($contextHandle);
        $richText = $documents->normalize($decoded, $config);

        // A comment can be only an image, so there may be no plain text.
        if ($richText->isEmpty()) {
            throw CommentValidationException::fromErrors(['comment' => [Craft::t('comments', 'Comment content cannot be empty.')]]);
        }

        $plainText = trim($richText->toPlainText());
        $mentionIdentityIds = [];

        if ($ownerId && $siteId) {
            $mentionIdentityIds = CommentsPlugin::$plugin->getMentions()->resolveIdentityIdsFromRichText(
                $richText,
                $actorUserId,
                (int)$ownerId,
                (int)$siteId,
            );
        }

        $assetReferences = CommentsPlugin::$plugin->getCommentAssets()->resolveAssetReferencesFromRichText($richText);
        $assetIds = array_map(static fn(array $reference): int => (int)$reference['id'], $assetReferences);

        return [
            'contentFormat' => ContentFormat::Editor,
            'bodyContent' => Json::encode($richText->getValue()),
            'plainText' => $plainText,
            'mentionIdentityIds' => $mentionIdentityIds,
            'assetIds' => $assetIds,
            'assetReferences' => $assetReferences,
        ];
    }

    /**
     * Apply canonical body content from the current front-end POST request to a Comment element.
     */
    public function applyBodyFromRequest(Comment $comment): void
    {
        $this->_pendingMentionIdentityIds = null;
        $this->_pendingAssetIds = null;
        $this->_pendingAssetReferences = null;

        $request = Craft::$app->getRequest();
        $settings = CommentsPlugin::$plugin->getSettings();
        $actorUserId = CommentsPlugin::$plugin->getService()->getUser()?->id;
        $ownerId = (int)($comment->ownerId ?: $request->getParam('ownerId') ?: $request->getParam('elementId') ?: 0);
        $siteId = (int)($comment->siteId ?: $request->getParam('siteId', Craft::$app->getSites()->getCurrentSite()->id));

        // Rich edits must preserve format; never silently flatten to plain.
        if ($comment->id && $comment->contentFormat === ContentFormat::Editor->value) {
            $formatParam = ContentFormat::tryFrom((string)$request->getParam('contentFormat', ''));

            if ($formatParam === ContentFormat::Plain) {
                throw new UserException(Craft::t('comments', 'Rich comments cannot be converted to plain text.'));
            }

            $editorContent = $request->getBodyParam('fields.editorContent');

            if ($editorContent === null) {
                return;
            }

            $normalized = $this->normalizeEditorPayload(
                (string)$editorContent,
                'comments.edit',
                $actorUserId ? (int)$actorUserId : null,
                $ownerId,
                $siteId,
            );
            $comment->bodyContent = $normalized['bodyContent'];
            $comment->comment = $normalized['plainText'];
            $comment->contentFormat = ContentFormat::Editor->value;
            $this->_pendingMentionIdentityIds = $normalized['mentionIdentityIds'];
            $this->_pendingAssetIds = $normalized['assetIds'];
            $this->_pendingAssetReferences = $normalized['assetReferences'];

            return;
        }

        $formatParam = (string)$request->getParam('contentFormat', ContentFormat::Plain->value);
        $contentFormat = ContentFormat::tryFrom($formatParam) ?? ContentFormat::Plain;

        if ($contentFormat === ContentFormat::Editor && $settings->isRichAuthoringEnabled()) {
            $editorContent = (string)$request->getParam('fields.editorContent', '');

            if ($editorContent !== '') {
                $normalized = $this->normalizeEditorPayload(
                    $editorContent,
                    'comments.edit',
                    $actorUserId ? (int)$actorUserId : null,
                    $ownerId,
                    $siteId,
                );
                $comment->bodyContent = $normalized['bodyContent'];
                $comment->comment = $normalized['plainText'];
                $comment->contentFormat = ContentFormat::Editor->value;
                $this->_pendingMentionIdentityIds = $normalized['mentionIdentityIds'];
                $this->_pendingAssetIds = $normalized['assetIds'];
                $this->_pendingAssetReferences = $normalized['assetReferences'];

                return;
            }
        }

        $plain = $this->_normalizePlainContent((string)$request->getParam('fields.comment', $comment->comment ?? ''));
        $comment->comment = $plain;
        $comment->contentFormat = ContentFormat::Plain->value;
        $comment->bodyContent = $plain;
    }

    /** Null when the body was not updated from the request. */
    public function takePendingMentionIdentityIds(): ?array
    {
        $ids = $this->_pendingMentionIdentityIds;
        $this->_pendingMentionIdentityIds = null;

        return $ids;
    }

    /** Null when the body was not updated from the request. */
    public function takePendingAssetIds(): ?array
    {
        $ids = $this->_pendingAssetIds;
        $this->_pendingAssetIds = null;

        return $ids;
    }

    public function takePendingAssetReferences(): ?array
    {
        $references = $this->_pendingAssetReferences;
        $this->_pendingAssetReferences = null;

        return $references;
    }

    /**
     * Persist mention rows after a comment body save and queue mention notifications when published.
     */
    public function finalizeCommentMentions(Comment $comment, array $previousIdentityIds, array $nextIdentityIds): void
    {
        $synced = CommentsPlugin::$plugin->getMentions()->syncIdentityIds((int)$comment->id, $nextIdentityIds);

        if ($comment->status !== Comment::STATUS_APPROVED) {
            return;
        }

        $delta = CommentsPlugin::$plugin->getMentions()->newlyAddedIdentityIds(
            $previousIdentityIds,
            $synced,
            $comment->identityId ? (int)$comment->identityId : null,
        );

        if ($delta !== []) {
            CommentsPlugin::$plugin->getNotifications()->queueMentionNotifications($comment, $delta);
        }
    }

    /**
     * Apply the moderation consequence of an author edit. A held comment stays held until a
     * moderator decides, and participation restrictions apply to edits as they do to creates.
     */
    public function applyEditModerationState(Comment $comment): ModerationState
    {
        $moderation = CommentsPlugin::$plugin->getModeration();
        $identityId = (int)$comment->identityId;
        $conversationId = $comment->conversationId ? (int)$comment->conversationId : null;

        if ($identityId && $moderation->isBlocked($identityId, $conversationId)) {
            throw new UserException(Craft::t('comments', 'You are not permitted to comment in this discussion.'));
        }

        $config = CommentsPlugin::$plugin->getConversations()->getConfigForOwner(
            (int)$comment->ownerId,
            (int)$comment->ownerSiteId,
        );
        $previousState = $moderation->currentState($comment);
        $state = $this->_resolveInitialModerationState($config);

        if ($state === ModerationState::Pending && $identityId && $moderation->isTrusted($identityId, $conversationId)) {
            $state = ModerationState::Approved;
        }

        if (
            $previousState === ModerationState::Pending
            || ($identityId && $moderation->isModerated($identityId, $conversationId))
        ) {
            $state = ModerationState::Pending;
        }

        $comment->moderationState = $state->value;
        $comment->status = $state === ModerationState::Pending
            ? Comment::STATUS_PENDING
            : Comment::STATUS_APPROVED;

        if ($state === ModerationState::Approved) {
            $comment->heldReason = null;
        } elseif ($previousState !== ModerationState::Pending) {
            // A comment already waiting keeps the reason it was first held for.
            $comment->heldReason = HeldReason::Edited->value;
        }

        return $state;
    }

    /**
     * Build a CommentSubmission from the current front-end POST request.
     */
    public function buildSubmissionFromPost(): CommentSubmission
    {
        $request = Craft::$app->getRequest();
        $currentUser = CommentsPlugin::$plugin->getService()->getUser();
        $settings = CommentsPlugin::$plugin->getSettings();

        $ownerSiteId = (int)$request->getParam('ownerSiteId', Craft::$app->getSites()->getCurrentSite()->id);
        $ownerId = $request->getParam('ownerId') ?? $request->getParam('elementId');
        $siteId = (int)$request->getParam('siteId', $ownerSiteId);
        $parentId = $request->getParam('parentId');

        $formatParam = (string)$request->getParam('contentFormat', ContentFormat::Plain->value);
        $contentFormat = ContentFormat::tryFrom($formatParam) ?? ContentFormat::Plain;

        $content = (string)$request->getParam('fields.editorContent', '');

        // Without JavaScript there's no editor to fill this in, only the plain box beside it.
        if ($contentFormat !== ContentFormat::Editor || !$settings->isRichAuthoringEnabled() || $content === '') {
            $contentFormat = ContentFormat::Plain;
            $content = (string)$request->getParam('fields.comment', '');
        }

        return new CommentSubmission([
            'protectionEvidence' => CommentsPlugin::$plugin->getProtection()->evidence($request->getBodyParam('__protect', [])),
            'protectionRequest' => CommentsPlugin::$plugin->getProtection()->requestSignals(),
            'headless' => false,
            'ownerId' => $ownerId ? (int)$ownerId : null,
            'siteId' => $siteId ?: $ownerSiteId,
            'parentId' => $parentId ? (int)$parentId : null,
            'content' => $content,
            'contentFormat' => $contentFormat,
            'name' => $request->getParam('fields.name'),
            'email' => $request->getParam('fields.email'),
            'notifyReplies' => (bool)$request->getParam('fields.notifyReplies'),
            'url' => $request->getParam('url', $request->referrer),
            'userId' => $currentUser ? (int)$currentUser->id : null,
        ]);
    }

    /**
     * Apply a front-end POST to an existing comment (edit flow).
     */
    public function updateFromPost(int $commentId): Comment
    {
        $siteId = (int)Craft::$app->getRequest()->getParam('siteId', Craft::$app->getSites()->getCurrentSite()->id);
        $comment = $this->getCommentById($commentId, $siteId);

        if (!$comment) {
            throw new UserException(Craft::t('comments', 'No comment with the ID “{id}”', ['id' => $commentId]));
        }

        if (!$comment->canEdit()) {
            throw new UserException(Craft::t('comments', 'You do not have permission to edit this comment.'));
        }

        $previousMentionIds = CommentsPlugin::$plugin->getMentions()->getIdentityIdsForComment($commentId);
        $comment->setScenario(Comment::SCENARIO_FRONT_END);
        $comment->setAction(Comment::ACTION_SAVE);

        $this->applyBodyFromRequest($comment);
        $this->applyEditModerationState($comment);

        $comment->setPublicFieldValuesFromRequest();

        $this->savePublicUpdate($comment, CommentsPlugin::$plugin->getProtection()->evidence(Craft::$app->getRequest()->getBodyParam('__protect', [])), false);

        $pendingMentionIds = $this->takePendingMentionIdentityIds();

        if ($pendingMentionIds !== null) {
            $this->finalizeCommentMentions($comment, $previousMentionIds, $pendingMentionIds);
        }

        $this->takePendingAssetIds();
        $pendingAssetReferences = $this->takePendingAssetReferences();

        if ($pendingAssetReferences !== null) {
            CommentsPlugin::$plugin->getCommentAssets()->syncEditedReferences(
                (int)$comment->id,
                $pendingAssetReferences,
                (int)(CommentsPlugin::$plugin->getService()->getUser()?->id ?? 0),
            );
        }

        return $comment;
    }

    public function savePublicUpdate(Comment $comment, ?\verbb\protect\models\ProtectionEvidence $evidence, bool $headless = true): Comment
    {
        if (!$comment->canEdit()) {
            throw new UserException(Craft::t('comments', 'You do not have permission to edit this comment.'));
        }

        if (!$comment->validate()) {
            throw CommentValidationException::fromErrors($comment->getErrors());
        }

        CommentsPlugin::$plugin->getProtection()->check($comment, $evidence, headless: $headless);

        // Compare against the stored row: the element already carries the edit's outcome.
        $storedState = ModerationState::tryFrom((string)CommentRecord::find()
            ->select(['moderationState'])
            ->where(['id' => $comment->id])
            ->scalar());

        if (!Craft::$app->getElements()->saveElement($comment, false, false)) {
            throw new UserException(Craft::t('comments', 'Could not save comment.'));
        }

        $moderation = CommentsPlugin::$plugin->getModeration();

        if ($storedState && $storedState !== $moderation->currentState($comment)) {
            $moderation->recordAuthorEdit($comment, $storedState);
        }

        return $comment;
    }

    /**
     * Resolve the comment a visitor is voting on or flagging, enforcing the rules every
     * transport shares: only published comments take engagement, within the abuse limits.
     */
    public function requireEngagementTarget(int $commentId, string $operation, ?int $siteId = null): Comment
    {
        $comment = Comment::find()->id($commentId)->siteId('*')->status(null)->one();

        // A comment lives on one site. Compared here, because Craft leaves the site out of a query
        // while it knows of only one site.
        if (!$comment || ($siteId !== null && (int)$comment->siteId !== $siteId)) {
            throw new UserException(Craft::t('comments', 'No comment with the ID “{id}”', ['id' => $commentId]));
        }

        $moderation = CommentsPlugin::$plugin->getModeration();

        if ($comment->authorRemovedAt || $moderation->currentState($comment) !== ModerationState::Approved) {
            throw new UserException(Craft::t('comments', 'This comment is not available.'));
        }

        CommentsPlugin::$plugin->getProtection()->limitEngagement($operation);

        return $comment;
    }

    /**
     * Members of the configured moderator group. A deleted group yields nobody, never everyone.
     */
    public function getModeratorRecipients(): array
    {
        $groupUid = CommentsPlugin::$plugin->getSettings()->moderatorUserGroup;
        $groupId = $groupUid ? Db::idByUid(Table::USERGROUPS, $groupUid) : null;

        if (!$groupId) {
            CommentsPlugin::info('Cannot send moderator notification: Moderator group not found.');

            return [];
        }

        return User::find()->groupId($groupId)->all();
    }

    /**
     * Author self-removal — hides the comment without moderator deletion.
     *
     * The actor defaults to the session's user. GraphQL passes the user its token signed in, who may
     * have no session at all.
     */
    public function authorRemove(int $commentId, ?int $siteId = null, ?User $actor = null): Comment
    {
        $siteId ??= (int)Craft::$app->getRequest()->getParam('siteId', Craft::$app->getSites()->getCurrentSite()->id);
        $comment = $this->getCommentById($commentId, $siteId);

        if (!$comment) {
            throw new UserException(Craft::t('comments', 'No comment with the ID “{id}”', ['id' => $commentId]));
        }

        $viewer = $actor ?? Craft::$app->getUser()->getIdentity();

        if (!$viewer || !$comment->canDelete($viewer)) {
            throw new UserException(Craft::t('comments', 'You do not have permission to delete this comment.'));
        }

        $comment->authorRemovedAt = new DateTime();
        $comment->setScenario(Comment::SCENARIO_FRONT_END);

        if (!Craft::$app->getElements()->saveElement($comment, true, false)) {
            throw new UserException(Craft::t('comments', 'Unable to delete comment.'));
        }

        if ($comment->conversationId) {
            CommentsPlugin::$plugin->getConversations()->rebuildAggregates((int)$comment->conversationId);
        }

        return $comment;
    }

    public function render($elementId, $criteria = [], $jsSettings = []): Markup
    {
        $view = Craft::$app->getView();
        $settings = CommentsPlugin::$plugin->getSettings();

        $markup = CommentsPlugin::$plugin->getFrontend()->render((int)$elementId, is_array($criteria) ? $criteria : []);

        if ($settings->outputDefaultCss && $settings->resolveEditorTheme() !== 'none') {
            $view->registerAssetBundle(FrontEndCssAsset::class);
        }

        if ($settings->outputDefaultJs) {
            // The thread's script loads Editor itself, and only when someone starts writing in a rich editor.
            // Plain authoring never needs it. A template override that leaves out where to load it from gets
            // it with the page instead.
            if ($settings->isRichAuthoringEnabled() && !str_contains((string)$markup, 'data-comments-editor-module')) {
                $view->registerAssetBundle(EditorAsset::class);
            }

            $view->registerAssetBundle(FrontEndAsset::class);
        }

        return $markup;
    }

    // Checks is there are sufficient permissions for commenting on this element
    public function checkPermissions($element): bool
    {
        // Get the global permissions settings
        $permissions = CommentsPlugin::$plugin->getSettings()->permissions;

        if (!$element) {
            return false;
        }

        $elementType = get_class($element);

        // Do we even have any settings setup? By default - anything can be commented on
        if ($permissions && isset($permissions[$elementType])) {
            // All are set to enabled
            if ($permissions[$elementType] === '*') {
                return true;
            }

            // None are selected
            if (!is_array($permissions[$elementType])) {
                return false;
            }

            // Check for various elements
            if ($element instanceof Entry) {
                // Section entries use section UID; Matrix/nested/field-owned entries have no section — use type UID
                $uid = $element->getSection()?->uid ?? $element->getType()?->uid;
            } elseif ($element instanceof Asset) {
                $uid = $element->getVolume()->uid;
            } elseif ($element instanceof User) {
                $userGroupUids = array_map(static fn($group) => $group->uid, $element->getGroups());

                return array_intersect($userGroupUids, $permissions[$elementType]) !== [];
            } else {
                $uid = $element->group->uid ?? null;
            }

            if ($uid === null || !in_array($uid, $permissions[$elementType], true)) {
                return false;
            }
        }

        return true;
    }

    public function checkManuallyClosed($element): bool
    {
        if (!$element || !isset($element->id, $element->siteId)) {
            return false;
        }

        $config = CommentsPlugin::$plugin->getConversations()->getConfigForOwner(
            (int)$element->id,
            (int)$element->siteId,
        );

        return $config->closed;
    }

    public function checkExpired($element): bool
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        // Has this element's publish date exceeded the set auto-close limit? Does it even have an auto-close limit?
        // Not every commentable element has a post date, and those can't expire
        $postDate = $element->postDate ?? null;

        if ($settings->autoCloseDays && $postDate) {
            $now = new DateTime('now');
            $interval = $now->diff($postDate);

            if ($interval->days > $settings->autoCloseDays) {
                return true;
            }
        }

        return false;
    }

    public function triggerNotificationEmail($type, $comment, ?int $mentionedIdentityId = null, ?int $actorUserId = null): void
    {
        // A queued notification can run well after the comment changed.
        if (!$this->_isStillRelevant($type, $comment)) {
            CommentsPlugin::info('Skipping {type} notification: comment #{id} has changed state.', ['type' => $type, 'id' => $comment->id]);

            return;
        }

        $this->_deliveryFailures = 0;

        if (in_array($type, ['admin', 'flag', 'moderator', 'moderator-edit'], true)) {
            $this->notifyStaff($type, $comment, $actorUserId);
        } elseif ($type === 'author') {
            $this->notifyElementAuthor($comment);
        } elseif ($type === 'reply') {
            $this->sendReplyNotificationEmail($comment);
        } elseif ($type === 'moderator-approved') {
            $this->sendModeratorApprovedNotificationEmail($comment);
        } elseif ($type === 'subscribe') {
            $this->sendSubscribeNotificationEmail($comment);
        } elseif ($type === 'mention' && $mentionedIdentityId) {
            $this->notifyMention($comment, $mentionedIdentityId);
        }

        if ($this->_deliveryFailures > 0) {
            throw new NotificationDeliveryException("Unable to send {$this->_deliveryFailures} comment notification email(s). See the Comments log for details.");
        }
    }

    /**
     * New comments, comments waiting for review, edits to them and reports go to the moderator group as
     * an event. Staff have accounts and the control panel, so Comments only emails them when Email the
     * Moderator Group is on, and then exactly the people the event named.
     *
     * Whoever caused the notification is left out: the author of a new or edited comment, and the
     * person who made a report.
     */
    public function notifyStaff(string $type, Comment $comment, ?int $actorUserId = null): void
    {
        $element = $comment->getOwner();
        $actorUserId = $type === 'flag' ? $actorUserId : $comment->userId;
        $recipients = array_filter(
            $this->getModeratorRecipients(),
            fn($recipient) => !$actorUserId || !$recipient instanceof User || (int)$recipient->id !== (int)$actorUserId,
        );
        $named = CommentsPlugin::$plugin->getNotifications()->notifyAccountHolders($type, $comment, $element, $recipients);
        $messageKey = self::STAFF_EMAIL_MESSAGES[$type] ?? null;

        if (!$messageKey || !$element || !CommentsPlugin::$plugin->getSettings()->emailModeratorGroup) {
            return;
        }

        foreach ($named as $recipient) {
            $this->_mail($messageKey, self::EVENT_BEFORE_SEND_STAFF_EMAIL, $comment, $element, $recipient);
        }
    }

    /**
     * The author of the element that was commented on, unless a reply or mention already covers them.
     */
    public function notifyElementAuthor(Comment $comment): void
    {
        $element = $comment->getOwner();

        if (!$element) {
            return;
        }

        $recipient = null;

        try {
            $recipient = $element instanceof Asset ? $element->getUploader() : $element->getAuthor();
        } catch (Throwable) {
            // Not every commentable element has an author.
        }

        if (!$recipient instanceof User || ($comment->userId && (int)$comment->userId === (int)$recipient->id)) {
            return;
        }

        if ($this->_receivesReplyEmail($comment, $recipient) || $this->_receivesMentionEmail($comment, $recipient)) {
            return;
        }

        $identity = CommentsPlugin::$plugin->getIdentities()->getOrCreateForUser($recipient);

        if (!CommentsPlugin::$plugin->getIdentities()->acceptsEmail($identity)) {
            return;
        }

        CommentsPlugin::$plugin->getNotifications()->notifyAccountHolders(Notifications::TYPE_AUTHOR, $comment, $element, [$recipient]);
    }

    /**
     * Only people with an account can be mentioned, so this is always an event.
     */
    public function notifyMention(Comment $comment, int $mentionedIdentityId): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        if (!$settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_MENTION)) {
            return;
        }

        $identity = CommentsPlugin::$plugin->getIdentities()->getById($mentionedIdentityId);

        if (!$identity || !$identity->userId || !CommentsPlugin::$plugin->getIdentities()->acceptsEmail($identity)) {
            return;
        }

        if ($comment->identityId && (int)$comment->identityId === $mentionedIdentityId) {
            return;
        }

        $recipient = Craft::$app->getUsers()->getUserById((int)$identity->userId);
        $element = $comment->getOwner();

        if (!$recipient || !$element || $this->_receivesReplyEmail($comment, $recipient)) {
            return;
        }

        CommentsPlugin::$plugin->getNotifications()->notifyAccountHolders(Notifications::TYPE_MENTION, $comment, $element, [$recipient]);
    }

    public function sendReplyNotificationEmail(Comment $comment): void
    {
        $element = $comment->getOwner();
        $parentComment = $comment->getParentComment();

        if (!$element || !$parentComment) {
            CommentsPlugin::info('Cannot send reply notification: no element or parent comment.');

            return;
        }

        $parentIdentity = $parentComment->identityId ? CommentsPlugin::$plugin->getIdentities()->getById((int)$parentComment->identityId) : null;

        if (!CommentsPlugin::$plugin->getIdentities()->acceptsEmail($parentIdentity)) {
            CommentsPlugin::info('Not sending reply notification: the address is unconfirmed or has stopped comment emails.');

            return;
        }

        if ($comment->identityId && (int)$comment->identityId === (int)$parentIdentity->id) {
            return;
        }

        if ($parentIdentity->userId) {
            $recipient = $parentComment->getAuthor();

            if ($recipient instanceof User && (int)$recipient->id !== (int)$comment->userId) {
                CommentsPlugin::$plugin->getNotifications()->notifyAccountHolders(Notifications::TYPE_REPLY, $comment, $element, [$recipient]);
            }

            return;
        }

        // A comment's own address was never confirmed, so a guest's comes from their identity.
        $recipient = $this->_guestRecipient($parentIdentity);

        if (!$recipient || ($comment->email && strcasecmp((string)$comment->email, (string)$recipient->email) === 0)) {
            return;
        }

        $this->_mail('comments_reply_notification', self::EVENT_BEFORE_SEND_REPLY_EMAIL, $comment, $element, $recipient, $this->_stopEmailsUrl($parentIdentity, $comment));
    }

    public function sendModeratorApprovedNotificationEmail(Comment $comment): void
    {
        $element = $comment->getOwner();

        if (!$element) {
            CommentsPlugin::info('Cannot send approval notification: no element.');

            return;
        }

        $authorIdentity = $comment->identityId ? CommentsPlugin::$plugin->getIdentities()->getById((int)$comment->identityId) : null;

        if (!CommentsPlugin::$plugin->getIdentities()->acceptsEmail($authorIdentity)) {
            CommentsPlugin::info('Not sending approval notification: the address is unconfirmed or has stopped comment emails.');

            return;
        }

        if ($authorIdentity->userId) {
            $recipient = $comment->getAuthor();

            if ($recipient instanceof User) {
                CommentsPlugin::$plugin->getNotifications()->notifyAccountHolders(Notifications::TYPE_MODERATOR_APPROVED, $comment, $element, [$recipient]);
            }

            return;
        }

        $recipient = $this->_guestRecipient($authorIdentity);

        if ($recipient) {
            $this->_mail('comments_moderator_approved_notification', self::EVENT_BEFORE_SEND_MODERATOR_APPROVED_EMAIL, $comment, $element, $recipient, $this->_stopEmailsUrl($authorIdentity, $comment));
        }
    }

    public function sendSubscribeNotificationEmail(Comment $comment): void
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $element = $comment->getOwner();

        if (!$element || !$comment->conversationId) {
            CommentsPlugin::info('Cannot send subscription notification: No conversation owner.');

            return;
        }

        $subscriptions = CommentsPlugin::$plugin->getSubscriptions();
        $conversationId = (int)$comment->conversationId;
        $recipientTypes = [];

        if ($settings->allowsSubscriptionType(SubscriptionType::Conversation)) {
            foreach ($subscriptions->getConversationSubscribers($conversationId) as $subscription) {
                $recipientTypes[(int)$subscription->identityId] = 'conversation';
            }
        }

        $commentAncestors = $this->_getCommentAncestors([], $comment);

        if ($settings->allowsSubscriptionType(SubscriptionType::Replies)) {
            foreach ($commentAncestors as $ancestor) {
                foreach ($subscriptions->getReplySubscribers($conversationId, (int)$ancestor->id) as $subscription) {
                    $recipientTypes[(int)$subscription->identityId] = 'replies';
                }

                // Upgraded v3 installs represented the default reply subscription implicitly.
                // Honour that default for historical comments unless the author opted out.
                if ($settings->subscriptionDefault === Settings::SUBSCRIPTION_DEFAULT_REPLIES && $ancestor->identityId) {
                    $existing = $subscriptions->get(
                        $conversationId,
                        (int)$ancestor->identityId,
                        (int)$ancestor->id,
                        SubscriptionType::Replies,
                    ) ?? $subscriptions->get($conversationId, (int)$ancestor->identityId);

                    if (!$existing) {
                        $recipientTypes[(int)$ancestor->identityId] = 'replies';
                    }
                }
            }
        }

        // The direct reply and owner-author notices are more specific than a generic
        // subscription update, so suppress the duplicate.
        if ($settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_REPLY)) {
            $parentIdentityId = $comment->getParentComment()?->identityId;

            if ($parentIdentityId) {
                unset($recipientTypes[(int)$parentIdentityId]);
            }
        }

        if ($settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_OWNER)) {
            try {
                $ownerAuthor = $element->getAuthor();
                $ownerIdentity = $ownerAuthor?->id
                    ? CommentsPlugin::$plugin->getIdentities()->findForUser((int)$ownerAuthor->id)
                    : null;

                if ($ownerIdentity) {
                    unset($recipientTypes[(int)$ownerIdentity->id]);
                }
            } catch (Throwable) {
                // Not every commentable element has an author.
            }
        }

        if ($settings->isParticipantNotificationEnabled(Settings::NOTIFICATION_MENTION)) {
            foreach (CommentsPlugin::$plugin->getMentions()->getIdentityIdsForComment((int)$comment->id) as $mentionedIdentityId) {
                unset($recipientTypes[(int)$mentionedIdentityId]);
            }
        }

        if ($comment->identityId) {
            unset($recipientTypes[(int)$comment->identityId]);
        }

        if ($recipientTypes === []) {
            CommentsPlugin::info('No subscription recipients for this comment.');

            return;
        }

        $identityService = CommentsPlugin::$plugin->getIdentities();
        $identities = $identityService->getByIds(array_keys($recipientTypes));
        $accountHolders = [];
        $sentTo = [];

        foreach ($recipientTypes as $identityId => $subscriptionType) {
            $identity = $identities[$identityId] ?? null;

            if (!$identityService->acceptsEmail($identity)) {
                continue;
            }

            if ($identity->userId) {
                $user = Craft::$app->getUsers()->getUserById((int)$identity->userId);

                if ($user) {
                    $accountHolders[] = $user;
                }

                continue;
            }

            $guest = $this->_guestRecipient($identity);

            // Two guest identities can hold the same address.
            if (!$guest || isset($sentTo[strtolower((string)$guest->email)])) {
                continue;
            }

            $sentTo[strtolower((string)$guest->email)] = true;
            $emailKey = $subscriptionType === 'replies'
                ? 'comments_subscriber_notification_comment'
                : 'comments_subscriber_notification_element';

            $this->_mail(
                $emailKey,
                self::EVENT_BEFORE_SEND_SUBSCRIBE_EMAIL,
                $comment,
                $element,
                $guest,
                $subscriptions->managementUrl(Subscriptions::PURPOSE_CONVERSATION, $identity, (int)$comment->siteId, $conversationId),
                ['emailKey' => $emailKey],
            );
        }

        if ($accountHolders !== []) {
            CommentsPlugin::$plugin->getNotifications()->notifyAccountHolders(Notifications::TYPE_SUBSCRIBE, $comment, $element, $accountHolders);
        }
    }

    /**
     * Rechecked here, because the guest can change their address while this waits in the queue.
     */
    public function sendGuestConfirmationEmail(int $identityId, string $email, ?int $conversationId, int $siteId): void
    {
        $identities = CommentsPlugin::$plugin->getIdentities();
        $subscriptions = CommentsPlugin::$plugin->getSubscriptions();
        $identity = $identities->getById($identityId);

        if (!$identity || strcasecmp((string)$identity->email, $email) !== 0 || !$subscriptions->canConfirmAddress($identity)) {
            return;
        }

        $site = Craft::$app->getSites()->getSiteById($siteId);

        $message = $this->_renderEmail('comments_guest_confirmation', [
            'siteName' => $site?->getName() ?? Craft::$app->getSystemName(),
            'confirmUrl' => $subscriptions->managementUrl(Subscriptions::PURPOSE_CONFIRM, $identity, $siteId, $conversationId),
        ], $subscriptions->managementUrl(Subscriptions::PURPOSE_ALL, $identity, $siteId))
            ->setTo($identity->email);

        if (!Craft::$app->getMailer()->send($message)) {
            throw new NotificationDeliveryException('Unable to send a comment email confirmation. See the Comments log for details.');
        }
    }

    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty($config = reset($data))) {
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Comment::class)->id;
        $layout->type = Comment::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout);
    }

    public function pruneDeletedField(FieldEvent $event): void
    {
        $field = $event->field;
        $fieldUid = $field->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $layoutData = $projectConfig->get(self::CONFIG_FIELDLAYOUT_KEY);

        // Prune the UID from field layouts.
        if (is_array($layoutData)) {
            foreach ($layoutData as $layoutUid => $layout) {
                if (!empty($layout['tabs'])) {
                    foreach ($layout['tabs'] as $tabUid => $tab) {
                        $projectConfig->remove(self::CONFIG_FIELDLAYOUT_KEY . '.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                    }
                }
            }
        }
    }

    public function handleDeletedFieldLayout(ConfigEvent $event): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Comment::class);
    }

    public function saveFieldLayout(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $fieldLayoutUid = StringHelper::UUID();

        $fieldLayout = null;
        $layoutData = $projectConfig->get(self::CONFIG_FIELDLAYOUT_KEY) ?? [];

        // If no config data create one from scratch
        $config = Craft::$app->getRequest()->isConsoleRequest
            ? null
            : Craft::$app->getRequest()->getBodyParam('settings.fieldLayout');

        if ($config) {
            $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost('settings');
        } elseif (!$layoutData) {
            $fieldLayout = Craft::$app->getFields()->getLayoutByType(Comment::class) ?? new FieldLayout();

            $tab1 = new FieldLayoutTab(['name' => 'Content']);
            $tab1->setLayout($fieldLayout);

            $tab1->setElements([
                Craft::createObject([
                    'class' => CommentsFieldElement::class,
                ]),
            ]);

            $fieldLayout->setTabs([$tab1]);
        }

        if ($fieldLayout) {
            if ($layoutData) {
                $fieldLayoutUid = array_keys($layoutData)[0];
            }

            $configData = [$fieldLayoutUid => $fieldLayout->getConfig()];

            $projectConfig->set(self::CONFIG_FIELDLAYOUT_KEY, $configData);
        }
    }


    // Private Methods
    // =========================================================================

    private function _normalizePlainContent(string $content): string
    {
        // Prefer NFC when intl is available; otherwise keep raw unicode.
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($content, \Normalizer::FORM_C);

            if (is_string($normalized)) {
                $content = $normalized;
            }
        }

        $content = preg_replace("/\r\n?/", "\n", $content) ?? $content;

        return trim($content);
    }

    private function _resolveSubmissionIdentity(CommentSubmission $submission): \verbb\comments\records\Identity
    {
        $identities = CommentsPlugin::$plugin->getIdentities();

        if ($submission->identityId) {
            $identity = $identities->getById($submission->identityId);

            if (!$identity) {
                throw new UserException(Craft::t('comments', 'Commenter identity not found.'));
            }

            return $identity;
        }

        if ($submission->userId) {
            return $identities->getOrCreateForUser($submission->userId);
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser) {
            return $identities->getOrCreateForUser($currentUser);
        }

        $guestToken = $submission->guestToken ?? $identities->guestTokenFromRequest();
        $resolved = $identities->resolveOrCreateGuest(
            $guestToken,
            $submission->name,
            $submission->email,
        );

        if ($resolved['isNewToken'] && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            $identities->issueGuestCookie($resolved['guestToken']);
        }

        return $resolved['identity'];
    }

    /** Returns the parent ID, the root ID (null until the root is saved) and the depth. */
    private function _resolveHierarchy(int $conversationId, ?int $parentId): array
    {
        if ($parentId === null) {
            return [null, null, 0];
        }

        $parent = (new \craft\db\Query())
            ->from('{{%comments_comments}}')
            ->where(['id' => $parentId])
            ->one();

        if (!$parent) {
            throw new UserException(Craft::t('comments', 'Parent comment not found.'));
        }

        if ((int)($parent['conversationId'] ?? 0) !== $conversationId) {
            throw new UserException(Craft::t('comments', 'Parent comment belongs to a different conversation.'));
        }

        $rootId = $parent['rootId'] !== null ? (int)$parent['rootId'] : (int)$parent['id'];
        $depth = ((int)($parent['depth'] ?? 0)) + 1;

        return [$parentId, $rootId, $depth];
    }

    private function _legacyStatus(ModerationState $state): string
    {
        return match ($state) {
            ModerationState::Approved => Comment::STATUS_APPROVED,
            ModerationState::Pending => Comment::STATUS_PENDING,
            ModerationState::Spam => Comment::STATUS_SPAM,
            ModerationState::Removed => Comment::STATUS_REMOVED,
        };
    }

    private function _resolveInitialModerationState(?ConversationConfig $config = null): ModerationState
    {
        $requiresModeration = $config
            ? $config->requiresModeration()
            : CommentsPlugin::$plugin->getSettings()->doesRequireModeration();

        return $requiresModeration
            ? ModerationState::Pending
            : ModerationState::Approved;
    }

    /**
     * A held commenter waits for review whatever the site does, and a trusted one skips pre-moderation.
     * Spam protection and the keyword filters have already had their say, and neither setting softens
     * what they found. Their holds carry their own reason, so only a comment waiting for pre-moderation
     * alone is approved for a trusted commenter.
     */
    private function _applyReviewSetting(Comment $comment, int $identityId, ?int $conversationId): void
    {
        $moderation = CommentsPlugin::$plugin->getModeration();
        $state = ModerationState::tryFrom((string)$comment->moderationState);

        if ($moderation->isModerated($identityId, $conversationId)) {
            if ($state === ModerationState::Approved || $comment->heldReason === HeldReason::PreModeration->value) {
                $comment->moderationState = ModerationState::Pending->value;
                $comment->status = $this->_legacyStatus(ModerationState::Pending);
                $comment->heldReason = HeldReason::Commenter->value;
            }

            return;
        }

        if ($state === ModerationState::Pending && $comment->heldReason === HeldReason::PreModeration->value && $moderation->isTrusted($identityId, $conversationId)) {
            $comment->moderationState = ModerationState::Approved->value;
            $comment->status = $this->_legacyStatus(ModerationState::Approved);
            $comment->heldReason = null;
        }
    }

    /**
     * Email one guest or moderator, letting `beforeSend…` listeners change or stop it. Guest emails carry
     * a link to stop them; staff email is the site's choice, made in the settings.
     */
    private function _mail(string $key, string $eventName, Comment $comment, ElementInterface $element, User $recipient, ?string $stopEmailsUrl = null, array $variables = []): void
    {
        try {
            $message = $this->_renderEmail($key, $variables + [
                'element' => $element,
                'comment' => $comment,
                'user' => $recipient,
            ], $stopEmailsUrl)->setTo($recipient);

            $event = new EmailEvent([
                'mail' => $message,
                'user' => $recipient,
                'element' => $element,
                'comment' => $comment,
            ]);
            $this->trigger($eventName, $event);

            if (!$event->isValid) {
                CommentsPlugin::info('Email blocked via event hook.');

                return;
            }

            if (Craft::$app->getMailer()->send($message)) {
                CommentsPlugin::info('Comment email sent: {key}.', ['key' => $key]);
            } else {
                $this->_reportDeliveryFailure('Unable to send a comment email: {key}.', ['key' => $key]);
            }
        } catch (Throwable $e) {
            $this->_reportDeliveryFailure('Error sending a comment email ({key}): {message} {file}:{line}.', [
                'key' => $key,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Whether a queued notification still applies to the comment as it is now.
     */
    private function _isStillRelevant(string $type, Comment $comment): bool
    {
        $state = CommentsPlugin::$plugin->getModeration()->currentState($comment);

        return match ($type) {
            'author', 'reply', 'subscribe', 'mention', 'moderator-approved' => $state === ModerationState::Approved && !$comment->authorRemovedAt,
            'moderator', 'moderator-edit' => $state === ModerationState::Pending,
            // Every new comment, spam included, until a moderator or its author removes it.
            'admin' => $state !== ModerationState::Removed && !$comment->authorRemovedAt,
            // Kept, removed or marked as spam since, there's no report left to look at.
            'flag' => $state === ModerationState::Approved && !$comment->authorRemovedAt && $comment->getFlags() > 0,
            default => true,
        };
    }

    /**
     * One comment sends one participant email per person: reply, then mention, then element author,
     * then subscription.
     */
    private function _receivesReplyEmail(Comment $comment, User $user): bool
    {
        if (!CommentsPlugin::$plugin->getSettings()->isParticipantNotificationEnabled(Settings::NOTIFICATION_REPLY)) {
            return false;
        }

        $parentAuthor = $comment->getParentComment()?->getAuthor();

        return $parentAuthor && (int)$parentAuthor->id === (int)$user->id;
    }

    private function _receivesMentionEmail(Comment $comment, User $user): bool
    {
        if (!CommentsPlugin::$plugin->getSettings()->isParticipantNotificationEnabled(Settings::NOTIFICATION_MENTION)) {
            return false;
        }

        $identity = CommentsPlugin::$plugin->getIdentities()->findForUser((int)$user->id);

        return $identity && in_array((int)$identity->id, CommentsPlugin::$plugin->getMentions()->getIdentityIdsForComment((int)$comment->id), true);
    }

    /**
     * Mail needs a user to address, and guests don't have one.
     */
    private function _guestRecipient(IdentityRecord $identity): ?User
    {
        if (!$identity->email) {
            return null;
        }

        $user = new User();
        $user->email = $identity->email;
        $user->fullName = $identity->name ?: Craft::t('comments', 'Guest');
        $user->firstName = $user->fullName;

        return $user;
    }

    private function _stopEmailsUrl(IdentityRecord $identity, Comment $comment): string
    {
        return CommentsPlugin::$plugin->getSubscriptions()->managementUrl(Subscriptions::PURPOSE_ALL, $identity, (int)$comment->siteId);
    }

    private function _reportDeliveryFailure(string $message, array $params = []): void
    {
        CommentsPlugin::error($message, $params);

        $this->_deliveryFailures++;
    }

    private function _getCommentAncestors($comments, $comment)
    {
        if ($parent = $comment->getParentComment()) {
            $comments[] = $parent;

            return $this->_getCommentAncestors($comments, $parent);
        }

        return $comments;
    }

    private function _renderEmail($key, $variables, ?string $stopEmailsUrl = null)
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        $view = Craft::$app->getView();
        $mailer = Craft::$app->getMailer();
        $message = Craft::createObject(['class' => $mailer->messageClass, 'mailer' => $mailer]);

        // Default to the current language
        $language = Craft::$app->getRequest()->getIsSiteRequest() ? Craft::$app->language : Craft::$app->getSites()->getPrimarySite()->language;
        $systemMessage = Craft::$app->getSystemMessages()->getMessage($key, $language);

        $message->setSubject(CommentsPlugin::$plugin->getTemplates()->renderSandboxedString($systemMessage->subject, $variables));
        $textBody = CommentsPlugin::$plugin->getTemplates()->renderSandboxedString($systemMessage->body, $variables);

        // Added here so a customised system message can't lose the way out.
        if ($stopEmailsUrl) {
            $textBody .= "\n\n---\n\n" . Craft::t('comments', 'Don’t want these emails? [Unsubscribe]({url}).', ['url' => $stopEmailsUrl]);
            $message->addHeader('List-Unsubscribe', "<{$stopEmailsUrl}>");
            $message->addHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        $message->setTextBody($textBody);

        if ($settings->templateEmail) {
            $template = $settings->templateEmail;
            $templateMode = View::TEMPLATE_MODE_SITE;
        } else {
            // Default to the `_special/email` template from Craft.
            $template = '_special/email';
            $templateMode = View::TEMPLATE_MODE_CP;
        }

        try {
            $message->setHtmlBody($view->renderTemplate($template, array_merge($variables, [
                'body' => Template::raw(Markdown::process($textBody)),

                // Required when using `_special/email` from Craft.
                'language' => $language,
            ]), $templateMode));
        } catch (Throwable $e) {
            CommentsPlugin::error('Error rendering email template: {message} {file}:{line}.', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $message;
    }
}
