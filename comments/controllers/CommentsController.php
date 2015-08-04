<?php
namespace Craft;

class CommentsController extends BaseController
{
    protected $allowAnonymous = array('actionAdd');

    //
    // Control Panel
    //

    public function actionPermissions()
    {
        $settings = craft()->plugins->getPlugin('comments')->getSettings();

        $this->renderTemplate('comments/permissions', array(
            'settings' => $settings,
        ));
    }

    public function actionEditTemplate(array $variables = array())
    {
        $commentId = $variables['commentId'];
        $comment = craft()->comments->getCommentById($commentId);

        $variables['comment'] = $comment;

        $this->renderTemplate('comments/edit', $variables);
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

    //
    // Comments Front-End
    //

    public function actionEdit()
    {
        $this->requirePostRequest();

        $commentId = craft()->request->getRequiredPost('commentId');
        $comment = craft()->comments->getCommentById($commentId);

        $fields = craft()->request->getPost('fields');
        $comment->comment = array_key_exists('comment', $fields) ? $fields['comment'] : null;

        $result = craft()->comments->saveComment($comment);

        craft()->comments->response($result);
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

            craft()->comments->response($result);
        }
    }

    public function actionAdd()
    {
        $this->requirePostRequest();

        $settings = craft()->plugins->getPlugin('comments')->getSettings();
        $fieldSettings = craft()->comments_settings->getFieldSettings(craft()->request->getPost('elementId'));
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
        if ($fieldSettings->requireModeration) {
            $commentModel->status = Comments_CommentModel::PENDING;
        } else {
            $commentModel->status = Comments_CommentModel::APPROVED;
        }


        // Let's check for spam!
        if (!craft()->comments_protect->verifyFields()) {
            craft()->comments->response($this, array('error' => 'Form validation failed. Marked as spam.'));
        }

        // Check against any security keywords we've set. Can be words, IP's, User Agents, etc.
        if (!craft()->comments_security->checkSecurityPolicy($commentModel)) {
            craft()->comments->response($this, array('error' => 'Comment blocked due to security policy.'));
        }

        // Protect against Anonymous submissions, if turned off
        if (!$fieldSettings->allowAnonymous && !$commentModel->userId) {
            craft()->comments->response($this, array('error' => 'Must be logged in to comment.'));
        }

        // Is someone sneakily making a comment on a non-allowed element through some black magic POST-ing?
        $element = craft()->elements->getElementById($commentModel->elementId);
        if (!craft()->comments_settings->checkPermissions($element)) {
            craft()->comments->response($this, array('error' => 'Comments are disabled for this element.'));
        }

        // Must have an actual comment
        if (!$commentModel->comment) {
            craft()->comments->response($this, array('error' => 'Comment must not be blank.'));
        }

        // Is this user logged in? Or they've provided user/email?
        if ($commentModel->userId || ($commentModel->name && $commentModel->email)) {
            $result = craft()->comments->saveComment($commentModel);

            craft()->comments->response($this, $result);
        } else {
            craft()->comments->response($this, array('error' => 'Must be logged in, or supply Name/Email to comment.'));
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

            craft()->comments->response($this, $result);
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
            craft()->comments->response($this, array('error' => 'Cannot make vote.'));
        }

        if ($user) {
            $model->userId = $user->id;

            $result = craft()->comments_vote->saveVote($model);

            craft()->comments->response($this, $result);
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
            craft()->comments->response($this, array('error' => 'Cannot make vote.'));
        }

        if ($user) {
            $model->userId = $user->id;

            $result = craft()->comments_vote->saveVote($model);

            craft()->comments->response($this, $result);
        }
    }


}