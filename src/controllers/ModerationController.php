<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationState;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\web\assets\cp\CommentEditAsset;

use Craft;
use craft\web\Controller;

use yii\base\UserException;
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
     * The reply form, in Craft's slideout, for a comment's page or an entry's Comments panel. The comment
     * being answered sits above the box, so the reply can be written without switching back.
     */
    public function actionReply(): Response
    {
        $parent = $this->_replyParent();
        $settings = Comments::$plugin->getSettings();

        return $this->asCpScreen()
            ->title(Craft::t('comments', 'Reply to {name}', ['name' => $parent->getAuthorName() ?: Craft::t('comments', 'Guest')]))
            ->action('comments/moderation/save-reply')
            ->submitButtonLabel(Craft::t('comments', 'Post reply'))
            ->contentTemplate('comments/comments/_reply', [
                'parent' => $parent,
                'rich' => $settings->isRichAuthoringEnabled(),
                'editorOptions' => $settings->isRichAuthoringEnabled()
                    ? EditorContexts::browserOptions('comments.reply', (int)$parent->ownerId, (int)$parent->ownerSiteId)
                    : null,
            ])
            ->prepareScreen(function(Response $response, string $containerId): void {
                // The comment page's script starts Editor in the slideout, as it does in the editor itself.
                $view = Craft::$app->getView();
                $view->registerAssetBundle(CommentEditAsset::class);
                $view->registerJsWithVars(static fn($selector) => "Craft.Comments.initCommentEditor(document.querySelector($selector));", [
                    "#$containerId",
                ]);
            });
    }

    public function actionSaveReply(): Response
    {
        $this->requirePostRequest();

        $parent = $this->_replyParent();
        $format = ContentFormat::tryFrom((string)$this->request->getBodyParam('contentFormat')) ?? ContentFormat::Plain;
        $content = (string)$this->request->getBodyParam('fields.editorContent', '');

        // Editor fills the hidden document as it's typed in. Without rich authoring there's only the plain box.
        if ($format !== ContentFormat::Rich || !Comments::$plugin->getSettings()->isRichAuthoringEnabled() || $content === '') {
            $format = ContentFormat::Plain;
            $content = (string)$this->request->getBodyParam('fields.comment', '');
        }

        try {
            $result = Comments::$plugin->getCommentEditor()->reply($parent, Craft::$app->getUser()->getIdentity(), $format, $content);
        } catch (UserException $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess(Craft::t('comments', 'Reply posted.'), [
            'commentId' => (int)$result->comment->id,
            'cpEditUrl' => $result->comment->getCpEditUrl(),
        ]);
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
     * The comment being answered, in the site it's being edited in, once this person is known to be
     * able to reply to it.
     */
    private function _replyParent(): Comment
    {
        $siteId = $this->request->getParam('siteId');
        $parent = Comments::$plugin->getComments()->getCommentById((int)$this->request->getRequiredParam('commentId'), $siteId ? (int)$siteId : null);

        if (!$parent) {
            throw new NotFoundHttpException('Comment not found.');
        }

        if (!Comments::$plugin->getCommentEditor()->canReply($parent, Craft::$app->getUser()->getIdentity())) {
            throw new ForbiddenHttpException('User not authorized to reply to this comment.');
        }

        return $parent;
    }

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
