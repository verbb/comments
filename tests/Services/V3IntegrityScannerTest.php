<?php
use craft\db\Table;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\V3Site;
use verbb\comments\migrations\support\V3IntegrityScanner;

beforeEach(function() {
    V3Site::downgrade();
});

afterEach(function() {
    V3Site::restore();
});

it('accepts a healthy v3 site', function() {
    $entry = CommentsFixtureFactory::entry('Healthy owner');
    $root = V3Site::comment(['ownerId' => $entry->id]);
    $reply = V3Site::comment(['ownerId' => $entry->id]);

    // Craft removes a trashed element from its Structure, so this has no node on a healthy site.
    V3Site::comment(['ownerId' => $entry->id], '2024-03-01 00:00:00');

    V3Site::tree([$root => [$reply => []]]);
    V3Site::row('{{%comments_votes}}', ['commentId' => $root, 'sessionId' => 's1', 'upvote' => true]);

    $report = V3IntegrityScanner::scan(Craft::$app->getDb());

    expect($report->issues)->toBe([])
        ->and($report->summary['comments'])->toBe(3)
        ->and($report->summary['votes'])->toBe(1);
});

it('treats damaged data as warnings and reports it without sensitive detail', function() {
    $db = Craft::$app->getDb();
    $entry = CommentsFixtureFactory::entry('Damaged owner');
    $root = V3Site::comment(['ownerId' => $entry->id, 'email' => 'guest@example.test', 'ipAddress' => '203.0.113.9']);
    $reply = V3Site::comment(['ownerId' => $entry->id]);
    $noNode = V3Site::comment(['ownerId' => $entry->id]);
    $orphan = V3Site::comment(['ownerId' => null]);
    $unknown = V3Site::comment(['ownerId' => $entry->id, 'status' => null]);

    V3Site::tree([$root => [$reply => []], $orphan => [], $unknown => []]);

    $db->createCommand()->update(Table::STRUCTUREELEMENTS, ['lft' => 99], ['elementId' => $reply])->execute();

    $report = V3IntegrityScanner::scan($db);

    expect($report->countByCode('owner.missing'))->toBe(1)
        ->and($report->countByCode('hierarchy.invalidBounds'))->toBe(1)
        ->and($report->countByCode('hierarchy.nodeMissing'))->toBe(1)
        ->and($report->countByCode('status.unknown'))->toBe(1)
        ->and($report->hasErrors())->toBeFalse();

    expect(json_encode($report->toArray(), JSON_THROW_ON_ERROR))
        ->not->toContain('guest@example.test')
        ->not->toContain('203.0.113.9');
});

it('reports a missing structure and an unexpected schema', function() {
    $db = Craft::$app->getDb();
    $entry = CommentsFixtureFactory::entry('No nodes owner');
    V3Site::comment(['ownerId' => $entry->id]);

    expect(V3IntegrityScanner::scan($db)->countByCode('hierarchy.structureMissing'))->toBe(1);

    $db->createCommand()->dropTable('{{%comments_flags}}')->execute();
    $db->getSchema()->refresh();

    $report = V3IntegrityScanner::scan($db);

    expect($report->hasErrors())->toBeTrue()
        ->and($report->countByCode('schema.tableMissing'))->toBe(1);
});
