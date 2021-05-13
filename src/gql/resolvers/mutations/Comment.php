<?php
namespace verbb\comments\gql\resolvers\mutations;

use craft\base\ElementInterface;
use verbb\comments\Comments;
use verbb\comments\elements\Comment as CommentElement;
use verbb\comments\elements\db\CommentQuery;
use verbb\comments\models\Flag;
use verbb\comments\models\Subscribe;
use verbb\comments\models\Vote;

use craft\errors\GqlException;
use craft\errors\SiteNotFoundException;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\StructureMutationTrait;
use Craft;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Error\UserError;

/**
 * Implements custom mutation methods take GraphQL mutations and make stuff happen.
 *
 * @package verbb\comments\gql\resolvers\mutations
 */
class Comment extends ElementMutationResolver
{
    use StructureMutationTrait;

    /* @inheritdoc */
    protected $immutableAttributes = ['id', 'uid', 'userId'];

    /**
     * Handles GraphQL mutation arguments to either create or update a comment.
     *
     * @param             $source
     * @param array       $arguments    GraphQL query arguments in key-value pairs
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @return ElementInterface|null
     * @throws GqlException|Error|SiteNotFoundException
     */
    public function saveComment($source, array $arguments, $context, ResolveInfo $resolveInfo)
    {
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']);
        $elementService = Craft::$app->getElements();
        $settings = Comments::$plugin->getSettings();

        if ($canIdentify) {
            $this->requireSchemaAction('comments', 'save');
        } else {
            $this->requireSchemaAction('comments', 'edit');
        }

        $comment = $this->getCommentElement($arguments);
        $comment = $this->populateElementWithData($comment, $arguments);

        // Set any new comment to be pending if requireModeration is true
        if ($settings->requireModeration) {
            $comment->status = CommentElement::STATUS_PENDING;
        } else {
            $comment->status = CommentElement::STATUS_APPROVED;
        }

        if (isset($arguments['newParentId'])) {
            $comment->newParentId = $arguments['newParentId'];
        }

        $comment->setScenario(CommentElement::SCENARIO_LIVE);
        $comment = $this->saveElement($comment);

        if ($comment->hasErrors()) {
            $validationErrors = [];

            foreach ($comment->getFirstErrors() as $attribute => $errorMessage) {
                $validationErrors[] = $errorMessage;
            }

            throw new UserError(implode("\n", $validationErrors));
        }

        return $elementService->getElementById($comment->id, CommentElement::class);
    }

    /**
     * Handles GraphQL mutation arguments to record a comment upvote or downvote.
     *
     * @param             $source
     * @param array       $arguments    GraphQL query arguments in key-value pairs
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @return ElementInterface|null
     */
    public function voteComment($source, array $arguments, $context, ResolveInfo $resolveInfo): Vote
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $commentId = $arguments['id'];
        $upvote = isset($arguments['upvote']) && $arguments['upvote'];
        $downvote = isset($arguments['downvote']) && $arguments['downvote'];

        $userId = $currentUser->id ?? null;

        $vote = Comments::$plugin->getVotes()->getVoteByUser($commentId, $userId) ?? new Vote();
        $vote->commentId = $commentId;

        if ($upvote) {
            // Reset like no votes were taken!
            if ($vote->downvote) {
                $vote->downvote = null;
                $vote->upvote = null;
            } else {
                $vote->downvote = null;
                $vote->upvote = '1';
            }
        } else {
            // Reset like no votes were taken!
            if ($vote->upvote) {
                $vote->downvote = null;
                $vote->upvote = null;
            } else {
                $vote->downvote = '1';
                $vote->upvote = null;
            }
        }

        // Okay if no user here, although required, the model validation will pick it up
        $vote->userId = $userId;

        Comments::$plugin->getVotes()->saveVote($vote);

        if ($vote->hasErrors()) {
            $validationErrors = [];

            foreach ($vote->getFirstErrors() as $attribute => $errorMessage) {
                $validationErrors[] = $errorMessage;
            }

            throw new UserError(implode("\n", $validationErrors));
        }

        return $vote;
    }

    /**
     * Handles GraphQL mutation arguments to flag a comment.
     *
     * @param             $source
     * @param array       $arguments    GraphQL query arguments in key-value pairs
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @return ElementInterface|null
     */
    public function flagComment($source, array $arguments, $context, ResolveInfo $resolveInfo)
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $commentId = $arguments['id'];

        $userId = $currentUser->id ?? null;

        $flag = Comments::$plugin->getFlags()->getFlagByUser($commentId, $userId) ?? new Flag();
        $flag->commentId = $commentId;

        // Okay if no user here, although required, the model validation will pick it up
        $flag->userId = $userId;

        Comments::$plugin->getFlags()->toggleFlag($flag);

        if ($flag->hasErrors()) {
            $validationErrors = [];

            foreach ($flag->getFirstErrors() as $attribute => $errorMessage) {
                $validationErrors[] = $errorMessage;
            }

            throw new UserError(implode("\n", $validationErrors));
        }

        return $flag;
    }

    /**
     * Handles GraphQL mutation arguments to toggle comment subscription.
     *
     * @param             $source
     * @param array       $arguments    GraphQL query arguments in key-value pairs
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @return string
     */
    public function subscribeComment($source, array $arguments, $context, ResolveInfo $resolveInfo): string
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $commentId = $arguments['id'] ?? null;
        $ownerId = $arguments['ownerId'];
        $siteId = $arguments['siteId'] ?? null;

        $userId = $currentUser->id ?? null;

        $subscribe = Comments::$plugin->getSubscribe()->getSubscribe($ownerId, $siteId, $userId, $commentId) ?? new Subscribe();
        $subscribe->ownerId = $ownerId;
        $subscribe->ownerSiteId = $siteId;
        $subscribe->commentId = $commentId;

        // Okay if no user here, although required, the model validation will pick it up
        $subscribe->userId = $userId;

        Comments::$plugin->getSubscribe()->toggleSubscribe($subscribe);

        if ($subscribe->hasErrors()) {
            $validationErrors = [];

            foreach ($subscribe->getFirstErrors() as $attribute => $errorMessage) {
                $validationErrors[] = $errorMessage;
            }

            throw new UserError(implode("\n", $validationErrors));
        }

        return $subscribe->subscribed ? 'Subscribed to discussion.' : 'Unsubscribed from discussion.';
    }

    /**
     * Handles GraphQL mutation arguments to delete a comment.

     * @param             $source
     * @param array       $arguments    GraphQL query arguments in key-value pairs
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @return bool
     * @throws \Throwable
     */
    public function deleteComment($source, array $arguments, $context, ResolveInfo $resolveInfo): bool
    {
        $commentId = $arguments['id'];
        $elementService = Craft::$app->getElements();
        $comment = $elementService->getElementById($commentId);

        if (!$comment) {
            return true;
        }

        $this->requireSchemaAction('comments', 'delete');
        $elementService->deleteElementById($commentId);

        return true;
    }

    /**
     * Returns a new or existing Comment element based on the provided query parameters.
     *
     * @param array $arguments GraphQL query arguments in key-value pairs
     * @return CommentElement
     * @throws Error
     * @throws SiteNotFoundException
     */
    protected function getCommentElement(array $arguments): CommentElement
    {
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']);
        $this->requireSchemaAction('comments', $canIdentify ? 'save' : 'edit');
        $elementService = Craft::$app->getElements();

        if ($canIdentify) {
            $siteId = $arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;
            $commentQuery = $elementService->createElementQuery(CommentElement::class)
                ->anyStatus()
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
     * Attempts to use GraphQL query arguments to set the appropriate ID on a Craft element query.
     *
     * If the arguments don’t contain a UID or ID, sets ID to -1 so the element query doesn’t return
     * any results.
     *
     * @param CommentQuery $commentQuery
     * @param array        $arguments     GraphQL query arguments in key-value pairs
     * @return CommentQuery
     */
    protected function identifyComment(CommentQuery $commentQuery, array $arguments): CommentQuery
    {
        if (!empty($arguments['uid'])) {
            $commentQuery->uid($arguments['uid']);
        } else if (!empty($arguments['id'])) {
            $commentQuery->id($arguments['id']);
        } else {
            // Unable to identify, make sure nothing is returned.
            $commentQuery->id(-1);
        }

        return $commentQuery;
    }
}
