<?php

declare(strict_types=1);

use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\HttpClient;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\enums\ReviewSetting;
use verbb\comments\models\CommentSubmission;

/**
 * The moderation controllers through real HTTP: sessions, CSRF and permissions as a browser meets them.
 */

const COMMENTS_HTTP_PASSWORD = 'Comments-Http-Test-1!';

function httpUser(string $prefix, array $permissions): User
{
    $user = CommentsFixtureFactory::user($prefix);
    $user->newPassword = COMMENTS_HTTP_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, array_merge(['accessCp'], $permissions));

    return $user;
}

function httpBrowser(User $user): HttpClient
{
    // Craft 5.12 rate-limits sign-ins per address, and every browser here signs in from the same one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();
    $browser->login((string)$user->username, COMMENTS_HTTP_PASSWORD);

    return $browser;
}

function httpComment(string $content, ?ModerationState $state = null): Comment
{
    $entry = CommentsFixtureFactory::entry('HTTP owner ' . uniqid());
    Comments::$plugin->getSettings()->requireModeration = false;
    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)CommentsFixtureFactory::user('httpAuthor')->id,
    ]))->comment;

    if ($state === ModerationState::Pending) {
        Comments::$plugin->getModeration()->setState($comment, ModerationState::Pending);
    } elseif ($state === ModerationState::Spam) {
        Comments::$plugin->getModeration()->markSpam($comment);
    }

    return $comment;
}

function httpState(Comment $comment): ?string
{
    return (new craft\db\Query())->select(['moderationState'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar() ?: null;
}

beforeAll(function() {
    // The web app reads the edition from project config, and in Solo every user can do everything,
    // which would let every refusal below pass for the wrong reason.
    Craft::$app->getProjectConfig()->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
});

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
});

// A check that always answers "forbidden" would pass every refusal below, so prove an allowed request works.
it('lets a moderator list commenters and decide a comment, as a baseline for the refusals', function() {
    $moderator = httpUser('httpModerator', ['comments-view', 'comments-moderate', 'comments-trash']);
    $browser = httpBrowser($moderator);
    $held = httpComment('Waiting over HTTP', ModerationState::Pending);

    $list = $browser->get('/admin/actions/comments/commenters/table-data', ['source' => 'all']);

    expect($list['status'])->toBe(200, $list['body'])
        ->and($list['data']['total'])->toBeGreaterThan(0);

    $decided = $browser->post('/admin/actions/comments/moderation/decide', ['commentId' => $held->id, 'decision' => 'approve']);

    expect($decided['status'])->toBe(200, $decided['body'])
        ->and($decided['data']['message'] ?? null)->toBe('Comment approved.')
        ->and(httpState($held))->toBe('approved');
});

it('refuses moderation to people who can only view comments, and requests without CSRF', function() {
    $viewer = httpUser('httpViewer', ['comments-view']);
    $browser = httpBrowser($viewer);
    $held = httpComment('Still waiting', ModerationState::Pending);

    expect($browser->get('/admin/actions/comments/commenters/table-data', ['source' => 'all'])['status'])->toBe(403)
        ->and($browser->post('/admin/actions/comments/moderation/decide', ['commentId' => $held->id, 'decision' => 'approve'])['status'])->toBe(403)
        ->and($browser->post('/admin/actions/comments/commenters/set-review-setting', ['identityId' => $held->identityId, 'reviewSetting' => 'blocked'])['status'])->toBe(403)
        ->and(httpState($held))->toBe('pending');

    $moderator = httpBrowser(httpUser('httpCsrf', ['comments-view', 'comments-moderate']));

    expect($moderator->post('/admin/actions/comments/moderation/decide', ['commentId' => $held->id, 'decision' => 'approve'], false)['status'])->toBe(400)
        ->and(httpState($held))->toBe('pending');
});

it('only removes for people who can trash comments, and keeps a reported comment', function() {
    $moderator = httpBrowser(httpUser('httpNoTrash', ['comments-view', 'comments-moderate']));
    $reported = httpComment('Reported over HTTP');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$reported->id, (int)$reporter->id, 'spam');

    expect($moderator->post('/admin/actions/comments/moderation/decide', ['commentId' => $reported->id, 'decision' => 'remove'])['status'])->toBe(403)
        ->and(httpState($reported))->toBe('approved');

    $kept = $moderator->post('/admin/actions/comments/moderation/decide', ['commentId' => $reported->id, 'decision' => 'keep']);

    expect($kept['status'])->toBe(200, $kept['body'])
        ->and(Comments::$plugin->getModeration()->hasOpenReports($reported))->toBeFalse();
});

it('deletes only spam permanently, and only for people who can delete', function() {
    $spam = httpComment('Spam over HTTP', ModerationState::Spam);
    $approved = httpComment('Fine over HTTP');
    $noDelete = httpBrowser(httpUser('httpNoDelete', ['comments-view', 'comments-moderate']));

    expect($noDelete->post('/admin/actions/comments/moderation/delete-permanently', ['commentId' => $spam->id])['status'])->toBe(403);

    $deleter = httpBrowser(httpUser('httpDeleter', ['comments-view', 'comments-moderate', 'comments-delete']));
    $refused = $deleter->post('/admin/actions/comments/moderation/delete-permanently', ['commentId' => $approved->id]);

    expect($refused['status'])->toBe(400)
        ->and($refused['data']['message'] ?? null)->toBe('Only spam can be deleted permanently.')
        ->and(Comment::find()->id($approved->id)->status(null)->exists())->toBeTrue();

    $deleted = $deleter->post('/admin/actions/comments/moderation/delete-permanently', ['commentId' => $spam->id]);

    expect($deleted['status'])->toBe(200, $deleted['body'])
        ->and(Comment::find()->id($spam->id)->status(null)->trashed(null)->exists())->toBeFalse();
});

it('sets a review setting for several commenters at once', function() {
    $browser = httpBrowser(httpUser('httpSettings', ['comments-view', 'comments-moderate']));
    $first = httpComment('First commenter');
    $second = httpComment('Second commenter');

    $response = $browser->post('/admin/actions/comments/commenters/set-review-setting', [
        'identityIds' => [$first->identityId, $second->identityId],
        'reviewSetting' => 'trusted',
        'note' => 'Regulars',
    ]);

    expect($response['status'])->toBe(200, $response['body'])
        ->and(Comments::$plugin->getModeration()->getReviewSetting((int)$first->identityId))->toBe(ReviewSetting::Trusted)
        ->and(Comments::$plugin->getModeration()->getReviewSetting((int)$second->identityId))->toBe(ReviewSetting::Trusted)
        ->and($browser->post('/admin/actions/comments/commenters/set-review-setting', ['identityId' => $first->identityId, 'reviewSetting' => 'sometimes'])['status'])->toBe(400);
});

it('approves through the comment editor’s own save, saving the edit with the decision', function() {
    $held = httpComment('Before the edit', ModerationState::Pending);
    // An admin, because earlier tests leave other sites behind and Craft's per-site edit permission
    // isn't what this checks. The permission tests above cover who may decide.
    $editor = httpUser('httpEditor', []);
    $editor->admin = true;
    Craft::$app->getElements()->saveElement($editor, false);
    $browser = httpBrowser($editor);

    $response = $browser->post('/admin/actions/elements/save', [
        'elementId' => $held->id,
        'siteId' => $held->siteId,
        'decision' => 'approve',
        'status' => 'pending',
        'contentFormat' => 'plain',
        'fields' => ['comment' => 'After the edit'],
    ]);

    expect($response['status'])->toBe(200, $response['body'])
        ->and(httpState($held))->toBe('approved')
        ->and((new craft\db\Query())->select(['comment'])->from('{{%comments_comments}}')->where(['id' => $held->id])->scalar())->toBe('After the edit');
});

it('lets a moderator keep a reported comment from the site, and nobody else', function() {
    Comments::$plugin->getSettings()->allowFlagging = true;
    $reported = httpComment('Reported on the site');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$reported->id, (int)$reporter->id, 'spam');

    $member = httpBrowser(httpUser('httpMember', []));
    $refused = $member->post('/comments/api/v1/moderate', ['commentId' => $reported->id, 'moderationAction' => 'moderate-keep']);

    expect($refused['data']['status'] ?? null)->toBe('error')
        ->and(Comments::$plugin->getModeration()->hasOpenReports($reported))->toBeTrue();

    $moderator = httpBrowser(httpUser('httpSiteModerator', ['comments-view', 'comments-moderate']));
    $kept = $moderator->post('/comments/api/v1/moderate', ['commentId' => $reported->id, 'moderationAction' => 'moderate-keep']);

    expect($kept['data']['status'] ?? null)->toBe('ok', $kept['body'])
        ->and(Comments::$plugin->getModeration()->hasOpenReports($reported))->toBeFalse();
});

it('adds a note from the Activity tab for moderators only, escaped, and refuses an empty one', function() {
    $comment = httpComment('Needs a note');
    $note = '<img src=x onerror=alert(1)> Checked with the author';

    $viewer = httpBrowser(httpUser('httpNoteViewer', ['comments-view']));

    expect($viewer->post('/admin/actions/comments/moderation/add-note', ['commentId' => $comment->id, 'note' => $note])['status'])->toBe(403);

    $moderator = httpUser('httpNoter', ['comments-view', 'comments-moderate']);
    $browser = httpBrowser($moderator);

    expect($browser->post('/admin/actions/comments/moderation/add-note', ['commentId' => $comment->id, 'note' => $note], false)['status'])->toBe(400)
        ->and($browser->post('/admin/actions/comments/moderation/add-note', ['commentId' => 999999, 'note' => $note])['status'])->toBe(404);

    $empty = $browser->post('/admin/actions/comments/moderation/add-note', ['commentId' => $comment->id, 'note' => '   ']);

    expect($empty['status'])->toBe(400)
        ->and($empty['data']['message'] ?? null)->toBe('Internal note cannot be empty.')
        ->and((new craft\db\Query())->from('{{%comments_moderatornotes}}')->where(['commentId' => $comment->id])->exists())->toBeFalse();

    $added = $browser->post('/admin/actions/comments/moderation/add-note', ['commentId' => $comment->id, 'note' => $note]);
    $rows = (new craft\db\Query())->from('{{%comments_moderatornotes}}')->where(['commentId' => $comment->id])->all();

    // The answer is the refreshed tab, with the new note in it and its markup escaped.
    expect($added['status'])->toBe(200, $added['body'])
        ->and($added['data']['message'] ?? null)->toBe('Note added.')
        ->and($added['data']['label'] ?? null)->toBe('Activity')
        ->and($added['data']['html'] ?? '')->toContain('data-comments-panel="activity"')
        ->toContain('&lt;img src=x onerror=alert(1)&gt; Checked with the author')
        ->not->toContain('<img src=x')
        ->and($rows)->toHaveCount(1)
        ->and((int)$rows[0]['userId'])->toBe((int)$moderator->id)
        ->and($rows[0]['note'])->toBe($note);
});

it('gives the queue counts to moderators only', function() {
    httpComment('Counted', ModerationState::Pending);
    $counts = Comments::$plugin->getModeration()->getQueueCounts();

    expect(httpBrowser(httpUser('httpCountViewer', ['comments-view']))->get('/admin/actions/comments/moderation/queue-counts')['status'])->toBe(403);

    $response = httpBrowser(httpUser('httpCounter', ['comments-view', 'comments-moderate']))->get('/admin/actions/comments/moderation/queue-counts');

    expect($response['status'])->toBe(200, $response['body'])
        ->and($response['data']['needs-review'])->toBe($counts['pending'])
        ->and($response['data']['needs-review'])->toBeGreaterThan(0)
        ->and($response['data']['total'])->toBe($counts['pending'] + $counts['reported']);
});

it('shows a commenter’s page to moderators without their address, and to admins with it', function() {
    $comment = httpComment('About a commenter');
    $address = (string)User::find()->id($comment->userId)->one()->email;
    $path = '/admin/comments/commenters/' . $comment->identityId;

    expect(httpBrowser(httpUser('httpPageViewer', ['accessPlugin-comments', 'comments-view']))->get($path)['status'])->toBe(403);

    $moderated = httpBrowser(httpUser('httpPageModerator', ['accessPlugin-comments', 'comments-view', 'comments-moderate']))->get($path);

    expect($moderated['status'])->toBe(200, $moderated['body'])
        ->and($moderated['body'])->toContain('comments-commenter__name')
        ->not->toContain($address);

    $admin = httpUser('httpPageAdmin', []);
    $admin->admin = true;
    Craft::$app->getElements()->saveElement($admin, false);
    $adminPage = httpBrowser($admin)->get($path);

    expect($adminPage['status'])->toBe(200, $adminPage['body'])
        ->and($adminPage['body'])->toContain($address)
        ->and(httpBrowser($admin)->get('/admin/comments/commenters/999999')['status'])->toBe(404);
});
