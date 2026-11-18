<?php
namespace verbb\comments\gql\resolvers\mutations;

use verbb\comments\compatibility\gql\LegacyGql;

use verbb\comments\Comments;
use verbb\comments\elements\Comment as CommentElement;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\exceptions\ApiException;
use verbb\comments\helpers\Gql as GqlHelper;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Flag;
use verbb\comments\models\Settings;
use verbb\comments\models\Vote;
use verbb\comments\services\Api;
use verbb\comments\services\Identities;
use verbb\comments\services\Moderation;
use verbb\comments\services\Protection;

use Craft;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\errors\GqlException;
use craft\errors\SiteNotFoundException;
use craft\gql\base\ElementMutationResolver;
use craft\helpers\Gql;

use yii\base\Model;
use yii\base\UserException;

use Throwable;

use GraphQL\Error\Error;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Implements custom mutation methods take GraphQL mutations and make stuff happen.
 *
 * Every mutation takes an optional `operationId`, which shares the data API's replay store, so a repeat
 * returns the first result rather than acting again. A refusal is a GraphQL error whose `extensions` carry
 * the data API's `code`, `category`, `fieldErrors`, `recoverable` and `retryAfter`.
 *
 * @package verbb\comments\gql\resolvers\mutations
 */
class Comment extends ElementMutationResolver
{
    // Properties
    // =========================================================================

    // An edit can change a comment's content, not who wrote it, where it sits or when it was posted.
    protected array $immutableAttributes = [
        'id',
        'uid',
        'siteId',
        'ownerId',
        'ownerSiteId',
        'parentId',
        'rootId',
        'depth',
        'conversationId',
        'identityId',
        'userId',
        'commentDate',
        'status',
        'moderationState',
        'contentFormat',
        'bodyContent',
        'name',
        'email',
        'url',
    ];


    // Public Methods
    // =========================================================================

    /**
     * Handles GraphQL mutation arguments to either create or update a comment.
     *
     * @throws Error
     * @throws GqlException
     * @throws SiteNotFoundException
     */
    public function saveComment($source, array $arguments, $context, ResolveInfo $resolveInfo): ?ElementInterface
    {
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']);

        return $this->_write(
            $canIdentify ? 'update' : 'create',
            $arguments,
            fn(): int => (int)LegacyGql::write($arguments, fn(): ElementInterface => $this->_saveComment(LegacyGql::withoutIgnoredArguments($arguments)))->id,
            static fn(int $id): ?ElementInterface => Craft::$app->getElements()->getElementById($id, CommentElement::class),
        );
    }

    /**
     * Handles GraphQL mutation arguments to record a comment upvote or downvote. With `vote`, it sets the
     * vote to `upvote`, `downvote` or none rather than toggling it, so a repeat changes nothing.
     */
    public function voteComment($source, array $arguments, $context, ResolveInfo $resolveInfo): ?Vote
    {
        return $this->_write(
            'vote',
            $arguments,
            fn(): array => $this->_voteComment($arguments),
            fn(array $vote): Vote => $this->_voteModelFromInteraction($vote['commentId'], $vote['userId'], InteractionType::tryFrom((string)$vote['type'])),
        );
    }

    /**
     * Handles GraphQL mutation arguments to flag a comment. With `withdraw`, it sets the asker's report
     * rather than toggling it.
     */
    public function flagComment($source, array $arguments, $context, ResolveInfo $resolveInfo): ?Flag
    {
        return $this->_write(
            'report',
            $arguments,
            fn(): array => $this->_flagComment($arguments),
            static fn(array $flag): Flag => new Flag($flag),
        );
    }

    /**
     * Handles GraphQL mutation arguments to toggle comment subscription. With `following`, it sets the
     * subscription instead, and returns `followed`, `confirming` or `unfollowed` as the data API's
     * `follow` does. Without it, the toggle returns Comments 3's message, which Comments 3 sites show.
     */
    public function subscribeComment($source, array $arguments, $context, ResolveInfo $resolveInfo): string
    {
        return $this->_write(
            'follow',
            $arguments,
            fn(): string => $this->_subscribeComment($arguments),
            static fn(string $outcome): string => $outcome,
        );
    }

    /**
     * Runs the same workflow as every other way of moderating, for a user with the same permissions.
     */
    public function moderateComment($source, array $arguments, $context, ResolveInfo $resolveInfo): ?ElementInterface
    {
        return $this->_write(
            'moderate',
            $arguments,
            fn(): int => (int)$this->_moderateComment($arguments)->id,
            static fn(int $id): ?ElementInterface => CommentElement::find()->id($id)->status(null)->siteId('*')->trashed(null)->one(),
        );
    }

    /**
     * Handles GraphQL mutation arguments to delete a comment.
     *
     * @throws Throwable
     */
    public function deleteComment($source, array $arguments, $context, ResolveInfo $resolveInfo): bool
    {
        return $this->_write(
            'delete',
            $arguments,
            fn(): bool => $this->_deleteComment($arguments),
            static fn(bool $deleted): bool => $deleted,
        );
    }


    // Protected Methods
    // =========================================================================

    /**
     * Returns a new or existing Comment element based on the provided query parameters.
     *
     * @throws Error
     * @throws SiteNotFoundException
     */
    protected function getCommentElement(array $arguments): array|Model|ElementInterface
    {
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']);
        $this->requireSchemaAction('comments', $canIdentify ? 'save' : 'edit');

        $elementService = Craft::$app->getElements();

        if ($canIdentify) {
            $siteId = $arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;
            $commentQuery = $elementService->createElementQuery(CommentElement::class)
                ->status(null)
                ->siteId($siteId);
            $commentQuery = $this->identifyComment($commentQuery, $arguments);
            $comment = $commentQuery->one();

            if (!$comment) {
                throw new ApiException('No such comment exists', 'not_found', 404);
            }
        } else {
            $comment = $elementService->createElement(CommentElement::class);
        }

        return $comment;
    }

    /**
     * Craft asks whether a user-bound schema's user can save the comment as an element, which only staff
     * can. Its author editing it is decided by the same rules as on the site, and only when that author
     * is also the person Comments identifies for the request.
     */
    protected function requireUserAuthorization(ElementInterface $element, string $action): void
    {
        $schemaUser = Gql::getSchemaUser();

        if ($action === 'save' && $schemaUser && $element instanceof CommentElement && $element->userId && (int)$element->userId === (int)$schemaUser->id && $element->canEdit()) {
            return;
        }

        parent::requireUserAuthorization($element, $action);
    }

    /**
     * Attempts to use GraphQL query arguments to set the appropriate ID on a Craft element query.
     *
     * If the arguments don’t contain a UID or ID, sets ID to -1 so the element query doesn’t return
     * any results.
     */
    protected function identifyComment(CommentQuery $commentQuery, array $arguments): CommentQuery
    {
        if (!empty($arguments['uid'])) {
            $commentQuery->uid($arguments['uid']);
        } elseif (!empty($arguments['id'])) {
            $commentQuery->id($arguments['id']);
        } else {
            // Unable to identify, make sure nothing is returned.
            $commentQuery->id(-1);
        }

        return $commentQuery;
    }


    // Private Methods
    // =========================================================================

    /**
     * Run a mutation, once per `operationId` when one is given. What `$write` returns is what's remembered
     * for a repeat, and `$answer` turns it into the mutation's result, so a replay answers with the same
     * comment as the first time. Refusals become GraphQL errors carrying the data API's error details.
     */
    private function _write(string $name, array $arguments, callable $write, callable $answer): mixed
    {
        try {
            $this->_requireSafeRequest();
            $result = isset($arguments['operationId'])
                ? Comments::$plugin->getApi()->once($name, $arguments['operationId'], $write, 'graphql')[0]
                : $write();
        } catch (Error $e) {
            // Craft's own GraphQL errors, such as a schema without the permission, stay as they are.
            throw $e;
        } catch (UserError|UserException $e) {
            $api = Comments::$plugin->getApi();
            $error = $e instanceof UserError ? new ApiException($e->getMessage(), 'validation_failed', 422) : $api->errorFor($e);
            $details = $api->errorData($error)['errors'][0];

            throw new Error($error->getMessage(), extensions: [
                'code' => $details['code'],
                'category' => $details['category'],
                'fieldErrors' => $details['fieldErrors'],
                'recoverable' => $details['recoverable'],
                'retryAfter' => $details['retryAfter'],
            ]);
        }

        return $answer($result);
    }

    /**
     * Craft's GraphQL endpoint takes queries over GET and checks no CSRF token, because a schema's token
     * normally says who's asking. These mutations can act for whoever a session or guest cookie belongs
     * to, though, so a page on another site could make a visitor's browser post, vote or delete for them.
     * They're refused over GET, and with a public schema, which needs no token, a request carrying a
     * session or guest cookie needs the page's CSRF token too, as the data API's writes do. Requests
     * from the command line, such as tests and queue jobs, can't be cross-site.
     */
    private function _requireSafeRequest(): void
    {
        $request = Craft::$app->getRequest();

        if (PHP_SAPI === 'cli' || !$request instanceof \craft\web\Request) {
            return;
        }

        if (!$request->getIsPost()) {
            throw new ApiException(Craft::t('comments', 'This action only accepts POST requests.'), 'method_not_allowed', 405);
        }

        $cookies = $request->getCookies();
        $cookieIdentity = Craft::$app->getSession()->getHasSessionId()
            || $cookies->has(Craft::$app->getUser()->identityCookie['name'] ?? '_identity')
            || $cookies->has(Identities::GUEST_COOKIE_NAME);

        if (GqlHelper::isPublicSchema() && $cookieIdentity && !$request->validateCsrfToken()) {
            throw new ApiException(Craft::t('comments', 'Your session expired. Refresh the page and try again.'), 'csrf_failed', 403);
        }
    }

    /**
     * Votes, reports and new comments are only taken on a thread anyone can see, the same as through
     * the data API.
     */
    private function _requireLiveOwner(int $ownerId, int $siteId): void
    {
        Comments::$plugin->getApi()->resolveOwner($ownerId, null, $siteId);
    }

    /**
     * Someone blocked from a discussion can't follow it (SD-45).
     */
    private function _requireNotBlocked(int $ownerId, int $siteId): void
    {
        if (Comments::$plugin->getComments()->isRequestBlocked($ownerId, $siteId)) {
            throw new ApiException(Craft::t('comments', 'You can’t take part in this discussion.'), 'blocked', 403);
        }
    }

    /**
     * A mutation's comment ID, from its `uid` or `id`.
     */
    private function _commentIdArgument(array $arguments): int
    {
        if (!empty($arguments['uid'])) {
            $id = CommentElement::find()->uid((string)$arguments['uid'])->status(null)->siteId('*')->trashed(null)->ids()[0] ?? null;

            if ($id) {
                return (int)$id;
            }
        } elseif (!empty($arguments['id'])) {
            return (int)$arguments['id'];
        }

        throw new ApiException(Craft::t('comments', 'This can’t be shown.'), 'not_found', 404);
    }

    private function _saveComment(array $arguments): ElementInterface
    {
        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']);
        $elementService = Craft::$app->getElements();

        if ($canIdentify) {
            // If we have an element ID, we’re trying to save an existing record
            $this->requireSchemaAction('comments', 'save');
        } else {
            // If not, we’re saving a new one
            $this->requireSchemaAction('comments', 'edit');
        }

        unset($arguments['operationId']);
        $currentUser = Comments::$plugin->getService()->getUser();

        if (empty($currentUser) && !$settings->allowGuest) {
            $message = !empty($settings->guestNotice) ? $settings->guestNotice : Craft::t('comments', 'Must be logged in to comment.');

            throw new ApiException($message, 'sign_in_required', 401);
        }

        $comment = $this->getCommentElement($arguments);

        if ($canIdentify) {
            $previousMentionIds = Comments::$plugin->getMentions()->getIdentityIdsForComment((int)$comment->id);
            $pendingMentionIds = null;
            $pendingAssetReferences = null;

            // Changing only the plain text would leave the document that's displayed as it was.
            if ($comment->isEditorContent() && array_key_exists('comment', $arguments) && (empty($arguments['contentDocument']) || !$settings->isRichAuthoringEnabled())) {
                throw new ApiException(Craft::t('comments', 'Rich comments cannot be converted to plain text.'), 'validation_failed', 422, [
                    'content' => [Craft::t('comments', 'Rich comments cannot be converted to plain text.')],
                ]);
            }

            $evidence = Comments::$plugin->getProtection()->evidence($arguments['protection'] ?? []);
            unset($arguments['protection'], $arguments['parentUid'], $arguments['notifyReplies']);
            [$arguments, $fieldValues] = $this->_splitFieldArguments($arguments);
            $comment = $this->populateElementWithData($comment, $arguments);

            // The fields it was posted with, as the data API's `edit` gives them, under the same rules, so
            // an Assets field can't be pointed at someone else's file.
            $formFields = Comments::$plugin->getFormFields();
            $formFields->apply($comment, $fieldValues, $formFields->editable($comment, $currentUser instanceof User ? $currentUser : null));

            if (!empty($arguments['contentDocument']) && $settings->isRichAuthoringEnabled()) {
                $normalized = Comments::$plugin->getComments()->normalizeEditorPayload(
                    (string)$arguments['contentDocument'],
                    'comments.edit',
                    $currentUser?->id ? (int)$currentUser->id : null,
                    (int)$comment->ownerId,
                    (int)$comment->siteId,
                    (int)$comment->id,
                );
                $comment->bodyContent = $normalized['bodyContent'];
                $comment->comment = $normalized['plainText'];
                $comment->contentFormat = ContentFormat::Rich->value;
                $pendingMentionIds = $normalized['mentionIdentityIds'];
                $pendingAssetReferences = $normalized['assetReferences'];
            }

            // The site's rules, as for an edit through the data API: required fields are those it was
            // posted with, rather than every required field in the layout.
            $comment->setScenario(CommentElement::SCENARIO_FRONT_END);
            $comment->setAction(CommentElement::ACTION_SAVE);

            if (!$comment->canEdit()) {
                throw new ApiException(Craft::t('comments', 'You do not have permission to edit this comment.'), 'forbidden', 403);
            }

            Comments::$plugin->getComments()->applyEditModerationState($comment);
            $comment = Comments::$plugin->getComments()->savePublicUpdate($comment, $evidence);

            if ($comment->hasErrors()) {
                $this->_throwErrors($comment->getFirstErrors());
            }

            if ($pendingMentionIds !== null) {
                Comments::$plugin->getComments()->finalizeCommentMentions(
                    $comment,
                    $previousMentionIds,
                    $pendingMentionIds,
                );
            }

            if ($pendingAssetReferences !== null) {
                Comments::$plugin->getCommentAssets()->syncEditedReferences($comment, $pendingAssetReferences, (int)$currentUser?->id);
            }

            return $comment;
        }

        // New comments use the canonical create path.
        $ownerId = $arguments['ownerId'] ?? null;
        $siteId = $arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;

        if (!$ownerId) {
            throw new ApiException(Craft::t('comments', 'Owner element is required.'), 'validation_failed', 422, [
                'ownerId' => [Craft::t('comments', 'Owner element is required.')],
            ]);
        }

        $this->_requireLiveOwner((int)$ownerId, (int)$siteId);
        $owner = Craft::$app->getElements()->getElementById((int)$ownerId, null, (int)$siteId);

        if (!$settings->canComment($owner)) {
            throw new ApiException(Craft::t('comments', 'Comment not allowed.'), 'forbidden', 403);
        }

        $contentFormat = ContentFormat::Plain;
        $content = (string)($arguments['comment'] ?? '');

        if (!empty($arguments['contentDocument']) && $settings->isRichAuthoringEnabled()) {
            $contentFormat = ContentFormat::Rich;
            $content = (string)$arguments['contentDocument'];
        } elseif (($arguments['contentFormat'] ?? null) === ContentFormat::Rich->value && $settings->isRichAuthoringEnabled()) {
            $contentFormat = ContentFormat::Rich;
            $content = (string)($arguments['contentDocument'] ?? $arguments['comment'] ?? '');
        }

        // `parentId`, or Comments 3's `newParentId` in compatibility mode.
        $parentId = LegacyGql::parentId($arguments);

        // The data API names a parent by its UID. It has to be on the same owner and showing.
        if (!empty($arguments['parentUid'])) {
            $parent = CommentElement::find()->uid((string)$arguments['parentUid'])->siteId('*')->one();

            if (!$parent || (int)$parent->ownerId !== (int)$ownerId || $parent->authorRemovedAt || Comments::$plugin->getModeration()->currentState($parent) !== ModerationState::Approved) {
                throw new ApiException(Craft::t('comments', 'This can’t be shown.'), 'not_found', 404);
            }

            $parentId = (int)$parent->id;
        }

        [, $fieldValues] = $this->_splitFieldArguments($arguments);
        $parent = $parentId ? CommentElement::find()->id($parentId)->status(null)->siteId('*')->one() : null;
        $author = $currentUser instanceof User ? $currentUser : null;

        $submission = new CommentSubmission([
            'protectionEvidence' => Comments::$plugin->getProtection()->evidence($arguments['protection'] ?? []),
            'protectionRequest' => Comments::$plugin->getProtection()->requestSignals(),
            'ownerId' => (int)$ownerId,
            'siteId' => (int)$siteId,
            'parentId' => $parentId,
            'content' => $content,
            'contentFormat' => $contentFormat,
            'userId' => $currentUser?->id,
            'name' => $arguments['name'] ?? null,
            'email' => $arguments['email'] ?? null,
            'notifyReplies' => !$currentUser && !empty($arguments['notifyReplies']),
        ]);

        // The fields described to this person, worked out from the comment as it would be created.
        return Comments::$plugin->getComments()->create($submission, static function(CommentElement $comment) use ($ownerId, $siteId, $parent, $author, $fieldValues): void {
            $formFields = Comments::$plugin->getFormFields();
            $formFields->apply($comment, $fieldValues, $formFields->collectable($formFields->prospect((int)$ownerId, (int)$siteId, $parent, $author), $author));
        })->comment;
    }

    /**
     * A mutation's arguments, and its custom field values keyed by handle, apart.
     */
    private function _splitFieldArguments(array $arguments): array
    {
        $contentFields = $this->getResolutionData(self::CONTENT_FIELD_KEY) ?? [];
        $values = array_intersect_key($arguments, $contentFields);

        return [array_diff_key($arguments, $contentFields), $values];
    }

    private function _voteComment(array $arguments): array
    {
        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();

        $mode = $settings->resolveInteractionMode();

        if (!$mode->isEnabled()) {
            throw new ApiException(Craft::t('comments', 'Voting is not allowed.'), 'forbidden', 403);
        }

        if (empty($currentUser) && !$settings->allowGuestVoting) {
            throw new ApiException(Craft::t('comments', 'Must be logged in to vote.'), 'sign_in_required', 401);
        }

        $commentId = $this->_commentIdArgument($arguments);
        $setState = array_key_exists('vote', $arguments);
        $requested = $setState && $arguments['vote'] !== null ? InteractionType::tryFrom((string)$arguments['vote']) : null;
        $upvote = !empty($arguments['upvote']);

        if ($setState) {
            if ($arguments['vote'] !== null && (!$requested || !$mode->allows($requested))) {
                throw new ApiException(Craft::t('comments', 'That interaction is not enabled.'), 'validation_failed', 422, [
                    'vote' => [Craft::t('comments', 'That interaction is not enabled.')],
                ]);
            }
        } elseif ($upvote === !empty($arguments['downvote'])) {
            // One direction or the other: a vote with neither, or both, used to count as a downvote.
            throw new ApiException(Craft::t('comments', 'Choose to upvote or downvote.'), 'validation_failed', 422);
        } elseif (!$upvote && !$mode->allowsDownvote()) {
            throw new ApiException(Craft::t('comments', 'Downvoting is not enabled.'), 'validation_failed', 422);
        }

        $userId = $currentUser->id ?? null;
        $target = Comments::$plugin->getComments()->requireEngagementTarget($commentId, Protection::ENGAGEMENT_INTERACTION, $this->_siteIdArgument($arguments));
        $this->_requireLiveOwner((int)$target->ownerId, (int)$target->ownerSiteId);

        // The user GraphQL authenticated, who may only have a token and no session.
        $identity = Comments::$plugin->getIdentities()->resolveForUserOrGuest($currentUser instanceof User ? $currentUser : null);
        $interactions = Comments::$plugin->getInteractions();

        if (!$setState) {
            $type = $interactions->toggleVote($commentId, (int)$identity->id, $upvote);
        } elseif ($requested) {
            $type = InteractionType::from((string)$interactions->set($commentId, (int)$identity->id, $requested)->type);
        } else {
            $interactions->remove($commentId, (int)$identity->id);
            $type = null;
        }

        return ['commentId' => $commentId, 'userId' => $userId ? (int)$userId : null, 'type' => $type?->value];
    }

    private function _flagComment(array $arguments): array
    {
        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $commentId = $this->_commentIdArgument($arguments);
        $userId = $currentUser->id ?? null;

        if (!$settings->allowFlagging) {
            throw new ApiException(Craft::t('comments', 'Flagging is not allowed.'), 'forbidden', 403);
        }

        if (empty($currentUser) && !$settings->allowGuestFlagging) {
            throw new ApiException(Craft::t('comments', 'Must be logged in to flag comments.'), 'sign_in_required', 401);
        }

        $target = Comments::$plugin->getComments()->requireEngagementTarget($commentId, Protection::ENGAGEMENT_REPORT, $this->_siteIdArgument($arguments));
        $this->_requireLiveOwner((int)$target->ownerId, (int)$target->ownerSiteId);
        $identity = Comments::$plugin->getIdentities()->resolveForUserOrGuest($currentUser instanceof User ? $currentUser : null);
        $reason = $arguments['reason'] ?? null;
        $details = $arguments['details'] ?? null;

        if (is_string($details) && mb_strlen($details) > Api::REPORT_DETAILS_MAX) {
            throw new ApiException(Craft::t('comments', 'Keep it to {max} characters.', ['max' => Api::REPORT_DETAILS_MAX]), 'validation_failed', 422, [
                'details' => [Craft::t('comments', 'Keep it to {max} characters.', ['max' => Api::REPORT_DETAILS_MAX])],
            ]);
        }

        $isOwn = $currentUser instanceof User ? (int)$target->userId === (int)$currentUser->id : (!$target->userId && (int)$target->identityId === (int)$identity->id);

        if ($isOwn && empty($arguments['withdraw'])) {
            throw new ApiException(Craft::t('comments', 'You can’t report your own comment.'), 'forbidden', 403);
        }

        $reason = in_array($reason, array_column(Comments::$plugin->getFrontend()::reportReasons(), 'value'), true) ? $reason : null;
        $reports = Comments::$plugin->getReports();

        if (!array_key_exists('withdraw', $arguments) || $arguments['withdraw'] === null) {
            $reports->toggle($commentId, (int)$identity->id, $reason, $arguments['details'] ?? null);
        } elseif ($arguments['withdraw']) {
            $reports->withdrawOpen($commentId, (int)$identity->id);
        } elseif (!$reports->getOpenForIdentity($commentId, (int)$identity->id)) {
            $reports->create($commentId, (int)$identity->id, $reason, $arguments['details'] ?? null);
        }

        return [
            'commentId' => $commentId,
            'userId' => $userId ? (int)$userId : null,
        ];
    }

    private function _subscribeComment(array $arguments): string
    {
        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $commentId = $arguments['commentId'] ?? null;
        $ownerId = $arguments['ownerId'];
        $siteId = $arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;
        $owner = Craft::$app->getElements()->getElementById((int)$ownerId, null, (int)$siteId);

        if (!$owner || $owner instanceof CommentElement || !Comments::$plugin->getConversations()->getConfigForOwner((int)$ownerId, (int)$siteId)->enabled) {
            throw new ApiException(Craft::t('comments', 'Comments are unavailable for this element.'), 'not_found', 404);
        }

        $this->_requireLiveOwner((int)$ownerId, (int)$siteId);

        if ($commentId && !$settings->allowsSubscriptionType(SubscriptionType::Replies)) {
            throw new ApiException(Craft::t('comments', 'Per-comment subscriptions are not allowed.'), 'forbidden', 403);
        }

        if (!$commentId && !$settings->allowsSubscriptionType(SubscriptionType::Conversation)) {
            throw new ApiException(Craft::t('comments', 'Discussion subscriptions are not allowed.'), 'forbidden', 403);
        }

        // Setting the conversation's subscription works as the data API's `follow` does, guests included.
        if (!$commentId && array_key_exists('following', $arguments) && $arguments['following'] !== null) {
            return Comments::$plugin->getSubscriptions()->setConversationSubscription(
                (int)$ownerId,
                (int)$siteId,
                (bool)$arguments['following'],
                is_string($arguments['email'] ?? null) ? $arguments['email'] : null,
            );
        }

        if (empty($currentUser)) {
            throw new ApiException(Craft::t('comments', 'Must be logged in to subscribe.'), 'sign_in_required', 401);
        }

        $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner((int)$ownerId, (int)$siteId);
        $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($currentUser);
        $subscriptions = Comments::$plugin->getSubscriptions();

        if ($commentId) {
            $subscribed = $subscriptions->isSubscribed(
                (int)$conversation->id,
                (int)$identity->id,
                (int)$commentId,
                SubscriptionType::Replies,
            );

            if ($subscribed) {
                $subscriptions->unsubscribe(
                    (int)$conversation->id,
                    (int)$identity->id,
                    (int)$commentId,
                    SubscriptionType::Replies,
                );
            } else {
                $this->_requireNotBlocked((int)$ownerId, (int)$siteId);
                $subscriptions->subscribeToReplies((int)$conversation->id, (int)$identity->id, (int)$commentId);
            }

            $subscribed = $subscriptions->isSubscribed(
                (int)$conversation->id,
                (int)$identity->id,
                (int)$commentId,
                SubscriptionType::Replies,
            );
        } else {
            $subscribed = $subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id);

            if ($subscribed) {
                $subscriptions->unsubscribe((int)$conversation->id, (int)$identity->id);
            } else {
                $this->_requireNotBlocked((int)$ownerId, (int)$siteId);
                $subscriptions->subscribeToConversation((int)$conversation->id, (int)$identity->id);
            }

            $subscribed = $subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id);
        }

        return $subscribed ?
            Craft::t('comments', 'Subscribed to discussion.') :
            Craft::t('comments', 'Unsubscribed from discussion.');
    }

    private function _moderateComment(array $arguments): ElementInterface
    {
        $this->requireSchemaAction('comments', 'moderate');

        // A public schema needs no token, so a cross-site request could use a moderator's session.
        if (GqlHelper::isPublicSchema()) {
            throw new ApiException(Craft::t('comments', 'You are not permitted to moderate this comment.'), 'forbidden', 403);
        }

        $comment = CommentElement::find()->id($this->_commentIdArgument($arguments))->status(null)->siteId('*')->trashed(null)->one();
        $moderation = Comments::$plugin->getModeration();
        $actor = Comments::$plugin->getService()->getUser();

        if (!$comment || !$actor || !$moderation->canModerateComment($actor, $comment)) {
            throw new ApiException(Craft::t('comments', 'You are not permitted to moderate this comment.'), 'forbidden', 403);
        }

        $state = $moderation->currentState($comment);

        // Restoring needs the trash permission, and approving or holding a removed comment would restore it.
        if ((in_array($arguments['action'], ['remove', 'restore'], true) || $state === ModerationState::Removed) && !$moderation->canRemoveComment($actor)) {
            throw new ApiException(Craft::t('comments', 'You are not permitted to remove this comment.'), 'forbidden', 403);
        }

        if ($arguments['action'] === 'hold' && $state !== ModerationState::Approved) {
            throw new ApiException(Craft::t('comments', 'This comment has changed since it was loaded.'), 'conflict', 409);
        }

        $reason = $arguments['reason'] ?? null;

        $comment = match ($arguments['action']) {
            'approve' => $moderation->approve($comment, $actor, $reason),
            'remove' => $moderation->remove($comment, $actor, $reason),
            'restore' => $moderation->restore($comment, $actor, $reason),
            'spam' => $moderation->markSpam($comment, $actor, $reason),
            'not-spam' => $moderation->markNotSpam($comment, $actor, $reason),
            // Keep closes the comment's reports, as it does everywhere else.
            'keep' => $moderation->decide($comment, Moderation::DECISION_KEEP, $actor),
            'hold' => $moderation->setState($comment, ModerationState::Pending, $actor),
            default => throw new ApiException(Craft::t('comments', 'Unknown moderation action.'), 'validation_failed', 422, [
                'action' => [Craft::t('comments', 'Unknown moderation action.')],
            ]),
        };

        $note = trim((string)($arguments['note'] ?? ''));

        if ($note !== '') {
            $moderation->addNote($comment, $note, $actor);
        }

        return $comment;
    }

    private function _deleteComment(array $arguments): bool
    {
        $this->requireSchemaAction('comments', 'delete');

        $commentId = !empty($arguments['uid'])
            ? (int)(CommentElement::find()->uid((string)$arguments['uid'])->status(null)->siteId('*')->ids()[0] ?? 0)
            : (int)($arguments['id'] ?? 0);
        $comment = $commentId ? CommentElement::find()->id($commentId)->siteId($arguments['siteId'] ?? '*')->status(null)->one() : null;
        $currentUser = Comments::$plugin->getService()->getUser();

        if (!$comment) {
            return true;
        }

        $siteId = (int)$comment->siteId;

        if (!$currentUser instanceof User || (int)$comment->userId !== (int)$currentUser->id) {
            throw new ApiException(Craft::t('comments', 'You may only delete your own comments.'), 'forbidden', 403);
        }

        // The user GraphQL authenticated, who may only have a token and no session.
        Comments::$plugin->getComments()->authorRemove($commentId, $siteId, $currentUser);

        return true;
    }

    /**
     * A mutation's optional `siteId`, which limits it to the comment on that site.
     */
    private function _siteIdArgument(array $arguments): ?int
    {
        return isset($arguments['siteId']) && $arguments['siteId'] !== '' ? (int)$arguments['siteId'] : null;
    }

    /**
     * Formats and throws validation errors.
     */
    private function _throwErrors(array $errors): void
    {
        $validationErrors = [];

        foreach ($errors as $attribute => $errorMessage) {
            $validationErrors[] = $errorMessage;
        }

        throw new ApiException(implode("\n", $validationErrors), 'validation_failed', 422);
    }

    /**
     * The vote a mutation returns, from the interaction that's now current.
     */
    private function _voteModelFromInteraction(int $commentId, ?int $userId, ?InteractionType $type): Vote
    {
        $vote = new Vote([
            'commentId' => $commentId,
            'userId' => $userId,
        ]);

        if ($type === InteractionType::Upvote) {
            $vote->upvote = 1;
        } elseif ($type === InteractionType::Downvote) {
            $vote->downvote = 1;
        }

        return $vote;
    }
}
