<?php
namespace Craft;

class CommentsController extends BaseController
{
    protected $allowAnonymous = array('actionAdd');

    public function actionPermissions()
    {
        $plugin = craft()->plugins->getPlugin('comments');

        $this->renderTemplate('comments/permissions', array(
            'settings' => $plugin->getSettings(),
        ));
    }

    public function actionEditTemplate(array $variables = array())
    {
        $commentId = $variables['commentId'];
        $comment = craft()->comments->getCommentById($commentId);

        $variables['comment'] = $comment;

        $this->renderTemplate('comments/edit', $variables);
    }

    public function actionEdit()
    {
        $this->requirePostRequest();

        $commentId = craft()->request->getRequiredPost('commentId');
        $comment = craft()->comments->getCommentById($commentId);

        $fields = craft()->request->getPost('fields');
        $comment->comment = array_key_exists('comment', $fields) ? $fields['comment'] : null;

        $result = craft()->comments->saveComment($comment);

        if (craft()->request->isAjaxRequest()) {
            $this->returnJson($result);
        }
    }

    public function actionDelete()
    {
        $user = craft()->userSession->getUser();
        
        $commentId = craft()->request->getQuery('id');
        $comment = craft()->comments->getCommentById($commentId);
        $comment->status = Comments_CommentModel::TRASHED;

        // Only logged in users can delete
        if ($user) {

            // We're actually only changing this status to 'trashed'
            $result = craft()->comments->deleteComment($comment);

            if ($result === true) {
                if (craft()->request->isAjaxRequest()) {
                    $this->returnJson($result);
                }
            } else {
                craft()->comments->redirect($result['object']);
            }
        }
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $commentId = craft()->request->getRequiredPost('commentId');
        $comment = craft()->request->getRequiredPost('comment');
        $status = craft()->request->getRequiredPost('status');

        $commentModel = craft()->comments->getCommentById($commentId);
        $commentModel->status = $status;
        $commentModel->comment = $comment;

        if ($result = craft()->comments->saveComment($commentModel)) {
            craft()->userSession->setNotice(Craft::t('Comment saved successfully.'));
        } else {
            craft()->userSession->setError($result);
        }
    }

    public function actionAdd()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $plugin = craft()->plugins->getPlugin('comments');
        $user = craft()->userSession->getUser();

        $commentModel = new Comments_CommentModel();

        $commentModel->elementId = craft()->request->getPost('elementId');
        $commentModel->elementType = craft()->elements->getElementTypeById($commentModel->elementId);
        $commentModel->userId = ($user) ? $user->id : null;
        $commentModel->parentId = craft()->request->getPost('parentId');
        $commentModel->structureId = craft()->comments->getStructureId();
        
        // Other handy stuff
        $commentModel->url = craft()->request->urlReferrer;
        $commentModel->ipAddress = craft()->request->getUserHostAddress();
        $commentModel->userAgent = craft()->request->getUserAgent();
            
        // Handle the fields
        $fields = craft()->request->getPost('fields');
        $commentModel->name = array_key_exists('name', $fields) ? $fields['name'] : null;
        $commentModel->email = array_key_exists('email', $fields) ? $fields['email'] : null;
        $commentModel->comment = array_key_exists('comment', $fields) ? $fields['comment'] : null;

        // Set any new comment to be pending if requireModeration is true
        if ($plugin->getSettings()->requireModeration) {
            $commentModel->status = Comments_CommentModel::PENDING;
        } else {
            $commentModel->status = Comments_CommentModel::APPROVED;
        }


        // Let's check for spam!
        if (!craft()->comments_protect->verifyFields()) {
            $this->returnJson(array('error' => 'Form validation failed. Marked as spam.'));
        }

        // Protect against Anonymous submissions, if turned off
        if (!$plugin->getSettings()->allowAnonymous && !$commentModel->userId) {
            $this->returnJson(array('error' => 'Must be logged in to comment.'));
        }

        // Is someone sneakily making a comment on a non-allowed element through some black magic POST-ing?
        $element = craft()->elements->getElementById($commentModel->elementId);
        if (!craft()->comments->checkPermissions($element)) {
            $this->returnJson(array('error' => 'Comments are disabled for this element.'));
        }

        // Must have an actual comment
        if (!$commentModel->comment) {
            $this->returnJson(array('error' => 'Comment must not be blank.'));
        }

        // Is this user logged in? Or they've provided user/email?
        if ($commentModel->userId || ($commentModel->name && $commentModel->email)) {
            $result = craft()->comments->saveComment($commentModel);

            if (!array_key_exists('error', $result)) {
                $this->returnJson($result);
            } else {
                $this->returnJson($result);
            }
        } else {
            $this->returnJson(array('error' => 'Must be logged in, or supply Name/Email to comment.'));
        }
    }

    public function actionFlagComment()
    {
        $user = craft()->userSession->getUser();
        $flagModel = new Comments_FlagModel();

        $flagModel->commentId = craft()->request->getQuery('id');

        // Only logged in users can flag
        if ($user) {
            $flagModel->userId = $user->id;

            $result = craft()->comments_flag->saveFlag($flagModel);

            if ($result === true) {
                if (craft()->request->isAjaxRequest()) {
                    $this->returnJson($result);
                } else {
                    craft()->comments->redirect($result['object']);
                }
            }
        }
    }

    public function actionUpvoteComment()
    {
        $user = craft()->userSession->getUser();
        $model = new Comments_VoteModel();

        $model->commentId = craft()->request->getQuery('id');
        $model->upvote = '1';

        // Check if the user in question can vote
        $comment = craft()->comments->getCommentById($model->commentId);

        if (!$comment->canUpVote()) {
            $this->returnJson(array('error' => 'Cannot make vote.'));
        }

        if ($user) {
            $model->userId = $user->id;

            $result = craft()->comments_vote->saveVote($model);

            if (craft()->request->isAjaxRequest()) {
                $this->returnJson($result);
            } else {
                craft()->comments->redirect($result['object']);
            }
        }

    }

    public function actionDownvoteComment()
    {
        $user = craft()->userSession->getUser();
        $model = new Comments_VoteModel();

        $model->commentId = craft()->request->getQuery('id');
        $model->downvote = '1';

        // Check if the user in question can vote
        $comment = craft()->comments->getCommentById($model->commentId);

        if (!$comment->canDownVote()) {
            $this->returnJson(array('error' => 'Cannot make vote.'));
        }

        if ($user) {
            $model->userId = $user->id;

            $result = craft()->comments_vote->saveVote($model);

            if (craft()->request->isAjaxRequest()) {
                $this->returnJson($result);
            } else {
                craft()->comments->redirect($result['object']);
            }
        }
    }


}