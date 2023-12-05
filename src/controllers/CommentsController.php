<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\Flag;
use verbb\comments\models\Subscribe;
use verbb\comments\models\Vote;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;

use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CommentsController extends Controller
{
    // Properties
    // =========================================================================

    protected array|bool|int $allowAnonymous = ['save', 'get-js-variables'];


    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        $settings = Comments::$plugin->getSettings();

        // Protect Voting/Flagging - only allowed when the config is set
        if ($settings->allowGuestFlagging) {
            $this->allowAnonymous['flag'] = 1;
        }

        if ($settings->allowGuestVoting) {
            $this->allowAnonymous['vote'] = 1;
        }

        return parent::beforeAction($action);
    }

    // Grab the required JS variables with a separate call
    // This is required when loading single comments async
    public function actionGetJsVariables(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $elementId = $request->getParam('elementId');
        $criteria = $request->getParam('criteria') ? Json::decode($request->getParam('criteria')) : [];

        $id = 'cc-w-' . $elementId;
        $jsVariables = Comments::$plugin->getComments()->getRenderJsVariables($id, $elementId, $criteria);

        return $this->asJson([
            'id' => '#' . $id,
            'settings' => $jsVariables,
        ]);
    }

    //
    // Control Panel
    //

    public function actionEditComment($commentId, string $siteHandle = null, Comment $comment = null): Response
    {
        if (!$siteHandle) {
            $siteHandle = $this->request->getParam('site', Craft::$app->getSites()->getCurrentSite()->handle);
        }

        $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);

        if (!$comment) {
            $comment = Comments::$plugin->getComments()->getCommentById($commentId, $site->id);
        }

        if (!$comment) {
            throw new NotFoundHttpException('Comment not found.');
        }

        $comment->setScenario(Comment::SCENARIO_CP);

        // Set the "Continue Editing" URL
        $siteSegment = Craft::$app->getIsMultiSite() && Craft::$app->getSites()->getCurrentSite()->id != $site->id ? "/{$site->handle}" : '';
        $continueEditingUrl = 'comments/{id}' . $siteSegment;

        return $this->renderTemplate('comments/comments/_edit', [
            'comment' => $comment,
            'continueEditingUrl' => $continueEditingUrl,
        ]);
    }

    public function actionSaveComment(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();
        $currentUser = Comments::$plugin->getService()->getUser();

        $commentId = $request->getParam('commentId');
        $siteId = (int)$request->getParam('siteId');

        // Ensure we only set a selection of attributes from the CP
        $comment = Comments::$plugin->getComments()->getCommentById($commentId, $siteId);
        $comment->status = $request->getParam('status', $comment->status);
        $comment->comment = $request->getParam('comment', $comment->comment);

        $comment->setFieldValuesFromRequest('fields');
        $comment->setScenario(Comment::SCENARIO_CP);

        // Is this another user’s comment?
        if ($comment->id && $comment->userId != $currentUser->id) {
            $this->requirePermission('comments-edit');

            if ($comment->status === Comment::STATUS_TRASHED) {
                $this->requirePermission('comments-trash');
            }
        }

        if (!Craft::$app->getElements()->saveElement($comment, true, false)) {
            $session->setError(Craft::t('comments', 'Couldn’t save comment.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'comment' => $comment,
                'errors' => $comment->getErrors(),
            ]);

            return null;
        }

        $session->setNotice(Craft::t('comments', 'Comment saved successfully.'));

        return $this->redirectToPostedUrl($comment);
    }

    public function actionDeleteComment(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        $commentId = $request->getParam('commentId');

        if (!Craft::$app->getElements()->deleteElementById($commentId)) {
            $session->setError(Craft::t('comments', 'Unable to delete comment.'));
        }

        $session->setNotice(Craft::t('comments', 'Comment deleted.'));

        return $this->redirectToPostedUrl();
    }

    //
    // Comments Front-End
    //

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $siteId = (int)$request->getParam('siteId');

        $currentSite = Craft::$app->getSites()->getCurrentSite();

        if ($siteId) {
            $currentSite = Craft::$app->getSites()->getSiteById($siteId);
        }

        $comment = $this->_setCommentFromPost();
        $comment->setScenario(Comment::SCENARIO_FRONT_END);
        $comment->setAction(Comment::ACTION_SAVE);

        if (!Craft::$app->getElements()->saveElement($comment, true, false)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'comment' => $comment,
                    'errors' => $comment->getErrors(),
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                'comment' => $comment,
                'errors' => $comment->getErrors(),
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            // Return some HTML with the template generated
            $html = Comments::$plugin->getComments()->renderComment($comment);
            $notice = '';

            if ($comment->status == Comment::STATUS_PENDING) {
                $notice = Craft::t('comments', 'Your comment has been posted and is under review.', [], $currentSite->language);
            }

            return $this->asJson([
                'success' => true,
                'id' => $comment->id,
                'comment' => $comment,
                'html' => $html,
                'notice' => $notice,
            ]);
        }

        return $this->redirectToPostedUrl();
    }

    public function actionFlag(): Response
    {
        $this->requirePostRequest();

        $currentUser = Comments::$plugin->getService()->getUser();
        $request = Craft::$app->getRequest();

        $commentId = $request->getParam('commentId');

        $userId = $currentUser->id ?? null;

        $flag = Comments::$plugin->getFlags()->getFlagByUser($commentId, $userId) ?? new Flag();
        $flag->commentId = $commentId;

        // Okay if no user here, although required, the model validation will pick it up
        $flag->userId = $userId;

        if (!Comments::$plugin->getFlags()->toggleFlag($flag)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'flag' => $flag,
                    'errors' => $flag->getErrors(),
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                'flag' => $flag,
                'errors' => $flag->getErrors(),
            ]);

            return $this->redirect($request->referrer);
        }

        if ($request->getAcceptsJson()) {
            $comment = Comments::$plugin->getComments()->getCommentById($commentId);
            $hasFlagged = Comments::$plugin->getFlags()->hasFlagged($comment, $currentUser);
            $message = $hasFlagged ? 'Comment has been flagged.' : 'Comment has been un-flagged.';

            return $this->asJson([
                'success' => true,
                'flag' => $flag,
                'notice' => Craft::t('comments', $message),
            ]);
        }

        return $this->redirect($request->referrer);
    }

    public function actionVote(): Response
    {
        $this->requirePostRequest();

        $currentUser = Comments::$plugin->getService()->getUser();
        $request = Craft::$app->getRequest();

        $upvote = $request->getParam('upvote');
        $downvote = $request->getParam('downvote');
        $commentId = $request->getParam('commentId');

        $userId = $currentUser->id ?? null;

        $vote = Comments::$plugin->getVotes()->getVoteByUser($commentId, $userId) ?? new Vote();
        $vote->commentId = $commentId;

        if ($upvote) {
            // Reset like no votes were taken!
            $vote->upvote = ($vote->downvote) ? null : 1;
            $vote->downvote = null;
        } else {
            // Reset like no votes were taken!
            $vote->downvote = ($vote->upvote) ? null : 1;
            $vote->upvote = null;
        }

        // Okay if no user here, although required, the model validation will pick it up
        $vote->userId = $userId;

        if (!Comments::$plugin->getVotes()->saveVote($vote)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'vote' => $vote,
                    'errors' => $vote->getErrors(),
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                'vote' => $vote,
                'errors' => $vote->getErrors(),
            ]);

            return $this->redirect($request->referrer);
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'vote' => $vote,
            ]);
        }

        return $this->redirect($request->referrer);
    }

    public function actionTrash(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $comment = $this->_setCommentFromPost();
        $comment->status = Comment::STATUS_TRASHED;
        $comment->setScenario(Comment::SCENARIO_FRONT_END);
        $comment->setAction(Comment::ACTION_DELETE);

        if (!Craft::$app->getElements()->saveElement($comment, true, false)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'comment' => $comment,
                    'errors' => $comment->getErrors(),
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                'comment' => $comment,
                'errors' => $comment->getErrors(),
            ]);

            return $this->redirect($request->referrer);
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'comment' => $comment,
                'id' => $comment->id,
            ]);
        }

        return $this->redirect($request->referrer);
    }

    public function actionSubscribe(): Response
    {
        $currentUser = Comments::$plugin->getService()->getUser();
        $request = Craft::$app->getRequest();

        $ownerId = $request->getParam('ownerId');
        $siteId = (int)$request->getParam('siteId');
        $commentId = $request->getParam('commentId', null);
        $userId = $currentUser->id ?? null;

        $subscribe = Comments::$plugin->getSubscribe()->getSubscribe($ownerId, $siteId, $userId, $commentId) ?? new Subscribe();
        $subscribe->ownerId = $ownerId;
        $subscribe->ownerSiteId = $siteId;
        $subscribe->commentId = $commentId;

        // Okay if no user here, although required, the model validation will pick it up
        $subscribe->userId = $userId;

        if (!Comments::$plugin->getSubscribe()->toggleSubscribe($subscribe)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'subscribe' => $subscribe,
                    'errors' => $subscribe->getErrors(),
                    'message' => Craft::t('comments', 'Unable to update subscribe status.'),
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                'subscribe' => $subscribe,
                'errors' => $subscribe->getErrors(),
            ]);

            return $this->redirect($request->referrer);
        }

        if ($request->getAcceptsJson()) {
            $message = $subscribe->subscribed ? 'Subscribed to discussion.' : 'Unsubscribed from discussion.';

            return $this->asJson([
                'success' => true,
                'subscribe' => $subscribe,
                'notice' => Craft::t('comments', $message),
            ]);
        }

        return $this->redirect($request->referrer);
    }


    // Private Methods
    // =========================================================================

    private function _setCommentFromPost(): Comment
    {
        $currentUser = Comments::$plugin->getService()->getUser();
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();
        $settings = Comments::$plugin->getSettings();

        $commentId = $request->getParam('commentId');
        $newParentId = $request->getParam('newParentId');
        $siteId = (int)$request->getParam('siteId', Craft::$app->getSites()->getCurrentSite()->id);

        if ($commentId) {
            $comment = Comments::$plugin->getComments()->getCommentById($commentId, $siteId);

            if (!$comment) {
                throw new Exception(Craft::t('comments', 'No comment with the ID “{id}”', ['id' => $commentId]));
            }
        } else {
            $comment = new Comment();
        }

        $ownerSiteId = (int)$request->getParam('ownerSiteId', $comment->ownerSiteId);

        // Backward compatibility
        $ownerId = $request->getParam('ownerId');
        $elementId = $request->getParam('elementId');

        $comment->ownerId = $ownerId ?? $elementId ?? $comment->ownerId;
        $comment->ownerSiteId = $ownerSiteId ?: Craft::$app->getSites()->getCurrentSite()->id;
        $comment->siteId = (int)$request->getParam('siteId', $comment->siteId);

        if (!$comment->userId) {
            $comment->userId = ($currentUser) ? $currentUser->id : null;
        }

        // Other handy stuff
        $comment->url = $request->getParam('url', $request->referrer);
        $comment->ipAddress = $request->getUserIP();
        $comment->userAgent = $request->getUserAgent();

        // Handle the fields
        $comment->name = $request->getParam('fields.name', $comment->name);
        $comment->email = $request->getParam('fields.email', $comment->email);
        $comment->comment = $request->getParam('fields.comment', $comment->comment);

        // Set any other field content
        $comment->setFieldValuesFromRequest('fields');

        // Set any new comment to be pending if requireModeration is true
        if ($settings->doesRequireModeration()) {
            $comment->status = Comment::STATUS_PENDING;
        } else {
            $comment->status = Comment::STATUS_APPROVED;
        }

        if ($newParentId) {
            $comment->newParentId = $newParentId;
        }

        return $comment;
    }


}
