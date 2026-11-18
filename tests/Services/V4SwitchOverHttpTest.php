<?php

declare(strict_types=1);

use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\SpamSettingsStore;
use Tests\Support\HttpClient;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\services\Subscriptions;

/**
 * The switch-over through real requests: the HTML fragment endpoints are gone, the Comments 3 form
 * actions still take JSON and plain form posts as a bridge (SD-37), and the guest subscription page
 * from email links is still a server-rendered page.
 */

const SWITCH_OVER_PASSWORD = 'Comments-SwitchOver-Http-1!';

function switchOverHttpComment($entry, string $text, ?User $user = null, ?int $parentId = null): Comment
{
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'parentId' => $parentId,
        'userId' => $user ? (int)$user->id : (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')->id,
    ]))->comment;
}

function switchOverUser(string $prefix): User
{
    $user = CommentsFixtureFactory::user($prefix);
    $user->newPassword = SWITCH_OVER_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    return $user;
}

function switchOverBrowser(?User $user = null): HttpClient
{
    // Craft limits sign-ins per address, and every browser here signs in from the same one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();

    if ($user) {
        $browser->login((string)$user->username, SWITCH_OVER_PASSWORD);
    }

    return $browser;
}

/** A plain HTML form post, as a site's own Comments 3 template sends one. */
function switchOverForm(HttpClient $browser, string $path, array $fields): array
{
    $fields['CRAFT_CSRF_TOKEN'] = $browser->csrfToken();

    return $browser->request('POST', $path, ['form_params' => $fields, 'headers' => ['Accept' => 'text/html']]);
}


/** Run `$callback` with these settings in project config, which the web app reads, then put them back. */
function switchOverWithSettings(array $values, callable $callback): mixed
{
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = [];
    $spam = SpamSettingsStore::snapshot();

    foreach ($values as $name => $value) {
        $previous[$name] = $projectConfig->get('plugins.comments.settings.' . $name);
        $projectConfig->set('plugins.comments.settings.' . $name, $value);
    }

    $projectConfig->saveModifiedConfigData();
    SpamSettingsStore::apply($values);

    try {
        return $callback();
    } finally {
        SpamSettingsStore::restore($spam);

        foreach ($previous as $name => $value) {
            // A value Craft would treat as unchanged is changed first, or its return isn't saved.
            $projectConfig->set('plugins.comments.settings.' . $name, 'switch-over');
            $projectConfig->set('plugins.comments.settings.' . $name, $value);
        }

        $projectConfig->saveModifiedConfigData();
    }
}

/** A guest's JSON post to the bridge's save, with whatever else is given. */
function switchOverSave(HttpClient $browser, $entry, array $fields = []): array
{
    return $browser->post('/actions/comments/comments/save', array_replace_recursive([
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['comment' => 'Through the bridge', 'name' => 'Guest Poster'],
    ], $fields));
}

function switchOverBlocked(User $member, $entry): void
{
    $own = switchOverHttpComment($entry, 'Before the block', $member);
    Comments::$plugin->getModeration()->blockIdentity((int)$own->identityId, null, Craft::$app->getUsers()->getUserByUsernameOrEmail('admin'), 'Enough');
}

beforeAll(function() {
    // The web app reads the settings from project config, not from this process. Only the settings these
    // requests need are changed there, and the whole of it is put back afterwards.
    $projectConfig = Craft::$app->getProjectConfig();
    $GLOBALS['switchOverConfig'] = $projectConfig->get('plugins.comments.settings');
    $GLOBALS['switchOverSpam'] = SpamSettingsStore::snapshot();
    $GLOBALS['switchOverSettings'] = Comments::$plugin->getSettings()->getAttributes();

    foreach ([
        'enableSpamChecks' => false,
        'requireModeration' => false,
        'allowGuest' => true,
        'guestRequireEmailName' => false,
        'guestShowEmailName' => true,
        'allowGuestVoting' => true,
        'allowGuestFlagging' => true,
        'participantNotifications' => [],
        'staffNotifications' => [],
        'subscriptionTypes' => ['conversation', 'replies'],
    ] as $name => $value) {
        $projectConfig->set('plugins.comments.settings.' . $name, $value);
        SpamSettingsStore::apply([$name => $value]);
    }

    $projectConfig->saveModifiedConfigData();

    // A page of the site's own, showing what a Comments 3 template would: Craft's messages and `errors`.
    craft\helpers\FileHelper::writeToFile(
        Craft::$app->getPath()->getSiteTemplatesPath() . '/switch-over-form.twig',
        'notice={{ craft.app.session.getFlash(\'notice\') }}|error={{ craft.app.session.getFlash(\'error\') }}|errors={{ (errors ?? null) | json_encode | raw }}|comment={{ comment.comment ?? \'\' }}',
    );

    // A refill page that would show anything a stored comment holds, if one ever reached it.
    craft\helpers\FileHelper::writeToFile(
        Craft::$app->getPath()->getSiteTemplatesPath() . '/switch-over-refill.twig',
        'C={{ comment.comment ?? \'\' }}|N={{ comment.name ?? \'\' }}|E={{ comment.email ?? \'\' }}|IP={{ comment.ipAddress ?? \'\' }}|S={{ comment.status ?? \'\' }}|U={{ comment.userId ?? \'\' }}|ID={{ comment.id ?? \'\' }}',
    );

    // One that reaches through `comment` for its parent, replies and owner, as a template might.
    craft\helpers\FileHelper::writeToFile(
        Craft::$app->getPath()->getSiteTemplatesPath() . '/switch-over-reach.twig',
        'T={{ className(comment) }}|P={{ comment.parent.email ?? \'\' }}{{ comment.parent.comment ?? \'\' }}|R={% for reply in (comment.getReplies() ?? []) %}{{ reply.comment }}{{ reply.email }}{% endfor %}{% for reply in (comment.children ?? []) %}{{ reply.comment }}{% endfor %}|O={{ comment.owner.email ?? \'\' }}{{ comment.owner.title ?? \'\' }}|C={{ comment.comment ?? \'\' }}|E={{ comment.getErrors(\'comment\')|length }}',
    );

    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
    SpamSettingsStore::restore($GLOBALS['switchOverSpam']);
    @unlink(Craft::$app->getPath()->getSiteTemplatesPath() . '/switch-over-form.twig');
    @unlink(Craft::$app->getPath()->getSiteTemplatesPath() . '/switch-over-refill.twig');
    @unlink(Craft::$app->getPath()->getSiteTemplatesPath() . '/switch-over-reach.twig');
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = $GLOBALS['switchOverConfig'];

    if ($previous === null) {
        $projectConfig->remove('plugins.comments.settings');
    } else {
        $projectConfig->set('plugins.comments.settings', $previous);
    }

    $projectConfig->saveModifiedConfigData();
    Comments::$plugin->getSettings()->setAttributes($GLOBALS['switchOverSettings'], false);
});

beforeEach(function() {
    // Rate limits are per address, and every request here comes from the same one.
    Craft::$app->getCache()->flush();
});

it('no longer answers the HTML fragment endpoints', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over fragments owner');
    $browser = switchOverBrowser();

    foreach (['conversation', 'roots', 'viewer-controls', 'editor', 'protection', 'thread'] as $endpoint) {
        $response = $browser->get('/actions/comments/frontend-api/' . $endpoint, ['ownerId' => $entry->id, 'siteId' => $entry->siteId]);

        expect($response['status'])->toBe(404, $endpoint . ' ' . $response['status']);
    }

    $create = $browser->postJson('/actions/comments/frontend-api/create', ['ownerId' => $entry->id, 'fields' => ['comment' => 'Nope']], [], $browser->csrfToken());

    expect($create['status'])->toBe(404)
        ->and(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(0);
});

it('posts a comment through comments/comments/save, answering JSON when asked', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge JSON owner');
    $browser = switchOverBrowser();
    $response = $browser->post('/actions/comments/comments/save', [
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['comment' => 'From a Comments 3 form', 'name' => 'Guest Poster'],
    ]);

    expect($response['status'])->toBe(200, $response['body'])
        ->and($response['data']['success'] ?? null)->toBeTrue()
        ->and($response['data'])->toHaveKeys(['id', 'comment', 'notice'])
        ->and($response['data'])->not->toHaveKey('html')
        ->and(Comment::find()->ownerId($entry->id)->one()?->comment)->toBe('From a Comments 3 form');

    $refused = $browser->post('/actions/comments/comments/save', ['elementId' => $entry->id, 'siteId' => $entry->siteId, 'fields' => ['comment' => '']]);

    expect($refused['data']['success'] ?? null)->toBeFalse()
        ->and($refused['data']['errors']['comment'][0] ?? null)->toBeString();
});

it('takes a plain form post to comments/comments/save and redirects back with Craft’s notice', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge form owner');
    $browser = switchOverBrowser();
    $response = switchOverForm($browser, '/switch-over-form', [
        'action' => 'comments/comments/save',
        'redirect' => Craft::$app->getSecurity()->hashData('switch-over-form'),
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['comment' => 'Posted without JavaScript', 'name' => 'Plain Poster'],
    ]);

    expect($response['status'])->toBe(302, $response['body'])
        ->and($response['headers']['location'][0] ?? '')->toEndWith('/switch-over-form')
        ->and(Comment::find()->ownerId($entry->id)->one()?->comment)->toBe('Posted without JavaScript')
        ->and($browser->request('GET', '/switch-over-form', ['headers' => ['Accept' => 'text/html']])['body'])->toBe('notice=Comment posted.|error=|errors=null|comment=');
});

it('renders the page again with errors and Craft’s error when a plain form post fails', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge form error owner');
    $browser = switchOverBrowser();
    $response = switchOverForm($browser, '/switch-over-form', [
        'action' => 'comments/comments/save',
        'redirect' => Craft::$app->getSecurity()->hashData('switch-over-form'),
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['comment' => ''],
    ]);

    preg_match('/^notice=(.*)\|error=(.+)\|errors=(.+)\|comment=(.*)$/s', $response['body'], $parts);

    expect($response['status'])->toBe(200, $response['body'])
        ->and($parts[2] ?? null)->not->toBe('')
        ->and(json_decode($parts[3] ?? 'null', true)['comment'][0] ?? null)->toBe($parts[2] ?? null)
        ->and(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(0);
});

it('keeps the Comments 3 vote, flag and subscribe actions working', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge engagement owner');
    $member = switchOverUser('switchOverMember');
    $comment = switchOverHttpComment($entry, 'Worth a vote');
    $browser = switchOverBrowser($member);

    $vote = $browser->post('/actions/comments/comments/vote', ['commentId' => $comment->id, 'upvote' => 1]);
    $flag = $browser->post('/actions/comments/comments/flag', ['commentId' => $comment->id]);
    $subscribe = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId]);

    expect($vote['data'] ?? null)->toBe(['success' => true, 'type' => 'upvote'], $vote['body'])
        ->and($flag['data'] ?? null)->toBe(['success' => true, 'notice' => 'Comment has been reported.'], $flag['body'])
        ->and($subscribe['data']['subscribed'] ?? null)->toBeTrue($subscribe['body'])
        ->and(Comments::$plugin->getReports()->getOpenForComment((int)$comment->id))->not->toBe([]);

    // A plain form post goes back where it came from, with Craft's notice.
    $plain = switchOverForm($browser, '/switch-over-form', ['action' => 'comments/comments/flag', 'commentId' => $comment->id]);

    expect($plain['status'])->toBe(302)
        ->and($browser->request('GET', '/switch-over-form', ['headers' => ['Accept' => 'text/html']])['body'])->toStartWith('notice=Report withdrawn.|');
});

it('deletes an author’s own comment through Comments 3’s trash action, by the data API’s rules', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge trash owner');
    $member = switchOverUser('switchOverTrash');
    $other = switchOverUser('switchOverTrashOther');
    $theirs = switchOverHttpComment($entry, 'Someone else’s', $other);
    $own = switchOverHttpComment($entry, 'My own', $member);
    $browser = switchOverBrowser($member);

    $refused = $browser->post('/actions/comments/comments/trash', ['commentId' => $theirs->id]);
    $deleted = $browser->post('/actions/comments/comments/trash', ['commentId' => $own->id]);

    expect($refused['data']['success'] ?? null)->toBeFalse($refused['body'])
        ->and($deleted['data'] ?? null)->toBe(['success' => true], $deleted['body'])
        ->and(Comment::find()->id($own->id)->status(null)->one()->authorRemovedAt)->not->toBeNull()
        ->and(Comment::find()->id($theirs->id)->status(null)->one()->authorRemovedAt)->toBeNull();

    // A guest has no comment of their own to delete.
    $guest = switchOverBrowser()->post('/actions/comments/comments/trash', ['commentId' => $theirs->id]);

    expect($guest['status'])->not->toBe(200);

    // With compatibility mode off, the action doesn't exist.
    $off = switchOverWithSettings(['compatibilityMode' => false], fn() => switchOverBrowser($member)->post('/actions/comments/comments/trash', ['commentId' => $theirs->id]));

    expect($off['status'])->toBe(404, $off['body']);
});

it('still serves the guest subscription page from email links', function() {
    $subscriptions = Comments::$plugin->getSubscriptions();
    $identity = Comments::$plugin->getIdentities()->resolveOrCreateGuest(null, 'Switch Guest', 'switch-over-guest@example.test')['identity'];
    $url = $subscriptions->managementUrl(Subscriptions::PURPOSE_ALL, $identity, (int)Craft::$app->getSites()->getPrimarySite()->id);
    parse_str((string)parse_url($url, PHP_URL_QUERY), $parameters);
    $query = ['emailToken' => (string)($parameters['emailToken'] ?? '')];
    $browser = switchOverBrowser();

    $invalid = $browser->request('GET', '/actions/comments/subscriptions/manage', ['query' => ['emailToken' => 'nope'], 'headers' => ['Accept' => 'text/html']]);
    $page = $browser->request('GET', '/actions/comments/subscriptions/manage', ['query' => $query, 'headers' => ['Accept' => 'text/html']]);
    $applied = $browser->request('POST', '/actions/comments/subscriptions/manage', ['form_params' => $query + ['scope' => Subscriptions::PURPOSE_ALL], 'headers' => ['Accept' => 'text/html']]);

    expect($invalid['status'])->toBe(404)
        ->and($invalid['body'])->toContain('This link isn’t valid')
        ->and($page['status'])->toBe(200, $page['body'])
        ->and($page['body'])->toContain('Stop all comment emails')
        ->and($page['body'])->not->toContain('data-comments-root')
        ->and($applied['status'])->toBe(200, $applied['body'])
        ->and($applied['body'])->toContain('You’re unsubscribed');
});

// The bridge follows the data API's rules
// =========================================================================

it('refuses a bridge post onto an element the data API wouldn’t answer for', function() {
    $disabled = CommentsFixtureFactory::entry('Switch-over bridge disabled owner');
    $disabled->enabled = false;
    Craft::$app->getElements()->saveElement($disabled, false);
    $entry = CommentsFixtureFactory::entry('Switch-over bridge comment owner');
    $comment = switchOverHttpComment($entry, 'Not an owner');
    $browser = switchOverBrowser();

    $onDisabled = switchOverSave($browser, $disabled);
    $onComment = switchOverSave($browser, $entry, ['elementId' => $comment->id]);
    $noOwner = $browser->post('/actions/comments/comments/save', ['fields' => ['comment' => 'Nowhere']]);

    foreach ([$onDisabled, $onComment, $noOwner] as $response) {
        expect($response['status'])->toBe(200, $response['body'])
            ->and($response['data']['success'] ?? null)->toBeFalse()
            ->and($response['data']['errors']['comment'][0] ?? null)->toBeString();
    }

    expect(Comment::find()->ownerId($disabled->id)->status(null)->count())->toEqual(0)
        ->and(Comment::find()->ownerId($comment->id)->status(null)->count())->toEqual(0);
});

it('only takes bridge replies to an approved comment on the same owner', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge parent owner');
    $other = CommentsFixtureFactory::entry('Switch-over bridge other parent owner');
    $held = switchOverHttpComment($entry, 'Held parent');
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['moderationState' => 'pending', 'status' => Comment::STATUS_PENDING], ['id' => $held->id])->execute();
    $elsewhere = switchOverHttpComment($other, 'Elsewhere');
    $removed = switchOverHttpComment($entry, 'Removed by its author');
    Comments::$plugin->getComments()->authorRemove((int)$removed->id);
    $approved = switchOverHttpComment($entry, 'Approved parent');
    $browser = switchOverBrowser();

    foreach ([$held, $elsewhere, $removed] as $parent) {
        expect(switchOverSave($browser, $entry, ['parentId' => $parent->id])['data']['success'] ?? null)->toBeFalse();
    }

    $reply = switchOverSave($browser, $entry, ['parentId' => $approved->id]);

    expect($reply['data']['success'] ?? null)->toBeTrue($reply['body'])
        ->and((int)Comment::find()->id($reply['data']['id'])->status(null)->one()?->parentId)->toBe((int)$approved->id);
});

it('takes a Comments 3 reply form’s newParentId as the parent, held to the same rules', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge newParentId owner');
    $other = CommentsFixtureFactory::entry('Switch-over bridge newParentId other owner');
    $approved = switchOverHttpComment($entry, 'Approved parent for a Comments 3 form');
    $elsewhere = switchOverHttpComment($other, 'Parent on another owner');
    $browser = switchOverBrowser();

    $reply = switchOverSave($browser, $entry, ['newParentId' => $approved->id]);

    expect($reply['data']['success'] ?? null)->toBeTrue($reply['body'])
        ->and((int)Comment::find()->id($reply['data']['id'])->status(null)->one()?->parentId)->toBe((int)$approved->id)
        ->and(switchOverSave($browser, $entry, ['newParentId' => $elsewhere->id])['data']['success'] ?? null)->toBeFalse();
});

it('answers every refused bridge save cleanly, never with an error page', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge refusals owner');
    $browser = switchOverBrowser();
    $member = switchOverUser('switchOverLimit');
    $memberBrowser = switchOverBrowser($member);

    // Guests off.
    $guestsOff = switchOverWithSettings(['allowGuest' => false], fn() => switchOverSave($browser, $entry));

    // Closed by hand.
    Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, true);
    $closed = switchOverSave($browser, $entry);
    Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, false);

    // Closed by age.
    $old = CommentsFixtureFactory::entry('Switch-over bridge expired owner');
    $old->postDate = new DateTime('-30 days');
    Craft::$app->getElements()->saveElement($old, false);
    $expired = switchOverWithSettings(['autoCloseDays' => 10], fn() => switchOverSave($browser, $old));

    // The comment limit, for a member who has already posted.
    switchOverHttpComment($entry, 'Their first', $member);
    $limited = switchOverWithSettings(['maxUserComments' => 1], fn() => switchOverSave($memberBrowser, $entry, ['fields' => ['comment' => 'Their second']]));

    foreach (['guests off' => $guestsOff, 'closed' => $closed, 'expired' => $expired, 'limit' => $limited] as $case => $response) {
        expect($response['status'])->toBe(200, $case . ': ' . $response['body'])
            ->and($response['data']['success'] ?? null)->toBeFalse($case)
            ->and($response['data']['errors']['comment'][0] ?? null)->toBeString($case);
    }

    // The same refusal from a plain form renders the page again with what was typed and `errors`.
    $plain = switchOverWithSettings(['allowGuest' => false], fn() => switchOverForm($browser, '/switch-over-form', [
        'action' => 'comments/comments/save',
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['comment' => 'Kept for the visitor'],
    ]));

    expect($plain['status'])->toBe(200, $plain['body'])
        ->and($plain['body'])->toContain('|comment=Kept for the visitor')
        ->and($plain['body'])->toMatch('/\|errors=\{"comment":\[".+"\]\}/')
        ->and(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(1);
});

it('keys a field’s own problem by its name on a refused bridge save', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge field errors owner');
    $browser = switchOverBrowser();

    // A guest's name is required here, and missing.
    $response = switchOverWithSettings(['guestRequireEmailName' => true], fn() => $browser->post('/actions/comments/comments/save', [
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['comment' => 'No name', 'email' => 'no-name@example.test'],
    ]));

    expect($response['data']['success'] ?? null)->toBeFalse($response['body'])
        ->and($response['data']['errors']['comment'][0] ?? null)->toBeString()
        ->and($response['data']['errors']['name'][0] ?? null)->toBe('Name is required.');
});

it('only lets an author edit their own comment through the bridge, on a live owner', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge edit owner');
    $author = switchOverUser('switchOverAuthor');
    $someoneElse = switchOverUser('switchOverOther');
    $comment = switchOverHttpComment($entry, 'As first written', $author);
    $edit = fn(HttpClient $browser, string $text) => $browser->post('/actions/comments/comments/save', ['commentId' => $comment->id, 'siteId' => $entry->siteId, 'fields' => ['comment' => $text]]);

    $refused = $edit(switchOverBrowser($someoneElse), 'Not theirs');
    $guest = $edit(switchOverBrowser(), 'Not a guest’s');
    $authorBrowser = switchOverBrowser($author);
    $edited = $edit($authorBrowser, 'Edited by its author');

    expect($refused['data']['success'] ?? null)->toBeFalse($refused['body'])
        ->and($guest['data']['success'] ?? null)->toBeFalse($guest['body'])
        ->and($edited['data']['success'] ?? null)->toBeTrue($edited['body'])
        ->and(Comment::find()->id($comment->id)->one()?->comment)->toBe('Edited by its author');

    $entry->enabled = false;
    Craft::$app->getElements()->saveElement($entry, false);

    expect($edit($authorBrowser, 'On a disabled entry')['data']['success'] ?? null)->toBeFalse()
        ->and(Comment::find()->id($comment->id)->status(null)->one()?->comment)->toBe('Edited by its author');
});

it('keeps blocked members from following through the bridge, as the data API does (SD-45)', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge blocked follow owner');
    $member = switchOverUser('switchOverBlocked');
    $comment = switchOverHttpComment($entry, 'A comment to follow');
    switchOverBlocked($member, $entry);
    $browser = switchOverBrowser($member);

    $whole = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'subscribed' => 1]);
    $replies = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'commentId' => $comment->id]);
    $identityId = (int)Comments::$plugin->getIdentities()->findForUser((int)$member->id)->id;
    $conversation = Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId);

    expect($whole['data']['success'] ?? null)->toBeFalse($whole['body'])
        ->and($whole['data']['errors']['message'][0] ?? null)->toBe('You can’t take part in this discussion.')
        ->and($replies['data']['success'] ?? null)->toBeFalse($replies['body'])
        ->and(Comments::$plugin->getSubscriptions()->isSubscribed((int)$conversation->id, $identityId))->toBeFalse()
        ->and(Comments::$plugin->getSubscriptions()->isSubscribed((int)$conversation->id, $identityId, (int)$comment->id, SubscriptionType::Replies))->toBeFalse();
});

it('only follows live owners and approved comments through the bridge, and answers a missing owner cleanly', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge follow rules owner');
    $other = CommentsFixtureFactory::entry('Switch-over bridge follow other owner');
    $held = switchOverHttpComment($entry, 'Held, so not followable');
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['moderationState' => 'pending', 'status' => Comment::STATUS_PENDING], ['id' => $held->id])->execute();
    $approved = switchOverHttpComment($entry, 'Followable');
    $browser = switchOverBrowser(switchOverUser('switchOverFollower'));
    $conversations = (int)(new craft\db\Query())->from('{{%comments_conversations}}')->count();

    $missing = $browser->post('/actions/comments/comments/subscribe', []);
    $unknown = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => 999999999, 'siteId' => $entry->siteId]);
    $heldReplies = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'commentId' => $held->id]);
    $wrongOwner = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $other->id, 'siteId' => $entry->siteId, 'commentId' => $approved->id]);

    foreach (['missing' => $missing, 'unknown' => $unknown, 'held' => $heldReplies, 'wrong owner' => $wrongOwner] as $case => $response) {
        expect($response['status'])->toBe(200, $case . ': ' . $response['body'])
            ->and($response['data']['success'] ?? null)->toBeFalse($case);
    }

    expect((int)(new craft\db\Query())->from('{{%comments_conversations}}')->count())->toBe($conversations);

    $entry->enabled = false;
    Craft::$app->getElements()->saveElement($entry, false);

    expect($browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId])['data']['success'] ?? null)->toBeFalse()
        ->and($browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'commentId' => $approved->id])['data']['success'] ?? null)->toBeFalse();
});

it('has a guest confirm their address before following through the bridge', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge guest follow owner');
    $browser = switchOverBrowser();

    $noEmail = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId]);
    $withEmail = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'email' => 'bridge-follower@example.test']);
    $replies = $browser->post('/actions/comments/comments/subscribe', ['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'commentId' => switchOverHttpComment($entry, 'Replies')->id]);

    expect($noEmail['data']['success'] ?? null)->toBeFalse($noEmail['body'])
        ->and($withEmail['data'] ?? null)->toBe(['success' => true, 'subscribed' => false, 'notice' => 'Check your email to confirm your subscription.'], $withEmail['body'])
        ->and($replies['data']['errors']['message'][0] ?? null)->toBe('Must be logged in to subscribe.');
});

it('sends plain-form votes and follows back to the page, and never to another site', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge redirects owner');
    $comment = switchOverHttpComment($entry, 'Vote and follow');
    $browser = switchOverBrowser(switchOverUser('switchOverRedirects'));
    // The site's configured address, which is what counts, not the Host the request came in on.
    $host = parse_url((string)Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), PHP_URL_HOST);
    $plain = fn(array $fields, string $referrer, array $headers = []) => $browser->request('POST', '/actions/comments/comments/' . $fields['_action'], [
        'form_params' => array_diff_key($fields, ['_action' => true]) + ['CRAFT_CSRF_TOKEN' => $browser->csrfToken()],
        'headers' => ['Accept' => 'text/html', 'Referer' => $referrer, ...$headers],
    ]);

    $vote = $plain(['_action' => 'vote', 'commentId' => $comment->id, 'upvote' => 1], 'http://' . $host . '/post-page');
    $follow = $plain(['_action' => 'subscribe', 'ownerId' => $entry->id, 'siteId' => $entry->siteId], 'http://' . $host . '/post-page');
    $foreign = $plain(['_action' => 'vote', 'commentId' => $comment->id, 'upvote' => 1], 'https://evil.example/landing');
    // A forwarded host can't make another site's page count as this one.
    $forwarded = $plain(['_action' => 'vote', 'commentId' => $comment->id, 'upvote' => 1], 'https://evil.example/landing', ['X-Forwarded-Host' => 'evil.example']);

    expect($vote['status'])->toBe(302)
        ->and($vote['headers']['location'][0] ?? null)->toBe('http://' . $host . '/post-page')
        ->and($follow['status'])->toBe(302)
        ->and($follow['headers']['location'][0] ?? null)->toBe('http://' . $host . '/post-page')
        ->and($foreign['status'])->toBe(302)
        ->and($foreign['headers']['location'][0] ?? '')->not->toContain('evil.example')
        // The site's own home page instead.
        ->and(parse_url($foreign['headers']['location'][0] ?? '', PHP_URL_HOST))->toBe($host)
        ->and($forwarded['headers']['location'][0] ?? '')->not->toContain('evil.example');
});

it('refills a refused plain-form edit only with what was posted, never the stored comment', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge refill owner');
    $author = switchOverUser('switchOverRefillAuthor');
    $intruder = switchOverUser('switchOverRefillIntruder');
    $members = switchOverHttpComment($entry, 'A member’s private draft words', $author);

    // A held guest comment, with an address and an IP that only moderators should see.
    Comments::$plugin->getSettings()->requireModeration = true;
    Comments::$plugin->getSettings()->allowGuest = true;
    Comments::$plugin->getSettings()->guestRequireEmailName = false;
    Comments::$plugin->getConversations()->clearCaches();
    $user = Craft::$app->getUser();
    $admin = $user->getIdentity();
    $user->setIdentity(null);

    try {
        $guests = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => 'Held guest text',
            'name' => 'Hidden Guest',
            'email' => 'hidden-guest@example.test',
        ]), static function(Comment $comment): void {
            $comment->ipAddress = '203.0.113.9';
        })->comment;
    } finally {
        $user->setIdentity($admin);
        Comments::$plugin->getSettings()->requireModeration = false;
    }

    $edit = static fn(HttpClient $browser, Comment $comment, array $fields = []): string => switchOverForm($browser, '/switch-over-refill', [
        'action' => 'comments/comments/save',
        'commentId' => $comment->id,
        'siteId' => $entry->siteId,
    ] + $fields)['body'];

    $responses = [
        'someone else’s' => $edit(switchOverBrowser($intruder), $members),
        'a guest’s' => $edit(switchOverBrowser($intruder), $guests),
        'anonymous' => $edit(switchOverBrowser(), $guests),
        'anonymous with text' => $edit(switchOverBrowser(), $members, ['fields' => ['comment' => 'Posted text']]),
    ];

    foreach ($responses as $case => $body) {
        expect($body)->toStartWith('C=', $case)
            ->not->toContain('private draft words')
            ->not->toContain('Held guest text')
            ->not->toContain('Hidden Guest')
            ->not->toContain('hidden-guest@example.test')
            ->not->toContain('203.0.113.9')
            ->not->toContain('|S=pending')
            ->not->toContain('|S=approved')
            ->not->toContain('|U=' . $author->id);
    }

    expect($responses['anonymous with text'])->toStartWith('C=Posted text|N=|E=|IP=|S=|U=|ID=' . $members->id)
        ->and($responses['someone else’s'])->toBe('C=|N=|E=|IP=|S=|U=|ID=' . $members->id);
});

it('hands a refused plain post back as plain values that can’t reach a stored comment, its replies or its owner', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over bridge reach owner');
    $settings = Comments::$plugin->getSettings();
    $settings->allowGuest = true;
    $settings->guestRequireEmailName = false;
    $user = Craft::$app->getUser();
    $admin = $user->getIdentity();
    $root = switchOverHttpComment($entry, 'Approved root');

    // A held guest comment and a held guest reply, which only moderators should see.
    $settings->requireModeration = true;
    Comments::$plugin->getConversations()->clearCaches();
    $user->setIdentity(null);

    try {
        $held = Comments::$plugin->getComments()->create(new CommentSubmission(['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'content' => 'Held parent text', 'name' => 'Held Guest', 'email' => 'held-guest@example.test']))->comment;
        Comments::$plugin->getComments()->create(new CommentSubmission(['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'parentId' => $root->id, 'content' => 'Held reply text', 'name' => 'Reply Guest', 'email' => 'reply-guest@example.test']));
    } finally {
        $user->setIdentity($admin);
        $settings->requireModeration = false;
    }

    $browser = switchOverBrowser();
    $post = static fn(array $fields): array => switchOverForm($browser, '/switch-over-reach', ['action' => 'comments/comments/save'] + $fields);

    $responses = [
        'reply to a held comment' => $post(['elementId' => $entry->id, 'siteId' => $entry->siteId, 'parentId' => $held->id, 'fields' => ['comment' => 'A reply']]),
        'edit of a comment with held replies' => $post(['commentId' => $root->id, 'siteId' => $entry->siteId, 'fields' => ['comment' => 'An edit']]),
        'a user as the owner' => $post(['elementId' => $admin->id, 'siteId' => $entry->siteId, 'fields' => ['comment' => '']]),
    ];

    foreach ($responses as $case => $response) {
        expect($response['status'])->toBe(200, $case . ': ' . $response['body'])
            ->and($response['body'])->toStartWith('T=verbb\\comments\\compatibility\\forms\\PostedComment|P=|R=|O=|C=', $case)
            ->not->toContain('Held parent text')
            ->not->toContain('held-guest@example.test')
            ->not->toContain('Held reply text')
            ->not->toContain('reply-guest@example.test')
            ->not->toContain((string)$admin->email)
            ->not->toContain('|E=0');
    }
});
