<?php
namespace verbb\comments\compatibility\forms;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\exceptions\ApiException;
use verbb\comments\services\Compatibility;

use Craft;

use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Comments 3's form actions that only work in compatibility mode, on the controller that bridges the rest.
 */
trait LegacyFormActions
{
    // Public Methods
    // =========================================================================

    /**
     * Comments 3's `comments/comments/trash`, which deletes the signed-in author's own comment by the data
     * API's rules, never Comments 3's own. The comment is hidden as deleted by its author, and its replies
     * stay under a placeholder.
     */
    public function actionTrash(): Response
    {
        $this->requirePostRequest();

        if (!Compatibility::enabled()) {
            throw new NotFoundHttpException();
        }

        Compatibility::deprecated('formActions.trash', 'The `comments/comments/trash` action has been removed. Comments 4’s thread offers **Delete** to a comment’s author itself, and the data API’s `delete` action does the same.');

        $commentId = (int)$this->request->getParam('commentId');
        $comment = $commentId ? Comment::find()->id($commentId)->siteId('*')->status(null)->one() : null;

        if (!$comment) {
            return $this->_engagementError('This comment is not available.');
        }

        try {
            Comments::$plugin->getApi()->delete(['uid' => (string)$comment->uid]);
        } catch (ApiException $e) {
            return $this->_engagementError($e->getMessage());
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('comments', 'Comment deleted.'));

        return $this->redirect($this->_back());
    }


    // Private Methods
    // =========================================================================

    /**
     * The comment a reply form names as its parent, as `parentId` or Comments 3's `newParentId`.
     */
    private function _postedParentId(): mixed
    {
        return LegacyFormRequest::parentId($this->request);
    }
}
