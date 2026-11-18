<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\events\ModerationEvent;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Moderation;
use verbb\comments\widgets\Comments as CommentsWidget;
use yii\base\Event;

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $this->admin = Craft::$app->getUser()->getIdentity();
});

afterEach(function() {
    Craft::$app->getUser()->setIdentity($this->admin);
});

function readinessComment($entry, string $text = 'A comment', ?int $userId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => $userId ?? (int)Craft::$app->getUser()->getId(),
    ]))->comment;
}

it('refuses a comment on an auto-closed element whichever way it’s sent', function() {
    $entry = CommentsFixtureFactory::entry('Readiness auto-close');
    $entry->postDate = (new DateTime())->modify('-30 days');
    Craft::$app->getElements()->saveElement($entry, false);
    Comments::$plugin->getSettings()->autoCloseDays = 7;

    expect(fn() => readinessComment($entry))->toThrow(yii\base\UserException::class, 'Comments are closed');

    Comments::$plugin->getSettings()->autoCloseDays = 60;
    expect(readinessComment($entry)->id)->not->toBeNull();
});

it('only offers guests the voting and flagging they’re allowed', function() {
    $entry = CommentsFixtureFactory::entry('Readiness guest actions');
    $comment = readinessComment($entry);
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = 'updown';
    $settings->allowFlagging = true;
    $settings->allowGuestVoting = false;
    $settings->allowGuestFlagging = false;
    $frontend = Comments::$plugin->getFrontend();

    $state = static fn() => $frontend->viewerStatesFor([Comments::$plugin->getComments()->getCommentById((int)$comment->id)])[(int)$comment->id];

    expect($state())->toMatchArray(['canVote' => true, 'canReport' => true]);

    Craft::$app->getUser()->setIdentity(null);
    expect($state())->toMatchArray(['canVote' => false, 'canReport' => false]);

    $settings->allowGuestVoting = true;
    $settings->allowGuestFlagging = true;
    expect($state())->toMatchArray(['canVote' => true, 'canReport' => true]);
});

it('only offers the public delete action on someone’s own comment', function() {
    $entry = CommentsFixtureFactory::entry('Readiness delete action');
    $author = CommentsFixtureFactory::user('readiness-author-' . bin2hex(random_bytes(3)));
    $comment = readinessComment($entry, 'By the author', (int)$author->id);
    $frontend = Comments::$plugin->getFrontend();

    // The admin running this test can remove it, but through the moderation actions.
    expect($frontend->viewerStatesFor([$comment])[(int)$comment->id]['canDelete'])->toBeFalse();

    Craft::$app->getUser()->setIdentity($author);
    expect($frontend->viewerStatesFor([Comments::$plugin->getComments()->getCommentById((int)$comment->id)])[(int)$comment->id]['canDelete'])->toBeTrue();
});

it('matches keyword filters against what people wrote, not a comment’s internal details', function() {
    $entry = CommentsFixtureFactory::entry('Readiness keywords');
    $settings = Comments::$plugin->getSettings();
    $security = Comments::$plugin->getSecurity();
    $candidate = static fn(string $text) => new Comment(['comment' => $text, 'name' => 'Pat', 'email' => 'pat@example.test', 'ipAddress' => '192.0.2.8', 'status' => Comment::STATUS_APPROVED, 'contentFormat' => 'plain', 'ownerId' => $entry->id]);

    $settings->securityMatchExact = false;
    $settings->securityBanned = 'approved';
    expect($security->checkSecurityPolicy($candidate('Nothing to see')))->toBeTrue();
    expect($security->checkSecurityPolicy($candidate('It got APPROVED today')))->toBeFalse();

    // Words in other alphabets used to be stripped before comparing, so they never matched.
    $settings->securityBanned = 'Привет';
    expect($security->checkSecurityPolicy($candidate('ну привет всем')))->toBeFalse();

    // The author's details and address still count.
    $settings->securityBanned = '192.0.2.8';
    expect($security->checkSecurityPolicy($candidate('Innocent words')))->toBeFalse();

    // Exact matching takes the word literally, in any case, and only as a whole word.
    $settings->securityMatchExact = true;
    $settings->securityBanned = 'spam';
    expect($security->checkSecurityPolicy($candidate('Some SPAM here')))->toBeFalse();
    expect($security->checkSecurityPolicy($candidate('A spammy word')))->toBeTrue();
    expect($security->checkSecurityPolicy($candidate("First line\nspam at the start of another")))->toBeFalse();

    $settings->securityBanned = 'c++/';
    expect($security->checkSecurityPolicy($candidate('Nothing to see')))->toBeTrue();
});

it('counts a comment’s length in characters, up to and including the limit', function() {
    $security = Comments::$plugin->getSecurity();
    Comments::$plugin->getSettings()->securityMaxLength = 5;

    expect($security->checkCommentLength(new Comment(['comment' => 'éééééé'])))->toBeFalse();
    expect($security->checkCommentLength(new Comment(['comment' => 'ééééé'])))->toBeTrue();
});

it('leaves comments their authors deleted out of approved results', function() {
    $entry = CommentsFixtureFactory::entry('Readiness author removal');
    $kept = readinessComment($entry, 'Kept');
    $deleted = readinessComment($entry, 'Deleted by its author');
    Comments::$plugin->getComments()->authorRemove((int)$deleted->id, (int)$entry->siteId);

    expect(Comment::find()->ownerId($entry->id)->ids())->toEqual([$kept->id]);
    expect(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(2);

    // Moderators can still find them, and they don't read as approved.
    $found = Comment::find()->ownerId($entry->id)->status(Comment::STATUS_AUTHOR_REMOVED)->all();
    expect(array_map(static fn($comment) => $comment->id, $found))->toEqual([$deleted->id]);
    expect($found[0]->getStatus())->toBe(Comment::STATUS_AUTHOR_REMOVED);
    expect($found[0]->status)->toBe(Comment::STATUS_APPROVED);
});

it('counts the same comments for the form as for posting when limiting per user', function() {
    $entry = CommentsFixtureFactory::entry('Readiness user limit');
    $settings = Comments::$plugin->getSettings();
    $comment = readinessComment($entry);
    Comments::$plugin->getModeration()->setState($comment, ModerationState::Pending, $this->admin);
    $settings->maxUserComments = 1;

    expect($settings->commentingAvailable($entry)['permission'])->toBeFalse();
    expect(fn() => readinessComment($entry))->toThrow(yii\base\UserException::class);

    // Something a moderator has marked as spam doesn't use up what they're allowed.
    Comments::$plugin->getModeration()->markSpam($comment, $this->admin);
    expect($settings->commentingAvailable($entry)['permission'])->toBeTrue();
    expect(readinessComment($entry)->id)->not->toBeNull();
});

it('shows every comment in the dashboard widget by default, flagged or not', function() {
    $entry = CommentsFixtureFactory::entry('Readiness widget');
    $comment = readinessComment($entry, 'Shown in the widget');

    expect((new CommentsWidget())->getBodyHtml())->toContain('Shown in the widget');
});

it('tells other plugins when a comment’s moderation state changes', function() {
    $entry = CommentsFixtureFactory::entry('Readiness event');
    $comment = readinessComment($entry);
    $events = [];
    $handler = static function(ModerationEvent $event) use (&$events) {
        $events[] = [$event->comment->id, $event->previousState, $event->state, $event->actor?->id];
    };
    Event::on(Moderation::class, Moderation::EVENT_AFTER_CHANGE_STATE, $handler);

    try {
        Comments::$plugin->getModeration()->markSpam($comment, $this->admin);
    } finally {
        Event::off(Moderation::class, Moderation::EVENT_AFTER_CHANGE_STATE, $handler);
    }

    expect($events)->toBe([[$comment->id, ModerationState::Approved, ModerationState::Spam, $this->admin->id]]);
});

it('returns the submitter and comment on GraphQL flags together', function() {
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema(new craft\models\GqlSchema(['scope' => ['usergroups.everyone:read', 'comments:read']]));

    try {
        expect(verbb\comments\gql\interfaces\Flag::getFieldDefinitions())->toHaveKeys(['userId', 'user', 'commentId', 'comment']);
    } finally {
        $gql->setActiveSchema(null);
        $gql->flushCaches();
    }
});

it('subscribes through GraphQL without a site, using the primary one', function() {
    $entry = CommentsFixtureFactory::entry('Readiness subscribe');
    Comments::$plugin->getSettings()->subscriptionTypes = [verbb\comments\enums\SubscriptionType::Conversation->value];
    $schema = new craft\models\GqlSchema(['name' => 'Readiness', 'scope' => ['comments:read', 'comments:save']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $result = $gql->executeQuery($schema, "mutation { subscribeComment(ownerId: {$entry->id}) }", debugMode: true);
    $missing = $gql->executeQuery($schema, 'mutation { subscribeComment(ownerId: 99999) }', debugMode: true);
    $gql->setActiveSchema(null);

    expect($result['errors'][0]['message'] ?? '')->toBe('');
    expect($missing['errors'][0]['message'] ?? '')->toContain('unavailable');
});
