<?php
use craft\db\Query;
use craft\elements\User;
use craft\elements\actions\Restore;
use craft\models\UserGroup;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Fixtures\MultiSiteFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ModerationState;
use verbb\comments\errors\NotificationDeliveryException;
use verbb\comments\models\CommentSubmission;
use verbb\comments\queue\jobs\SendNotification;

/**
 * Regressions from the 2 November 2026 review, section 3.
 */

function quietCorrectness(): void
{
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
}

function submit($entry, string $content = 'Comment', ?int $parentId = null, ?int $userId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => $userId ?? (int)Craft::$app->getUser()->getIdentity()->id,
        'parentId' => $parentId,
    ], static fn($v) => $v !== null)))->comment;
}

/** A guest's comment from a confirmed address, the only kind Comments emails. */
function submitAsConfirmedGuest($entry, string $content, string $email): Comment
{
    Comments::$plugin->getSettings()->allowGuest = true;
    $user = Craft::$app->getUser();
    $signedIn = $user->getIdentity();
    $user->setIdentity(null);

    try {
        $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $content,
            'name' => 'Guest',
            'email' => $email,
        ]))->comment;
    } finally {
        $user->setIdentity($signedIn);
    }

    $identity = verbb\comments\records\Identity::findOne((int)$comment->identityId);
    $identity->verifiedAt = (new DateTime())->format('Y-m-d H:i:s');
    $identity->save(false);
    Comments::$plugin->getIdentities()->clearCaches();

    return $comment;
}

function grantCommentPermissions(User $user, array $permissions): void
{
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, $permissions);
    Craft::$app->getUser()->getIdentity();
}

function conversationRow(Comment $comment): array
{
    return (new Query())->from('{{%comments_conversations}}')->where(['id' => $comment->conversationId])->one();
}

function asPublicViewer(callable $callback): mixed
{
    $userService = Craft::$app->getUser();
    $admin = $userService->getIdentity();
    $userService->setIdentity(null);

    try {
        return $callback();
    } finally {
        $userService->setIdentity($admin);
    }
}

it('opens a comment made on another site', function() {
    quietCorrectness();
    $pair = MultiSiteFixtureFactory::sameOwnerDifferentSites('B1 owner');
    $comment = submit($pair['secondary']);

    $moderation = Comments::$plugin->getModeration();

    expect(Comments::$plugin->getComments()->getCommentById((int)$comment->id)?->id)->toBe($comment->id)
        ->and($moderation->getDetailPanel((int)$comment->id, 'history'))->toBeArray();
});

it('restores a trashed comment through the control panel action', function() {
    quietCorrectness();
    $comment = submit(CommentsFixtureFactory::entry('B2 owner'));
    Craft::$app->getElements()->deleteElement($comment);

    $query = Comment::find()->id($comment->id)->status(null)->trashed(true)->siteId('*');
    $action = Craft::$app->getElements()->createAction(['type' => Restore::class, 'elementType' => Comment::class]);

    expect($action->performAction($query))->toBeTrue()
        ->and(Comment::find()->id($comment->id)->status(null)->siteId('*')->exists())->toBeTrue();
});

it('only lets people who can trash comments restore them, and only trashed ones', function() {
    quietCorrectness();
    $comment = submit(CommentsFixtureFactory::entry('B2 permission owner'));
    $trasher = CommentsFixtureFactory::user('canTrash');
    grantCommentPermissions($trasher, ['comments-view', 'comments-trash']);

    expect($comment->canSave($trasher))->toBeFalse();

    Craft::$app->getElements()->deleteElement($comment);
    $trashed = Comment::find()->id($comment->id)->status(null)->trashed(true)->siteId('*')->one();

    expect($trashed->canSave($trasher))->toBeTrue()
        ->and($trashed->canSave(CommentsFixtureFactory::user('noRestore')))->toBeFalse();
});

it('keeps conversation counts honest through every kind of removal', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B3 owner');
    $first = submit($entry);
    $second = submit($entry);

    expect((int)conversationRow($first)['commentCount'])->toBe(2);

    Comments::$plugin->getComments()->authorRemove((int)$second->id);
    expect((int)conversationRow($first)['commentCount'])->toBe(1);

    Craft::$app->getElements()->deleteElement($first);
    expect((int)conversationRow($first)['commentCount'])->toBe(0);
});

it('does not move activity backwards when an older comment is approved', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B3 activity owner');
    $held = submit($entry);
    $moderation = Comments::$plugin->getModeration();
    $admin = Craft::$app->getUser()->getIdentity();

    $moderation->setState($held, ModerationState::Pending, $admin);
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['commentDate' => '2020-01-01 00:00:00'], ['id' => $held->id])->execute();
    $latest = submit($entry);
    $before = conversationRow($latest)['lastActivityAt'];

    $moderation->approve(Comment::find()->id($held->id)->status(null)->one(), $admin);

    expect(conversationRow($latest)['lastActivityAt'])->toBe($before)
        ->and((int)conversationRow($latest)['commentCount'])->toBe(2);
});

it('keeps approved replies reachable under a hidden parent, without revealing the parent', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Placeholder owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $root = submit($entry, 'Secret root text');
    $reply = submit($entry, 'Visible reply', (int)$root->id);
    $lone = submit($entry, 'Removed with no replies');

    $moderation = Comments::$plugin->getModeration();
    $moderation->remove($root, $admin);
    $moderation->remove($lone, $admin);

    $page = Comments::$plugin->getConversations()->getRootComments((int)$root->conversationId);
    $ids = array_map(static fn(Comment $c): int => (int)$c->id, $page->comments);

    expect($ids)->toBe([(int)$root->id])
        ->and((int)conversationRow($root)['commentCount'])->toBe(1);

    $views = asPublicViewer(fn() => Comments::$plugin->getFrontend()->buildDetachedCommentViews([...$page->comments, Comment::find()->id($reply->id)->one()]));

    expect($views[(int)$root->id]->placeholder)->toBeTrue()
        ->and($views[(int)$root->id]->content)->toBe('')
        ->and($views[(int)$root->id]->authorName)->toBeNull()
        ->and($views[(int)$root->id]->state)->toBe('unavailable')
        ->and($views[(int)$reply->id]->content)->toBe('Visible reply');
});

it('keeps replies under a trashed parent as well, right through to it being purged', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Trashed placeholder owner');
    $root = submit($entry, 'Trashed root');
    $reply = submit($entry, 'Reply', (int)$root->id);

    Craft::$app->getElements()->deleteElement($root);

    $page = Comments::$plugin->getConversations()->getRootComments((int)$root->conversationId);

    expect($page->comments)->toHaveCount(1)
        ->and((int)$page->comments[0]->id)->toBe((int)$root->id)
        ->and((int)conversationRow($root)['commentCount'])->toBe(1);

    $views = asPublicViewer(fn() => Comments::$plugin->getFrontend()->buildDetachedCommentViews([...$page->comments, Comment::find()->id($reply->id)->one()]));
    expect($views[(int)$root->id]->placeholder)->toBeTrue()
        ->and($views[(int)$reply->id]->content)->toBe('Reply');
});

it('finds a comment through search as soon as it is created', function() {
    quietCorrectness();
    $comment = submit(CommentsFixtureFactory::entry('B4 owner'), 'xylophonequartet');

    expect(Comment::find()->search('xylophonequartet')->status(null)->ids())->toContain($comment->id);
});

it('does not end pagination early when a page is full of trashed comments', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B6 owner');
    $older = submit($entry, 'Older');
    $trashed = [];

    foreach (range(1, 3) as $i) {
        $trashed[] = submit($entry, "Trashed {$i}");
    }

    foreach ($trashed as $comment) {
        Craft::$app->getElements()->deleteElement($comment);
    }

    $page = Comments::$plugin->getConversations()->getRootComments((int)$older->conversationId, CommentSort::Newest, 2);

    expect(array_map(static fn(Comment $c): int => (int)$c->id, $page->comments))->toBe([(int)$older->id]);
});

it('only notifies once when a comment is approved, held and approved again', function() {
    quietCorrectness();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = true;
    $sent = captureMail();
    $entry = CommentsFixtureFactory::entry('B7 owner');
    $moderation = Comments::$plugin->getModeration();
    $admin = Craft::$app->getUser()->getIdentity();
    $comment = submitAsConfirmedGuest($entry, 'Held', 'b7approved@example.test');

    $settings->participantNotifications = [\verbb\comments\models\Settings::NOTIFICATION_APPROVED];

    $moderation->setState($comment, ModerationState::Approved, $admin);
    expect($sent->messages)->toHaveCount(1);

    $moderation->setState($comment, ModerationState::Pending, $admin);
    $moderation->setState($comment, ModerationState::Approved, $admin);
    expect($sent->messages)->toHaveCount(1);
});

it('does not mail about a comment that was removed before its job ran', function() {
    quietCorrectness();
    $settings = Comments::$plugin->getSettings();
    $sent = captureMail();
    $entry = CommentsFixtureFactory::entry('B7 removed owner');
    $author = CommentsFixtureFactory::user('b7author');
    $parent = submit($entry, 'Parent', null, (int)$author->id);
    $reply = submit($entry, 'Reply', (int)$parent->id);

    $settings->participantNotifications = [\verbb\comments\models\Settings::NOTIFICATION_REPLY];
    Comments::$plugin->getModeration()->remove($reply, Craft::$app->getUser()->getIdentity());

    $job = new SendNotification(['commentId' => (int)$reply->id, 'siteId' => (int)$reply->siteId, 'type' => 'reply']);
    $job->execute(Craft::$app->getQueue());

    expect($sent->messages)->toBe([]);

    Craft::$app->getElements()->deleteElement($reply);
    $job->execute(Craft::$app->getQueue());

    expect($sent->messages)->toBe([]);
});

it('tells a person once when a reply also mentions them', function() {
    quietCorrectness();
    $settings = Comments::$plugin->getSettings();
    $sent = captureMail();
    $told = [];
    yii\base\Event::on(verbb\comments\services\Notifications::class, verbb\comments\services\Notifications::EVENT_AFTER_NOTIFY, static function($event) use (&$told) {
        $told[] = $event->type;
    });
    $entry = CommentsFixtureFactory::entry('B7 dedupe owner');
    $author = CommentsFixtureFactory::user('b7dedupe');
    $parent = submit($entry, 'Parent', null, (int)$author->id);
    $reply = submit($entry, 'Reply', (int)$parent->id);
    $identityId = (int)Comments::$plugin->getIdentities()->getOrCreateForUser($author)->id;
    Comments::$plugin->getMentions()->syncIdentityIds((int)$reply->id, [$identityId]);

    $settings->participantNotifications = [
        \verbb\comments\models\Settings::NOTIFICATION_REPLY,
        \verbb\comments\models\Settings::NOTIFICATION_MENTION,
    ];

    try {
        $service = Comments::$plugin->getComments();
        $service->triggerNotificationEmail('reply', $reply);
        $service->triggerNotificationEmail('mention', $reply, $identityId);
    } finally {
        yii\base\Event::off(verbb\comments\services\Notifications::class, verbb\comments\services\Notifications::EVENT_AFTER_NOTIFY);
    }

    // One email, for the reply.
    expect($told)->toBe(['reply'])
        ->and($sent->messages)->toHaveCount(1)
        ->and(array_keys((array)$sent->messages[0]->getTo()))->toBe([$author->email])
        ->and((string)$sent->messages[0]->getSubject())->toBe('Someone has replied to your comment on Plugin Tests.');
});

it('fails the queue job when an email cannot be sent, but not the comment', function() {
    quietCorrectness();
    $settings = Comments::$plugin->getSettings();
    $settings->participantNotifications = [\verbb\comments\models\Settings::NOTIFICATION_REPLY];
    captureMail(fail: true);
    $entry = CommentsFixtureFactory::entry('B8 owner');
    $parent = submitAsConfirmedGuest($entry, 'Parent', 'b8author@example.test');

    // Sent straight away here, so the failure is logged rather than undoing the save.
    $reply = submit($entry, 'Reply', (int)$parent->id);
    expect($reply->id)->not->toBeNull();

    $job = new SendNotification(['commentId' => (int)$reply->id, 'siteId' => (int)$reply->siteId, 'type' => 'reply']);

    expect(fn() => $job->execute(Craft::$app->getQueue()))->toThrow(NotificationDeliveryException::class);
});

it('writes nothing when protection rejects a new comment', function() {
    quietCorrectness();
    Comments::$plugin->getSettings()->enableSpamChecks = true;
    $entry = CommentsFixtureFactory::entry('B9 owner');
    $author = CommentsFixtureFactory::user('b9author');

    expect(fn() => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Rejected',
        'userId' => (int)$author->id,
    ])))->toThrow(verbb\comments\exceptions\ProtectionException::class);

    expect((new Query())->from('{{%comments_conversations}}')->where(['ownerId' => $entry->id])->exists())->toBeFalse()
        ->and((new Query())->from('{{%comments_identities}}')->where(['userId' => $author->id])->exists())->toBeFalse()
        ->and((new Query())->from('{{%comments_comments}}')->where(['ownerId' => $entry->id])->exists())->toBeFalse();
});

it('does not hold a database transaction while contacting a spam provider', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B9 transaction owner');
    $db = Craft::$app->getDb();
    $during = null;

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Checked outside the transaction',
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
    ]), static function(Comment $comment) use ($db, &$during): void {
        // Runs immediately before validation and the Protect check.
        $during = $db->getTransaction();
    })->comment;

    expect($during)->toBeNull()
        ->and($comment->conversationId)->not->toBeNull()
        ->and($comment->rootId)->toBe((int)$comment->id);
});

it('still marks a comment as moderated when its author is restricted', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B9 restriction owner');
    $author = CommentsFixtureFactory::user('b9restricted');
    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($author);
    Comments::$plugin->getModeration()->moderateIdentity((int)$identity->id, null, Craft::$app->getUser()->getIdentity());

    $comment = submit($entry, 'Needs moderation', null, (int)$author->id);

    expect($comment->moderationState)->toBe(ModerationState::Pending->value)
        ->and($comment->status)->toBe(Comment::STATUS_PENDING);
});

it('does not fall over when an owner element type no longer exists', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B10 owner');
    $comment = submit($entry);

    Craft::$app->getDb()->createCommand()->update('{{%elements}}', ['type' => 'gone\\plugin\\Thing'], ['id' => $entry->id])->execute();

    try {
        $found = Comment::find()->id($comment->id)->status(null)->all();

        expect($found)->toHaveCount(1)
            ->and($found[0]->getOwner())->toBeNull();
    } finally {
        Craft::$app->getDb()->createCommand()->update('{{%elements}}', ['type' => $entry::class], ['id' => $entry->id])->execute();
    }
});

it('checks user owners against their groups in the permission settings', function() {
    quietCorrectness();
    $user = CommentsFixtureFactory::user('b11owner');
    $group = new UserGroup(['name' => 'B11 group', 'handle' => 'b11group' . uniqid()]);
    Craft::$app->getUserGroups()->saveGroup($group);
    Craft::$app->getUsers()->assignUserToGroups((int)$user->id, [(int)$group->id]);
    $user = User::find()->id($user->id)->status(null)->one();
    $settings = Comments::$plugin->getSettings();

    try {
        $settings->permissions = [User::class => [$group->uid]];
        expect(Comments::$plugin->getComments()->checkPermissions($user))->toBeTrue();

        $settings->permissions = [User::class => ['another-group']];
        expect(Comments::$plugin->getComments()->checkPermissions($user))->toBeFalse();
    } finally {
        Craft::$app->getUserGroups()->deleteGroup($group);
    }
});

it('can load every reply when a parent has more than one request returns', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B12 width owner');
    $root = submit($entry);

    foreach (range(1, 25) as $i) {
        submit($entry, "Reply {$i}", (int)$root->id);
    }

    $conversations = Comments::$plugin->getConversations();
    $first = $conversations->getReplyPreviewMap([(int)$root->id], CommentSort::Newest, 20)[(int)$root->id];
    $second = $conversations->getReplyPreviewMap([(int)$root->id], CommentSort::Newest, 40)[(int)$root->id];

    expect($first['replies'])->toHaveCount(20)
        ->and($first['total'])->toBe(25)
        ->and($first['hasMore'])->toBeTrue()
        ->and($second['replies'])->toHaveCount(25)
        ->and($second['hasMore'])->toBeFalse();
});

it('anchors comments by the ID that email links use', function() {
    quietCorrectness();
    $comment = submit(CommentsFixtureFactory::entry('B13 owner'));
    $thread = Comments::$plugin->getApi()->thread($comment->getOwner());

    expect($thread['comments'][0]['permalink'])->toEndWith('#comment-' . $comment->id);
});

it('renders GIF-only comments, and only for web addresses', function() {
    quietCorrectness();
    $comment = submit(CommentsFixtureFactory::entry('B14 owner'), 'Placeholder text');
    $frontend = Comments::$plugin->getFrontend();

    $comment->gifUrl = 'https://media.example.test/a.gif';
    expect($frontend->buildDetachedCommentViews([$comment])[(int)$comment->id]->gifUrl)->toBe('https://media.example.test/a.gif');

    $comment->gifUrl = 'javascript:alert(1)';
    expect($frontend->buildDetachedCommentViews([$comment])[(int)$comment->id]->gifUrl)->toBeNull();
});

it('links the field to just that element’s comments', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('B15 owner');
    $url = Comments::$plugin->getOwnerElement()->getFieldSummary((int)$entry->id, (int)$entry->siteId)['manageUrl'] ?? null;

    expect($url)->toContain('ownerId=' . $entry->id)
        ->not->toContain('search=');
});

it('names an author who has no full name', function() {
    quietCorrectness();
    $author = CommentsFixtureFactory::user('noFullName');
    $author->firstName = null;
    $author->fullName = null;
    Craft::$app->getElements()->saveElement($author, false);

    $comment = submit(CommentsFixtureFactory::entry('Author name owner'), 'Comment', null, (int)$author->id);

    expect(Comment::find()->id($comment->id)->status(null)->one()->getAuthorName())->toBe($author->username);
});

it('keeps comments when their owner is permanently deleted', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Owner deletion owner');
    $root = submit($entry);
    $reply = submit($entry, 'Reply', (int)$root->id);

    Craft::$app->getElements()->deleteElement($entry, true);

    $row = (new Query())->from('{{%comments_comments}}')->where(['id' => $reply->id])->one();
    $found = Comment::find()->id([$root->id, $reply->id])->status(null)->all();

    expect($row['ownerId'])->toBeNull()
        ->and((int)$row['parentId'])->toBe((int)$root->id)
        ->and($found)->toHaveCount(2)
        ->and($found[0]->getOwner())->toBeNull()
        ->and((string)$found[0]->getAttributeHtml('ownerId'))->toContain('Deleted element')
        ->and(Comments::$plugin->getDiagnostics()->runIntegrityScan()['hasErrors'])->toBeFalse();
});

it('keeps comments through an owner being trashed and restored', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Owner trash owner');
    $comment = submit($entry);

    Craft::$app->getElements()->deleteElement($entry);
    expect((new Query())->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->not->toBeFalse();

    Craft::$app->getElements()->restoreElement($entry);
    $found = Comment::find()->id($comment->id)->status(null)->one();

    expect($found->getOwner()?->id)->toBe($entry->id)
        ->and((int)conversationRow($comment)['commentCount'])->toBe(1);
});

/**
 * Stands in for the mailer so tests can see what would be sent, or make every send fail.
 */
function captureMail(bool $fail = false): object
{
    $recorder = new class () {
        public array $messages = [];
    };

    $mailer = new class (['recorder' => $recorder, 'fail' => $fail, 'messageClass' => craft\mail\Message::class]) extends craft\mail\Mailer {
        public object $recorder;
        public bool $fail = false;

        public function send($message): bool
        {
            if ($this->fail) {
                return false;
            }

            $this->recorder->messages[] = $message;

            return true;
        }
    };

    Craft::$app->set('mailer', $mailer);

    return $recorder;
}

// Emoji stored as shortcodes
// =========================================================================

/** The comment's row as the database holds it. */
function emojiRow(Comment $comment): array
{
    return (new Query())->select(['comment', 'content'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->one();
}

function emojiDocument(string $text): string
{
    return json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.test/a:100:b']]]],
    ]]]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function emojiDocumentText(Comment $comment): array
{
    $document = json_decode((string)$comment->bodyContent, true, flags: JSON_THROW_ON_ERROR);
    // Editor stores a document's nodes without the `doc` wrapper.
    $text = ($document['content'] ?? $document)[0]['content'][0];

    return [$text['text'], $text['marks'][0]['attrs']['href']];
}

it('shows a Comments 2 comment’s emoji wherever it’s read', function() {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Emoji from Comments 2');
    $comment = submit($entry, 'Placeholder');
    // Comments 2 stored emoji as shortcodes, and the upgrade copies them as they are.
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', [
        'comment' => 'Party :tada: time',
        'content' => 'Party :tada: time',
    ], ['id' => $comment->id])->execute();
    Craft::$app->getElements()->invalidateCachesForElement($comment);

    $loaded = Comment::find()->id($comment->id)->one();

    expect($loaded->comment)->toBe('Party 🎉 time')
        ->and($loaded->bodyContent)->toBe('Party 🎉 time')
        ->and($loaded->getExcerpt())->toBe('Party 🎉 time')
        // The control panel's editor.
        ->and(Craft::$app->getView()->renderTemplate('comments/comments/_editor-body', ['comment' => $loaded, 'static' => false], craft\web\View::TEMPLATE_MODE_CP))->toContain('Party 🎉 time')
        ->and(Craft::$app->getView()->renderTemplate('comments/comments/_editor-body', ['comment' => $loaded, 'static' => false], craft\web\View::TEMPLATE_MODE_CP))->not->toContain(':tada:');

    // The data API's thread, which the renderer draws.
    $text = Comments::$plugin->getApi()->thread($entry)['comments'][0]['content']['text'] ?? null;

    expect($text)->toBe('Party 🎉 time');

    // GraphQL.
    $schema = new craft\models\GqlSchema(['name' => 'Emoji', 'scope' => ['comments:read']]);
    Craft::$app->getGql()->flushCaches();
    Craft::$app->getGql()->setActiveSchema($schema);

    try {
        $result = Craft::$app->getGql()->executeQuery($schema, "{ comments(id: {$comment->id}) { comment } }", debugMode: true);
    } finally {
        Craft::$app->getGql()->setActiveSchema(null);
        // The schema built here would otherwise be reused by later tests.
        Craft::$app->getGql()->flushCaches();
    }

    expect($result['data']['comments'][0]['comment'] ?? null)->toBe('Party 🎉 time');

    // Saving it again isn't an edit.
    expect(Craft::$app->getElements()->saveElement($loaded, false))->toBeTrue()
        ->and(Comment::find()->id($comment->id)->one()->editedAt)->toBeNull();
});

it('leaves a rich comment’s document alone when emoji are turned back', function() {
    quietCorrectness();
    Comments::$plugin->getSettings()->authoringMode = verbb\comments\enums\AuthoringMode::Rich->value;
    $entry = CommentsFixtureFactory::entry('Emoji in a rich comment');
    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => emojiDocument('Typed :tada: and 🎉'),
        'contentFormat' => verbb\comments\enums\ContentFormat::Rich,
        'userId' => (int)Craft::$app->getUser()->getId(),
    ]))->comment;

    $loaded = Comment::find()->id($comment->id)->one();

    // What was typed stays as typed, and an address that looks like it has a shortcode isn't rewritten.
    expect(emojiDocumentText($loaded))->toBe(['Typed :tada: and 🎉', 'https://example.test/a:100:b'])
        ->and(json_decode((string)$loaded->getContentDocument(), true))->toBe(json_decode((string)$comment->bodyContent, true));
});

it('keeps emoji on a database that can’t store 4-byte characters', function() {
    quietCorrectness();
    Comments::$plugin->getSettings()->authoringMode = verbb\comments\enums\AuthoringMode::Rich->value;
    $entry = CommentsFixtureFactory::entry('Emoji without utf8mb4');
    // Only Comments' own table is narrow, which Craft's check for the whole database doesn't see.
    verbb\comments\helpers\StringHelper::setTableSupportsMb4('{{%comments_comments}}', false);

    try {
        expect(verbb\comments\helpers\StringHelper::emojiForDb('Launch 🚀 day'))->toBe('Launch :rocket: day');

        $plain = submit($entry, 'Launch 🚀 day');
        $rich = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => emojiDocument('Rich 🎉 day'),
            'contentFormat' => verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)Craft::$app->getUser()->getId(),
        ]))->comment;

        $plainRow = emojiRow($plain);
        $richRow = emojiRow($rich);

        // Nothing written has a 4-byte character in it, which a utf8mb3 table would refuse.
        expect($plainRow)->toBe(['comment' => 'Launch :rocket: day', 'content' => 'Launch :rocket: day'])
            ->and($richRow['comment'])->toBe('Rich :tada: day')
            ->and(craft\helpers\StringHelper::containsMb4($richRow['content']))->toBeFalse()
            ->and($richRow['content'])->toContain(substr(json_encode('🎉'), 1, -1));

        // And they come back as emoji.
        $plainLoaded = Comment::find()->id($plain->id)->one();
        $richLoaded = Comment::find()->id($rich->id)->one();

        expect($plainLoaded->comment)->toBe('Launch 🚀 day')
            ->and($plainLoaded->bodyContent)->toBe('Launch 🚀 day')
            ->and($richLoaded->comment)->toBe('Rich 🎉 day')
            ->and(emojiDocumentText($richLoaded))->toBe(['Rich 🎉 day', 'https://example.test/a:100:b'])
            ->and((string)$richLoaded->getRenderedBodyHtml())->toContain('Rich 🎉 day');

        // Saving again leaves them as they were, and isn't counted as an edit.
        expect(Craft::$app->getElements()->saveElement($plainLoaded, false))->toBeTrue()
            ->and(Craft::$app->getElements()->saveElement($richLoaded, false))->toBeTrue()
            ->and(emojiRow($plain))->toBe($plainRow)
            ->and(Comment::find()->id($plain->id)->one()->editedAt)->toBeNull()
            ->and(Comment::find()->id($rich->id)->one()->editedAt)->toBeNull();
    } finally {
        verbb\comments\helpers\StringHelper::setTableSupportsMb4('{{%comments_comments}}', null);
    }
});

/**
 * Runs `$callback` with every table Comments writes free text to answering whether it can hold 4-byte
 * characters, then checks them again for real.
 */
function withEmojiTables(bool $supportsMb4, callable $callback): mixed
{
    $tables = ['{{%comments_comments}}', '{{%comments_identities}}', '{{%comments_reports}}', '{{%comments_moderationactions}}', '{{%comments_moderatornotes}}', '{{%comments_commenternotes}}', '{{%comments_restrictions}}'];

    foreach ($tables as $table) {
        verbb\comments\helpers\StringHelper::setTableSupportsMb4($table, $supportsMb4);
    }

    try {
        return $callback();
    } finally {
        foreach ($tables as $table) {
            verbb\comments\helpers\StringHelper::setTableSupportsMb4($table, null);
        }

        Comments::$plugin->getIdentities()->clearCaches();
    }
}

function emojiColumn(string $table, string $column, int $id): ?string
{
    return (new Query())->select([$column])->from($table)->where(['id' => $id])->scalar();
}

function submitAsNamedGuest($entry, string $content, string $name): Comment
{
    Comments::$plugin->getSettings()->allowGuest = true;
    $user = Craft::$app->getUser();
    $signedIn = $user->getIdentity();
    $user->setIdentity(null);

    try {
        return Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $content,
            'name' => $name,
            'email' => 'named-guest@example.test',
        ]))->comment;
    } finally {
        $user->setIdentity($signedIn);
    }
}

it('checks each table’s own 4-byte support', function() {
    $db = Craft::$app->getDb();
    $narrow = '{{%emojiprobe_mb3}}';
    $db->createCommand()->dropTableIfExists($narrow)->execute();
    $db->createCommand('CREATE TABLE ' . $db->quoteTableName($narrow) . ' ([[id]] INT AUTO_INCREMENT PRIMARY KEY, [[note]] TEXT) DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci')->execute();
    verbb\comments\helpers\StringHelper::setTableSupportsMb4($narrow, null);

    try {
        // Craft's check only reads `elements_sites`, which can hold emoji.
        expect($db->getSupportsMb4())->toBeTrue()
            ->and(verbb\comments\helpers\StringHelper::tableSupportsMb4($narrow))->toBeFalse()
            ->and(verbb\comments\helpers\StringHelper::tableSupportsMb4('{{%comments_comments}}'))->toBeTrue()
            ->and(verbb\comments\helpers\StringHelper::emojiForDb('Zoë 🎉', $narrow))->toBe('Zoë :tada:')
            ->and(verbb\comments\helpers\StringHelper::emojiForDb('Zoë 🎉', '{{%comments_comments}}'))->toBe('Zoë 🎉')
            ->and(verbb\comments\helpers\StringHelper::emojiForDb('Zoë 🎉'))->toBe('Zoë 🎉')
            // A table that isn't there yet follows Craft's check.
            ->and(verbb\comments\helpers\StringHelper::tableSupportsMb4('{{%emojiprobe_missing}}'))->toBeTrue();

        // The narrow table refuses the emoji, and takes the shortcode.
        expect(fn() => craft\helpers\Db::insert($narrow, ['note' => 'Zoë 🎉']))->toThrow(yii\db\Exception::class);

        craft\helpers\Db::insert($narrow, ['note' => verbb\comments\helpers\StringHelper::emojiForDb('Zoë 🎉', $narrow)]);

        expect((new Query())->select(['note'])->from($narrow)->scalar())->toBe('Zoë :tada:');
    } finally {
        $db->createCommand()->dropTableIfExists($narrow)->execute();
        verbb\comments\helpers\StringHelper::setTableSupportsMb4($narrow, null);
    }
});

it('keeps emoji in guest names', function(bool $supportsMb4) {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Emoji in a name');
    $stored = $supportsMb4 ? 'Zoë 🎉' : 'Zoë :tada:';

    withEmojiTables($supportsMb4, function() use ($entry, $stored) {
        $comment = submitAsNamedGuest($entry, 'Hello', 'Zoë 🎉');
        $identityId = (int)$comment->identityId;

        // Each table gets what it can hold.
        expect(emojiColumn('{{%comments_comments}}', 'name', (int)$comment->id))->toBe($stored)
            ->and(emojiColumn('{{%comments_identities}}', 'name', $identityId))->toBe($stored);

        Craft::$app->getElements()->invalidateCachesForElement($comment);
        Comments::$plugin->getIdentities()->clearCaches();
        $loaded = Comment::find()->id($comment->id)->one();
        $commenters = Comments::$plugin->getCommenters();

        // And it reads back as typed, and can be looked up as typed.
        expect($loaded->name)->toBe('Zoë 🎉')
            ->and($loaded->getAuthorName())->toBe('Zoë 🎉')
            ->and(Comment::find()->name('Zoë 🎉')->ids())->toBe([(int)$comment->id])
            ->and(Comment::find()->name(['Zoë 🎉', 'Someone else'])->ids())->toBe([(int)$comment->id])
            ->and(verbb\comments\records\Identity::findOne($identityId)->name)->toBe('Zoë 🎉')
            ->and($commenters->getDetail($identityId)['name'])->toBe('Zoë 🎉')
            ->and(array_column($commenters->getTableData(verbb\comments\services\Commenters::SOURCE_GUESTS, '🎉', 1)['rows'], 'name'))->toBe(['Zoë 🎉'])
            ->and(Comments::$plugin->getApi()->thread($entry)['comments'][0]['author']['name'] ?? null)->toBe('Zoë 🎉');

        // Saving them again leaves them as they were.
        expect(Craft::$app->getElements()->saveElement($loaded, false))->toBeTrue()
            ->and(verbb\comments\records\Identity::findOne($identityId)->save(false))->toBeTrue()
            ->and(emojiColumn('{{%comments_comments}}', 'name', (int)$comment->id))->toBe($stored)
            ->and(emojiColumn('{{%comments_identities}}', 'name', $identityId))->toBe($stored);
    });
})->with(['without utf8mb4' => false, 'with utf8mb4' => true]);

it('keeps emoji in report details', function(bool $supportsMb4) {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Emoji in a report');
    $comment = submit($entry, 'Reported');
    $stored = $supportsMb4 ? 'Rude 🙄 reply' : 'Rude :rolling_eyes: reply';

    withEmojiTables($supportsMb4, function() use ($comment, $stored, $supportsMb4) {
        $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Zoë 🎉', null);
        $reports = Comments::$plugin->getReports();
        $report = $reports->create((int)$comment->id, (int)$reporter->id, 'other', 'Rude 🙄 reply');

        expect($report->details)->toBe('Rude 🙄 reply')
            ->and(emojiColumn('{{%comments_reports}}', 'details', (int)$report->id))->toBe($stored)
            ->and(emojiColumn('{{%comments_identities}}', 'name', (int)$reporter->id))->toBe($supportsMb4 ? 'Zoë 🎉' : 'Zoë :tada:');

        $item = Comments::$plugin->getModeration()->getDetailPanel((int)$comment->id, 'reports')['items'][0];
        $loaded = Comment::find()->id($comment->id)->one();

        expect($reports->getOpenForComment((int)$comment->id)[0]->details)->toBe('Rude 🙄 reply')
            ->and($item['details'])->toBe('Rude 🙄 reply')
            ->and($item['reporterName'])->toBe('Zoë 🎉')
            ->and(Comments::$plugin->getCommentEditor()->reviewNotice($loaded)['lines'][0])->toEndWith(': Rude 🙄 reply');

        // Closing it saves the record again, without changing the details.
        $reports->dismiss((int)$report->id);

        expect(emojiColumn('{{%comments_reports}}', 'details', (int)$report->id))->toBe($stored);
    });
})->with(['without utf8mb4' => false, 'with utf8mb4' => true]);

it('keeps emoji in moderators’ reasons and notes', function(bool $supportsMb4) {
    quietCorrectness();
    $entry = CommentsFixtureFactory::entry('Emoji in moderation');
    $comment = submit($entry, 'Moderated');
    $admin = Craft::$app->getUser()->getIdentity();
    $encode = static fn(string $text): string => $supportsMb4 ? $text : verbb\comments\helpers\StringHelper::emojiToShortcodes($text);

    withEmojiTables($supportsMb4, function() use ($comment, $admin, $encode) {
        $moderation = Comments::$plugin->getModeration();
        $moderation->remove($comment, $admin, 'Off topic 🙄');
        $note = $moderation->addNote((int)$comment->id, 'Checked 👀', $admin);
        $identityId = (int)$comment->identityId;
        $restriction = $moderation->blockIdentity($identityId, null, $admin, 'Spam 🥫');
        $commenterNote = $moderation->addCommenterNote($identityId, 'Watching 👀', $admin);
        $action = $moderation->getActions((int)$comment->id)[0];

        expect($note->note)->toBe('Checked 👀')
            ->and($restriction->reason)->toBe('Spam 🥫')
            ->and($commenterNote->note)->toBe('Watching 👀')
            ->and(emojiColumn('{{%comments_moderationactions}}', 'reason', (int)$action->id))->toBe($encode('Off topic 🙄'))
            ->and(emojiColumn('{{%comments_moderatornotes}}', 'note', (int)$note->id))->toBe($encode('Checked 👀'))
            ->and(emojiColumn('{{%comments_restrictions}}', 'reason', (int)$restriction->id))->toBe($encode('Spam 🥫'))
            ->and(emojiColumn('{{%comments_commenternotes}}', 'note', (int)$commenterNote->id))->toBe($encode('Watching 👀'));

        // The comment's page.
        $history = $moderation->getDetailPanel((int)$comment->id, 'history')['items'][0];
        $timeline = Comments::$plugin->getCommentEditor()->historyTimeline(Comment::find()->id($comment->id)->status(null)->one());

        expect($action->reason)->toBe('Off topic 🙄')
            ->and($history['reasonLabel'])->toBe('Off topic 🙄')
            ->and($moderation->getDetailPanel((int)$comment->id, 'notes')['items'][0]['note'])->toBe('Checked 👀')
            ->and(array_column($timeline, 'note'))->toContain('Checked 👀');

        // The commenter's page.
        $commenterHistory = Comments::$plugin->getCommenters()->getHistory($identityId);

        expect($moderation->getActiveRestrictions($identityId)[0]->reason)->toBe('Spam 🥫')
            ->and(Comments::$plugin->getCommenters()->getDetail($identityId)['restrictions'][0]['reason'])->toBe('Spam 🥫')
            ->and($moderation->getCommenterNotes($identityId)[0]->note)->toBe('Watching 👀')
            ->and(array_column($commenterHistory, 'note'))->toContain('Spam 🥫', 'Watching 👀')
            ->and(array_merge(...array_column($commenterHistory, 'chips')))->toContain(['label' => 'Off topic 🙄', 'tone' => 'red']);

        // Ending the block saves the record again, without changing the reason.
        $moderation->revokeRestriction((int)$restriction->id, $admin);

        expect(emojiColumn('{{%comments_restrictions}}', 'reason', (int)$restriction->id))->toBe($encode('Spam 🥫'));
    });
})->with(['without utf8mb4' => false, 'with utf8mb4' => true]);
