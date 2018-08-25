<?php
namespace Craft;

class CommentsController extends BaseController
{
    // Properties
    // =========================================================================

    protected $allowAnonymous = array('actionSave');

    
    // Public Methods
    // =========================================================================

    //
    // Control Panel
    //

    public function actionPermissions()
    {
        $settings = craft()->comments->getSettings();

        $sourceOptions = array();
        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = array('label' => $source->name, 'value' => $source->id);
        }

        $this->renderTemplate('comments/permissions', array(
            'settings' => $settings,
            'sourceOptions' => $sourceOptions,
        ));
    }

    public function actionSettings()
    {
        $settings = craft()->comments->getSettings();

        $this->renderTemplate('comments/settings', array(
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

    public function actionSaveComment()
    {
        $this->requirePostRequest();

        $commentId = craft()->request->getRequiredPost('commentId');

        $model = craft()->comments->getCommentById($commentId);
        $model->status = craft()->request->getPost('status');
        $model->comment = craft()->request->getPost('comment');

        if ($result = craft()->comments->saveComment($model, false)) {
            craft()->userSession->setNotice(Craft::t('Comment saved successfully.'));
        } else {
            craft()->userSession->setError($result);
        }
    }

    public function actionDeleteComment()
    {
        $this->requirePostRequest();

        $commentId = craft()->request->getRequiredPost('commentId');
        $model = craft()->comments->getCommentById($commentId);

        if ($result = craft()->comments->deleteComment($model)) {
            craft()->userSession->setNotice(Craft::t('Comment deleted.'));
            $this->redirectToPostedUrl($model);
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
        $model = craft()->comments->getCommentById($commentId);

        $model->comment = craft()->request->getPost('fields.comment');

        // Validate the comment - includes all security/validation checks
        if ($model->validate()) {
            // And some extra checks specifically for editing
            if ($model->canEdit()) {
                if (craft()->comments->saveComment($model)) {
                    $this->_response(array('success' => true));
                }
            }
        }

        $this->_response(array('error' => 'Cannot edit comment.'));
    }

    public function actionTrash()
    {
        $commentId = craft()->request->getQuery('id');
        $model = craft()->comments->getCommentById($commentId);
        $model->status = Comments_CommentModel::TRASHED;

        // Make sure we have permission to trash this comment
        if ($model->canTrash()) {
            // Change the status to 'trashed'
            $result = craft()->comments->trashComment($model);

            $this->_response($result);
        }

        $this->_response(array('error' => 'Cannot trash comment.'));
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $settings = craft()->comments->getSettings();
        $user = craft()->userSession->getUser();

        $model = new Comments_CommentModel();

        $model->elementId = craft()->request->getPost('elementId');
        $model->elementType = craft()->elements->getElementTypeById($model->elementId);
        $model->userId = ($user) ? $user->id : null;
        $model->parentId = craft()->request->getPost('parentId');
        $model->structureId = craft()->comments->getStructureId();
        
        // Other handy stuff
        $model->url = craft()->request->urlReferrer;
        $model->ipAddress = craft()->request->getUserHostAddress();
        $model->userAgent = craft()->request->getUserAgent();
            
        // Handle the fields
        $model->name = craft()->request->getPost('fields.name');
        $model->email = craft()->request->getPost('fields.email');
        $model->comment = craft()->request->getPost('fields.comment');
        
        // Set any new comment to be pending if requireModeration is true
        if ($settings->requireModeration) {
            $model->status = Comments_CommentModel::PENDING;
        } else {
            $model->status = Comments_CommentModel::APPROVED;
        }

        // Validate the comment - includes all security/validation checks
        if ($model->validate()) {
            if (craft()->comments->saveComment($model)) {
                $html = craft()->comments->getCommentHtml($model);
                
                $this->_response(array('success' => true, 'comment' => $model, 'html' => $html));
            }
        }

        // Set our own variable to this comment - useful in allowing model errors
        // to be available in our templates
        craft()->comments->activeComment = $model;
    }

    public function actionFlagComment()
    {
        $user = craft()->userSession->getUser();
        $flagModel = new Comments_FlagModel();

        $flagModel->commentId = craft()->request->getQuery('id');

        // Only logged in users can flag
        if ($user) {
            $flagModel->userId = $user->id;

            $this->_response(craft()->comments_flag->saveFlag($flagModel));
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
            $this->_response(array('error' => 'Cannot make vote.'));
        }

        if ($user) {
            $model->userId = $user->id;

            $this->_response(craft()->comments_vote->saveVote($model));
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
            $this->_response(array('error' => 'Cannot make vote.'));
        }

        if ($user) {
            $model->userId = $user->id;

            $this->_response(craft()->comments_vote->saveVote($model));
        }
    }







    // Private Methods
    // =========================================================================

    private function _response($model = null)
    {
        // Handle Ajax response
        if (craft()->request->isAjaxRequest()) {
            $this->returnJson($model);
        } else {
            $this->_redirect($model);
        }
    }

    private function _redirect($model)
    {
        $url = craft()->request->getPost('redirect');

        if ($url === null) {
            $url = craft()->request->getParam('return');

            if ($url === null) {
                $url = craft()->request->getUrlReferrer();

                if ($url === null) {
                    $url = '/';
                }
            }
        }

        craft()->request->redirect($url);
    }




    // Deprecated Methods
    // =========================================================================

    public function actionDelete()
    {
        craft()->deprecator->log('CommentsController::actionDelete():renamed', 'The "comments/delete" controller action has been deprecated. Please use "comments/trash" instead.');
    }

}
