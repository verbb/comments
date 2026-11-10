<?php
use craft\db\Query;
use craft\elements\Entry;
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\ReportStatus;
use verbb\comments\enums\ReviewSetting;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Moderation;
use yii\web\ForbiddenHttpException;

/**
 * Why comments wait, the review settings on a commenter, and the decisions moderators make.
 */

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
});

afterEach(function() {
    unset($_SERVER['REQUEST_METHOD']);
    Craft::$app->getRequest()->setBodyParams([]);
});

function reviewPost(Entry $entry, int $userId, string $content = 'A comment'): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => $userId,
    ]))->comment;
}

function reviewIdentity(User $user): int
{
    return (int)Comments::$plugin->getIdentities()->getOrCreateForUser($user)->id;
}

function reviewState(Comment $comment): array
{
    return (new Query())->select(['moderationState', 'heldReason'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->one();
}

/** Post the comment's page with one of the sidebar's decision buttons. */
function reviewDecide(Comment $comment, string $decision, array $extra = []): Comment
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    Craft::$app->getRequest()->setBodyParams($extra + [
        'decision' => $decision,
        'status' => $comment->status,
        'contentFormat' => 'plain',
        'fields' => ['comment' => $comment->comment],
    ]);

    $editing = Comment::find()->id($comment->id)->status(null)->one();
    $editing->setAttributesFromRequest([]);
    $editing->setScenario(craft\base\Element::SCENARIO_LIVE);
    Craft::$app->getElements()->saveElement($editing);

    return $editing;
}

it('records why a comment is waiting', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('Held reasons');
    $member = CommentsFixtureFactory::user('heldReason');

    $settings->requireModeration = true;
    $premoderated = reviewPost($entry, (int)$member->id);

    expect(reviewState($premoderated))->toBe(['moderationState' => 'pending', 'heldReason' => HeldReason::PreModeration->value]);

    $settings->requireModeration = false;
    Comments::$plugin->getModeration()->setReviewSetting(reviewIdentity($member), ReviewSetting::Held);
    $held = reviewPost($entry, (int)$member->id);

    expect(reviewState($held))->toBe(['moderationState' => 'pending', 'heldReason' => HeldReason::Commenter->value])
        ->and(Comments::$plugin->getCommentEditor()->reviewNotice($held)['title'])->toContain('held commenter');

    // Approving clears the reason, and a moderator holding it again gives their own.
    $moderation = Comments::$plugin->getModeration();
    $admin = Craft::$app->getUser()->getIdentity();
    $moderation->approve($held, $admin);
    expect(reviewState($held)['heldReason'])->toBeNull();

    $moderation->setState($held, ModerationState::Pending, $admin);
    expect(reviewState($held)['heldReason'])->toBe(HeldReason::Moderator->value);
});

it('lets trusted commenters skip pre-moderation, unless a hold applies', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $entry = CommentsFixtureFactory::entry('Trusted owner');
    $member = CommentsFixtureFactory::user('trusted');
    $identityId = reviewIdentity($member);
    $moderation = Comments::$plugin->getModeration();

    $moderation->setReviewSetting($identityId, ReviewSetting::Trusted);
    $trusted = reviewPost($entry, (int)$member->id);

    expect(reviewState($trusted))->toBe(['moderationState' => 'approved', 'heldReason' => null]);

    // A hold on this one conversation still wins.
    $moderation->moderateIdentity($identityId, (int)$trusted->conversationId);
    expect($moderation->isTrusted($identityId, (int)$trusted->conversationId))->toBeFalse()
        ->and(reviewState(reviewPost($entry, (int)$member->id))['moderationState'])->toBe('pending');
});

it('holds a trusted commenter’s comment that matches a Moderated Keyword, with or without pre-moderation', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->securityModeration = "zqheldword";
    $entry = CommentsFixtureFactory::entry('Trusted keyword owner');
    $member = CommentsFixtureFactory::user('trustedKeyword');
    Comments::$plugin->getModeration()->setReviewSetting(reviewIdentity($member), ReviewSetting::Trusted);

    foreach ([true, false] as $requireModeration) {
        $settings->requireModeration = $requireModeration;
        Comments::$plugin->getConversations()->clearCaches();

        $clean = reviewPost($entry, (int)$member->id, 'Nothing to see here');
        $held = reviewPost($entry, (int)$member->id, 'This mentions zqheldword');

        expect(reviewState($clean))->toBe(['moderationState' => 'approved', 'heldReason' => null])
            ->and(reviewState($held))->toBe(['moderationState' => 'pending', 'heldReason' => HeldReason::Keyword->value]);
    }

    // Moderators see why it waits, in the index and at the top of the comment.
    $held = Comment::find()->id($held->id)->status(null)->one();

    expect($held->getAttributeHtml('reason'))->toContain('Keyword filter')->toContain('comments-reason--keyword')
        ->and(Comments::$plugin->getCommentEditor()->reviewNotice($held)['title'])->toBe('Waiting for review: keyword filter')
        ->and(Comments::$plugin->getCommentEditor()->reviewNotice($held)['text'])->toBe(HeldReason::Keyword->explanation());
});

it('keeps a more particular reason when a waiting comment also matches a keyword, and marks spam keywords', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->securityModeration = "zqheldword";
    $settings->securitySpamlist = "zqspamword";
    $entry = CommentsFixtureFactory::entry('Keyword reason owner');
    $member = CommentsFixtureFactory::user('heldKeyword');
    Comments::$plugin->getModeration()->setReviewSetting(reviewIdentity($member), ReviewSetting::Held);

    $commenter = reviewPost($entry, (int)$member->id, 'Held anyway, zqheldword');
    $spam = reviewPost($entry, (int)CommentsFixtureFactory::user('spamKeyword')->id, 'Buy zqspamword now');

    expect(reviewState($commenter))->toBe(['moderationState' => 'pending', 'heldReason' => HeldReason::Keyword->value])
        ->and(reviewState($spam))->toBe(['moderationState' => 'spam', 'heldReason' => HeldReason::Keyword->value])
        ->and(Comments::$plugin->getCommentEditor()->reviewNotice($spam)['text'])->toContain('Spam Keywords');

    // A moderator's hold is more particular than the keyword, so checking it again keeps that reason.
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getModeration()->setState($commenter, ModerationState::Approved, $admin);
    Comments::$plugin->getModeration()->setState($commenter, ModerationState::Pending, $admin);
    $commenter = Comment::find()->id($commenter->id)->status(null)->one();

    expect(Comments::$plugin->getSecurity()->checkSecurityPolicy($commenter))->toBeTrue()
        ->and($commenter->moderationState)->toBe(ModerationState::Pending->value)
        ->and($commenter->heldReason)->toBe(HeldReason::Moderator->value);
});

it('holds a comment as spam from beforeSaveComment, as the events docs show', function() {
    // The example from docs/developers/events.md, as written there.
    $listener = function(craft\events\ModelEvent $event) {
        /** @var Comment $comment */
        $comment = $event->sender;

        // Only check new comments, not edits or a moderator's changes.
        if (!$event->isNew) {
            return;
        }

        // Replace this with a call to your own spam service.
        $isSpam = stripos((string)$comment->comment, 'cheap watches') !== false;

        if ($isSpam) {
            $comment->moderationState = ModerationState::Spam->value;
            $comment->status = Comment::STATUS_SPAM;
            $comment->heldReason = HeldReason::Protection->value;
        }
    };
    yii\base\Event::on(Comment::class, Comment::EVENT_BEFORE_SAVE, $listener);

    try {
        $entry = CommentsFixtureFactory::entry('Spam event owner');
        $member = CommentsFixtureFactory::user('spamEvent');
        // A trusted commenter's review setting doesn't undo it either.
        Comments::$plugin->getModeration()->setReviewSetting(reviewIdentity($member), ReviewSetting::Trusted);
        $result = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => 'Cheap watches, this way',
            'userId' => (int)$member->id,
        ]));
        $clean = reviewPost($entry, (int)$member->id, 'A real comment');

        expect($result->moderationState)->toBe(ModerationState::Spam)
            ->and((new Query())->select(['status', 'moderationState', 'heldReason'])->from('{{%comments_comments}}')->where(['id' => $result->comment->id])->one())
            ->toBe(['status' => 'spam', 'moderationState' => 'spam', 'heldReason' => HeldReason::Protection->value])
            ->and(Comment::find()->ownerId($entry->id)->ids())->toBe([(int)$clean->id])
            ->and(Comment::find()->ownerId($entry->id)->status(Comment::STATUS_SPAM)->ids())->toBe([(int)$result->comment->id])
            ->and(reviewState($clean)['moderationState'])->toBe('approved');

        // A visitor posting the form without JavaScript is told it's under review, as the browser runtime does.
        Craft::$app->getUser()->setIdentity($member);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Craft::$app->getRequest()->getHeaders()->set('Accept', 'application/json');
        Craft::$app->getRequest()->setBodyParams([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'fields' => ['comment' => 'More cheap watches'],
        ]);
        $response = (new verbb\comments\controllers\CommentsController('comments', Comments::$plugin))->actionSave();

        expect($response->data['notice'] ?? null)->toBe('Your comment has been posted and is under review.')
            ->and($response->data['comment']['status'] ?? null)->toBe('spam');
    } finally {
        yii\base\Event::off(Comment::class, Comment::EVENT_BEFORE_SAVE, $listener);
        Craft::$app->getRequest()->getHeaders()->remove('Accept');
    }
});

it('keeps one site-wide review setting per commenter, with a history', function() {
    $member = CommentsFixtureFactory::user('reviewSetting');
    $identityId = reviewIdentity($member);
    $moderation = Comments::$plugin->getModeration();
    $admin = Craft::$app->getUser()->getIdentity();

    expect($moderation->getReviewSetting($identityId))->toBe(ReviewSetting::Normal);

    $moderation->setReviewSetting($identityId, ReviewSetting::Held, $admin, 'Look again in December');
    $moderation->setReviewSetting($identityId, ReviewSetting::Blocked, $admin);

    expect($moderation->getReviewSetting($identityId))->toBe(ReviewSetting::Blocked)
        ->and(count($moderation->getActiveRestrictions($identityId)))->toBe(1)
        ->and((new Query())->from('{{%comments_restrictions}}')->where(['identityId' => $identityId])->count())->toEqual(2)
        ->and((new Query())->select(['reason'])->from('{{%comments_restrictions}}')->where(['identityId' => $identityId, 'type' => 'moderated'])->scalar())->toBe('Look again in December');

    $moderation->setReviewSetting($identityId, ReviewSetting::Normal, $admin);

    expect($moderation->getReviewSetting($identityId))->toBe(ReviewSetting::Normal)
        ->and($moderation->getActiveRestrictions($identityId))->toBe([])
        // No setting ends by itself.
        ->and((new Query())->from('{{%comments_restrictions}}')->where(['identityId' => $identityId])->andWhere(['not', ['endsAt' => null]])->exists())->toBeFalse();
});

it('sends an approved comment back for review when its author edits it under pre-moderation', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('Edited owner');
    $member = CommentsFixtureFactory::user('editedAuthor');
    $comment = reviewPost($entry, (int)$member->id, 'Before');
    $settings->requireModeration = true;
    Comments::$plugin->getConversations()->clearCaches();

    $state = Comments::$plugin->getComments()->applyEditModerationState($comment);

    expect($state)->toBe(ModerationState::Pending)
        ->and($comment->heldReason)->toBe(HeldReason::Edited->value);

    // A trusted author's edit stays up.
    $trusted = reviewPost($entry, (int)CommentsFixtureFactory::user('editedTrusted')->id);
    $trusted->moderationState = ModerationState::Approved->value;
    Comments::$plugin->getModeration()->setReviewSetting((int)$trusted->identityId, ReviewSetting::Trusted);

    expect(Comments::$plugin->getComments()->applyEditModerationState($trusted))->toBe(ModerationState::Approved);
});

it('approves, marks spam and removes with a reason from the comment’s page', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $entry = CommentsFixtureFactory::entry('Decisions owner');
    $member = CommentsFixtureFactory::user('decisions');
    $first = reviewPost($entry, (int)$member->id, 'First');

    reviewDecide($first, Moderation::DECISION_APPROVE, ['fields' => ['comment' => 'First, tidied']]);

    expect(reviewState($first)['moderationState'])->toBe('approved')
        ->and((new Query())->select(['comment'])->from('{{%comments_comments}}')->where(['id' => $first->id])->scalar())->toBe('First, tidied');

    $second = reviewPost($entry, (int)$member->id, 'Second');

    $refused = reviewDecide($second, Moderation::DECISION_REMOVE, ['removalReason' => 'made-up', 'fields' => ['comment' => 'Not saved either']]);

    expect($refused->getErrors('comment'))->toBe(['Choose a reason for removing this comment.'])
        ->and(reviewState($second)['moderationState'])->toBe('pending')
        ->and((new Query())->select(['comment'])->from('{{%comments_comments}}')->where(['id' => $second->id])->scalar())->toBe('Second');

    reviewDecide($second, Moderation::DECISION_SPAM);

    expect(reviewState($second)['moderationState'])->toBe('spam');
});

it('removes with a fixed reason and a private note, and resolves the reports', function() {
    $entry = CommentsFixtureFactory::entry('Remove owner');
    $member = CommentsFixtureFactory::user('removed');
    $comment = reviewPost($entry, (int)$member->id, 'Personal remarks');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'harassment');

    expect(Comments::$plugin->getModeration()->availableDecisions($comment, Craft::$app->getUser()->getIdentity()))->toBe(['keep', 'remove']);

    reviewDecide($comment, Moderation::DECISION_REMOVE, ['removalReason' => 'harassment', 'removalNote' => 'Second time this week']);

    $action = (new Query())->from('{{%comments_moderationactions}}')->where(['commentId' => $comment->id, 'action' => 'remove'])->one();

    expect(reviewState($comment)['moderationState'])->toBe('removed')
        ->and($action['reason'])->toBe('harassment')
        ->and((new Query())->select(['status', 'moderationActionId'])->from('{{%comments_reports}}')->where(['commentId' => $comment->id])->one())
        ->toBe(['status' => ReportStatus::Resolved->value, 'moderationActionId' => $action['id']])
        ->and((new Query())->select(['note'])->from('{{%comments_moderatornotes}}')->where(['commentId' => $comment->id])->scalar())->toBe('Second time this week')
        ->and(Comments::$plugin->getCommentEditor()->reviewNotice(Comment::find()->id($comment->id)->status(null)->one())['text'])->toContain('Harassment or abuse');
});

it('keeps a reported comment by dismissing its reports', function() {
    $entry = CommentsFixtureFactory::entry('Keep owner');
    $comment = reviewPost($entry, (int)CommentsFixtureFactory::user('kept')->id, 'Fine really');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'off-topic');

    reviewDecide($comment, Moderation::DECISION_KEEP);

    expect(reviewState($comment)['moderationState'])->toBe('approved')
        ->and((new Query())->select(['status'])->from('{{%comments_reports}}')->where(['commentId' => $comment->id])->scalar())->toBe(ReportStatus::Dismissed->value)
        ->and(Comment::find()->id($comment->id)->hasOpenReports(true)->exists())->toBeFalse();
});

it('offers no decisions on a moderator’s own comment, or to someone who can’t moderate', function() {
    $entry = CommentsFixtureFactory::entry('Own comment owner');
    Comments::$plugin->getSettings()->requireModeration = true;
    $admin = Craft::$app->getUser()->getIdentity();
    $own = reviewPost($entry, (int)$admin->id);

    expect(Comments::$plugin->getModeration()->availableDecisions($own, $admin))->toBe([])
        ->and(fn() => reviewDecide($own, Moderation::DECISION_APPROVE))->toThrow(ForbiddenHttpException::class);

    $editor = CommentsFixtureFactory::user('decideNoModerate');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$editor->id, ['comments-view', 'comments-edit']);
    $editor = User::find()->id($editor->id)->status(null)->one();
    $other = reviewPost($entry, (int)CommentsFixtureFactory::user('decideOther')->id);

    expect(Comments::$plugin->getModeration()->availableDecisions($other, $editor))->toBe([]);
});

it('only offers removal to people who can trash comments', function() {
    $entry = CommentsFixtureFactory::entry('Trash permission owner');
    Comments::$plugin->getSettings()->requireModeration = true;
    $moderator = CommentsFixtureFactory::user('decideModerator');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['comments-view', 'comments-moderate']);
    $moderator = User::find()->id($moderator->id)->status(null)->one();
    $comment = reviewPost($entry, (int)CommentsFixtureFactory::user('decideAuthor')->id);

    expect(Comments::$plugin->getModeration()->availableDecisions($comment, $moderator))->toBe(['approve', 'spam']);
});

it('returns spam to Needs review with the reason it was first held for', function() {
    $entry = CommentsFixtureFactory::entry('Not spam owner');
    $comment = reviewPost($entry, (int)CommentsFixtureFactory::user('notSpam')->id);
    $moderation = Comments::$plugin->getModeration();
    $admin = Craft::$app->getUser()->getIdentity();
    $moderation->markSpam($comment, $admin);

    expect($moderation->availableDecisions($comment, $admin))->toBe(['not-spam']);

    $moderation->decide($comment, Moderation::DECISION_NOT_SPAM, $admin);

    expect(reviewState($comment))->toBe(['moderationState' => 'pending', 'heldReason' => HeldReason::Moderator->value]);
});

it('counts the queue again after Craft deletes or restores a waiting comment', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $entry = CommentsFixtureFactory::entry('Queue counts after delete');
    $member = CommentsFixtureFactory::user('queueCountsDelete');
    $moderation = Comments::$plugin->getModeration();

    $comment = reviewPost($entry, (int)$member->id);
    $before = $moderation->getQueueCounts()['pending'];

    Craft::$app->getElements()->deleteElement($comment);
    expect($moderation->getQueueCounts()['pending'])->toBe($before - 1);

    Craft::$app->getElements()->restoreElement($comment);
    expect($moderation->getQueueCounts()['pending'])->toBe($before);
});
