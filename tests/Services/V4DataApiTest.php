<?php
use craft\db\Query;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\models\FieldLayoutTab;
use craft\models\Site;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Performance\QueryProfiler;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\InteractionType;
use verbb\comments\enums\ModerationState;
use verbb\comments\events\DefineBadgesEvent;
use verbb\comments\events\DefineCommentDataEvent;
use verbb\comments\events\DefineViewerDataEvent;
use verbb\comments\exceptions\ApiException;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Api;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

/**
 * The data API's service: the public and private reads, cursors, limits, privacy, writes, events,
 * structured data and GraphQL. `V4DataApiHttpTest` covers the same actions through real requests.
 */

function dataApi(): Api
{
    return Comments::$plugin->getApi();
}

function dataApiAdmin(): User
{
    return Craft::$app->getUsers()->getUserByUsernameOrEmail('admin');
}

function dataApiAs(?User $user): void
{
    Craft::$app->getUser()->setIdentity($user);
    Comments::$plugin->getIdentities()->clearCaches();
    Comments::$plugin->getConversations()->clearCaches();
}

function dataApiModerator(): User
{
    $user = CommentsFixtureFactory::user('apiModerator');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, ['comments-view', 'comments-moderate', 'comments-trash']);

    return Craft::$app->getUsers()->getUserById((int)$user->id);
}

/**
 * A comment through the canonical create, as `$user`, approved unless `$held`.
 */
function dataApiPost($entry, string $text, ?User $user = null, ?Comment $parent = null, bool $held = false): Comment
{
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = $held;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $user ??= dataApiAdmin();
    // Each request works out an owner's settings once, and each post here stands in for a request.
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'parentId' => $parent?->id,
        'content' => $text,
        'userId' => (int)$user->id,
    ]))->comment;
}

/** Every comment in thread data, depth first. */
function dataApiFlatten(array $comments): array
{
    $flat = [];

    foreach ($comments as $comment) {
        $flat[] = $comment;
        array_push($flat, ...dataApiFlatten($comment['replies']['items'] ?? []));
    }

    return $flat;
}

function dataApiJson(mixed $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Thread data without the relative times and data version, which move on as a test runs. */
function dataApiStable(array $data): array
{
    unset($data['threadVersion']);

    $strip = static function(array $comment) use (&$strip): array {
        if (is_array($comment['display'] ?? null)) {
            $comment['display']['timeAgo'] = null;
        }

        if (isset($comment['replies']['items'])) {
            $comment['replies']['items'] = array_map($strip, $comment['replies']['items']);
        }

        return $comment;
    };

    foreach (['comments', 'extraComments'] as $key) {
        if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
            $data[$key] = array_map($strip, $data[$key]);
        }
    }

    if (isset($data['comment']) && is_array($data['comment'])) {
        $data['comment'] = $strip($data['comment']);
    }

    if (isset($data['replies']['items'])) {
        $data['replies']['items'] = array_map($strip, $data['replies']['items']);
    }

    return $data;
}

function dataApiError(callable $callback): ApiException
{
    try {
        $callback();
    } catch (Throwable $e) {
        return dataApi()->errorFor($e);
    }

    throw new RuntimeException('Expected the data API to refuse this.');
}

/**
 * A plain text field on the comment layout, ticked under Public Fields, for the length of a callback.
 */
function dataApiWithField(callable $callback): void
{
    $fields = Craft::$app->getFields();
    $field = new PlainText(['name' => 'Order number', 'handle' => 'apiOrder' . bin2hex(random_bytes(3))]);
    expect($fields->saveField($field))->toBeTrue();
    $layout = $fields->getLayoutByType(Comment::class);
    $tabs = $layout->getTabs();
    $tab = $tabs[0] ?? new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $original = $tab->getElements();
    $tab->setElements([...$original, new CustomField($field)]);
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
    expect($fields->saveLayout($layout, false))->toBeTrue();
    Comments::$plugin->getSettings()->publicFields = [$field->handle];

    try {
        $callback($field);
    } finally {
        $tab->setElements($original);
        $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
        $fields->saveLayout($layout, false);
        $fields->deleteField($field);
    }
}

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    dataApi()->invalidateCaches();
    Comments::$plugin->getIdentities()->clearCaches();
});

afterEach(function() {
    Event::off(Api::class, Api::EVENT_DEFINE_COMMENT_DATA);
    Event::off(Api::class, Api::EVENT_DEFINE_VIEWER_DATA);
    Event::off(CommentsService::class, CommentsService::EVENT_DEFINE_BADGES);
    Craft::$app->getSites()->setCurrentSite(Craft::$app->getSites()->getPrimarySite());
});

// Public reads
// =========================================================================

it('returns a thread with the conversation, the editor, approved comments, a page and labels', function() {
    $entry = CommentsFixtureFactory::entry('API thread owner');
    $member = CommentsFixtureFactory::user('apiMember');
    $root = dataApiPost($entry, "First line\n\nSecond paragraph", $member);
    $reply = dataApiPost($entry, 'A reply', null, $root);
    dataApiPost($entry, 'Waiting comment', $member, held: true);

    $thread = dataApi()->thread($entry);

    expect($thread)->toHaveKeys(['apiVersion', 'status', 'conversation', 'editor', 'comments', 'page', 'labels', 'locale'])
        ->and($thread['apiVersion'])->toBe(1)
        ->and($thread['status'])->toBe('ok')
        ->and($thread['conversation'])->toMatchArray([
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'status' => 'open',
            'commentCount' => 2,
            'rootCount' => 1,
            'sort' => 'newest',
            'interactionMode' => 'like',
        ])
        ->and($thread['conversation']['sorts'])->toBe(['newest', 'oldest', 'top'])
        ->and($thread['conversation']['guestPolicy'])->toHaveKeys(['allowed', 'nameAndEmail', 'canVote', 'canReport'])
        ->and($thread['editor'])->toHaveKeys(['authoring', 'maxLength', 'richEditor'])
        ->and($thread['page'])->toBe(['next' => null, 'limit' => 20])
        ->and($thread['labels']['reply'])->toBe('Reply')
        ->and(array_keys($thread['labels']))->toBe(array_keys(Api::LABELS))
        ->and($thread['comments'])->toHaveCount(1);

    $comment = $thread['comments'][0];

    expect($comment)->toHaveKeys(['uid', 'parentUid', 'depth', 'state', 'content', 'author', 'createdAt', 'editedAt', 'display', 'permalink', 'fields', 'interaction', 'custom', 'replies'])
        ->and($comment['uid'])->toBe($root->uid)
        ->and($comment['state'])->toBe('approved')
        ->and($comment['content'])->toBe(['format' => 'plain', 'html' => null, 'text' => "First line\n\nSecond paragraph", 'gifUrl' => null, 'gifWidth' => null, 'gifHeight' => null])
        ->and($comment['author'])->toHaveKeys(['name', 'initials', 'avatarUrl', 'avatarTone', 'isGuest', 'badges'])
        ->and($comment['author']['isGuest'])->toBeFalse()
        ->and($comment['permalink'])->toEndWith('#comment-' . $root->id)
        ->and($comment['display']['timeAgo'])->toBeString()
        ->and($comment['interaction'])->toBe(['upvotes' => 0, 'downvotes' => 0])
        ->and($comment['fields'])->toBe([])
        ->and($comment['replies']['count'])->toBe(1)
        ->and($comment['replies']['items'][0]['uid'])->toBe($reply->uid)
        ->and($comment['replies']['items'][0]['parentUid'])->toBe($root->uid)
        ->and(dataApiJson($thread))->not->toContain('Waiting comment');
});

it('never reads who is asking, so every viewer gets the same thread', function() {
    $entry = CommentsFixtureFactory::entry('API same thread owner');
    $member = CommentsFixtureFactory::user('apiSameMember');
    $root = dataApiPost($entry, 'Visible', $member);
    dataApiPost($entry, 'Held reply', $member, $root, true);
    dataApiPost($entry, 'Held root', $member, held: true);
    Comments::$plugin->getInteractions()->set((int)$root->id, (int)Comments::$plugin->getIdentities()->getOrCreateForUser($member)->id, InteractionType::Upvote);

    $answers = [];

    foreach ([dataApiAdmin(), $member, dataApiModerator(), null] as $viewer) {
        dataApiAs($viewer);
        dataApi()->invalidateCaches();
        $answers[] = dataApiJson(dataApiStable(dataApi()->thread($entry)));
        $answers[] = dataApiJson(dataApiStable(dataApi()->comment($root->uid)));
        $answers[] = dataApiJson(dataApiStable(dataApi()->replies($root->uid)));
    }

    expect(array_unique(array_slice($answers, 0, 12, true)))->toHaveCount(3)
        ->and($answers[0])->not->toContain('Held reply')
        ->and($answers[0])->not->toContain('Held root')
        ->and($answers[0])->not->toContain('"vote"');
});

it('keeps addresses, IP addresses, user agents and private IDs out of every public response', function() {
    $entry = CommentsFixtureFactory::entry('API privacy owner');
    $member = CommentsFixtureFactory::user('apiPrivacy');
    $root = dataApiPost($entry, 'Private details', $member);
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['ipAddress' => '198.51.100.7', 'userAgent' => 'SecretAgent/1.0'], ['id' => $root->id])->execute();
    $guest = CommentsFixtureFactory::guestComment($entry, 'Guest words', 'Gale Guest', 'gale@example.test');
    dataApi()->invalidateCaches();

    $json = dataApiJson([
        dataApi()->thread($entry),
        dataApi()->comment($root->uid),
        dataApi()->replies($root->uid),
        dataApi()->structuredData($entry, ['includeAuthors' => true]),
    ]);

    expect($json)->not->toContain($member->email)
        ->not->toContain('gale@example.test')
        ->not->toContain('198.51.100.7')
        ->not->toContain('SecretAgent')
        ->not->toContain('"userId"')
        ->not->toContain('"identityId"')
        ->not->toContain('"email"')
        ->not->toContain('"ipAddress"')
        ->not->toContain((string)Comments::$plugin->getIdentities()->findForUser((int)$member->id)->uid);
});

it('keeps values that are only collected out of public responses and the data cache', function() {
    dataApiWithField(function(PlainText $field) {
        $entry = CommentsFixtureFactory::entry('API collected owner');
        $root = dataApiPost($entry, 'Order placed');
        $root->setFieldValue($field->handle, 'ORDER-SECRET-4471');
        Craft::$app->getElements()->saveElement($root, false);
        dataApi()->invalidateCaches();

        $json = dataApiJson([
            dataApi()->thread($entry),
            dataApi()->comment($root->uid),
            dataApi()->replies($root->uid),
            dataApi()->structuredData($entry, ['includeAuthors' => true]),
        ]);

        expect($json)->not->toContain('ORDER-SECRET-4471')
            ->and(dataApi()->thread($entry)['comments'][0]['fields'])->toBe([]);

        // Nothing cached holds it either.
        $cache = Craft::$app->getCache();

        if ($cache instanceof yii\caching\FileCache) {
            $found = false;

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Craft::getAlias($cache->cachePath), FilesystemIterator::SKIP_DOTS)) as $file) {
                $found = $found || str_contains((string)file_get_contents($file->getPathname()), 'ORDER-SECRET-4471');
            }

            expect($found)->toBeFalse();
        }

        // Its author, editing it, gets it back, and so does nobody else.
        $edit = dataApi()->edit($root->uid);

        expect($edit['fields'][$field->handle])->toBe('ORDER-SECRET-4471')
            ->and(dataApiError(function() use ($root) {
                dataApiAs(CommentsFixtureFactory::user('apiNotAuthor'));
                dataApi()->edit($root->uid);
            })->errorCode)->toBe('forbidden');
    });
});

it('describes a public field to a viewer as collected, never shown', function() {
    dataApiWithField(function(PlainText $field) {
        $entry = CommentsFixtureFactory::entry('API field description owner');
        $viewer = dataApi()->viewer($entry);

        expect($viewer['editor']['fields']['comment'])->toHaveCount(1)
            ->and($viewer['editor']['fields']['comment'][0])->toMatchArray([
                'handle' => $field->handle,
                'label' => 'Order number',
                'required' => false,
                'type' => 'text',
                'multiple' => false,
                'public' => false,
            ])
            ->and($viewer['editor']['fields']['comment'][0])->toHaveKeys(['uid', 'instructions', 'options', 'attributes'])
            ->and($viewer['editor']['fields']['reply'])->toHaveCount(1);
    });
});

it('pages roots by a unique key, so equal dates and equal scores never repeat or skip a comment', function() {
    $entry = CommentsFixtureFactory::entry('API cursor owner');
    $comments = [];

    for ($i = 0; $i < 5; $i++) {
        $comments[] = dataApiPost($entry, "Root {$i}");
    }

    // Every root at the same moment, so only the ID orders them.
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['commentDate' => '2026-01-01 00:00:00'], ['id' => array_map(static fn($c) => $c->id, $comments)])->execute();
    $ids = array_map(static fn(Comment $comment): string => $comment->uid, $comments);

    foreach (['newest' => array_reverse($ids), 'oldest' => $ids] as $sort => $expected) {
        dataApi()->invalidateCaches();
        $seen = [];
        $cursor = null;

        do {
            $page = dataApi()->thread($entry, $sort, 2, $cursor);
            array_push($seen, ...array_column($page['comments'], 'uid'));
            $cursor = $page['page']['next'];
        } while ($cursor);

        expect($seen)->toBe($expected);
    }

    // Scores 2, 1, 1, 0, 0: ties are broken by the newest ID.
    $voters = array_map(static fn() => Comments::$plugin->getIdentities()->createHistoricalGuest(null, null), range(1, 2));
    Comments::$plugin->getInteractions()->set((int)$comments[1]->id, (int)$voters[0]->id, InteractionType::Upvote);
    Comments::$plugin->getInteractions()->set((int)$comments[1]->id, (int)$voters[1]->id, InteractionType::Upvote);
    Comments::$plugin->getInteractions()->set((int)$comments[0]->id, (int)$voters[0]->id, InteractionType::Upvote);
    Comments::$plugin->getInteractions()->set((int)$comments[3]->id, (int)$voters[0]->id, InteractionType::Upvote);
    $seen = [];
    $cursor = null;

    do {
        $page = dataApi()->thread($entry, 'top', 2, $cursor);
        array_push($seen, ...array_column($page['comments'], 'uid'));
        $cursor = $page['page']['next'];
    } while ($cursor);

    expect($seen)->toBe([$ids[1], $ids[3], $ids[0], $ids[4], $ids[2]]);
});

it('refuses a cursor that was changed, belongs to another list or another sort', function() {
    $entry = CommentsFixtureFactory::entry('API cursor tamper owner');
    $other = CommentsFixtureFactory::entry('API cursor other owner');

    foreach ([$entry, $other] as $owner) {
        dataApiPost($owner, 'One');
        dataApiPost($owner, 'Two');
    }

    $cursor = dataApi()->thread($entry, 'newest', 1)['page']['next'];
    $tampered = substr($cursor, 0, -4) . (str_ends_with($cursor, 'AAAA') ? 'BBBB' : 'AAAA');

    expect($cursor)->toBeString()
        ->and(dataApiError(fn() => dataApi()->thread($entry, 'newest', 1, $tampered))->errorCode)->toBe('invalid_cursor')
        ->and(dataApiError(fn() => dataApi()->thread($other, 'newest', 1, $cursor))->errorCode)->toBe('invalid_cursor')
        ->and(dataApiError(fn() => dataApi()->thread($entry, 'oldest', 1, $cursor))->errorCode)->toBe('invalid_cursor')
        ->and(dataApiError(fn() => dataApi()->thread($entry, 'newest', 1, 'not-a-cursor'))->errorCode)->toBe('invalid_cursor')
        ->and(dataApiError(fn() => dataApi()->thread($entry, 'newest', 1, str_repeat('a', 600)))->httpStatus)->toBe(400);
});

it('enforces the page and depth limits, the same for everyone', function() {
    $entry = CommentsFixtureFactory::entry('API limits owner');
    $root = dataApiPost($entry, 'Root');
    $parent = $root;
    $chain = [$root];

    for ($i = 1; $i <= 6; $i++) {
        $parent = dataApiPost($entry, "Depth {$i}", null, $parent);
        $chain[] = $parent;
    }

    foreach ([0, 51, 'ten', '2.5', -1] as $limit) {
        expect(dataApiError(fn() => dataApi()->thread($entry, null, $limit))->errorCode)->toBe('limit_exceeded');
    }

    expect(dataApiError(fn() => dataApi()->replies($root->uid, 101))->errorCode)->toBe('limit_exceeded')
        ->and(dataApi()->thread($entry, null, 50)['page']['limit'])->toBe(50);

    // Four levels at most in one response; deeper replies come through `replies`.
    $flat = dataApiFlatten(dataApi()->thread($entry)['comments']);
    $deepest = $flat[array_key_last($flat)];

    expect(max(array_column($flat, 'depth')))->toBe(4)
        ->and($deepest['replies']['count'])->toBe(1)
        ->and($deepest['replies']['items'])->toBe([])
        ->and($deepest['replies']['next'])->toBeString();

    $more = dataApi()->replies($deepest['uid'], null, $deepest['replies']['next']);

    expect($more['replies']['items'][0]['uid'])->toBe($chain[5]->uid)
        ->and($more['replies']['items'][0]['replies']['items'])->toBe([]);

    // Three replies are previewed, and the rest page through `replies`.
    $wide = CommentsFixtureFactory::entry('API wide owner');
    $wideRoot = dataApiPost($wide, 'Wide root');

    for ($i = 0; $i < 7; $i++) {
        dataApiPost($wide, "Wide reply {$i}", null, $wideRoot);
    }

    $preview = dataApi()->thread($wide)['comments'][0]['replies'];
    $rest = dataApi()->replies($wideRoot->uid, 2, $preview['next']);

    expect($preview['count'])->toBe(7)
        ->and($preview['items'])->toHaveCount(3)
        ->and($rest['replies']['items'])->toHaveCount(2)
        ->and(array_intersect(array_column($rest['replies']['items'], 'uid'), array_column($preview['items'], 'uid')))->toBe([])
        ->and(dataApi()->replies($wideRoot->uid, 100)['replies']['items'])->toHaveCount(7);
});

it('stops a response at 200 comments, counting previews', function() {
    $rows = [];
    $tops = [];

    // 50 roots, each with four replies that each have a reply.
    for ($r = 1; $r <= 50; $r++) {
        $rows[$r] = ['id' => $r, 'uid' => sprintf('%08d-0000-0000-0000-000000000000', $r), 'parentId' => null, 'rootId' => $r, 'depth' => 0, 'moderationState' => 'approved', 'removed' => false, 'commentDate' => '2026-01-01 00:00:00', 'userId' => null, 'identityId' => null];
        $tops[] = $rows[$r];

        for ($c = 1; $c <= 4; $c++) {
            $id = $r * 100 + $c;
            $rows[$id] = ['id' => $id, 'uid' => sprintf('%08d-0000-0000-0000-000000000000', $id), 'parentId' => $r, 'rootId' => $r, 'depth' => 1, 'moderationState' => 'approved', 'removed' => false, 'commentDate' => '2026-01-01 00:00:00', 'userId' => null, 'identityId' => null];
            $rows[$id * 10] = ['id' => $id * 10, 'uid' => sprintf('%08d-0000-0000-0000-000000000000', $id * 10), 'parentId' => $id, 'rootId' => $r, 'depth' => 2, 'moderationState' => 'approved', 'removed' => false, 'commentDate' => '2026-01-01 00:00:00', 'userId' => null, 'identityId' => null];
        }
    }

    $arrange = (new ReflectionClass(Api::class))->getMethod('_arrange');
    $nodes = $arrange->invoke(dataApi(), $tops, $rows, ['approved'], Api::MAX_COMMENTS);
    $count = static function(array $nodes) use (&$count): int {
        return array_sum(array_map(static fn(array $node): int => 1 + $count($node['items']), $nodes));
    };

    expect($count($nodes))->toBe(200)
        ->and($nodes[0]['items'])->toHaveCount(3)
        ->and($nodes[0]['items'][0]['items'])->toBe([])
        ->and($nodes[0]['items'][0]['next'])->toBeString()
        ->and($nodes[49]['items'])->toHaveCount(3);
});

it('returns a linked comment in its thread, and refuses one nobody can see', function() {
    $entry = CommentsFixtureFactory::entry('API deep link owner');
    $root = dataApiPost($entry, 'Root');

    for ($i = 0; $i < 4; $i++) {
        dataApiPost($entry, "Sibling {$i}", null, $root);
    }

    $reply = dataApiPost($entry, 'Linked reply', null, $root);
    $target = dataApiPost($entry, 'Linked target', null, $reply);
    $held = dataApiPost($entry, 'Held', CommentsFixtureFactory::user('apiHeld'), $root, true);

    // Made older, so the usual preview wouldn't reach it.
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['commentDate' => '2020-01-01 00:00:00'], ['id' => [$reply->id, $target->id]])->execute();
    dataApi()->invalidateCaches();
    $data = dataApi()->comment($target->uid);

    expect($data['targetUid'])->toBe($target->uid)
        ->and($data['comment']['uid'])->toBe($root->uid)
        ->and($data['comment']['replies']['items'][0]['uid'])->toBe($reply->uid)
        ->and($data['comment']['replies']['items'][0]['replies']['items'][0]['uid'])->toBe($target->uid)
        ->and(dataApiError(fn() => dataApi()->comment($held->uid))->errorCode)->toBe('not_found')
        ->and(dataApiError(fn() => dataApi()->comment('00000000-0000-0000-0000-000000000000'))->errorCode)->toBe('not_found')
        ->and(dataApiError(fn() => dataApi()->comment('not-a-uid'))->httpStatus)->toBe(404);
});

it('stands a placeholder in for a hidden comment with visible replies, saying nothing about it', function() {
    $entry = CommentsFixtureFactory::entry('API placeholder owner');
    $member = CommentsFixtureFactory::user('apiPlaceholder');
    $root = dataApiPost($entry, 'Removed later', $member);
    $reply = dataApiPost($entry, 'Still here', null, $root);
    Comments::$plugin->getModeration()->remove($root, dataApiAdmin());
    dataApi()->invalidateCaches();

    $thread = dataApi()->thread($entry);
    $placeholder = $thread['comments'][0];

    expect($placeholder['state'])->toBe('placeholder')
        ->and($placeholder['content'])->toBeNull()
        ->and($placeholder['author'])->toBeNull()
        ->and($placeholder['replies']['items'][0]['uid'])->toBe($reply->uid)
        ->and(dataApiJson($thread))->not->toContain('Removed later')
        ->and(dataApi()->replies($root->uid)['replies']['items'][0]['uid'])->toBe($reply->uid)
        ->and(dataApiError(fn() => dataApi()->comment($root->uid))->errorCode)->toBe('not_found');
});

it('answers not_found for an owner that is missing, not live, or has comments turned off', function() {
    $entry = CommentsFixtureFactory::entry('API not live owner');
    $entry->enabled = false;
    Craft::$app->getElements()->saveElement($entry);
    $comment = dataApiPost(CommentsFixtureFactory::entry('API comment as owner'), 'Not an owner');

    expect(dataApiError(fn() => dataApi()->resolveOwner($entry->id, null, null))->errorCode)->toBe('not_found')
        ->and(dataApiError(fn() => dataApi()->resolveOwner(999999999, null, null))->errorCode)->toBe('not_found')
        ->and(dataApiError(fn() => dataApi()->resolveOwner($comment->id, null, null))->errorCode)->toBe('not_found')
        ->and(dataApiError(fn() => dataApi()->resolveOwner(null, null, null))->errorCode)->toBe('validation_failed')
        ->and(dataApiError(fn() => dataApi()->resolveOwner(1, null, 99999))->errorCode)->toBe('not_found');
});

it('finds an owner by its URI as well as its ID', function() {
    $entry = CommentsFixtureFactory::entry('API owner by URI');
    dataApiPost($entry, 'By URI');

    expect($entry->uri)->not->toBeNull()
        ->and(dataApi()->resolveOwner(null, '/' . $entry->uri . '/', null)->id)->toBe($entry->id)
        ->and(dataApi()->thread(dataApi()->resolveOwner(null, $entry->uri, $entry->siteId))['conversation']['ownerId'])->toBe((int)$entry->id)
        ->and(dataApiError(fn() => dataApi()->resolveOwner(null, 'no/such/page', null))->errorCode)->toBe('not_found');
});

it('caches public responses until a comment, vote or setting changes them', function() {
    $entry = CommentsFixtureFactory::entry('API cache owner');
    $root = dataApiPost($entry, 'Cached');

    expect(dataApi()->thread($entry)['comments'][0]['interaction']['upvotes'])->toBe(0);

    $voter = Comments::$plugin->getIdentities()->createHistoricalGuest(null, null);
    Comments::$plugin->getInteractions()->set((int)$root->id, (int)$voter->id, InteractionType::Upvote);

    expect(dataApi()->thread($entry)['comments'][0]['interaction']['upvotes'])->toBe(1);

    dataApiPost($entry, 'Newer');

    expect(dataApi()->thread($entry)['comments'])->toHaveCount(2);

    Comments::$plugin->getSettings()->interactionMode = 'none';

    expect(dataApi()->thread($entry)['comments'][0]['interaction'])->toBeNull();
});

it('sends labels from the requested site’s translations', function() {
    Craft::$app->getSites()->setCurrentSite(new Site(['id' => 99999, 'language' => 'fr', 'handle' => 'apiFrench', 'name' => 'French', 'baseUrl' => 'https://fr.example.test/']));

    expect(dataApi()->labels()['reply'])->toBe('Répondre')
        ->and(dataApi()->labels()['post'])->not->toBe('');
});

// The private viewer read
// =========================================================================

it('gives a guest with no session a slim answer with nothing personal in it', function() {
    $entry = CommentsFixtureFactory::entry('API slim owner');
    $root = dataApiPost($entry, 'For a guest');
    dataApiAs(null);

    $viewer = dataApi()->viewer($entry, slim: true);

    expect($viewer['viewer'])->toMatchArray(['isGuest' => true, 'hasSession' => false, 'canComment' => false, 'isModerator' => false])
        ->and($viewer['csrf'])->toBeNull()
        ->and($viewer['editor']['protection'])->toBeNull()
        ->and($viewer['extraComments'])->toBe([])
        ->and($viewer['moderation'])->toBeNull()
        ->and($viewer['comments'][$root->uid])->toMatchArray(['canReply' => false, 'canEdit' => false, 'vote' => null, 'isAuthor' => false]);
});

it('gives a member their permissions and votes, and an author their own waiting comment', function() {
    $entry = CommentsFixtureFactory::entry('API viewer owner');
    $author = CommentsFixtureFactory::user('apiAuthor');
    $member = CommentsFixtureFactory::user('apiOther');
    $mine = dataApiPost($entry, 'My approved comment', $author);
    $theirs = dataApiPost($entry, 'Someone else', $member);
    $waiting = dataApiPost($entry, 'My waiting comment', $author, held: true);
    $waitingReply = dataApiPost($entry, 'My waiting reply', $author, $theirs, true);
    $spam = dataApiPost($entry, 'My spam', $author);
    Comments::$plugin->getModeration()->markSpam($spam);
    $memberIdentity = Comments::$plugin->getIdentities()->getOrCreateForUser($member);
    Comments::$plugin->getInteractions()->set((int)$mine->id, (int)$memberIdentity->id, InteractionType::Upvote);
    Comments::$plugin->getReports()->create((int)$mine->id, (int)$memberIdentity->id, 'spam');

    // The author sees what's theirs, and where it goes, through the private read only.
    dataApiAs($author);
    $viewer = dataApi()->viewer($entry);
    $extra = array_column($viewer['extraComments'], null, 'uid');

    expect($viewer['viewer'])->toMatchArray(['isGuest' => false, 'canComment' => true, 'isModerator' => false])
        ->and($viewer['comments'][$mine->uid])->toMatchArray(['canEdit' => true, 'canDelete' => true, 'isAuthor' => true, 'canReport' => false])
        ->and($viewer['comments'][$theirs->uid])->toMatchArray(['canEdit' => false, 'canDelete' => false, 'isAuthor' => false, 'canReply' => true, 'canReport' => true])
        ->and(array_keys($extra))->toEqualCanonicalizing([$waiting->uid, $waitingReply->uid, $spam->uid])
        ->and($extra[$waiting->uid]['state'])->toBe('pending')
        ->and($extra[$spam->uid]['state'])->toBe('pending')
        ->and($extra[$waitingReply->uid]['parentUid'])->toBe($theirs->uid)
        ->and($extra[$waiting->uid]['content']['text'])->toBe('My waiting comment')
        ->and($viewer['labels'] ?? null)->toBeNull()
        ->and(dataApiJson(dataApi()->thread($entry)))->not->toContain('My waiting');

    // Another member sees none of it, and has their own vote and report.
    dataApiAs($member);
    $viewer = dataApi()->viewer($entry);

    expect($viewer['extraComments'])->toBe([])
        ->and(dataApiJson($viewer))->not->toContain('My waiting')
        ->and($viewer['comments'][$mine->uid])->toMatchArray(['vote' => 'upvote', 'reported' => true, 'canEdit' => false]);
});

it('shows a moderator held, spam and reported comments with what they can do', function() {
    $entry = CommentsFixtureFactory::entry('API moderator owner');
    $member = CommentsFixtureFactory::user('apiModerated');
    $approved = dataApiPost($entry, 'Approved', $member);
    $held = dataApiPost($entry, 'Held for review', $member, held: true);
    $spam = dataApiPost($entry, 'Spammy', $member);
    Comments::$plugin->getModeration()->markSpam($spam);
    $reported = dataApiPost($entry, 'Reported', $member);
    Comments::$plugin->getReports()->create((int)$reported->id, (int)Comments::$plugin->getIdentities()->createHistoricalGuest(null, null)->id, 'spam');

    dataApiAs(dataApiModerator());
    $viewer = dataApi()->viewer($entry);
    $extra = array_column($viewer['extraComments'], null, 'uid');

    expect($viewer['viewer']['isModerator'])->toBeTrue()
        ->and($extra[$held->uid]['state'])->toBe('held')
        ->and($extra[$held->uid]['moderation']['actions'])->toEqualCanonicalizing(['approve', 'spam', 'remove'])
        ->and($extra[$spam->uid]['state'])->toBe('spam')
        ->and($extra[$spam->uid]['moderation']['actions'])->toBe(['not-spam'])
        ->and($extra[$reported->uid]['state'])->toBe('reported')
        ->and($extra[$reported->uid]['moderation']['openReportCount'])->toBe(1)
        ->and($extra[$reported->uid]['moderation']['actions'])->toContain('keep')
        ->and($viewer['comments'][$approved->uid]['moderation']['actions'])->toEqualCanonicalizing(['remove', 'spam'])
        ->and($viewer['moderation'])->toMatchArray(['heldCount' => 1, 'reportedCount' => 1])
        ->and($viewer['moderation']['reviewUrl'])->toContain('comments');
});

it('tells a blocked commenter they can’t comment, and offers no replies', function() {
    $entry = CommentsFixtureFactory::entry('API blocked owner');
    $blocked = CommentsFixtureFactory::user('apiBlocked');
    $root = dataApiPost($entry, 'Before the block', $blocked);
    Comments::$plugin->getModeration()->blockIdentity((int)$root->identityId, null, dataApiAdmin(), 'Enough');

    dataApiAs($blocked);
    $viewer = dataApi()->viewer($entry);

    expect($viewer['viewer'])->toMatchArray(['canComment' => false, 'blocked' => true])
        ->and($viewer['comments'][$root->uid]['canReply'])->toBeFalse()
        ->and($viewer['editor']['protection'])->toBeNull()
        ->and(dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Again']]))->errorCode)->toBe('blocked');
});

it('includes permissions for comments loaded since, and rejects too many', function() {
    $entry = CommentsFixtureFactory::entry('API viewer uids owner');
    $root = dataApiPost($entry, 'Root');
    $replies = [];

    for ($i = 0; $i < 5; $i++) {
        $replies[] = dataApiPost($entry, "Reply {$i}", null, $root);
    }

    dataApiAs(CommentsFixtureFactory::user('apiUids'));
    $viewer = dataApi()->viewer($entry, uids: [$replies[0]->uid, 'not-a-uid']);

    expect($viewer['comments'])->toHaveKey($replies[0]->uid)
        ->and(dataApiError(fn() => dataApi()->viewer($entry, uids: array_fill(0, 201, $root->uid)))->errorCode)->toBe('limit_exceeded');
});

it('keeps the viewer read to a fixed number of queries', function() {
    $entry = CommentsFixtureFactory::entry('API viewer budget owner');
    $member = CommentsFixtureFactory::user('apiBudget');
    $others = [CommentsFixtureFactory::user('apiBudgetA'), CommentsFixtureFactory::user('apiBudgetB')];
    $roots = [];

    // 20 comments in the window: 14 roots, six of them with a reply, two of them by the member.
    for ($i = 0; $i < 14; $i++) {
        $roots[] = dataApiPost($entry, "Root {$i}", $i < 2 ? $member : $others[$i % 2]);
    }

    for ($i = 0; $i < 6; $i++) {
        dataApiPost($entry, "Reply {$i}", $others[$i % 2], $roots[$i]);
    }

    Comments::$plugin->getInteractions()->set((int)$roots[3]->id, (int)Comments::$plugin->getIdentities()->getOrCreateForUser($member)->id, InteractionType::Upvote);
    // And one of theirs waiting, which only they see.
    dataApiPost($entry, 'Waiting', $member, held: true);
    expect(count(dataApiFlatten(dataApi()->thread($entry)['comments'])))->toBe(20);

    // A member, loaded as Craft loads whoever a request signs in.
    $member = Craft::$app->getUsers()->getUserById((int)$member->id);
    dataApiAs($member);
    $profile = QueryProfiler::profile(static fn() => dataApi()->viewer(dataApi()->resolveOwner($entry->id, null, null)));

    expect($profile['queries'])->toBeLessThanOrEqual(15, json_encode($profile['patterns']));
    $viewer = dataApi()->viewer($entry);

    expect($viewer['extraComments'])->toHaveCount(1)
        ->and($viewer['comments'])->toHaveCount(21);

    // A guest with no session.
    dataApiAs(null);
    $profile = QueryProfiler::profile(static fn() => dataApi()->viewer(dataApi()->resolveOwner($entry->id, null, null), slim: true));

    expect($profile['queries'])->toBeLessThanOrEqual(5, json_encode($profile['patterns']));
});

// Writes
// =========================================================================

it('posts comments and replies, approved or waiting, with the state the author sees', function() {
    $entry = CommentsFixtureFactory::entry('API create owner');
    $member = CommentsFixtureFactory::user('apiCreator');
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    dataApiAs($member);

    $created = dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Posted through the API']]);

    expect($created)->toHaveKeys(['comment', 'moderationState', 'conversation', 'announcement'])
        ->and($created['moderationState'])->toBe('approved')
        ->and($created['comment']['state'])->toBe('approved')
        ->and($created['comment']['content']['text'])->toBe('Posted through the API')
        ->and($created['comment']['viewer'])->toMatchArray(['canEdit' => true, 'isAuthor' => true])
        ->and($created['conversation']['commentCount'])->toBe(1);

    $reply = dataApi()->create(['ownerUri' => $entry->uri, 'parentUid' => $created['comment']['uid'], 'content' => ['format' => 'plain', 'text' => 'A reply']]);

    expect($reply['comment']['parentUid'])->toBe($created['comment']['uid'])
        ->and($reply['comment']['depth'])->toBe(1);

    $settings->requireModeration = true;
    Comments::$plugin->getConversations()->clearCaches();
    $waiting = dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Needs approval']]);

    expect($waiting['moderationState'])->toBe('pending')
        ->and($waiting['comment']['state'])->toBe('pending')
        ->and(dataApiJson(dataApi()->thread($entry)))->not->toContain('Needs approval');
});

it('refuses writes with stable codes and field errors keyed by field', function() {
    $entry = CommentsFixtureFactory::entry('API refusals owner');
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $root = dataApiPost($entry, 'Root');

    $settings->allowGuest = false;
    dataApiAs(null);

    expect(dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Guest']]))->errorCode)->toBe('sign_in_required');

    $settings->allowGuest = true;
    $settings->guestRequireEmailName = true;
    Comments::$plugin->getConversations()->clearCaches();
    $error = dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Guest'], 'guest' => ['email' => 'g@example.test']]));

    expect($error->errorCode)->toBe('validation_failed')
        ->and($error->httpStatus)->toBe(422)
        ->and($error->fieldErrors)->toHaveKey('guest.name');

    $error = dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => '   '], 'guest' => ['name' => 'G', 'email' => 'g@example.test']]));

    expect($error->errorCode)->toBe('validation_failed')->and($error->fieldErrors)->toHaveKey('content');

    $error = dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Hi'], 'guest' => ['name' => 'G', 'email' => 'g@example.test'], 'fields' => ['notAField' => 'x']]));

    expect($error->errorCode)->toBe('validation_failed')
        ->and($error->fieldErrors)->toBe(['fields.notAField' => ['This field can’t be filled in here.']]);

    expect(dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'parentUid' => '00000000-0000-0000-0000-000000000000', 'content' => ['format' => 'plain', 'text' => 'x'], 'guest' => ['name' => 'G', 'email' => 'g@example.test']]))->errorCode)->toBe('not_found');

    dataApiAs(dataApiAdmin());
    Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, true);

    expect(dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Closed']]))->errorCode)->toBe('closed')
        ->and(dataApi()->thread($entry)['conversation']['status'])->toBe('closed');
});

it('maps protection refusals to codes a client can act on', function() {
    $entry = CommentsFixtureFactory::entry('API protection owner');
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->enableSpamChecks = true;
    $settings->honeypotEnabled = true;
    $settings->minimumSubmitTime = 0;
    $settings->rateLimitEnabled = false;
    dataApiAs(CommentsFixtureFactory::user('apiProtected'));
    $body = ['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Protected']];

    $missing = dataApiError(fn() => dataApi()->create($body));
    $broken = dataApiError(fn() => dataApi()->create([...$body, 'protection' => ['version' => 2]]));
    $protection = dataApi()->protection($entry);
    $created = dataApi()->create([...$body, 'protection' => ['version' => 1, 'preparation' => $protection['manifest']['preparation'], 'honeypot' => [$protection['manifest']['honeypot'] => '']]]);
    $honeypot = dataApi()->protection($entry);
    $filled = dataApiError(fn() => dataApi()->create([...$body, 'protection' => ['version' => 1, 'preparation' => $honeypot['manifest']['preparation'], 'honeypot' => [$honeypot['manifest']['honeypot'] => 'bot']]]));

    expect($missing->errorCode)->toBe('protection_required')
        ->and($missing->httpStatus)->toBe(422)
        ->and($broken->errorCode)->toBe('protection_required')
        ->and($protection)->toHaveKeys(['version', 'manifest', 'expiresAt'])
        ->and($created['moderationState'])->toBe('approved')
        ->and($filled->errorCode)->toBe('possible_spam');

    // A preparation is used once.
    expect(dataApiError(fn() => dataApi()->create([...$body, 'protection' => ['version' => 1, 'preparation' => $protection['manifest']['preparation'], 'honeypot' => [$protection['manifest']['honeypot'] => '']]]))->errorCode)->toBe('protection_expired');
});

it('edits and removes only the author’s own comment, within the edit window', function() {
    $entry = CommentsFixtureFactory::entry('API update owner');
    $author = CommentsFixtureFactory::user('apiEditor');
    $other = CommentsFixtureFactory::user('apiIntruder');
    $comment = dataApiPost($entry, 'Before', $author);
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;

    dataApiAs($other);
    expect(dataApiError(fn() => dataApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Hijacked']]))->errorCode)->toBe('forbidden')
        ->and(dataApiError(fn() => dataApi()->delete(['uid' => $comment->uid]))->errorCode)->toBe('forbidden');

    dataApiAs(null);
    expect(dataApiError(fn() => dataApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Guest']]))->errorCode)->toBe('sign_in_required');

    dataApiAs($author);
    $updated = dataApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'After']]);

    expect($updated['comment']['content']['text'])->toBe('After')
        ->and($updated['moderationState'])->toBe('approved');

    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['commentDate' => '2020-01-01 00:00:00'], ['id' => $comment->id])->execute();
    $settings->authorEditWindow = 5;

    expect(dataApiError(fn() => dataApi()->update(['uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => 'Late']]))->errorCode)->toBe('edit_window_passed')
        ->and(dataApiError(fn() => dataApi()->delete(['uid' => $comment->uid]))->errorCode)->toBe('edit_window_passed');

    $settings->authorEditWindow = null;
    $removed = dataApi()->delete(['uid' => $comment->uid]);

    expect($removed['uid'])->toBe($comment->uid)
        ->and($removed['conversation']['commentCount'])->toBe(0)
        ->and(dataApi()->thread($entry)['comments'])->toBe([]);
});

it('sets a vote and a report rather than toggling them', function() {
    $entry = CommentsFixtureFactory::entry('API vote owner');
    $comment = dataApiPost($entry, 'Vote on me');
    dataApiAs(CommentsFixtureFactory::user('apiVoter'));

    $first = dataApi()->vote(['uid' => $comment->uid, 'vote' => 'upvote']);
    $again = dataApi()->vote(['uid' => $comment->uid, 'vote' => 'upvote']);

    expect($first['comment']['interaction']['upvotes'])->toBe(1)
        ->and($again['comment']['interaction']['upvotes'])->toBe(1)
        ->and($again['comment']['viewer']['vote'])->toBe('upvote')
        ->and(dataApiError(fn() => dataApi()->vote(['uid' => $comment->uid, 'vote' => 'downvote']))->fieldErrors)->toHaveKey('vote');

    $cleared = dataApi()->vote(['uid' => $comment->uid, 'vote' => null]);

    expect($cleared['comment']['interaction']['upvotes'])->toBe(0)
        ->and($cleared['comment']['viewer']['vote'])->toBeNull();

    $reported = dataApi()->report(['uid' => $comment->uid, 'reason' => 'spam']);
    dataApi()->report(['uid' => $comment->uid, 'reason' => 'spam']);

    expect($reported['comment']['viewer']['reported'])->toBeTrue()
        ->and(Comments::$plugin->getReports()->getOpenCounts([(int)$comment->id])[(int)$comment->id])->toBe(1);

    $withdrawn = dataApi()->report(['uid' => $comment->uid, 'withdraw' => true]);

    expect($withdrawn['comment']['viewer']['reported'])->toBeFalse();

    dataApiAs(null);
    expect(dataApiError(fn() => dataApi()->vote(['uid' => $comment->uid, 'vote' => 'upvote']))->errorCode)->toBe('sign_in_required')
        ->and(dataApiError(fn() => dataApi()->report(['uid' => $comment->uid]))->errorCode)->toBe('sign_in_required');

    $held = dataApiPost($entry, 'Held', null, held: true);
    dataApiAs(dataApiAdmin());
    expect(dataApiError(fn() => dataApi()->vote(['uid' => $held->uid, 'vote' => 'upvote']))->errorCode)->toBe('not_found');
});

it('follows and stops following a conversation', function() {
    $entry = CommentsFixtureFactory::entry('API follow owner');
    Comments::$plugin->getSettings()->subscriptionTypes = ['conversation'];
    dataApiAs(CommentsFixtureFactory::user('apiFollower'));

    $followed = dataApi()->follow(['ownerId' => $entry->id, 'following' => true]);
    $stopped = dataApi()->follow(['ownerId' => $entry->id, 'following' => false]);

    expect($followed['viewer']['follow']['following'])->toBeTrue()
        ->and($followed['outcome'])->toBe('followed')
        ->and($stopped['viewer']['follow']['following'])->toBeFalse();

    Comments::$plugin->getSettings()->subscriptionTypes = [];
    expect(dataApiError(fn() => dataApi()->follow(['ownerId' => $entry->id, 'following' => true]))->errorCode)->toBe('forbidden');
});

it('lets moderators decide from the site, and refuses everyone else or a stale decision', function() {
    $entry = CommentsFixtureFactory::entry('API moderate owner');
    $author = CommentsFixtureFactory::user('apiModAuthor');
    $held = dataApiPost($entry, 'Decide me', $author, held: true);

    dataApiAs(CommentsFixtureFactory::user('apiNotModerator'));
    expect(dataApiError(fn() => dataApi()->moderate(['uid' => $held->uid, 'action' => 'approve']))->errorCode)->toBe('forbidden');

    dataApiAs(dataApiModerator());
    expect(dataApiError(fn() => dataApi()->moderate(['uid' => $held->uid, 'action' => 'launch']))->errorCode)->toBe('validation_failed');

    $approved = dataApi()->moderate(['uid' => $held->uid, 'action' => 'approve']);

    expect($approved['comment']['state'])->toBe('approved')
        ->and($approved['comment']['moderation']['actions'])->toEqualCanonicalizing(['remove', 'spam'])
        ->and(dataApiError(fn() => dataApi()->moderate(['uid' => $held->uid, 'action' => 'approve']))->errorCode)->toBe('conflict');

    $removed = dataApi()->moderate(['uid' => $held->uid, 'action' => 'remove']);

    expect($removed['comment'])->toMatchArray(['uid' => $held->uid, 'state' => 'removed'])
        ->and($removed['comment']['moderation']['actions'])->toContain('restore');

    // A moderator can't decide their own comment.
    $own = dataApiPost($entry, 'My own', Craft::$app->getUser()->getIdentity(), held: true);
    expect(dataApiError(fn() => dataApi()->moderate(['uid' => $own->uid, 'action' => 'approve']))->errorCode)->toBe('forbidden');
});

// Events, badges and structured data
// =========================================================================

it('adds public data to comments in one batch, and private data to the viewer', function() {
    $entry = CommentsFixtureFactory::entry('API events owner');
    $first = dataApiPost($entry, 'First');
    $second = dataApiPost($entry, 'Second', null, $first);
    $calls = 0;

    Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function(DefineCommentDataEvent $event) use (&$calls) {
        $calls++;

        foreach ($event->comments as $uid => $comment) {
            $event->custom[$uid] = ['verifiedBuyer' => $comment->comment === 'First'];
        }
    });

    Event::on(Api::class, Api::EVENT_DEFINE_VIEWER_DATA, function(DefineViewerDataEvent $event) use ($first) {
        $event->custom = ['plan' => 'gold'];
        $event->commentCustom[$first->uid] = ['purchased' => true];
    });

    $thread = dataApi()->thread($entry);
    $viewer = dataApi()->viewer($entry);

    expect($calls)->toBe(1)
        ->and($thread['comments'][0]['custom'])->toBe(['verifiedBuyer' => true])
        ->and($thread['comments'][0]['replies']['items'][0]['custom'])->toBe(['verifiedBuyer' => false])
        ->and($viewer['custom'])->toBe(['plan' => 'gold'])
        ->and($viewer['comments'][$first->uid]['custom'])->toBe(['purchased' => true])
        ->and($viewer['comments'][$second->uid]['custom'])->toEqual(new stdClass());
});

it('adds badges a site defines to authors in the thread, GraphQL’s author and the data API', function() {
    $entry = CommentsFixtureFactory::entry('API badges owner');
    $comment = dataApiPost($entry, 'Badge me');

    Event::on(CommentsService::class, CommentsService::EVENT_DEFINE_BADGES, function(DefineBadgesEvent $event) {
        $event->badges[] = ['type' => 'verified', 'label' => 'Verified buyer'];
        $event->badges[] = ['label' => 'No type'];
    });

    $badges = dataApi()->thread($entry)['comments'][0]['author']['badges'];
    $graphql = Comments::$plugin->getFrontend()->publicAuthorsFor([$comment])[(int)$comment->id]['badges'];

    expect($badges)->toContain(['type' => 'verified', 'label' => 'Verified buyer'])
        ->and(array_column($badges, 'label'))->not->toContain('No type')
        ->and($graphql)->toBe($badges);
});

it('outputs structured data without names unless asked, attached to an id when given', function() {
    $entry = CommentsFixtureFactory::entry('API structured data owner');
    $author = CommentsFixtureFactory::user('apiStructured');
    $author->fullName = 'Sasha Writer';
    Craft::$app->getElements()->saveElement($author, false);
    $root = dataApiPost($entry, 'Nice </script><script>alert(1)</script> post', $author);
    dataApiPost($entry, 'A reply', $author, $root);
    $voter = Comments::$plugin->getIdentities()->createHistoricalGuest(null, null);
    Comments::$plugin->getInteractions()->set((int)$root->id, (int)$voter->id, InteractionType::Upvote);

    $data = dataApi()->structuredData($entry);
    $named = dataApi()->structuredData($entry, ['includeAuthors' => true, 'id' => 'https://example.test/#article']);

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@type'])->toBe('Article')
        ->and($data['commentCount'])->toBe(2)
        ->and($data['comment'][0])->toMatchArray(['@type' => 'Comment', 'upvoteCount' => 1])
        ->and($data['comment'][0])->toHaveKeys(['datePublished', 'text', 'url'])
        ->and($data['comment'][0]['comment'][0]['text'])->toBe('A reply')
        ->and(dataApiJson($data))->not->toContain('Sasha')
        ->and($named['@id'])->toBe('https://example.test/#article')
        ->and($named)->not->toHaveKey('@type')
        ->and($named['comment'][0]['author'])->toBe(['@type' => 'Person', 'name' => 'Sasha Writer']);

    $variable = new verbb\comments\variables\CommentsVariable();
    $html = (string)$variable->structuredData($entry);

    expect($html)->toStartWith('<script type="application/ld+json">')
        ->and(substr_count($html, '</script>'))->toBe(1)
        ->and($html)->not->toContain('Sasha')
        ->and($variable->structuredData($entry, ['asArray' => true, 'limit' => 1])['commentCount'])->toBe(2)
        ->and((string)$variable->structuredData(999999999))->toBe('');
});

// Errors and GraphQL
// =========================================================================

it('gives every error a stable code, status, category and field errors keyed by field', function() {
    $api = dataApi();
    $validation = $api->errorFor(verbb\comments\exceptions\CommentValidationException::fromErrors(['comment' => ['Empty'], 'name' => ['Name'], 'email' => ['Email']]));
    $data = $api->errorData($validation);

    expect($validation->fieldErrors)->toBe(['content' => ['Empty'], 'guest.name' => ['Name'], 'guest.email' => ['Email']])
        ->and($data)->toMatchArray(['apiVersion' => 1, 'status' => 'error'])
        ->and($data['errors'][0])->toMatchArray(['category' => 'validation', 'code' => 'validation_failed', 'recoverable' => true, 'retryAfter' => null])
        ->and($api->errorFor(new verbb\comments\exceptions\ProtectionException('Wait', 429, 30, 'rate_limited'))->retryAfter)->toBe(30)
        ->and($api->errorFor(new verbb\comments\exceptions\RefusedException('Closed', 'closed'))->httpStatus)->toBe(403)
        ->and($api->errorFor(new yii\web\NotFoundHttpException())->errorCode)->toBe('not_found')
        ->and($api->errorFor(new RuntimeException('Secret details'))->getMessage())->not->toContain('Secret')
        ->and($api->errorFor(new RuntimeException('x'))->httpStatus)->toBe(500);
});

it('returns the same data through GraphQL as through REST', function() {
    $entry = CommentsFixtureFactory::entry('API GraphQL owner');
    $root = dataApiPost($entry, 'Through both');
    $reply = dataApiPost($entry, 'Reply through both', null, $root);
    $schema = new craft\models\GqlSchema(['name' => 'Data API', 'scope' => ['comments:read']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $rest = static function(array $data): array {
        unset($data['apiVersion'], $data['status']);

        return dataApiStable($data);
    };
    $graphql = static function(string $query, array $variables) use ($gql, $schema): array {
        $result = $gql->executeQuery($schema, $query, $variables, debugMode: true);
        expect($result['errors'] ?? null)->toBeNull(json_encode($result['errors'] ?? null));
        $field = array_key_first($result['data']);
        expect($result['data'][$field]['apiVersion'])->toBe(1);

        return dataApiStable($result['data'][$field]['data']);
    };

    expect(dataApiJson($graphql('query($owner: ID) { commentsThread(ownerId: $owner, limit: 5) { apiVersion data } }', ['owner' => $entry->id])))
        ->toBe(dataApiJson($rest(dataApi()->thread($entry, null, 5))))
        ->and(dataApiJson($graphql('query($uid: String!) { commentsReplies(parentUid: $uid) { apiVersion data } }', ['uid' => $root->uid])))
        ->toBe(dataApiJson($rest(dataApi()->replies($root->uid))))
        ->and(dataApiJson($graphql('query($uid: String!) { commentsComment(uid: $uid) { apiVersion data } }', ['uid' => $reply->uid])))
        ->toBe(dataApiJson($rest(dataApi()->comment($reply->uid))));

    $viewer = $graphql('query($owner: ID) { commentsViewer(ownerId: $owner) { apiVersion data } }', ['owner' => $entry->id]);
    $restViewer = $rest(dataApi()->viewer($entry));
    unset($viewer['editor']['protection'], $restViewer['editor']['protection']);

    expect(dataApiJson($viewer))->toBe(dataApiJson($restViewer));

    $missing = $gql->executeQuery($schema, '{ commentsComment(uid: "00000000-0000-0000-0000-000000000000") { data } }');

    expect($missing['errors'][0]['extensions']['code'] ?? null)->toBe('not_found');
});

it('writes through GraphQL with the same results and replays as the data API', function() {
    $entry = CommentsFixtureFactory::entry('API GraphQL writes owner');
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->subscriptionTypes = ['conversation'];
    Comments::$plugin->getConversations()->clearCaches();
    $member = CommentsFixtureFactory::user('apiGqlWriter');
    dataApiAs($member);
    $schema = new craft\models\GqlSchema(['name' => 'Data API writes', 'scope' => ['comments:read', 'comments:edit', 'comments:save', 'comments:delete', 'comments:moderate']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $run = static function(string $query, array $variables = []) use ($gql, $schema): array {
        return $gql->executeQuery($schema, $query, $variables, debugMode: true);
    };
    // What differs between two comments made the same way.
    $same = static function(array $comment): array {
        unset($comment['uid'], $comment['permalink'], $comment['createdAt'], $comment['display'], $comment['parentUid']);

        return $comment;
    };

    // Creating, with a replay.
    $rest = dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Same words']]);
    $create = 'mutation($owner: ID, $op: String, $parent: String) { createComment(ownerId: $owner, comment: "Same words", operationId: $op, parentUid: $parent) { id commentsData } }';
    $operationId = craft\helpers\StringHelper::UUID();
    $first = $run($create, ['owner' => $entry->id, 'op' => $operationId]);
    $repeat = $run($create, ['owner' => $entry->id, 'op' => $operationId]);

    expect($first['errors'] ?? null)->toBeNull(json_encode($first['errors'] ?? null))
        ->and($same($first['data']['createComment']['commentsData']))->toEqual($same($rest['comment']))
        ->and($repeat['data']['createComment']['id'])->toBe($first['data']['createComment']['id'])
        ->and((int)Comment::find()->ownerId($entry->id)->count())->toBe(2);

    $reply = $run($create, ['owner' => $entry->id, 'op' => craft\helpers\StringHelper::UUID(), 'parent' => $rest['comment']['uid']]);

    expect($reply['data']['createComment']['commentsData']['parentUid'])->toBe($rest['comment']['uid']);

    // Voting and reporting set a state, through either.
    $target = dataApiPost($entry, 'Vote on me', CommentsFixtureFactory::user('apiGqlTarget'));
    $vote = 'mutation($uid: String, $vote: String, $op: String) { voteComment(uid: $uid, vote: $vote, operationId: $op) { upvote } }';
    $run($vote, ['uid' => $target->uid, 'vote' => 'upvote', 'op' => craft\helpers\StringHelper::UUID()]);
    $run($vote, ['uid' => $target->uid, 'vote' => 'upvote', 'op' => craft\helpers\StringHelper::UUID()]);
    $restVote = dataApi()->vote(['uid' => $target->uid, 'vote' => 'upvote']);

    expect($restVote['comment']['interaction']['upvotes'])->toBe(1)
        ->and($restVote['comment']['viewer']['vote'])->toBe('upvote');

    $cleared = $run($vote, ['uid' => $target->uid, 'vote' => null, 'op' => craft\helpers\StringHelper::UUID()]);
    $data = $run('query($uid: [String]) { comment(uid: $uid) { commentsData } }', ['uid' => [$target->uid]])['data']['comment']['commentsData'];

    expect($cleared['data']['voteComment']['upvote'])->toBeNull()
        ->and($data['interaction']['upvotes'])->toBe(0)
        ->and($data['viewer']['vote'])->toBeNull();

    $flag = 'mutation($uid: String, $withdraw: Boolean) { flagComment(uid: $uid, reason: "spam", withdraw: $withdraw) { commentId } }';
    $run($flag, ['uid' => $target->uid, 'withdraw' => false]);
    $run($flag, ['uid' => $target->uid, 'withdraw' => false]);

    expect(Comments::$plugin->getReports()->getOpenCounts([(int)$target->id])[(int)$target->id])->toBe(1)
        ->and(dataApi()->report(['uid' => $target->uid, 'withdraw' => true])['comment']['viewer']['reported'])->toBeFalse();

    // Following answers with the same outcome.
    $follow = $run('mutation($owner: ID!) { subscribeComment(ownerId: $owner, following: true) }', ['owner' => $entry->id]);

    expect($follow['data']['subscribeComment'])->toBe('followed')
        ->and(dataApi()->follow(['ownerId' => $entry->id, 'following' => true])['outcome'])->toBe('followed');

    // A moderator keeps a reported comment and holds another, with a note.
    Comments::$plugin->getReports()->create((int)$target->id, (int)Comments::$plugin->getIdentities()->createHistoricalGuest(null, null)->id, 'spam');
    dataApiAs(dataApiModerator());
    $keep = $run('mutation($uid: String) { moderateComment(uid: $uid, action: "keep", note: "Fine") { id commentsData } }', ['uid' => $target->uid]);
    $hold = $run('mutation($uid: String) { moderateComment(uid: $uid, action: "hold") { commentsData } }', ['uid' => $rest['comment']['uid']]);

    expect($keep['errors'] ?? null)->toBeNull(json_encode($keep['errors'] ?? null))
        ->and($keep['data']['moderateComment']['commentsData']['state'])->toBe('approved')
        ->and($keep['data']['moderateComment']['commentsData']['moderation']['openReportCount'])->toBe(0)
        ->and(Comments::$plugin->getModeration()->getNotes((int)$target->id))->toHaveCount(1)
        ->and($hold['data']['moderateComment']['commentsData']['state'])->toBe('held');

    // Refusals carry the same codes and details.
    $settings->allowGuest = false;
    Comments::$plugin->getConversations()->clearCaches();
    dataApiAs(null);
    $refused = $run('mutation($owner: ID) { createComment(ownerId: $owner, comment: "Guest", operationId: "guest-operation-1") { id } }', ['owner' => $entry->id]);
    $restRefusal = dataApiError(fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Guest']]));

    expect($refused['errors'][0]['extensions'])->toMatchArray([
        'code' => $restRefusal->errorCode,
        'category' => 'auth',
        'recoverable' => false,
        'retryAfter' => null,
    ])
        ->and($refused['errors'][0]['extensions'])->toHaveKey('fieldErrors');

    $missing = $run('mutation { voteComment(uid: "00000000-0000-0000-0000-000000000000", vote: "upvote") { upvote } }');

    expect($missing['errors'][0]['extensions']['code'] ?? null)->toBe('sign_in_required');
});

it('never serves one person’s commentsData from Craft’s GraphQL cache to another', function() {
    $entry = CommentsFixtureFactory::entry('API GraphQL cache owner');
    $author = CommentsFixtureFactory::user('apiGqlCacheAuthor');
    $comment = dataApiPost($entry, 'Whose is this?', $author);
    $general = Craft::$app->getConfig()->getGeneral();
    $previous = $general->enableGraphqlCaching;
    $general->enableGraphqlCaching = true;
    $schema = new craft\models\GqlSchema(['name' => 'Data API cache', 'scope' => ['comments:read'], 'isPublic' => true, 'uid' => craft\helpers\StringHelper::UUID()]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $query = "{ comment(id: {$comment->id}) { commentsData } }";

    try {
        dataApiAs($author);
        $mine = $gql->executeQuery($schema, $query)['data']['comment']['commentsData'];
        dataApiAs(null);
        $theirs = $gql->executeQuery($schema, $query)['data']['comment']['commentsData'];
    } finally {
        $general->enableGraphqlCaching = $previous;
    }

    expect($mine['viewer']['isAuthor'])->toBeTrue()
        ->and($theirs['viewer']['isAuthor'])->toBeFalse()
        ->and($mine)->not->toHaveKey('moderation');
});

// Fixes from the stage B security review
// =========================================================================

it('clears cached public data once changes are committed, including one cached in between', function() {
    $entry = CommentsFixtureFactory::entry('API commit owner');
    $author = CommentsFixtureFactory::user('apiCommitAuthor');
    $comment = dataApiPost($entry, 'Visible', $author);
    $authorRemoved = dataApiPost($entry, 'Removed by its author', $author);
    // Always in the thread, so every build has a comment to report.
    dataApiPost($entry, 'Always there');
    $builds = 0;
    Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function() use (&$builds) {
        $builds++;
    });
    $read = static fn() => dataApi()->thread($entry);

    $read();
    $read();
    expect($builds)->toBe(1);

    // A read during a transaction caches what it sees; the commit clears it again.
    $transaction = Craft::$app->getDb()->beginTransaction();
    dataApi()->invalidateCaches();
    $read();
    $transaction->commit();
    $read();
    expect($builds)->toBe(3);

    $moderator = dataApiModerator();
    $steps = [
        'hold' => fn() => Comments::$plugin->getModeration()->setState($comment, ModerationState::Pending, $moderator),
        'approve' => fn() => Comments::$plugin->getModeration()->approve($comment, $moderator),
        'remove' => fn() => Comments::$plugin->getModeration()->remove($comment, $moderator),
        'restore' => fn() => Comments::$plugin->getModeration()->restore($comment, $moderator),
        'rename the author' => function() use ($author) {
            $author->fullName = 'Renamed Author';
            Craft::$app->getElements()->saveElement($author, false);
        },
        'author removal' => function() use ($author, $authorRemoved) {
            dataApiAs($author);
            Comments::$plugin->getComments()->authorRemove((int)$authorRemoved->id, (int)$authorRemoved->siteId, $author);
        },
    ];

    foreach ($steps as $name => $step) {
        $read();
        $before = $builds;
        $step();
        $data = $read();

        expect($builds)->toBe($before + 1, $name);
    }

    expect(json_encode($data))->not->toContain('Removed by its author')
        ->and(json_encode($data))->toContain('Always there');
});

it('tells the viewer when a thread it was served is older than the data', function() {
    $entry = CommentsFixtureFactory::entry('API version owner');
    dataApiPost($entry, 'First');
    $thread = dataApi()->thread($entry);

    expect(dataApi()->viewer($entry)['threadVersion'])->toBe($thread['threadVersion']);

    dataApiPost($entry, 'Second');

    expect(dataApi()->viewer($entry)['threadVersion'])->not->toBe($thread['threadVersion'])
        ->and(dataApi()->thread($entry)['threadVersion'])->toBe(dataApi()->viewer($entry)['threadVersion']);
});

it('keeps the request’s host, the time and other page sizes out of the cached data', function() {
    $entry = CommentsFixtureFactory::entry('API host owner');
    dataApiPost($entry, 'Host');
    $builds = 0;
    Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function() use (&$builds) {
        $builds++;
    });
    $request = Craft::$app->getRequest();

    // Permalinks are only completed as the response leaves.
    expect(dataApi()->thread($entry)['comments'][0]['permalink'])->toStartWith((string)$entry->getUrl() . '#comment-');

    // While `@web` comes from the request's host, nothing is cached.
    $request->isWebAliasSetDynamically = true;

    try {
        dataApi()->thread($entry);
        dataApi()->thread($entry);
    } finally {
        $request->isWebAliasSetDynamically = false;
    }

    expect($builds)->toBe(3);

    // Page sizes other than the default are built each time, and a page's cursor keeps its size.
    dataApiPost($entry, 'Another');
    $page = dataApi()->thread($entry, null, 1);
    dataApi()->thread($entry, null, 1);

    expect($builds)->toBe(5)
        ->and(dataApiError(fn() => dataApi()->thread($entry, null, 2, $page['page']['next']))->errorCode)->toBe('invalid_cursor')
        ->and(dataApi()->thread($entry, null, 1, $page['page']['next'])['comments'])->toHaveCount(1);

    // Closing after a number of days depends on the time, so a cached thread still says when it closes.
    dataApi()->thread($entry);
    Comments::$plugin->getSettings()->autoCloseDays = 1;
    Craft::$app->getDb()->createCommand()->update(craft\db\Table::ENTRIES, ['postDate' => '2020-01-01 00:00:00'], ['id' => $entry->id])->execute();
    $owner = dataApi()->resolveOwner($entry->id, null, null);
    $closed = dataApi()->thread($owner);
    Comments::$plugin->getSettings()->autoCloseDays = null;

    expect($closed['conversation']['status'])->toBe('closed');
});

it('builds public data as nobody, and tells event handlers whether it’s public', function() {
    $entry = CommentsFixtureFactory::entry('API nobody owner');
    $author = CommentsFixtureFactory::user('apiNobodyAuthor');
    dataApiPost($entry, 'Mine, waiting', $author, held: true);
    dataApiPost($entry, 'Public');
    $seen = [];
    Event::on(Api::class, Api::EVENT_DEFINE_COMMENT_DATA, function(DefineCommentDataEvent $event) use (&$seen) {
        $seen[] = [$event->isPublic, Craft::$app->getUser()->getIdentity()?->id];
    });
    Event::on(CommentsService::class, CommentsService::EVENT_DEFINE_BADGES, function(DefineBadgesEvent $event) use (&$seen) {
        $seen[] = ['badges', $event->isPublic];
    });

    dataApiAs($author);
    dataApi()->thread($entry);
    dataApi()->viewer($entry);

    expect($seen)->toContain([true, null])
        ->and($seen)->toContain([false, (int)$author->id])
        ->and($seen)->toContain(['badges', true])
        ->and($seen)->toContain(['badges', false])
        ->and(Craft::$app->getUser()->getIdentity()->id)->toBe($author->id);
});

it('reports an author’s spam like their waiting comment, and refuses saving it without saying why', function() {
    $entry = CommentsFixtureFactory::entry('API spam author owner');
    $author = CommentsFixtureFactory::user('apiSpamAuthor');
    $waiting = dataApiPost($entry, 'Waiting', $author, held: true);
    $spam = dataApiPost($entry, 'Spammy', $author);
    Comments::$plugin->getModeration()->markSpam($spam);
    dataApiAs($author);
    $viewer = dataApi()->viewer($entry);
    $refusal = dataApiError(fn() => dataApi()->update(['uid' => $spam->uid, 'content' => ['format' => 'plain', 'text' => 'Edited']]));

    expect($viewer['comments'][$spam->uid]['canEdit'])->toBe($viewer['comments'][$waiting->uid]['canEdit'])
        ->and($viewer['comments'][$spam->uid]['canEdit'])->toBeTrue()
        ->and(dataApi()->edit($spam->uid)['comment']['content']['text'])->toBe('Spammy')
        ->and($refusal->errorCode)->toBe('forbidden')
        ->and(strtolower($refusal->getMessage()))->not->toContain('spam');
});

it('never lets a moderator without the trash permission restore a removed comment', function() {
    $entry = CommentsFixtureFactory::entry('API restore owner');
    $removed = dataApiPost($entry, 'Removed', CommentsFixtureFactory::user('apiRestoreAuthor'));
    Comments::$plugin->getModeration()->remove($removed, dataApiAdmin());
    $moderator = CommentsFixtureFactory::user('apiNoTrash');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['comments-view', 'comments-moderate']);
    dataApiAs(Craft::$app->getUsers()->getUserById((int)$moderator->id));
    $schema = new craft\models\GqlSchema(['name' => 'No trash', 'scope' => ['comments:read', 'comments:moderate']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);

    foreach (['hold', 'approve', 'restore'] as $action) {
        expect(dataApiError(fn() => dataApi()->moderate(['uid' => $removed->uid, 'action' => $action]))->errorCode)->toBe('forbidden', $action);
        $result = $gql->executeQuery($schema, 'mutation($uid: String, $action: String!) { moderateComment(uid: $uid, action: $action) { id } }', ['uid' => $removed->uid, 'action' => $action]);
        expect($result['errors'][0]['extensions']['code'] ?? null)->toBe('forbidden', $action);
    }

    expect(Comments::$plugin->getModeration()->currentState(Comment::find()->id($removed->id)->status(null)->one()))->toBe(ModerationState::Removed);
});

it('includes a comment of the asker’s own asked for by UID, wherever it is in the thread', function() {
    $entry = CommentsFixtureFactory::entry('API deep own owner');
    $author = CommentsFixtureFactory::user('apiDeepOwn');

    for ($i = 0; $i < 3; $i++) {
        dataApiPost($entry, "Newer root {$i}");
    }

    $oldRoot = dataApiPost($entry, 'Old root');
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['commentDate' => '2020-01-01 00:00:00'], ['id' => $oldRoot->id])->execute();
    $mine = dataApiPost($entry, 'My waiting reply', $author, $oldRoot, true);
    dataApi()->invalidateCaches();
    dataApiAs($author);
    $outside = dataApi()->viewer($entry, limit: 1);
    $linked = dataApi()->viewer($entry, limit: 1, uids: [$mine->uid]);

    expect(array_column($outside['extraComments'], 'uid'))->not->toContain($mine->uid)
        ->and(array_column($linked['extraComments'], 'state', 'uid'))->toMatchArray([$mine->uid => 'pending'])
        ->and(dataApiError(fn() => dataApi()->comment($mine->uid))->errorCode)->toBe('not_found');

    dataApiAs(CommentsFixtureFactory::user('apiDeepOther'));

    expect(dataApi()->viewer($entry, limit: 1, uids: [$mine->uid])['extraComments'])->toBe([]);
});

it('reads booleans strictly, limits report details, refuses reporting yourself and hides server errors', function() {
    $entry = CommentsFixtureFactory::entry('API input owner');
    $member = CommentsFixtureFactory::user('apiInput');
    $mine = dataApiPost($entry, 'Mine', $member);
    $theirs = dataApiPost($entry, 'Theirs');
    Comments::$plugin->getSettings()->subscriptionTypes = ['conversation'];
    dataApiAs($member);

    expect(dataApi()->follow(['ownerId' => $entry->id, 'following' => 'false'])['viewer']['follow']['following'])->toBeFalse()
        ->and(dataApi()->follow(['ownerId' => $entry->id, 'following' => 'true'])['viewer']['follow']['following'])->toBeTrue()
        ->and(dataApiError(fn() => dataApi()->follow(['ownerId' => $entry->id, 'following' => 'maybe']))->fieldErrors)->toHaveKey('following')
        ->and(dataApiError(fn() => dataApi()->report(['uid' => $theirs->uid, 'withdraw' => 'perhaps']))->fieldErrors)->toHaveKey('withdraw')
        ->and(dataApiError(fn() => dataApi()->report(['uid' => $theirs->uid, 'details' => str_repeat('x', Api::REPORT_DETAILS_MAX + 1)]))->fieldErrors)->toHaveKey('details')
        ->and(dataApiError(fn() => dataApi()->report(['uid' => $mine->uid]))->errorCode)->toBe('forbidden')
        ->and(dataApi()->errorFor(new yii\web\BadRequestHttpException('Bad'))->errorCode)->toBe('invalid_request')
        ->and(dataApi()->errorFor(new yii\web\ServerErrorHttpException('Database password wrong'))->getMessage())->not->toContain('password');
});

it('counts IPv6 visitors by their /64 and limits GraphQL reads too', function() {
    $entry = CommentsFixtureFactory::entry('API rate owner');
    $previous = $_SERVER['REMOTE_ADDR'] ?? null;
    $schema = new craft\models\GqlSchema(['name' => 'Rate', 'scope' => ['comments:read'], 'isPublic' => true]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $cache = Craft::$app->getCache();
    $key = static fn(string $bucket, string $address): string => 'comments:api:rate:' . $bucket . ':' . hash('sha256', $address) . ':' . intdiv(craft\helpers\DateTimeHelper::currentTimeStamp(), 60);
    // Allowances are per minute, so the clock stops for the test rather than risk a new minute starting
    // between filling one and checking it.
    craft\helpers\DateTimeHelper::pause(new DateTime('@' . time()));

    try {
        $bucket = 'probe' . bin2hex(random_bytes(4));
        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:aaaa::1';
        dataApi()->rateLimit($bucket, 2);
        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:bbbb::2';
        dataApi()->rateLimit($bucket, 2);

        expect(dataApiError(fn() => dataApi()->rateLimit($bucket, 2))->errorCode)->toBe('rate_limited');

        // Another /64 has its own allowance.
        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:3::1';
        dataApi()->rateLimit($bucket, 2);

        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        $cache->set($key('reads', '203.0.113.50'), Api::CACHED_READS_PER_MINUTE, 60);
        $cache->set($key('viewer', '203.0.113.50'), Api::VIEWER_READS_PER_MINUTE, 60);
        $thread = $gql->executeQuery($schema, 'query($owner: ID) { commentsThread(ownerId: $owner) { apiVersion } }', ['owner' => $entry->id]);
        $viewer = $gql->executeQuery($schema, 'query($owner: ID) { commentsViewer(ownerId: $owner) { apiVersion } }', ['owner' => $entry->id]);

        expect($thread['errors'][0]['extensions']['code'] ?? null)->toBe('rate_limited')
            ->and($thread['errors'][0]['extensions']['retryAfter'] ?? null)->toBeGreaterThan(0)
            ->and($viewer['errors'][0]['extensions']['code'] ?? null)->toBe('rate_limited');
    } finally {
        craft\helpers\DateTimeHelper::resume();
        if ($previous === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $previous;
        }
    }
});

it('keeps moderator data off public GraphQL schemas', function() {
    $entry = CommentsFixtureFactory::entry('API public moderator owner');
    $held = dataApiPost($entry, 'Held', CommentsFixtureFactory::user('apiPublicHeld'), held: true);
    dataApiPost($entry, 'Approved');
    dataApiAs(dataApiModerator());
    $gql = Craft::$app->getGql();
    $public = new craft\models\GqlSchema(['name' => 'Public', 'scope' => ['comments:read'], 'isPublic' => true]);
    $gql->flushCaches();
    $gql->setActiveSchema($public);
    $data = $gql->executeQuery($public, 'query($owner: ID) { commentsViewer(ownerId: $owner) { data } }', ['owner' => $entry->id])['data']['commentsViewer']['data'];

    expect($data['viewer']['isModerator'])->toBeFalse()
        ->and($data['moderation'])->toBeNull()
        ->and(json_encode($data))->not->toContain($held->uid)
        ->and(json_encode($data))->not->toContain('"actions"');
});

it('refuses uid reads and writes on owners that are expired or drafts', function() {
    $entry = CommentsFixtureFactory::entry('API expired owner');
    $comment = dataApiPost($entry, 'On an expiring entry');
    $entry->expiryDate = new DateTime('-1 day');
    Craft::$app->getElements()->saveElement($entry);
    $draft = Craft::$app->getDrafts()->createDraft(CommentsFixtureFactory::entry('API draft owner'), (int)dataApiAdmin()->id);
    $onDraft = CommentsFixtureFactory::comment($draft, 'On a draft');
    dataApiAs(CommentsFixtureFactory::user('apiExpiredVoter'));
    $schema = new craft\models\GqlSchema(['name' => 'Owners', 'scope' => ['comments:read', 'comments:save']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);

    foreach ([$comment, $onDraft] as $target) {
        expect(dataApiError(fn() => dataApi()->comment($target->uid))->errorCode)->toBe('not_found')
            ->and(dataApiError(fn() => dataApi()->replies($target->uid))->errorCode)->toBe('not_found')
            ->and(dataApiError(fn() => dataApi()->vote(['uid' => $target->uid, 'vote' => 'upvote']))->errorCode)->toBe('not_found')
            ->and(dataApiError(fn() => dataApi()->report(['uid' => $target->uid]))->errorCode)->toBe('not_found');
        $result = $gql->executeQuery($schema, 'mutation($uid: String) { voteComment(uid: $uid, vote: "upvote") { upvote } }', ['uid' => $target->uid]);
        expect($result['errors'][0]['extensions']['code'] ?? null)->toBe('not_found');
    }

    expect(dataApiError(fn() => dataApi()->thread(dataApi()->resolveOwner($entry->id, null, null)))->errorCode)->toBe('not_found');
});

it('keeps a moderator’s viewer and a page of GraphQL commentsData to a fixed number of queries', function() {
    $entry = CommentsFixtureFactory::entry('API moderator budget owner');
    $others = [CommentsFixtureFactory::user('apiModBudgetA'), CommentsFixtureFactory::user('apiModBudgetB')];
    $roots = [];

    for ($i = 0; $i < 14; $i++) {
        $roots[] = dataApiPost($entry, "Root {$i}", $others[$i % 2]);
    }

    for ($i = 0; $i < 6; $i++) {
        dataApiPost($entry, "Reply {$i}", $others[$i % 2], $roots[$i]);
    }

    dataApiPost($entry, 'Held', $others[0], held: true);
    $moderator = Craft::$app->getUsers()->getUserById((int)dataApiModerator()->id);
    dataApiAs($moderator);
    $profile = QueryProfiler::profile(static fn() => dataApi()->viewer(dataApi()->resolveOwner($entry->id, null, null)));

    expect($profile['queries'])->toBeLessThanOrEqual(15, json_encode($profile['patterns']));

    $schema = new craft\models\GqlSchema(['name' => 'Batch', 'scope' => ['comments:read']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $query = 'query($owner: [QueryArgument]) { comments(ownerId: $owner, limit: 10) { id commentsData } }';
    $few = QueryProfiler::profile(static fn() => $gql->executeQuery($schema, str_replace('limit: 10', 'limit: 2', $query), ['owner' => [$entry->id]]));
    $many = QueryProfiler::profile(static fn() => $gql->executeQuery($schema, $query, ['owner' => [$entry->id]]));

    // Ten comments cost no more than two, give or take one lookup.
    expect($many['queries'])->toBeLessThanOrEqual($few['queries'] + 1, json_encode($many['patterns']));
});

it('keeps REST and GraphQL replays apart, so neither answers with the other’s result', function() {
    $entry = CommentsFixtureFactory::entry('API replay transports owner');
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getConversations()->clearCaches();
    dataApiAs(CommentsFixtureFactory::user('apiReplayTransports'));
    $operationId = craft\helpers\StringHelper::UUID();
    [$rest] = dataApi()->once('create', $operationId, fn() => dataApi()->create(['ownerId' => $entry->id, 'content' => ['format' => 'plain', 'text' => 'Through REST']]));
    $schema = new craft\models\GqlSchema(['name' => 'Replay', 'scope' => ['comments:read', 'comments:edit']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $result = $gql->executeQuery($schema, 'mutation($owner: ID, $op: String) { createComment(ownerId: $owner, comment: "Through GraphQL", operationId: $op) { id } }', ['owner' => $entry->id, 'op' => $operationId]);

    expect($result['errors'] ?? null)->toBeNull(json_encode($result['errors'] ?? null))
        ->and($rest['comment']['content']['text'])->toBe('Through REST')
        ->and((int)Comment::find()->ownerId($entry->id)->count())->toBe(2);
});

// SD-45: blocked commenters and closed conversations
// =========================================================================

it('stops a blocked commenter voting, reporting and following, through REST and GraphQL (SD-45)', function() {
    $entry = CommentsFixtureFactory::entry('API blocked engagement owner');
    $blocked = CommentsFixtureFactory::user('apiBlockedEngager');
    $own = dataApiPost($entry, 'Before the block', $blocked);
    $target = dataApiPost($entry, 'Someone else');
    Comments::$plugin->getModeration()->blockIdentity((int)$own->identityId, null, dataApiAdmin(), 'Enough');
    Comments::$plugin->getSettings()->subscriptionTypes = ['conversation'];
    dataApiAs($blocked);
    $viewer = dataApi()->viewer($entry);

    expect($viewer['viewer'])->toMatchArray(['canVote' => false, 'canReport' => false, 'blocked' => true])
        ->and($viewer['viewer']['follow']['available'])->toBeFalse()
        ->and($viewer['comments'][$target->uid])->toMatchArray(['canVote' => false, 'canReport' => false])
        ->and(dataApiError(fn() => dataApi()->vote(['uid' => $target->uid, 'vote' => 'upvote']))->errorCode)->toBe('blocked')
        ->and(dataApiError(fn() => dataApi()->report(['uid' => $target->uid, 'reason' => 'spam']))->errorCode)->toBe('blocked')
        ->and(dataApiError(fn() => dataApi()->follow(['ownerId' => $entry->id, 'following' => true]))->errorCode)->toBe('blocked')
        ->and(dataApi()->follow(['ownerId' => $entry->id, 'following' => false])['viewer']['follow']['following'])->toBeFalse()
        ->and(dataApi()->errorData(dataApiError(fn() => dataApi()->vote(['uid' => $target->uid, 'vote' => 'upvote'])))['errors'][0]['category'])->toBe('policy');

    $schema = new craft\models\GqlSchema(['name' => 'Blocked', 'scope' => ['comments:read', 'comments:save']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $code = static fn(array $result): ?string => $result['errors'][0]['extensions']['code'] ?? null;

    expect($code($gql->executeQuery($schema, 'mutation($uid: String) { voteComment(uid: $uid, vote: "upvote") { upvote } }', ['uid' => $target->uid])))->toBe('blocked')
        ->and($code($gql->executeQuery($schema, 'mutation($uid: String) { flagComment(uid: $uid, withdraw: false) { commentId } }', ['uid' => $target->uid])))->toBe('blocked')
        ->and($code($gql->executeQuery($schema, 'mutation($owner: ID!) { subscribeComment(ownerId: $owner, following: true) }', ['owner' => $entry->id])))->toBe('blocked')
        ->and($code($gql->executeQuery($schema, 'mutation($owner: ID!) { subscribeComment(ownerId: $owner) }', ['owner' => $entry->id])))->toBe('blocked')
        ->and(Comments::$plugin->getInteractions()->getTotals([(int)$target->id])[(int)$target->id]['upvote'])->toBe(0);
});

it('freezes voting on a closed conversation but keeps reporting and following (SD-45)', function() {
    $entry = CommentsFixtureFactory::entry('API closed engagement owner');
    $target = dataApiPost($entry, 'Report me if you must');
    Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, true);
    Comments::$plugin->getSettings()->subscriptionTypes = ['conversation'];
    dataApiAs(CommentsFixtureFactory::user('apiClosedEngager'));
    $viewer = dataApi()->viewer($entry);

    expect($viewer['viewer'])->toMatchArray(['canVote' => false, 'canReport' => true, 'canComment' => false])
        ->and($viewer['viewer']['follow']['available'])->toBeTrue()
        ->and($viewer['comments'][$target->uid])->toMatchArray(['canVote' => false, 'canDownvote' => false, 'canReport' => true, 'canReply' => false])
        ->and(dataApiError(fn() => dataApi()->vote(['uid' => $target->uid, 'vote' => 'upvote']))->errorCode)->toBe('closed')
        ->and(dataApi()->report(['uid' => $target->uid, 'reason' => 'spam'])['comment']['viewer']['reported'])->toBeTrue()
        ->and(dataApi()->follow(['ownerId' => $entry->id, 'following' => true])['viewer']['follow']['following'])->toBeTrue();

    $schema = new craft\models\GqlSchema(['name' => 'Closed', 'scope' => ['comments:read', 'comments:save']]);
    $gql = Craft::$app->getGql();
    $gql->flushCaches();
    $gql->setActiveSchema($schema);
    $vote = $gql->executeQuery($schema, 'mutation($uid: String) { voteComment(uid: $uid, vote: "upvote") { upvote } }', ['uid' => $target->uid]);
    $other = dataApiPost(CommentsFixtureFactory::entry('API closed other owner'), 'Open elsewhere');
    Comments::$plugin->getReports()->withdrawOpen((int)$target->id, (int)Comments::$plugin->getIdentities()->findForUser((int)Craft::$app->getUser()->getIdentity()->id)->id);
    $flag = $gql->executeQuery($schema, 'mutation($uid: String) { flagComment(uid: $uid, withdraw: false) { commentId } }', ['uid' => $target->uid]);

    expect($vote['errors'][0]['extensions']['code'] ?? null)->toBe('closed')
        ->and($flag['errors'] ?? null)->toBeNull(json_encode($flag['errors'] ?? null))
        ->and($gql->executeQuery($schema, 'mutation($uid: String) { voteComment(uid: $uid, vote: "upvote") { upvote } }', ['uid' => $other->uid])['errors'] ?? null)->toBeNull();
});
