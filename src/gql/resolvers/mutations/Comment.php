<?php
namespace verbb\comments\gql\resolvers\mutations;

use verbb\comments\Comments;
use verbb\comments\elements\Comment as CommentElement;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\helpers\Gql as GqlHelper;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Flag;
use verbb\comments\models\Settings;
use verbb\comments\models\Subscribe;
use verbb\comments\models\Vote;
use verbb\comments\services\Protection;

use Craft;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\errors\GqlException;
use craft\errors\SiteNotFoundException;
use craft\gql\base\ElementMutationResolver;
use craft\helpers\Gql;

use yii\base\Model;

use Throwable;

use GraphQL\Error\Error;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Implements custom mutation methods take GraphQL mutations and make stuff happen.
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

        $currentUser = Comments::$plugin->getService()->getUser();

        if (empty($currentUser) && !$settings->allowGuest) {
            $message = !empty($settings->guestNotice) ? $settings->guestNotice : Craft::t('comments', 'Must be logged in to comment.');

            throw new UserError($message);
        }

        $comment = $this->getCommentElement($arguments);

        if ($canIdentify) {
            $previousMentionIds = Comments::$plugin->getMentions()->getIdentityIdsForComment((int)$comment->id);
            $pendingMentionIds = null;
            $pendingAssetReferences = null;

            // Changing only the plain text would leave the document that's displayed as it was.
            if ($comment->isEditorContent() && array_key_exists('comment', $arguments) && (empty($arguments['editorContent']) || !$settings->isRichAuthoringEnabled())) {
                throw new UserError(Craft::t('comments', 'Rich comments cannot be converted to plain text.'));
            }

            $evidence = Comments::$plugin->getProtection()->evidence($arguments['protection'] ?? []);
            unset($arguments['protection']);

            // The same rules as a public form, so an Assets field can't be pointed at someone else's file.
            try {
                $comment = $comment->applyPublicFieldValues(fn() => $this->populateElementWithData($comment, $arguments));
            } catch (\yii\base\UserException $e) {
                throw new UserError($e->getMessage());
            }

            if (!empty($arguments['editorContent']) && $settings->isRichAuthoringEnabled()) {
                $normalized = Comments::$plugin->getComments()->normalizeEditorPayload(
                    (string)$arguments['editorContent'],
                    'comments.edit',
                    $currentUser?->id ? (int)$currentUser->id : null,
                    (int)$comment->ownerId,
                    (int)$comment->siteId,
                );
                $comment->bodyContent = $normalized['bodyContent'];
                $comment->comment = $normalized['plainText'];
                $comment->contentFormat = ContentFormat::Editor->value;
                $pendingMentionIds = $normalized['mentionIdentityIds'];
                $pendingAssetReferences = $normalized['assetReferences'];
            }

            $comment->setScenario(CommentElement::SCENARIO_LIVE);

            try {
                if (!$comment->canEdit()) {
                    throw new UserError(Craft::t('comments', 'You do not have permission to edit this comment.'));
                }

                Comments::$plugin->getComments()->applyEditModerationState($comment);
                $comment = Comments::$plugin->getComments()->savePublicUpdate($comment, $evidence);
            } catch (\yii\base\UserException $e) {
                throw new UserError($e->getMessage());
            }

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
                try {
                    Comments::$plugin->getCommentAssets()->syncEditedReferences((int)$comment->id, $pendingAssetReferences, (int)$currentUser?->id);
                } catch (\yii\base\UserException $e) {
                    throw new UserError($e->getMessage());
                }
            }

            return $elementService->getElementById($comment->id, CommentElement::class);
        }

        // New comments use the canonical create path.
        $ownerId = $arguments['ownerId'] ?? null;
        $siteId = $arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;

        if (!$ownerId) {
            throw new UserError(Craft::t('comments', 'Owner element is required.'));
        }

        $owner = Craft::$app->getElements()->getElementById((int)$ownerId, null, (int)$siteId);

        if (!$settings->canComment($owner)) {
            throw new UserError(Craft::t('comments', 'Comment not allowed.'));
        }

        $contentFormat = ContentFormat::Plain;
        $content = (string)($arguments['comment'] ?? '');

        if (!empty($arguments['editorContent']) && $settings->isRichAuthoringEnabled()) {
            $contentFormat = ContentFormat::Editor;
            $content = (string)$arguments['editorContent'];
        } elseif (($arguments['contentFormat'] ?? null) === ContentFormat::Editor->value && $settings->isRichAuthoringEnabled()) {
            $contentFormat = ContentFormat::Editor;
            $content = (string)($arguments['editorContent'] ?? $arguments['comment'] ?? '');
        }

        $submission = new CommentSubmission([
            'protectionEvidence' => Comments::$plugin->getProtection()->evidence($arguments['protection'] ?? []),
            'protectionRequest' => Comments::$plugin->getProtection()->requestSignals(),
            'ownerId' => (int)$ownerId,
            'siteId' => (int)$siteId,
            'parentId' => isset($arguments['parentId']) ? (int)$arguments['parentId'] : null,
            'content' => $content,
            'contentFormat' => $contentFormat,
            'userId' => $currentUser?->id,
            'name' => $arguments['name'] ?? null,
            'email' => $arguments['email'] ?? null,
        ]);

        try {
            $result = Comments::$plugin->getComments()->create($submission);
        } catch (\yii\base\UserException $e) {
            throw new UserError($e->getMessage());
        }

        return $elementService->getElementById($result->comment->id, CommentElement::class);
    }

    /**
     * Handles GraphQL mutation arguments to record a comment upvote or downvote.
     */
    public function voteComment($source, array $arguments, $context, ResolveInfo $resolveInfo): ?Vote
    {
        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();

        $mode = $settings->resolveInteractionMode();

        if (!$mode->isEnabled()) {
            throw new UserError(Craft::t('comments', 'Voting is not allowed.'));
        }

        if (empty($currentUser) && !$settings->allowGuestVoting) {
            throw new UserError(Craft::t('comments', 'Must be logged in to vote.'));
        }

        $commentId = $arguments['id'];
        $upvote = !empty($arguments['upvote']);

        // One direction or the other: a vote with neither, or both, used to count as a downvote.
        if ($upvote === !empty($arguments['downvote'])) {
            throw new UserError(Craft::t('comments', 'Choose to upvote or downvote.'));
        }

        if (!$upvote && !$mode->allowsDownvote()) {
            throw new UserError(Craft::t('comments', 'Downvoting is not enabled.'));
        }

        $userId = $currentUser->id ?? null;

        try {
            Comments::$plugin->getComments()->requireEngagementTarget((int)$commentId, Protection::ENGAGEMENT_INTERACTION, $this->_siteIdArgument($arguments));
        } catch (\yii\base\UserException $e) {
            throw new UserError($e->getMessage());
        }

        // The user GraphQL authenticated, who may only have a token and no session.
        $identity = Comments::$plugin->getIdentities()->resolveForUserOrGuest($currentUser instanceof User ? $currentUser : null);
        $type = Comments::$plugin->getInteractions()->toggleVote((int)$commentId, (int)$identity->id, $upvote);

        return $this->_voteModelFromInteraction((int)$commentId, $userId, $type);
    }

    /**
     * Handles GraphQL mutation arguments to flag a comment.
     */
    public function flagComment($source, array $arguments, $context, ResolveInfo $resolveInfo): ?Flag
    {
        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $commentId = $arguments['id'];
        $userId = $currentUser->id ?? null;

        if (!$settings->allowFlagging) {
            throw new UserError(Craft::t('comments', 'Flagging is not allowed.'));
        }

        if (empty($currentUser) && !$settings->allowGuestFlagging) {
            throw new UserError(Craft::t('comments', 'Must be logged in to flag comments.'));
        }

        try {
            Comments::$plugin->getComments()->requireEngagementTarget((int)$commentId, Protection::ENGAGEMENT_REPORT, $this->_siteIdArgument($arguments));
            $identity = Comments::$plugin->getIdentities()->resolveForUserOrGuest($currentUser instanceof User ? $currentUser : null);
            $reason = $arguments['reason'] ?? null;

            Comments::$plugin->getReports()->toggle(
                (int)$commentId,
                (int)$identity->id,
                in_array($reason, array_column(Comments::$plugin->getFrontend()::reportReasons(), 'value'), true) ? $reason : null,
                $arguments['details'] ?? null,
            );
        } catch (UserError $e) {
            throw $e;
        } catch (\yii\base\UserException $e) {
            throw new UserError($e->getMessage());
        }

        return new Flag([
            'commentId' => (int)$commentId,
            'userId' => $userId,
        ]);
    }

    /**
     * Handles GraphQL mutation arguments to toggle comment subscription.
     */
    public function subscribeComment($source, array $arguments, $context, ResolveInfo $resolveInfo): string
    {
        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $commentId = $arguments['commentId'] ?? null;
        $ownerId = $arguments['ownerId'];
        $siteId = $arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;

        $userId = $currentUser->id ?? null;
        $owner = Craft::$app->getElements()->getElementById((int)$ownerId, null, (int)$siteId);

        if (!$owner || $owner instanceof CommentElement || !Comments::$plugin->getConversations()->getConfigForOwner((int)$ownerId, (int)$siteId)->enabled) {
            throw new UserError(Craft::t('comments', 'Comments are unavailable for this element.'));
        }

        if ($commentId && !$settings->allowsSubscriptionType(SubscriptionType::Replies)) {
            throw new UserError(Craft::t('comments', 'Per-comment subscriptions are not allowed.'));
        }

        if (!$commentId && !$settings->allowsSubscriptionType(SubscriptionType::Conversation)) {
            throw new UserError(Craft::t('comments', 'Discussion subscriptions are not allowed.'));
        }

        if (empty($currentUser)) {
            throw new UserError(Craft::t('comments', 'Must be logged in to subscribe.'));
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
                $subscriptions->subscribeToConversation((int)$conversation->id, (int)$identity->id);
            }

            $subscribed = $subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id);
        }

        return $subscribed ?
            Craft::t('comments', 'Subscribed to discussion.') :
            Craft::t('comments', 'Unsubscribed from discussion.');
    }

    /**
     * Runs the same workflow as every other way of moderating, for a user with the same permissions.
     */
    public function moderateComment($source, array $arguments, $context, ResolveInfo $resolveInfo): ?ElementInterface
    {
        $this->requireSchemaAction('comments', 'moderate');

        // A public schema needs no token, so a cross-site request could use a moderator's session.
        if (GqlHelper::isPublicSchema()) {
            throw new UserError(Craft::t('comments', 'You are not permitted to moderate this comment.'));
        }

        $comment = CommentElement::find()->id((int)$arguments['id'])->status(null)->siteId('*')->one();
        $moderation = Comments::$plugin->getModeration();
        $actor = Comments::$plugin->getService()->getUser();

        if (!$comment || !$actor || !$moderation->canModerateComment($actor, $comment)) {
            throw new UserError(Craft::t('comments', 'You are not permitted to moderate this comment.'));
        }

        if ($arguments['action'] === 'remove' && !$moderation->canRemoveComment($actor)) {
            throw new UserError(Craft::t('comments', 'You are not permitted to remove this comment.'));
        }

        $reason = $arguments['reason'] ?? null;

        try {
            return match ($arguments['action']) {
                'approve' => $moderation->approve($comment, $actor, $reason),
                'remove' => $moderation->remove($comment, $actor, $reason),
                'restore' => $moderation->restore($comment, $actor, $reason),
                'spam' => $moderation->markSpam($comment, $actor, $reason),
                'notSpam' => $moderation->markNotSpam($comment, $actor, $reason),
                default => throw new UserError(Craft::t('comments', 'Unknown moderation action.')),
            };
        } catch (\yii\base\UserException $e) {
            throw new UserError($e->getMessage());
        }
    }

    /**
     * Handles GraphQL mutation arguments to delete a comment.
     *
     * @throws Throwable
     */
    public function deleteComment($source, array $arguments, $context, ResolveInfo $resolveInfo): bool
    {
        $this->requireSchemaAction('comments', 'delete');

        $commentId = (int)$arguments['id'];
        $comment = CommentElement::find()->id($commentId)->siteId($arguments['siteId'] ?? '*')->status(null)->one();
        $currentUser = Comments::$plugin->getService()->getUser();

        if (!$comment) {
            return true;
        }

        $siteId = (int)$comment->siteId;

        if (!$currentUser instanceof User || (int)$comment->userId !== (int)$currentUser->id) {
            throw new UserError(Craft::t('comments', 'You may only delete your own comments.'));
        }

        try {
            // The user GraphQL authenticated, who may only have a token and no session.
            Comments::$plugin->getComments()->authorRemove($commentId, $siteId, $currentUser);
        } catch (\yii\base\UserException $e) {
            throw new UserError($e->getMessage());
        }

        return true;
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
                throw new Error('No such comment exists');
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

        throw new UserError(implode("\n", $validationErrors));
    }

    /**
     * The vote a mutation returns, from the interaction that's now current.
     */
    private function _voteModelFromInteraction(int $commentId, ?int $userId, ?\verbb\comments\enums\InteractionType $type): Vote
    {
        $vote = new Vote([
            'commentId' => $commentId,
            'userId' => $userId,
        ]);

        if ($type === \verbb\comments\enums\InteractionType::Upvote) {
            $vote->upvote = 1;
        } elseif ($type === \verbb\comments\enums\InteractionType::Downvote) {
            $vote->downvote = 1;
        }

        return $vote;
    }
}
