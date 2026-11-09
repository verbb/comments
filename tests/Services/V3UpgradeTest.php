<?php
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\models\UserGroup;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\V3Site;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\IdentityType;
use verbb\comments\migrations\support\V3Schema;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\services\Moderation;

/**
 * Runs the Comments 3 upgrade through Craft's migrator against the real v3 schema.
 */

beforeEach(function() {
    V3Site::downgrade();
});

afterEach(function() {
    V3Site::restore();
});

function upgradeLogCount(string $code): int
{
    return V3UpgradeLog::countFor(Craft::$app->getDb(), $code) ?? 0;
}

function commentRow(int $id): array
{
    return (new Query())->from('{{%comments_comments}}')->where(['id' => $id])->one();
}

function grantDirectly($user, string $permission): void
{
    // v3 stored these as flat rows; saving through Craft now would filter them against v4's nesting.
    $db = Craft::$app->getDb();
    $db->createCommand()->upsert(Table::USERPERMISSIONS, ['name' => strtolower($permission)], false)->execute();

    $db->createCommand()->insert(Table::USERPERMISSIONS_USERS, [
        'permissionId' => (new Query())->select(['id'])->from(Table::USERPERMISSIONS)->where(['name' => strtolower($permission)])->scalar(),
        'userId' => $user->id,
    ])->execute();
}

function legacyGroup(string $handle): UserGroup
{
    // Creating a user first moves the test app onto an edition with user groups.
    CommentsFixtureFactory::user($handle . 'Setup');

    $group = new UserGroup(['name' => $handle, 'handle' => $handle . uniqid()]);
    Craft::$app->getUserGroups()->saveGroup($group);
    Craft::$app->getProjectConfig()->set('users.groups.' . $group->uid . '.permissions', ['comments-edit']);

    return $group;
}

function tableCount(string $table): int
{
    return (int)(new Query())->from($table)->count();
}

it('upgrades a representative site without losing anything', function() {
    $db = Craft::$app->getDb();
    $entry = CommentsFixtureFactory::entry('Upgrade owner');
    $quietEntry = CommentsFixtureFactory::entry('Followed but never commented on');
    $author = CommentsFixtureFactory::user('upgradeAuthor');
    $voter = CommentsFixtureFactory::user('upgradeVoter');
    $subscriber = CommentsFixtureFactory::user('upgradeSubscriber');
    $owner = ['ownerId' => $entry->id];

    $root = V3Site::comment($owner + ['userId' => $author->id, 'comment' => 'Root with “unicode” ✓']);
    $reply = V3Site::comment($owner + ['userId' => $author->id, 'comment' => 'Reply']);
    $deep = V3Site::comment($owner + ['name' => 'Guest One', 'email' => 'one@example.test', 'comment' => 'Deep reply']);
    $sibling = V3Site::comment($owner + ['name' => 'Guest One', 'email' => 'one@example.test', 'comment' => 'Sibling']);
    $pending = V3Site::comment($owner + ['status' => 'pending']);
    $spam = V3Site::comment($owner + ['status' => 'spam']);
    $removed = V3Site::comment($owner + ['status' => 'trashed']);
    $softDeleted = V3Site::comment($owner + ['comment' => 'In the trash'], '2024-03-01 00:00:00');
    $gif = V3Site::comment($owner + ['comment' => null, 'gifUrl' => 'https://example.test/a.gif']);
    $orphan = V3Site::comment(['ownerId' => null, 'comment' => 'Owner deleted']);

    V3Site::tree([
        $root => [$reply => [$deep => []], $sibling => []],
        $pending => [],
        $spam => [],
        $removed => [],
        $softDeleted => [],
        $gif => [],
        $orphan => [],
    ]);

    $vote = fn(array $attributes) => V3Site::row('{{%comments_votes}}', $attributes);
    $vote(['commentId' => $root, 'userId' => $author->id, 'sessionId' => 's-author', 'upvote' => true, 'lastIp' => '203.0.113.9']);
    $vote(['commentId' => $root, 'userId' => $voter->id, 'sessionId' => 's-voter', 'downvote' => true]);
    $vote(['commentId' => $reply, 'userId' => $voter->id, 'sessionId' => 's-voter', 'upvote' => null, 'downvote' => null]);
    $vote(['commentId' => $root, 'sessionId' => 's-guest', 'upvote' => true]);
    $vote(['commentId' => $reply, 'sessionId' => 's-guest', 'upvote' => true]);
    $vote(['commentId' => $sibling, 'upvote' => true]);
    $vote(['commentId' => $sibling, 'upvote' => true]);

    $flag = fn(array $attributes) => V3Site::row('{{%comments_flags}}', $attributes);
    $flag(['commentId' => $spam, 'userId' => $voter->id, 'sessionId' => 's-voter']);
    $flag(['commentId' => $spam, 'userId' => $voter->id, 'sessionId' => 's-voter']);
    $flag(['commentId' => $spam, 'sessionId' => 's-flagger']);

    $subscribe = fn(array $attributes) => V3Site::row('{{%comments_subscribe}}', $attributes + ['ownerSiteId' => $entry->siteId]);
    $subscribe(['ownerId' => $entry->id, 'userId' => $author->id, 'subscribed' => true]);
    $subscribe(['ownerId' => $entry->id, 'userId' => $author->id, 'commentId' => $root, 'subscribed' => false]);
    $subscribe(['ownerId' => $quietEntry->id, 'userId' => $subscriber->id, 'subscribed' => true]);
    $subscribe(['ownerId' => $entry->id, 'userId' => null, 'subscribed' => true]);

    V3Site::upgrade();

    // Element IDs, dates and text all come through in place.
    $rootRow = commentRow($root);
    expect($rootRow['content'])->toBe('Root with “unicode” ✓')
        ->and($rootRow['comment'])->toBe('Root with “unicode” ✓')
        ->and($rootRow['contentFormat'])->toBe('plain')
        ->and($rootRow['commentDate'])->toBe('2024-01-15 10:00:00')
        ->and($rootRow['dateUpdated'])->toBe('2024-01-16 11:00:00')
        ->and(tableCount('{{%comments_comments}}'))->toBe(10)
        ->and(commentRow($gif)['gifUrl'])->toBe('https://example.test/a.gif');

    // Hierarchy.
    expect(commentRow($root))->toMatchArray(['parentId' => null, 'rootId' => $root, 'depth' => 0])
        ->and(commentRow($reply))->toMatchArray(['parentId' => $root, 'rootId' => $root, 'depth' => 1])
        ->and(commentRow($deep))->toMatchArray(['parentId' => $reply, 'rootId' => $root, 'depth' => 2])
        ->and(commentRow($sibling))->toMatchArray(['parentId' => $root, 'rootId' => $root, 'depth' => 1]);

    // Moderation state, including a trashed comment reaching the widened status column.
    expect(commentRow($pending)['moderationState'])->toBe('pending')
        ->and(commentRow($spam)['moderationState'])->toBe('spam')
        ->and(commentRow($removed)['moderationState'])->toBe('removed')
        ->and(commentRow($removed)['status'])->toBe('removed')
        ->and(commentRow($softDeleted)['moderationState'])->toBe('approved');

    expect((new Query())->from('{{%comments_moderationactions}}')->where(['action' => Moderation::ACTION_IMPORT])->count())->toEqual(3);

    // Orphans survive without a fake Conversation, and they're reported.
    expect(commentRow($orphan)['conversationId'])->toBeNull()
        ->and(commentRow($orphan)['identityId'])->not->toBeNull()
        ->and(upgradeLogCount('owner.missing'))->toBe(1);

    // Conversations, including the one with a subscriber but no comments.
    expect(tableCount('{{%comments_conversations}}'))->toBe(2)
        ->and((new Query())->from('{{%comments_conversations}}')->where(['ownerId' => $entry->id])->one()['commentCount'])
        // Root, reply, deep, sibling and the GIF; held, removed and trashed comments don't count.
        ->toEqual(5);

    // Guests aren't merged, even with identical names and addresses.
    expect(commentRow($deep)['identityId'])->not->toBe(commentRow($sibling)['identityId']);

    $identity = (new Query())->from('{{%comments_identities}}')->where(['id' => commentRow($deep)['identityId']])->one();
    expect($identity)->toMatchArray([
        'type' => IdentityType::Guest->value,
        'name' => 'Guest One',
        'email' => 'one@example.test',
        'guestTokenHash' => null,
    ]);

    // Votes: withdrawn rows are dropped, sessions share an Identity, and anonymous rows stay distinct.
    $votes = (new Query())->select(['commentId', 'type'])->from('{{%comments_interactions}}')->all();
    expect($votes)->toHaveCount(6)
        ->and(array_count_values(array_column($votes, 'type')))->toEqual(['upvote' => 5, 'downvote' => 1]);

    $guestVoterIds = (new Query())
        ->select(['identityId'])
        ->from('{{%comments_interactions}}')
        ->where(['commentId' => [$root, $reply]])
        ->andWhere(['not', ['identityId' => [
            (new Query())->select(['id'])->from('{{%comments_identities}}')->where(['userId' => $author->id])->scalar(),
            (new Query())->select(['id'])->from('{{%comments_identities}}')->where(['userId' => $voter->id])->scalar(),
        ]]])
        ->column();
    expect(array_unique($guestVoterIds))->toHaveCount(1);

    // Flags keep their reporter, duplicates collapse, and a guest flag has an attributed reporter.
    $reports = (new Query())->from('{{%comments_reports}}')->all();
    expect($reports)->toHaveCount(2);

    foreach ($reports as $report) {
        expect($report['identityId'])->not->toBeNull()
            ->and($report['status'])->toBe('open')
            ->and($report['reason'])->toBe('legacy');
    }

    // Subscriptions: the opt-out holds, and someone who never commented keeps theirs.
    expect(tableCount('{{%comments_subscriptions}}'))->toBe(3)
        ->and((new Query())->from('{{%comments_subscriptions}}')->where(['subscribed' => false, 'commentId' => $root])->exists())->toBeTrue()
        ->and((new Query())->from('{{%comments_identities}}')->where(['userId' => $subscriber->id])->exists())->toBeTrue()
        ->and(upgradeLogCount('subscription.undeliverable'))->toBe(1);

    // Source tables are archived, not dropped, and no longer hold addresses.
    foreach (V3Schema::ARCHIVE_TABLES as $table => $archive) {
        expect($db->tableExists($table, true))->toBeFalse()
            ->and($db->tableExists($archive, true))->toBeTrue();
    }

    expect(tableCount('{{%comments_v3_votes}}'))->toBe(7)
        ->and(tableCount('{{%comments_v3_flags}}'))->toBe(3)
        ->and(tableCount('{{%comments_v3_subscribe}}'))->toBe(4)
        ->and($db->columnExists('{{%comments_v3_votes}}', 'lastIp', true))->toBeFalse()
        ->and($db->tableExists(V3Schema::MAP, true))->toBeFalse();

    // Deleting a comment doesn't reach into the archive.
    expect($db->getTableSchema('{{%comments_v3_votes}}', true)->foreignKeys)->toBe([]);

    // The Structure goes, its nodes kept.
    expect((new Query())->from(Table::STRUCTURES)->where(['id' => V3Site::structureId()])->exists())->toBeFalse()
        ->and((new Query())->from(Table::STRUCTUREELEMENTS)->where(['structureId' => V3Site::structureId()])->exists())->toBeFalse()
        ->and(tableCount(V3Schema::ARCHIVE_STRUCTURE))->toBe(11)
        ->and(Craft::$app->getProjectConfig()->get('plugins.comments.settings.structureUid'))->toBeNull();

    // Migrated data is readable through the real element, and nothing is pending.
    $element = Comment::find()->id($root)->status(null)->one();
    expect($element)->toBeInstanceOf(Comment::class)
        ->and($element->getParent())->toBeNull()
        ->and(Comments::$plugin->getMigrator()->getNewMigrations())->toBe([])
        ->and(Craft::$app->getProjectConfig()->get('plugins.comments.schemaVersion'))->toBe(Comments::$plugin->schemaVersion);

    expect($db->getTableSchema('{{%comments_comments}}', true)->getColumn('status')->allowNull)->toBeFalse();
});

it('lets garbage collection run while the upgrade is still pending', function() {
    $entry = CommentsFixtureFactory::entry('Pending GC owner');
    $comment = V3Site::comment(['ownerId' => $entry->id]);
    V3Site::tree([$comment => []]);

    ob_start();

    try {
        Craft::$app->getGc()->run(true);
    } finally {
        ob_end_clean();
    }

    expect(commentRow($comment)['status'])->toBe('approved');
});

it('completes the upgrade through the Craft updater path too', function() {
    $entry = CommentsFixtureFactory::entry('Updater owner');
    $trashed = V3Site::comment(['ownerId' => $entry->id, 'status' => 'trashed']);
    V3Site::tree([$trashed => []]);

    ob_start();

    try {
        Craft::$app->getUpdates()->runMigrations(['comments']);
    } finally {
        ob_end_clean();
    }

    expect(commentRow($trashed)['status'])->toBe('removed')
        ->and(Comments::$plugin->getMigrator()->getNewMigrations())->toBe([]);
});

it('finds threading even when the configured Structure is stale', function() {
    $entry = CommentsFixtureFactory::entry('Stale structure owner');
    $root = V3Site::comment(['ownerId' => $entry->id]);
    $reply = V3Site::comment(['ownerId' => $entry->id]);

    V3Site::tree([$root => [$reply => []]]);
    Craft::$app->getProjectConfig()->set('plugins.comments.settings.structureUid', 'not-a-real-structure');

    V3Site::upgrade();

    expect(commentRow($reply))->toMatchArray(['parentId' => $root, 'rootId' => $root, 'depth' => 1])
        ->and(upgradeLogCount('hierarchy.structureUnresolved'))->toBe(1);
});

it('warns rather than losing threading silently when there is no hierarchy at all', function() {
    $entry = CommentsFixtureFactory::entry('No structure owner');
    $first = V3Site::comment(['ownerId' => $entry->id]);
    $second = V3Site::comment(['ownerId' => $entry->id]);

    V3Site::upgrade();

    expect(commentRow($first))->toMatchArray(['parentId' => null, 'rootId' => $first, 'depth' => 0])
        ->and(commentRow($second))->toMatchArray(['parentId' => null, 'rootId' => $second, 'depth' => 0])
        ->and(upgradeLogCount('hierarchy.structureMissing'))->toBe(2)
        ->and(Comments::$plugin->getMigrator()->getNewMigrations())->toBe([]);
});

it('keeps separate trees apart when their nested-set values overlap', function() {
    $entry = CommentsFixtureFactory::entry('Multi-tree owner');
    $firstRoot = V3Site::comment(['ownerId' => $entry->id]);
    $firstReply = V3Site::comment(['ownerId' => $entry->id]);
    $secondRoot = V3Site::comment(['ownerId' => $entry->id]);
    $secondReply = V3Site::comment(['ownerId' => $entry->id]);

    V3Site::tree([$firstRoot => [$firstReply => []]]);
    V3Site::tree([$secondRoot => [$secondReply => []]]);

    V3Site::upgrade();

    expect(commentRow($firstReply)['parentId'])->toEqual($firstRoot)
        ->and(commentRow($secondReply)['parentId'])->toEqual($secondRoot)
        ->and(commentRow($secondRoot)['parentId'])->toBeNull();
});

it('attaches a reply to its nearest surviving ancestor when v3 lost the one between', function() {
    $entry = CommentsFixtureFactory::entry('Gap owner');
    $root = V3Site::comment(['ownerId' => $entry->id]);
    $gone = V3Site::comment(['ownerId' => $entry->id]);
    $reply = V3Site::comment(['ownerId' => $entry->id]);

    V3Site::tree([$root => [$gone => [$reply => []]]]);
    Craft::$app->getDb()->createCommand()->delete(Table::ELEMENTS, ['id' => $gone])->execute();

    V3Site::upgrade();

    expect(commentRow($reply))->toMatchArray(['parentId' => $root, 'rootId' => $root, 'depth' => 1]);
});

it('detaches a reply whose parent belongs to a different element, and says so', function() {
    $first = CommentsFixtureFactory::entry('Cross owner one');
    $second = CommentsFixtureFactory::entry('Cross owner two');
    $root = V3Site::comment(['ownerId' => $first->id]);
    $reply = V3Site::comment(['ownerId' => $second->id]);
    $nested = V3Site::comment(['ownerId' => $second->id]);

    V3Site::tree([$root => [$reply => [$nested => []]]]);

    V3Site::upgrade();

    expect(commentRow($reply))->toMatchArray(['parentId' => null, 'rootId' => $reply, 'depth' => 0])
        ->and(commentRow($nested))->toMatchArray(['parentId' => $reply, 'rootId' => $reply, 'depth' => 1])
        ->and(upgradeLogCount('hierarchy.crossOwnerParent'))->toBe(1);
});

it('resumes after failing part way, without duplicating anything', function() {
    $db = Craft::$app->getDb();
    $entry = CommentsFixtureFactory::entry('Resume owner');
    $user = CommentsFixtureFactory::user('resumeVoter');
    $guest = V3Site::comment(['ownerId' => $entry->id, 'name' => 'Guest']);
    $root = V3Site::comment(['ownerId' => $entry->id]);
    $reply = V3Site::comment(['ownerId' => $entry->id, 'userId' => $user->id]);

    V3Site::tree([$guest => [], $root => [$reply => []]]);
    V3Site::row('{{%comments_votes}}', ['commentId' => $root, 'userId' => $user->id, 'upvote' => true]);
    V3Site::row('{{%comments_votes}}', ['commentId' => $root, 'sessionId' => 's1', 'upvote' => true]);
    V3Site::row('{{%comments_flags}}', ['commentId' => $root, 'sessionId' => 's2']);

    // Prepare has to run first so the trigger's table exists; the data migration then stops at Reports.
    V3Site::upgrade(1);
    $db->createCommand("CREATE TRIGGER comments_test_fault BEFORE INSERT ON {$db->getSchema()->getRawTableName('{{%comments_reports}}')} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Simulated failure'")->execute();

    expect(fn() => V3Site::upgrade())->toThrow(Exception::class);

    // Everything before the failure is committed, and the source data is untouched.
    expect(commentRow($reply)['parentId'])->toEqual($root)
        ->and(tableCount('{{%comments_interactions}}'))->toBe(2)
        ->and(tableCount('{{%comments_votes}}'))->toBe(2)
        ->and((new Query())->from(Table::MIGRATIONS)->where(['name' => 'm400000_000002_migrate'])->exists())->toBeFalse();

    $identitiesBefore = tableCount('{{%comments_identities}}');
    $db->createCommand('DROP TRIGGER comments_test_fault')->execute();

    V3Site::upgrade();

    expect(tableCount('{{%comments_interactions}}'))->toBe(2)
        ->and(tableCount('{{%comments_reports}}'))->toBe(1)
        // Only the guest flagger's session is new.
        ->and(tableCount('{{%comments_identities}}'))->toBe($identitiesBefore + 1)
        ->and(Comments::$plugin->getMigrator()->getNewMigrations())->toBe([]);
});

it('refuses to archive anything when the migrated data does not reconcile', function() {
    $db = Craft::$app->getDb();
    $entry = CommentsFixtureFactory::entry('Reconcile owner');
    $user = CommentsFixtureFactory::user('reconcileFlagger');
    $root = V3Site::comment(['ownerId' => $entry->id]);

    V3Site::tree([$root => []]);
    V3Site::row('{{%comments_flags}}', ['commentId' => $root, 'userId' => $user->id]);

    // Prepare, data and permissions, stopping short of the cleanup.
    V3Site::upgrade(3);

    $report = (new Query())->from('{{%comments_reports}}')->one();
    $db->createCommand()->delete('{{%comments_reports}}')->execute();

    expect(fn() => V3Site::upgrade())->toThrow(Exception::class);

    expect($db->tableExists('{{%comments_flags}}', true))->toBeTrue()
        ->and(commentRow($root)['status'])->toBe('approved')
        ->and((new Query())->from(Table::STRUCTURES)->where(['id' => V3Site::structureId()])->exists())->toBeTrue()
        ->and(upgradeLogCount('reconcile.flags.mismatch'))->toBe(1);

    $db->createCommand()->insert('{{%comments_reports}}', $report)->execute();

    V3Site::upgrade();

    expect($db->tableExists('{{%comments_flags}}', true))->toBeFalse()
        ->and(Comments::$plugin->getMigrator()->getNewMigrations())->toBe([]);
});

it('picks up a cleanup that stopped between steps', function() {
    $db = Craft::$app->getDb();
    $entry = CommentsFixtureFactory::entry('Partial cleanup owner');
    $root = V3Site::comment(['ownerId' => $entry->id, 'status' => 'trashed']);

    V3Site::tree([$root => []]);
    V3Site::row('{{%comments_votes}}', ['commentId' => $root, 'sessionId' => 's1', 'upvote' => true]);
    V3Site::upgrade(3);

    // As if the run died after the votes table was renamed and status rewritten.
    $db->createCommand()->update('{{%comments_comments}}', ['status' => 'removed'], ['id' => $root])->execute();

    foreach ($db->getTableSchema('{{%comments_votes}}', true)->foreignKeys as $name => $foreignKey) {
        $db->createCommand()->dropForeignKey((string)$name, '{{%comments_votes}}')->execute();
    }

    Db::renameTable('{{%comments_votes}}', '{{%comments_v3_votes}}', $db);
    $db->getSchema()->refresh();

    V3Site::upgrade();

    expect($db->tableExists('{{%comments_flags}}', true))->toBeFalse()
        ->and($db->tableExists('{{%comments_v3_flags}}', true))->toBeTrue()
        ->and(tableCount('{{%comments_v3_votes}}'))->toBe(1)
        ->and(Comments::$plugin->getMigrator()->getNewMigrations())->toBe([]);
});

it('stops before changing anything if the source schema is not Comments 3', function() {
    $db = Craft::$app->getDb();

    V3Site::upgrade(1);
    $db->createCommand()->dropColumn('{{%comments_votes}}', 'sessionId')->execute();
    $db->getSchema()->refresh();

    expect(fn() => V3Site::upgrade())->toThrow(Exception::class);

    expect(upgradeLogCount('schema.columnMissing'))->toBe(1)
        ->and(tableCount('{{%comments_conversations}}'))->toBe(0);
});

it('grants the new permissions to direct holders even when project config is read-only', function() {
    $db = Craft::$app->getDb();
    $editor = CommentsFixtureFactory::user('legacyEditor');
    $trasher = CommentsFixtureFactory::user('legacyTrasher');
    $viewer = CommentsFixtureFactory::user('legacyViewer');
    $bystander = CommentsFixtureFactory::user('legacyBystander');

    grantDirectly($editor, 'comments-edit');
    grantDirectly($trasher, 'comments-trash');
    grantDirectly($viewer, 'accessPlugin-comments');

    Craft::$app->getProjectConfig()->readOnly = true;

    ob_start();

    try {
        Comments::$plugin->getMigrator()->up();
    } finally {
        ob_end_clean();
    }

    $held = fn($user) => (new Query())
        ->select(['p.name'])
        ->from(['p_u' => Table::USERPERMISSIONS_USERS])
        ->innerJoin(['p' => Table::USERPERMISSIONS], '[[p.id]] = [[p_u.permissionId]]')
        ->where(['p_u.userId' => $user->id])
        ->column($db);

    expect($held($editor))->toContain('comments-view')->toContain('comments-moderate')
        ->and($held($trasher))->toContain('comments-view')->not->toContain('comments-moderate')
        ->and($held($viewer))->toContain('comments-view')->not->toContain('comments-moderate')
        ->and($held($bystander))->toBe([]);

    expect(Comments::$plugin->getMigrator()->getNewMigrations())->toBe([]);
});

it('grants the new permissions to user groups through project config', function() {
    $group = legacyGroup('legacyModerators');

    try {
        V3Site::upgrade();

        $path = 'users.groups.' . $group->uid . '.permissions';
        expect(Craft::$app->getProjectConfig()->get($path))->toContain('comments-view')->toContain('comments-moderate');
    } finally {
        Craft::$app->getUserGroups()->deleteGroup($group);
    }
});

it('leaves group permissions to the deployed config once another environment has upgraded', function() {
    $group = legacyGroup('syncedModerators');
    Craft::$app->getProjectConfig()->set('plugins.comments.schemaVersion', Comments::$plugin->schemaVersion);
    V3Site::writeProjectConfig();

    try {
        V3Site::upgrade();

        $path = 'users.groups.' . $group->uid . '.permissions';
        expect(Craft::$app->getProjectConfig()->get($path))->toBe(['comments-edit']);
    } finally {
        Craft::$app->getUserGroups()->deleteGroup($group);
    }
});
