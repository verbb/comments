<?php
namespace verbb\comments\gql\resolvers\mutations;

use craft\base\ElementInterface;
use verbb\comments\Comments;
use verbb\comments\elements\Comment as CommentElement;
use verbb\comments\elements\db\CommentQuery;

use craft\errors\GqlException;
use craft\errors\SiteNotFoundException;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\StructureMutationTrait;
use Craft;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Error\UserError;

class Comment extends ElementMutationResolver
{
    use StructureMutationTrait;

    /* @inheritdoc */
    protected $immutableAttributes = ['id', 'uid', 'userId'];

    /**
     * @param             $source
     * @param array       $arguments
     * @param             $context
     * @param ResolveInfo $resolveInfo
     * @return ElementInterface|null
     * @throws GqlException|Error
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

    public function voteComment($source, array $arguments, $context, ResolveInfo $resolveInfo)
    {
        // TODO: follow CommentsController::actionVote()
    }

    public function flagComment($source, array $arguments, $context, ResolveInfo $resolveInfo)
    {
        // TODO: follow CommentsController::actionFlag()
    }

    /**
     * @param             $source
     * @param array       $arguments
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
    public function getCommentElement($arguments)
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
