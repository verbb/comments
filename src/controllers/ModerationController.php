<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\enums\ModerationState;

use Craft;
use craft\web\Controller;

use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use Throwable;

class ModerationController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireLogin();
        $this->requirePermission('comments-moderate');

        return true;
    }

    public function actionAddNote(): Response
    {
        $this->requirePostRequest();

        return $this->_run(function(int $commentId): void {
            Comments::$plugin->getModeration()->addNote(
                $commentId,
                trim((string)$this->request->getRequiredBodyParam('note')),
                Craft::$app->getUser()->getIdentity(),
            );
        }, Craft::t('comments', 'Note added.'));
    }

    /**
     * Permanently delete one spam comment, from its own page and after a confirmation. Bulk actions only
     * ever soft-delete, and nothing is deleted automatically.
     */
    public function actionDeletePermanently(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('comments-delete');

        $comment = Comments::$plugin->getComments()->getCommentById((int)$this->request->getRequiredBodyParam('commentId'));

        if (!$comment) {
            throw new NotFoundHttpException('Comment not found.');
        }

        if (Comments::$plugin->getModeration()->currentState($comment) !== ModerationState::Spam) {
            return $this->asFailure(Craft::t('comments', 'Only spam can be deleted permanently.'));
        }

        if (!Craft::$app->getElements()->deleteElement($comment, true)) {
            return $this->asFailure(Craft::t('comments', 'Couldn’t delete the comment.'));
        }

        return $this->asSuccess(Craft::t('comments', 'Comment deleted permanently.'));
    }

    /**
     * One moderator decision from somewhere other than the comment's page, such as an entry's Comments
     * panel. Answers with the decision's message.
     */
    public function actionDecide(): Response
    {
        $this->requirePostRequest();

        $comment = Comments::$plugin->getComments()->getCommentById((int)$this->request->getRequiredBodyParam('commentId'));

        if (!$comment) {
            throw new NotFoundHttpException('Comment not found.');
        }

        $moderation = Comments::$plugin->getModeration();
        $decision = (string)$this->request->getRequiredBodyParam('decision');
        $actor = Craft::$app->getUser()->getIdentity();

        if (!in_array($decision, $moderation->availableDecisions($comment, $actor), true)) {
            throw new ForbiddenHttpException('User not authorized to make this decision.');
        }

        try {
            $moderation->decide($comment, $decision, $actor, $this->request->getBodyParam('reason'), $this->request->getBodyParam('note'));
        } catch (Throwable $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess($moderation->decisionMessage($decision));
    }

    /**
     * What waits for a moderator, so the index can update its counts after a decision in a slideout.
     */
    public function actionQueueCounts(): Response
    {
        $this->requireAcceptsJson();

        $counts = Comments::$plugin->getModeration()->getQueueCounts();

        return $this->asJson([
            'needs-review' => $counts['pending'],
            'reported' => $counts['reported'],
            'total' => $counts['pending'] + $counts['reported'],
        ]);
    }


    // Private Methods
    // =========================================================================

    /**
     * These act on a tab inside the comment's editor, so they answer with the refreshed tab rather than
     * leaving the page and discarding whatever is being edited.
     */
    private function _run(callable $callback, string $successMessage): Response
    {
        $this->requireAcceptsJson();

        $commentId = (int)$this->request->getRequiredBodyParam('commentId');
        $comment = Comments::$plugin->getComments()->getCommentById($commentId);

        if (!$comment) {
            throw new NotFoundHttpException('Comment not found.');
        }

        try {
            $callback($commentId);
        } catch (Throwable $e) {
            return $this->asFailure($e->getMessage());
        }

        $editor = Comments::$plugin->getCommentEditor();

        return $this->asSuccess($successMessage, [
            'html' => $editor->panelHtml($comment),
            'label' => $editor->panelLabel($comment),
        ]);
    }
}
