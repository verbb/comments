<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\services\Frontend;
use verbb\comments\services\Protection;
use verbb\comments\services\Subscriptions;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;

use yii\base\Exception;
use yii\base\UserException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CommentsController extends Controller
{
    // Properties
    // =========================================================================

    protected array|bool|int $allowAnonymous = ['save', 'subscribe'];


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

    //
    // Comments Front-End
    //

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $siteId = (int)$this->request->getParam('siteId');
        $commentId = $this->request->getParam('commentId');

        $currentSite = Craft::$app->getSites()->getCurrentSite();

        if ($siteId) {
            $currentSite = Craft::$app->getSites()->getSiteById($siteId);
        }

        // Public creates and edits share the canonical submission services.
        if ($commentId) {
            return $this->_saveExistingComment($currentSite);
        }

        return $this->_saveNewComment($currentSite);
    }

    public function actionFlag(): Response
    {
        $this->requirePostRequest();

        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $commentId = (int)$this->request->getParam('commentId');

        if (!$settings->allowFlagging) {
            return $this->_engagementError('Flagging is not allowed.');
        }

        if (!$currentUser && !$settings->allowGuestFlagging) {
            return $this->_engagementError('Must be logged in to flag comments.');
        }

        try {
            Comments::$plugin->getComments()->requireEngagementTarget($commentId, Protection::ENGAGEMENT_REPORT);
            $identity = Comments::$plugin->getIdentities()->resolveForCurrentRequest();
            $hasFlagged = Comments::$plugin->getReports()->toggle($commentId, (int)$identity->id);
        } catch (UserException $e) {
            return $this->_engagementError($e->getMessage());
        }

        if ($this->request->getAcceptsJson()) {
            $message = $hasFlagged ? 'Comment has been reported.' : 'Report withdrawn.';

            return $this->asJson([
                'success' => true,
                'notice' => Craft::t('comments', $message),
            ]);
        }

        return $this->redirect($this->request->referrer);
    }

    public function actionVote(): Response
    {
        $this->requirePostRequest();

        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $upvote = (bool)$this->request->getParam('upvote');
        $commentId = (int)$this->request->getParam('commentId');

        $mode = $settings->resolveInteractionMode();

        if (!$mode->isEnabled()) {
            return $this->_engagementError('Voting is not allowed.');
        }

        if (!$upvote && !$mode->allowsDownvote()) {
            return $this->_engagementError('Downvoting is not enabled.');
        }

        if (!$currentUser && !$settings->allowGuestVoting) {
            return $this->_engagementError('Must be logged in to vote.');
        }

        try {
            Comments::$plugin->getComments()->requireEngagementTarget($commentId, Protection::ENGAGEMENT_INTERACTION);
            $identity = Comments::$plugin->getIdentities()->resolveForCurrentRequest();
            $type = Comments::$plugin->getInteractions()->toggleVote($commentId, (int)$identity->id, $upvote);
        } catch (UserException $e) {
            return $this->_engagementError($e->getMessage());
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'type' => $type?->value,
            ]);
        }

        return $this->redirect($this->request->referrer);
    }

    public function actionSubscribe(): Response
    {
        $this->requirePostRequest();

        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $ownerId = (int)$this->request->getParam('ownerId');
        $siteId = (int)$this->request->getParam('siteId');
        $commentId = $this->request->getParam('commentId');
        $commentId = $commentId !== null && $commentId !== '' ? (int)$commentId : null;

        if ($commentId && !$settings->allowsSubscriptionType(SubscriptionType::Replies)) {
            return $this->_engagementError('Per-comment subscriptions are not allowed.');
        }

        if (!$commentId && !$settings->allowsSubscriptionType(SubscriptionType::Conversation)) {
            return $this->_engagementError('Discussion subscriptions are not allowed.');
        }

        $subscriptions = Comments::$plugin->getSubscriptions();

        // Without an account a guest subscribes to whole discussions, and only after confirming their email.
        if (!$currentUser) {
            if ($commentId) {
                return $this->_engagementError('Must be logged in to subscribe.');
            }

            try {
                $outcome = $subscriptions->setConversationSubscription($ownerId, $siteId, (bool)$this->request->getParam('subscribed', true), $this->request->getParam('email'));
            } catch (UserException $e) {
                return $this->_engagementError($e->getMessage());
            }

            $notice = match ($outcome) {
                Subscriptions::OUTCOME_SUBSCRIBED => Craft::t('comments', 'Subscribed to discussion.'),
                Subscriptions::OUTCOME_CONFIRMING => Craft::t('comments', 'Check your email to confirm your subscription.'),
                default => Craft::t('comments', 'Unsubscribed from discussion.'),
            };

            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'subscribed' => $outcome === Subscriptions::OUTCOME_SUBSCRIBED,
                    'notice' => $notice,
                ]);
            }

            Craft::$app->getSession()->setFlash(Frontend::FLASH_NOTICE, $notice);

            return $this->redirect($this->request->referrer);
        }

        $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner($ownerId, $siteId);
        $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($currentUser);

        if ($commentId) {
            $subscribed = $subscriptions->isSubscribed(
                (int)$conversation->id,
                (int)$identity->id,
                $commentId,
                SubscriptionType::Replies,
            );

            if ($subscribed) {
                $subscriptions->unsubscribe(
                    (int)$conversation->id,
                    (int)$identity->id,
                    $commentId,
                    SubscriptionType::Replies,
                );
            } else {
                $subscriptions->subscribeToReplies((int)$conversation->id, (int)$identity->id, $commentId);
            }
        } else {
            $subscribed = $subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id);

            if ($subscribed) {
                $subscriptions->unsubscribe((int)$conversation->id, (int)$identity->id);
            } else {
                $subscriptions->subscribeToConversation((int)$conversation->id, (int)$identity->id);
            }
        }

        $subscribed = $commentId
            ? $subscriptions->isSubscribed(
                (int)$conversation->id,
                (int)$identity->id,
                $commentId,
                SubscriptionType::Replies,
            )
            : $subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id);

        if ($this->request->getAcceptsJson()) {
            $message = $subscribed ? 'Subscribed to discussion.' : 'Unsubscribed from discussion.';

            return $this->asJson([
                'success' => true,
                'subscribed' => $subscribed,
                'notice' => Craft::t('comments', $message),
            ]);
        }

        return $this->redirect($this->request->referrer);
    }


    // Private Methods
    // =========================================================================

    private function _saveNewComment($currentSite): ?Response
    {
        $comments = Comments::$plugin->getComments();
        $settings = Comments::$plugin->getSettings();
        $currentUser = Comments::$plugin->getService()->getUser();
        $submission = $comments->buildSubmissionFromPost();

        if (!$submission->ownerId || !$submission->siteId) {
            throw new Exception(Craft::t('comments', 'Owner element is required.'));
        }

        $owner = Craft::$app->getElements()->getElementById($submission->ownerId, null, $submission->siteId);
        $availability = $settings->commentingAvailable($owner);

        if (!$availability['permission']) {
            throw new UserException(Craft::t('comments', 'Comment not allowed.'));
        }

        if (!$currentUser && !$settings->allowGuest) {
            $message = $settings->guestNotice ?: Craft::t('comments', 'Must be logged in to comment.');

            throw new UserException($message);
        }

        if ($currentUser && !$settings->canComment($owner)) {
            throw new UserException(Craft::t('comments', 'Comment not allowed.'));
        }

        $commentData = function(Comment $comment) {
            return $comment->toArray([
                'commentDate',
                'id',
                'ownerId',
                'ownerSiteId',
                'siteId',
                'status',
                'uid',
            ]);
        };

        try {
            $result = $comments->createFromPost();
        } catch (UserException $e) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => ['comment' => [$e->getMessage()]],
                ]);
            }

            Craft::$app->getSession()->setFlash(Frontend::FLASH_ERROR, $e->getMessage());

            return null;
        }

        $comment = $result->comment;

        if ($this->request->getAcceptsJson()) {
            $notice = '';

            // Spam is held the same as pending, as far as the visitor is told.
            if ($result->moderationState !== ModerationState::Approved) {
                $notice = Craft::t('comments', 'Your comment has been posted and is under review.', [], $currentSite->language);
            }

            return $this->asJson([
                'success' => true,
                'id' => $comment->id,
                'comment' => $commentData($comment),
                'notice' => $notice,
            ]);
        }

        Craft::$app->getSession()->setFlash(Frontend::FLASH_NOTICE, $result->moderationState !== ModerationState::Approved
            ? Craft::t('comments', 'Your comment has been posted and is under review.', [], $currentSite->language)
            : Craft::t('comments', 'Comment posted.'));

        return $this->redirectToPostedUrl($comment);
    }

    private function _saveExistingComment($currentSite): ?Response
    {
        $commentId = (int)$this->request->getParam('commentId');
        $commentData = function(Comment $comment) {
            return $comment->toArray(['commentDate', 'id', 'ownerId', 'ownerSiteId', 'siteId', 'status', 'uid']);
        };

        try {
            $comment = Comments::$plugin->getComments()->updateFromPost($commentId);
        } catch (UserException $e) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => ['comment' => [$e->getMessage()]],
                ]);
            }

            Craft::$app->getSession()->setFlash(Frontend::FLASH_ERROR, $e->getMessage());

            return null;
        }

        if ($this->request->getAcceptsJson()) {
            $notice = '';

            if ($comment->status == Comment::STATUS_PENDING) {
                $notice = Craft::t('comments', 'Your comment has been posted and is under review.', [], $currentSite->language);
            }

            return $this->asJson([
                'success' => true,
                'id' => $comment->id,
                'comment' => $commentData($comment),
                'notice' => $notice,
            ]);
        }

        Craft::$app->getSession()->setFlash(Frontend::FLASH_NOTICE, Craft::t('comments', 'Comment updated.'));

        return $this->redirectToPostedUrl($comment);
    }

    private function _engagementError(string $message): Response
    {
        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'errors' => ['message' => [Craft::t('comments', $message)]],
            ]);
        }

        Craft::$app->getSession()->setFlash(Frontend::FLASH_ERROR, Craft::t('comments', $message));

        return $this->redirect($this->request->referrer);
    }
}
