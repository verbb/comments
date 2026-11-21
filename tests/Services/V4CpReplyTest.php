<?php

declare(strict_types=1);

use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\HttpClient;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationMode;
use verbb\comments\enums\ModerationState;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\exceptions\RefusedException;
use verbb\comments\models\CommentSubmission;
use yii\web\ForbiddenHttpException;

/**
 * Moderators replying from the control panel (SD-83): who can reply to what, how the reply is saved, and
 * the reply slideout and its save through real HTTP requests.
 */

const COMMENTS_CP_REPLY_PASSWORD = 'Comments-Reply-Test-1!';

function cpReplyUser(string $prefix, array $permissions): User
{
    $user = CommentsFixtureFactory::user($prefix . bin2hex(random_bytes(3)));
    $user->newPassword = COMMENTS_CP_REPLY_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, array_merge(['accessCp', 'comments-view'], $permissions));

    return User::find()->id($user->id)->status(null)->one();
}

function cpReplyParent(?ModerationState $state = null): Comment
{
    $entry = CommentsFixtureFactory::entry('CP reply owner ' . uniqid());
    $author = CommentsFixtureFactory::user('cpReplyAuthor');
    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Is this open on weekends?',
        'userId' => (int)$author->id,
    ]))->comment;

    if ($state === ModerationState::Pending) {
        Comments::$plugin->getModeration()->setState($comment, ModerationState::Pending);
    } elseif ($state === ModerationState::Spam) {
        Comments::$plugin->getModeration()->markSpam($comment);
    }

    return Comment::find()->id($comment->id)->status(null)->one();
}

function cpReplyRow(Comment $parent): ?array
{
    return (new craft\db\Query())->from('{{%comments_comments}}')->where(['parentId' => $parent->id])->one() ?: null;
}

function cpReplyBrowser(User $user): HttpClient
{
    // Craft 5.12 rate-limits sign-ins per address, and every browser here signs in from the same one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();
    $browser->login((string)$user->username, COMMENTS_CP_REPLY_PASSWORD);

    return $browser;
}

beforeAll(function() {
    // In Solo every user can do everything, which would let every refusal below pass for the wrong reason.
    Craft::$app->getProjectConfig()->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
});

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $this->original = $settings->getAttributes();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->closed = false;
    $settings->autoCloseDays = null;
    $settings->maxReplyDepth = null;
    $this->moderator = cpReplyUser('cpReplyMod', ['comments-moderate']);
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
    Comments::$plugin->getConversations()->clearCaches();
    Craft::$app->getUser()->setIdentity(null);
});

it('lets moderators reply to published comments only, within the reply depth and while comments are on', function() {
    $editor = Comments::$plugin->getCommentEditor();
    $viewer = cpReplyUser('cpReplyViewer', []);
    $approved = cpReplyParent();

    expect($editor->canReply($approved, $this->moderator))->toBeTrue()
        ->and($editor->canReply($approved, $viewer))->toBeFalse()
        ->and($editor->canReply($approved, null))->toBeFalse()
        ->and($editor->canReply(cpReplyParent(ModerationState::Pending), $this->moderator))->toBeFalse()
        ->and($editor->canReply(cpReplyParent(ModerationState::Spam), $this->moderator))->toBeFalse();

    // Reported comments are still published, so they can be answered.
    Comments::$plugin->getSettings()->maxReplyDepth = 0;
    expect($editor->canReply($approved, $this->moderator))->toBeFalse();
    Comments::$plugin->getSettings()->maxReplyDepth = null;

    // Closed still takes a staff reply. Off doesn't, since the thread isn't shown.
    Comments::$plugin->getConversations()->setClosed((int)$approved->ownerId, (int)$approved->ownerSiteId, true);
    Comments::$plugin->getConversations()->clearCaches();
    expect($editor->canReply($approved, $this->moderator))->toBeTrue();

    $conversation = Comments::$plugin->getConversations()->getOrCreateForOwner((int)$approved->ownerId, (int)$approved->ownerSiteId);
    $conversation->moderationMode = ModerationMode::Disabled->value;
    $conversation->save(false);
    Comments::$plugin->getConversations()->clearCaches();
    expect($editor->canReply($approved, $this->moderator))->toBeFalse();
});

it('publishes the reply as the moderator under its comment, past pre-moderation, spam checks, keyword filters and a closed discussion', function() {
    $parent = cpReplyParent();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $settings->holdFirstComments = true;
    $settings->enableSpamChecks = true;
    $settings->replayProtectionEnabled = true;
    $settings->rateLimitEnabled = true;
    $settings->rateLimitCount = 1;
    $settings->securityModeration = 'weekends';
    Comments::$plugin->getConversations()->setClosed((int)$parent->ownerId, (int)$parent->ownerSiteId, true);
    Comments::$plugin->getConversations()->clearCaches();
    Craft::$app->getUser()->setIdentity($this->moderator);

    // The same reply posted any other way is refused: the discussion is closed.
    expect(fn() => Comments::$plugin->getComments()->create(new CommentSubmission([
        'conversationId' => (int)$parent->conversationId,
        'parentId' => (int)$parent->id,
        'userId' => (int)$this->moderator->id,
        'content' => 'Sneaking in',
    ])))->toThrow(RefusedException::class);

    $first = Comments::$plugin->getCommentEditor()->reply($parent, $this->moderator, ContentFormat::Plain, 'Yes, 9 to 5 on Saturdays.');
    // A second within the rate limit, and without any spam protection evidence.
    $second = Comments::$plugin->getCommentEditor()->reply($parent, $this->moderator, ContentFormat::Plain, 'And 10 to 4 on weekends too.');

    foreach ([$first, $second] as $result) {
        $reply = Comment::find()->id($result->comment->id)->status(null)->one();

        expect($result->moderationState)->toBe(ModerationState::Approved)
            ->and($reply->status)->toBe(Comment::STATUS_APPROVED)
            ->and($reply->heldReason)->toBeNull()
            ->and((int)$reply->userId)->toBe((int)$this->moderator->id)
            ->and((int)$reply->parentId)->toBe((int)$parent->id)
            ->and((int)$reply->conversationId)->toBe((int)$parent->conversationId)
            ->and((int)$reply->depth)->toBe((int)$parent->depth + 1);
    }

    expect(Comment::find()->id($first->comment->id)->one()->comment)->toBe('Yes, 9 to 5 on Saturdays.');
});

it('still keeps spam protection for the moderator’s comments posted anywhere else', function() {
    $parent = cpReplyParent();
    $settings = Comments::$plugin->getSettings();
    $settings->enableSpamChecks = true;
    // Only the rate limit, so the first comment needs no prepared evidence.
    $settings->replayProtectionEnabled = false;
    $settings->rateLimitEnabled = true;
    $settings->rateLimitCount = 1;
    Craft::$app->getUser()->setIdentity($this->moderator);

    $submission = fn(string $text) => new CommentSubmission([
        'conversationId' => (int)$parent->conversationId,
        'parentId' => (int)$parent->id,
        'userId' => (int)$this->moderator->id,
        'content' => $text,
    ]);

    Comments::$plugin->getComments()->create($submission('First from the site'));

    expect(fn() => Comments::$plugin->getComments()->create($submission('Second from the site')))->toThrow(ProtectionException::class);
});

it('refuses a reply the moderator can’t make, an empty one, and one past the reply depth', function() {
    $editor = Comments::$plugin->getCommentEditor();
    $viewer = cpReplyUser('cpReplyRefused', []);
    $parent = cpReplyParent();

    expect(fn() => $editor->reply($parent, $viewer, ContentFormat::Plain, 'Not mine to answer'))->toThrow(ForbiddenHttpException::class)
        ->and(fn() => $editor->reply(cpReplyParent(ModerationState::Pending), $this->moderator, ContentFormat::Plain, 'Too soon'))->toThrow(ForbiddenHttpException::class)
        ->and(fn() => $editor->reply($parent, $this->moderator, ContentFormat::Plain, "  \n "))->toThrow(yii\base\UserException::class, 'Comment content cannot be empty.');

    Comments::$plugin->getSettings()->maxReplyDepth = 0;

    expect(fn() => $editor->reply($parent, $this->moderator, ContentFormat::Plain, 'Too deep'))->toThrow(ForbiddenHttpException::class)
        ->and(cpReplyRow($parent))->toBeNull();
});

it('offers Reply on the comment’s page to moderators, and not to people who can only view', function() {
    $parent = cpReplyParent();
    $viewer = cpReplyUser('cpReplyPageViewer', []);

    Craft::$app->getUser()->setIdentity($this->moderator);
    expect($parent->getAdditionalButtons())->toContain('data-comments-reply="' . $parent->id . '"');

    Craft::$app->getUser()->setIdentity($viewer);
    expect($parent->getAdditionalButtons())->not->toContain('data-comments-reply');

    Craft::$app->getUser()->setIdentity($this->moderator);
    expect(cpReplyParent(ModerationState::Pending)->getAdditionalButtons())->not->toContain('data-comments-reply');
});

it('opens the reply slideout and posts the reply over HTTP, for moderators only', function() {
    $parent = cpReplyParent();
    $browser = cpReplyBrowser($this->moderator);
    $screen = $browser->get('/admin/actions/comments/moderation/reply', ['commentId' => $parent->id], ['X-Craft-Container-Id' => 'reply-test']);

    expect($screen['status'])->toBe(200, $screen['body'])
        ->and($screen['data']['action'] ?? null)->toBe('comments/moderation/save-reply')
        ->and($screen['data']['submitButtonLabel'] ?? null)->toBe('Post reply')
        ->and($screen['data']['title'] ?? '')->toStartWith('Reply to ')
        ->and($screen['data']['content'] ?? '')->toContain('Is this open on weekends?')
        ->toContain('[commentId]')
        ->and($screen['data']['bodyHtml'] ?? '')->toContain('initCommentEditor');

    $namespace = $screen['data']['namespace'];
    $post = fn(HttpClient $client, array $fields) => $client->request('POST', '/admin/actions/comments/moderation/save-reply', [
        // As Craft's slideout sends it: the namespaced form, with the CSRF token in a header.
        'form_params' => [$namespace => $fields],
        'headers' => ['X-Craft-Namespace' => $namespace, 'X-CSRF-Token' => $client->csrfToken()],
    ]);

    $empty = $post($browser, ['commentId' => $parent->id, 'contentFormat' => 'plain', 'fields' => ['comment' => ' ']]);

    expect($empty['status'])->toBe(400, $empty['body'])
        ->and($empty['data']['message'] ?? null)->toBe('Comment content cannot be empty.')
        ->and(cpReplyRow($parent))->toBeNull();

    $saved = $post($browser, ['commentId' => $parent->id, 'contentFormat' => 'plain', 'fields' => ['comment' => 'We are, from 9.']]);
    $row = cpReplyRow($parent);

    expect($saved['status'])->toBe(200, $saved['body'])
        ->and($saved['data']['message'] ?? null)->toBe('Reply posted.')
        ->and((int)($saved['data']['commentId'] ?? 0))->toBe((int)$row['id'])
        ->and($row['moderationState'])->toBe('approved')
        ->and((int)$row['userId'])->toBe((int)$this->moderator->id);

    // Someone who can only view comments gets neither the form nor the save.
    $viewer = cpReplyBrowser(cpReplyUser('cpReplyHttpViewer', []));

    expect($viewer->get('/admin/actions/comments/moderation/reply', ['commentId' => $parent->id], ['X-Craft-Container-Id' => 'reply-test'])['status'])->toBe(403)
        ->and($post($viewer, ['commentId' => $parent->id, 'contentFormat' => 'plain', 'fields' => ['comment' => 'Me too']])['status'])->toBe(403);

    // A waiting comment is decided before it's answered.
    $waiting = cpReplyParent(ModerationState::Pending);

    expect($browser->get('/admin/actions/comments/moderation/reply', ['commentId' => $waiting->id], ['X-Craft-Container-Id' => 'reply-test'])['status'])->toBe(403)
        ->and(cpReplyRow($waiting))->toBeNull();
});
