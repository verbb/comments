<?php
namespace verbb\comments\controllers;

use verbb\comments\Comments;
use verbb\comments\compatibility\forms\LegacyFormActions;
use verbb\comments\compatibility\forms\PostedComment;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\exceptions\RefusedException;
use verbb\comments\services\Protection;
use verbb\comments\services\Subscriptions;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use yii\base\UserException;
use yii\web\Response;

/**
 * Comments 3's form actions, kept as a bridge for upgraded sites whose own templates post to them
 * (SD-37). They follow the data API's rules for owners, parents, edits, blocking and following, answer
 * JSON when it's asked for, and otherwise redirect back as Comments 3 did, with Craft's own notice and
 * error messages for the site's templates to show.
 */
class CommentsController extends Controller
{
    // Traits
    // =========================================================================

    use LegacyFormActions;


    // Properties
    // =========================================================================

    protected array|bool|int $allowAnonymous = ['save', 'subscribe'];


    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        // These are the site's own form actions. A control panel URL would skip Craft's control panel
        // access check for anonymous actions and look like a control panel request to the comment.
        $this->requireSiteRequest();

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

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $commentId = (int)$this->request->getParam('commentId');

        // Every refusal, from a missing owner to the comment limit, comes back the same way: JSON for a
        // script, or the page again with `errors` for a plain form.
        try {
            return $commentId ? $this->_saveExistingComment($commentId) : $this->_saveNewComment();
        } catch (UserException $e) {
            return $this->_saveFailure($e, $commentId ?: null);
        }
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
            $this->_requirePublicComment($commentId);
            Comments::$plugin->getComments()->requireEngagementTarget($commentId, Protection::ENGAGEMENT_REPORT);
            $identity = Comments::$plugin->getIdentities()->resolveForCurrentRequest();
            $hasFlagged = Comments::$plugin->getReports()->toggle($commentId, (int)$identity->id);
        } catch (UserException $e) {
            return $this->_engagementError($e->getMessage());
        }

        $notice = Craft::t('comments', $hasFlagged ? 'Comment has been reported.' : 'Report withdrawn.');

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'notice' => $notice,
            ]);
        }

        Craft::$app->getSession()->setNotice($notice);

        return $this->redirect($this->_back());
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
            $this->_requirePublicComment($commentId);
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

        return $this->redirect($this->_back());
    }

    public function actionSubscribe(): Response
    {
        $this->requirePostRequest();

        $commentId = $this->request->getParam('commentId');
        $commentId = $commentId !== null && $commentId !== '' ? (int)$commentId : null;

        try {
            [$subscribed, $notice] = $commentId ? $this->_followReplies($commentId) : $this->_followConversation();
        } catch (UserException $e) {
            return $this->_engagementError($e->getMessage());
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'subscribed' => $subscribed,
                'notice' => $notice,
            ]);
        }

        Craft::$app->getSession()->setNotice($notice);

        return $this->redirect($this->_back());
    }


    // Private Methods
    // =========================================================================

    private function _saveNewComment(): ?Response
    {
        $plugin = Comments::$plugin;
        $comments = $plugin->getComments();
        $settings = $plugin->getSettings();
        $currentUser = $plugin->getService()->getUser();
        $submission = $comments->buildSubmissionFromPost();

        // The data API's rules: a live owner that isn't a comment, on its own site, with comments on.
        $owner = $plugin->getApi()->resolveOwner($submission->ownerId, null, $submission->siteId);

        if ($submission->parentId) {
            $parent = Comment::find()->id($submission->parentId)->siteId('*')->status(null)->trashed(null)->one();

            if (!$parent) {
                throw new UserException(Craft::t('comments', 'This can’t be shown.'));
            }

            $plugin->getApi()->assertReplyTarget($parent, $owner);
        }

        if (!$settings->commentingAvailable($owner)['permission']) {
            throw new UserException(Craft::t('comments', 'Comment not allowed.'));
        }

        if (!$currentUser && !$settings->allowGuest) {
            throw new UserException($settings->guestNotice ?: Craft::t('comments', 'Must be logged in to comment.'));
        }

        if ($currentUser && !$settings->canComment($owner)) {
            throw new UserException(Craft::t('comments', 'Comment not allowed.'));
        }

        $site = Craft::$app->getSites()->getSiteById((int)$owner->siteId) ?? Craft::$app->getSites()->getCurrentSite();
        $result = $comments->createFromPost();
        $comment = $result->comment;

        // Spam is held the same as pending, as far as the visitor is told.
        $notice = $result->moderationState !== ModerationState::Approved
            ? Craft::t('comments', 'Your comment has been posted and is under review.', [], $site->language)
            : '';

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $comment->id,
                'comment' => $this->_commentData($comment),
                'notice' => $notice,
            ]);
        }

        Craft::$app->getSession()->setNotice($notice ?: Craft::t('comments', 'Comment posted.'));

        return $this->redirectToPostedUrl($comment, $this->_back());
    }

    private function _saveExistingComment(int $commentId): ?Response
    {
        $plugin = Comments::$plugin;
        $user = $plugin->getService()->getUser();
        $existing = Comment::find()->id($commentId)->siteId('*')->status(null)->one();

        if (!$existing) {
            throw new UserException(Craft::t('comments', 'No comment with the ID “{id}”', ['id' => $commentId]));
        }

        if (!$user) {
            throw new UserException(Craft::t('comments', 'You do not have permission to edit this comment.'));
        }

        // The same rules as an edit through the data API.
        $plugin->getApi()->ownerOfComment($existing);
        $plugin->getApi()->assertEditable($existing, $user);

        $comment = $plugin->getComments()->updateFromPost($commentId);
        $pending = $comment->status == Comment::STATUS_PENDING;

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $comment->id,
                'comment' => $this->_commentData($comment),
                'notice' => $pending ? Craft::t('comments', 'Your comment has been posted and is under review.') : '',
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('comments', 'Comment updated.'));

        return $this->redirectToPostedUrl($comment, $this->_back());
    }

    /**
     * A save that was refused. A plain form's page is rendered again, as Comments 3 did, with the unsaved
     * `comment` holding what was posted and `errors`, keyed `comment` for the whole refusal and by field
     * for each field's own problem, and Craft's error message.
     */
    private function _saveFailure(UserException $e, ?int $commentId): ?Response
    {
        $errors = ['comment' => [$e->getMessage()]];

        if ($e instanceof CommentValidationException) {
            foreach ($e->fieldErrors as $key => $messages) {
                $name = preg_match('/^fields\[(.+)\]$/', (string)$key, $match) ? $match[1] : (string)$key;
                $errors[$name] = array_values(array_unique([...($errors[$name] ?? []), ...$messages]));
            }
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'errors' => $errors,
            ]);
        }

        $comment = $this->_postedComment($commentId);
        $comment->addErrors($errors);

        Craft::$app->getSession()->setError($e->getMessage());
        Craft::$app->getUrlManager()->setRouteParams([
            'comment' => $comment,
            'errors' => $errors,
        ]);

        return null;
    }

    /**
     * What was posted, as plain values only, so a site's template can put it back in the form. It's never
     * a comment element, whose getters would look up whatever the posted IDs point at.
     */
    private function _postedComment(?int $commentId): PostedComment
    {
        $int = static fn(mixed $value): ?int => is_numeric($value) ? (int)$value : null;
        $string = static fn(mixed $value): ?string => is_string($value) ? $value : null;
        $siteId = $int($this->request->getParam('siteId'));

        return new PostedComment([
            'id' => $commentId,
            'ownerId' => $int($this->request->getParam('ownerId') ?? $this->request->getParam('elementId')),
            'ownerSiteId' => $siteId,
            'siteId' => $siteId,
            'parentId' => $int($this->_postedParentId()),
            'comment' => $string($this->request->getParam('fields.comment')),
            'name' => $string($this->request->getParam('fields.name')),
            'email' => $string($this->request->getParam('fields.email')),
        ]);
    }

    private function _commentData(Comment $comment): array
    {
        return $comment->toArray(['commentDate', 'id', 'ownerId', 'ownerSiteId', 'siteId', 'status', 'uid']);
    }

    /**
     * Follow or stop following a whole discussion, through the same rules as the data API's `follow`:
     * a live owner, no following while blocked (SD-45), and guests confirming their address first.
     * Without `subscribed`, a member's follow is toggled, as Comments 3 did, and a guest's is turned on.
     */
    private function _followConversation(): array
    {
        $plugin = Comments::$plugin;
        $user = $plugin->getService()->getUser();

        if (!$plugin->getSettings()->allowsSubscriptionType(SubscriptionType::Conversation)) {
            throw new UserException(Craft::t('comments', 'Discussion subscriptions are not allowed.'));
        }

        $siteId = $this->request->getParam('siteId');
        $owner = $plugin->getApi()->resolveOwner($this->request->getParam('ownerId'), null, $siteId === '' || $siteId === '0' ? null : $siteId);
        $subscriptions = $plugin->getSubscriptions();
        $requested = $this->request->getParam('subscribed');

        if ($requested !== null && $requested !== '') {
            $subscribed = (bool)$requested;
        } elseif ($user) {
            $conversation = $plugin->getConversations()->findForOwner((int)$owner->id, (int)$owner->siteId);
            $identity = $plugin->getIdentities()->findForUser((int)$user->id);
            $subscribed = !($conversation && $identity && $subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id));
        } else {
            $subscribed = true;
        }

        $outcome = $subscriptions->setConversationSubscription((int)$owner->id, (int)$owner->siteId, $subscribed, $this->request->getParam('email'));

        return [$outcome === Subscriptions::OUTCOME_FOLLOWED, match ($outcome) {
            Subscriptions::OUTCOME_FOLLOWED => Craft::t('comments', 'Subscribed to discussion.'),
            Subscriptions::OUTCOME_CONFIRMING => Craft::t('comments', 'Check your email to confirm your subscription.'),
            default => Craft::t('comments', 'Unsubscribed from discussion.'),
        }];
    }

    /**
     * Follow or stop following the replies to one comment, for a signed-in member: an approved comment on
     * a live owner (and the owner named, if one is), and no following while blocked (SD-45).
     */
    private function _followReplies(int $commentId): array
    {
        $plugin = Comments::$plugin;
        $user = $plugin->getService()->getUser();

        if (!$plugin->getSettings()->allowsSubscriptionType(SubscriptionType::Replies)) {
            throw new UserException(Craft::t('comments', 'Per-comment subscriptions are not allowed.'));
        }

        if (!$user) {
            throw new UserException(Craft::t('comments', 'Must be logged in to subscribe.'));
        }

        $comment = $this->_requirePublicComment($commentId);
        $owner = $plugin->getApi()->ownerOfComment($comment);
        $ownerId = $this->request->getParam('ownerId');

        if (is_numeric($ownerId) && (int)$ownerId !== (int)$owner->id) {
            throw new UserException(Craft::t('comments', 'This comment is not available.'));
        }

        $subscriptions = $plugin->getSubscriptions();
        $conversation = $plugin->getConversations()->getOrCreateForOwner((int)$owner->id, (int)$owner->siteId);
        $identity = $plugin->getIdentities()->getOrCreateForUser($user);
        $current = $subscriptions->isSubscribed((int)$conversation->id, (int)$identity->id, $commentId, SubscriptionType::Replies);
        $requested = $this->request->getParam('subscribed');
        $subscribed = $requested !== null && $requested !== '' ? (bool)$requested : !$current;

        // Someone blocked from a discussion can always stop following it, but not start (SD-45).
        if ($subscribed && $plugin->getComments()->isRequestBlocked((int)$owner->id, (int)$owner->siteId)) {
            throw new RefusedException(Craft::t('comments', 'You can’t take part in this discussion.'), 'blocked');
        }

        if ($subscribed && !$current) {
            $subscriptions->subscribeToReplies((int)$conversation->id, (int)$identity->id, $commentId);
        } elseif (!$subscribed && $current) {
            $subscriptions->unsubscribe((int)$conversation->id, (int)$identity->id, $commentId, SubscriptionType::Replies);
        }

        return [$subscribed, Craft::t('comments', $subscribed ? 'Subscribed to discussion.' : 'Unsubscribed from discussion.')];
    }

    /**
     * An approved comment, not removed by its author, on an owner the data API would answer for.
     */
    private function _requirePublicComment(int $commentId): Comment
    {
        $comment = $commentId ? Comment::find()->id($commentId)->siteId('*')->status(null)->one() : null;

        if (!$comment || $comment->authorRemovedAt || Comments::$plugin->getModeration()->currentState($comment) !== ModerationState::Approved) {
            throw new UserException(Craft::t('comments', 'This comment is not available.'));
        }

        Comments::$plugin->getApi()->ownerOfComment($comment);

        return $comment;
    }

    private function _engagementError(string $message): Response
    {
        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'errors' => ['message' => [Craft::t('comments', $message)]],
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('comments', $message));

        return $this->redirect($this->_back());
    }

    /**
     * Where a plain form goes back to: the page it came from, if that's on this site's own address, otherwise
     * the page it was posted to, or the home page when it was posted straight to the action's URL.
     */
    private function _back(): string
    {
        $referrer = $this->request->getReferrer();
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;

        // The site's configured address, not the request's Host, which a forwarded header can change.
        $siteHost = parse_url((string)Craft::$app->getSites()->getCurrentSite()->getBaseUrl(), PHP_URL_HOST);

        if (is_string($host) && is_string($siteHost) && strcasecmp($host, $siteHost) === 0) {
            return $referrer;
        }

        if ($this->request->getBodyParam('action') !== null) {
            return UrlHelper::siteUrl($this->request->getPathInfo());
        }

        return UrlHelper::siteUrl();
    }
}
