<?php
namespace Craft;

class CommentsService extends BaseApplicationComponent
{
    // Properties
    // =========================================================================

    public $activeComment;


    // Public Methods
    // =========================================================================

    public function getPlugin()
    {
        return craft()->plugins->getPlugin('comments');
    }

    public function getSettings()
    {
        return $this->getPlugin()->getSettings();
    }

    public function getCriteria(array $attributes = array())
    {
        return craft()->elements->getCriteria('Comments_Comment', $attributes);
    }

    public function getAllComments()
    {
        return $this->getCriteria(array('order' => 'dateCreated'))->find();
    }

    public function getCommentById($commentId)
    {
        return $this->getCriteria(array('limit' => 1, 'id' => $commentId))->first();
    }

    public function getCommentModels($criteria = null)
    {
        $records = Comments_CommentRecord::model()->ordered()->findAll();
        return Comments_CommentModel::populateModels($records, 'id');
    }

    public function getTotalComments($elementId)
    {
        $total = Comments_CommentRecord::model()->countByAttributes(array(
            'elementId' => $elementId
        ));

        return $total;
    }

    public function getElementsWithComments()
    {
        $criteria = new \CDbCriteria();
        $criteria->group = 'elementId';

        $comments = Comments_CommentRecord::model()->findAll($criteria);

        $elements = array();
        foreach ($comments as $comment) {
            $elements[] = craft()->elements->getElementById($comment->elementId);
        }

        return $elements;
    }

    public function getStructureId()
    {
        return $this->getSettings()->structureId;
    }

    public function saveComment(Comments_CommentModel $comment, $validate = true)
    {
        $settings = $this->getSettings();
        $isNewComment = !$comment->id;

        // Check for parent Comments
        $hasNewParent = $this->_checkForNewParent($comment);

        if ($hasNewParent) {
            if ($comment->parentId) {
                $parentComment = $this->getCommentById($comment->parentId);

                if (!$parentComment) {
                    throw new Exception(Craft::t('No comment exists with the ID “{id}”.', array('id' => $comment->parentId)));
                }
            } else {
                $parentComment = null;
            }

            $comment->setParent($parentComment);
        }

        // Get the comment record
        if (!$isNewComment) {
            $commentRecord = Comments_CommentRecord::model()->findById($comment->id);

            if (!$commentRecord) {
                throw new Exception(Craft::t('No comment exists with the ID “{id}”.', array('id' => $comment->id)));
            }
        } else {
            $commentRecord = new Comments_CommentRecord();
        }

        // Load in all the attributes from the Comment model into this record
        $commentRecord->setAttributes($comment->getAttributes(), false);

        // Fire an 'onBeforeSaveComment' event
        $event = new Event($this, array('comment' => $comment));
        $this->onBeforeSaveComment($event);

        // Allow event to cancel comment saving
        if (!$event->performAction) {
            return false;
        }

        // Validate - unless specifically opting out (for CP mostly)
        if ($validate) {
            if (!$comment->validate()) {
                return array('error' => $comment->getErrors());
            }
        }

        if (!craft()->elements->saveElement($comment)) {
            return array('error' => $comment->getErrors());
        }

        // Now that we have an element ID, save it on the other stuff
        if ($isNewComment) {
            $commentRecord->id = $comment->id;

            // Should we send a Notification email to the author of this comment?
            if ($settings->notificationAuthorEnabled) {
                $this->_sendAuthorNotificationEmail($comment);
            }

            // If a reply to another comment, should we send a Notification email 
            // to the author of the original comment?
            if ($settings->notificationReplyEnabled && $comment->parentId) {
                $this->_sendReplyNotificationEmail($comment);
            }
        }

        // Save the actual comment
        $commentRecord->save(false);

        // Has the parent changed?
        if ($hasNewParent) {
            if (!$comment->parentId) {
                craft()->structures->appendToRoot($this->getStructureId(), $comment);
            } else {
                craft()->structures->append($this->getStructureId(), $comment, $parentComment);
            }
        }

        // Fire an 'onSaveComment' event
        $this->onSaveComment(new Event($this, array('comment' => $comment)));

        // If this comment has been trashed, via the element status in the CP, make sure we trigger events
        if ($comment->status == Comments_CommentModel::TRASHED) {
            // Fire an 'onTrashComment' event
            $this->onTrashComment(new Event($this, array('comment' => $comment)));
        }

        return $comment;
    }

    public function trashComment(Comments_CommentModel $comment)
    {
        $commentRecord = Comments_CommentRecord::model()->findById($comment->id);

        // Load in all the attributes from the Comment model into this record
        $commentRecord->status = $comment->status;

        // Fire an 'onBeforeTrashComment' event
        $event = new Event($this, array('comment' => $comment));
        $this->onBeforeTrashComment($event);

        // Allow event to cancel comment saving
        if (!$event->performAction) {
            return false;
        }

        $commentRecord->save(false);

        // Fire an 'onTrashComment' event
        $this->onTrashComment(new Event($this, array('comment' => $comment)));

        return true;
    }

    public function getActiveComment()
    {
        if (isset($this->activeComment)) {
            return $this->activeComment;
        } else {
            return new Comments_CommentModel();
        }
    }

    public function getCommentElementHtml(&$context)
    {
        if (!isset($context['element'])) {
            return;
        }

        // Only do this for a Comment ElementType
        if ($context['element']->getElementType() == 'Comments_Comment') {
            $user = craft()->users->getUserById($context['element']->userId);

            if ($user == null) {
                $userName = $context['element']->name;
            } else {
                $url = UrlHelper::getCpUrl('users/' . $user->id);
                $userName = $user->getFriendlyName();
            }

            $html = '<div class="comment-block">';
            $html .= '<span class="status ' . $context['element']->status . '"></span>';
            $html .= '<a href="' . $context['element']->getCpEditUrl() . '">';
            $html .= '<span class="username">' . htmlspecialchars($userName) . '</span>';
            $html .= '<small>' . htmlspecialchars($context['element']->getExcerpt(0, 100)) . '</small></a>';
            $html .= '</div>';

            return $html;
        }
    }

    public function getCommentHtml($comment)
    {
        $settings = craft()->comments->getSettings();
        $oldPath = craft()->path->getTemplatesPath();
        $element = $comment->element;

        // Is the user providing their own templates?
        if ($settings->templateFolderOverride) {

            // Check if this file even exists
            $commentTemplate = craft()->path->getSiteTemplatesPath() . $settings->templateFolderOverride . '/comment';
            foreach (craft()->config->get('defaultTemplateExtensions') as $extension) {
                if (IOHelper::fileExists($commentTemplate . "." . $extension)) {
                    $templateFile =  $settings->templateFolderOverride . '/comment';
                }
            }
        }

        // If no user templates, use our default
        if (!isset($templateFile)) {
            $templateFile = '_forms/templates/comment';

            craft()->path->setTemplatesPath(craft()->path->getPluginsPath() . 'comments/templates');
        }

        $variables = array(
            'element' => $element,
            'comments' => array($comment),
            'settings' => $settings,
        );

        $html = craft()->templates->render($templateFile, $variables);

        craft()->path->setTemplatesPath($oldPath);

        // Finally - none of this matters if the permission to comment on this element is denied
        if (!craft()->comments_settings->checkPermissions($element)) {
            return false;
        }

        return $html;
    }

    public function deleteComment($comments)
    {
        if (!$comments) {
            return false;
        }

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

        try {
            if (!is_array($comments)) {
                $comments = array($comments);
            }

            $commentIds = array();
            foreach ($comments as $comment) {
                $commentIds[] = $comment->id;
            }

            if ($commentIds) {
                $success = craft()->elements->deleteElementById($commentIds);
            } else {
                $success = false;
            }

            if ($transaction !== null) {
                $transaction->commit();
            }
        } catch (\Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return $success;
    }



    // Event Handlers
    // =========================================================================

    public function onBeforeSaveComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['comment']) || !($params['comment'] instanceof Comments_CommentModel)) {
            throw new Exception('onBeforeSaveComment event requires "comment" param with CommentModel instance');
        }

        $this->raiseEvent('onBeforeSaveComment', $event);
    }

    public function onSaveComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['comment']) || !($params['comment'] instanceof Comments_CommentModel)) {
            throw new Exception('onSaveComment event requires "comment" param with CommentModel instance');
        }

        $this->raiseEvent('onSaveComment', $event);
    }

    public function onBeforeTrashComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['comment']) || !($params['comment'] instanceof Comments_CommentModel)) {
            throw new Exception('onBeforeTrashComment event requires "comment" param with CommentModel instance');
        }

        $this->raiseEvent('onBeforeTrashComment', $event);
    }

    public function onTrashComment(\CEvent $event)
    {
        $params = $event->params;
        
        if (empty($params['comment']) || !($params['comment'] instanceof Comments_CommentModel)) {
            throw new Exception('onTrashComment event requires "comment" param with CommentModel instance');
        }

        $this->raiseEvent('onTrashComment', $event);
    }



    // Private Methods
    // =========================================================================

    private function _checkForNewParent(Comments_CommentModel $comment)
    {
        // Is it a brand new comment?
        if (!$comment->id) {
            return true;
        }

        // Was a parentId actually submitted?
        if ($comment->parentId === null) {
            return false;
        }

        // Is it set to the top level now, but it hadn't been before?
        if ($comment->parentId === '' && $comment->level != 1) {
            return true;
        }

        // Is it set to be under a parent now, but didn't have one before?
        if ($comment->parentId !== '' && $comment->level == 1) {
            return true;
        }

        // Is the parentId set to a different comment ID than its previous parent?
        $criteria = craft()->elements->getCriteria('Comments_Comment');
        $criteria->ancestorOf = $comment;
        $criteria->ancestorDist = 1;
        $criteria->status = null;
        $criteria->localeEnabled = null;

        $oldParent = $criteria->first();
        $oldParentId = ($oldParent ? $oldParent->id : '');

        if ($comment->parentId != $oldParentId) {
            return true;
        }

        // Must be set to the same one then
        return false;
    }

    private function _sendAuthorNotificationEmail(Comments_CommentModel $comment)
    {
        // Get our commented-on element
        $element = $comment->element;

        $recipient = array();

        // Get our recipient
        if (isset($element->author)) {
            $recipient = $element->author;
        }

        // Check for Matrix and other elements which have an owner
        if (isset($element->owner)) {
            if (isset($element->owner->author)) {
                $recipient = $element->owner->author;
            }
        }

        if (count($recipient)) {
            // If the author and commenter are the same user - don't send
            if ($comment->userId != $recipient->id) {
                craft()->email->sendEmailByKey($recipient, 'comments_author_notification', array(
                    'element' => $element,
                    'comment' => $comment,
                ));
            }
        }
    }

    private function _sendReplyNotificationEmail(Comments_CommentModel $comment)
    {
        // Get the comment we're replying to
        $parentComment = craft()->comments->getCommentById($comment->parentId);

        // Get our recipient
        $recipient = $parentComment->author;

        // Get our commented-on element
        $element = $comment->element;

        if (count($recipient)) {
            // If the author and commenter are the same user - don't send
            if ($comment->userId != $recipient->id) {
                craft()->email->sendEmailByKey($recipient, 'comments_reply_notification', array(
                    'element' => $element,
                    'comment' => $comment,
                ));
            }
        }
    }


}
