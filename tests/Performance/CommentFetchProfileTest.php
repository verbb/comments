<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Fixtures\PerformanceCorpusFixture;
use Tests\Support\Performance\QueryProfiler;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;

it('profiles fetching comments for an owner element', function() {
    $entry = CommentsFixtureFactory::entry();
    CommentsFixtureFactory::manyRoots($entry, 50);

    $profile = QueryProfiler::profile(static function() use ($entry) {
        return Comment::find()
            ->ownerId($entry->id)
            ->status(null)
            ->all();
    });

    expect($profile['resultSize'])->toBe(50);
    expect($profile['queries'])->toBeLessThanOrEqual(8);
    expect($profile['duplicatePatterns'])->toBe(0);

    // Element hydration, reports, interactions, users and owners must stay batched.
    expect($profile['durationMs'])->toBeLessThan(5000);
})->group('perf');

it('profiles a normal 500-comment owner query', function() {
    $entry = CommentsFixtureFactory::entry();
    PerformanceCorpusFixture::seedLarge($entry, 500);

    $profile = QueryProfiler::profile(static function() use ($entry) {
        return Comment::find()
            ->ownerId($entry->id)
            ->status(null)
            ->all();
    });

    expect($profile['resultSize'])->toBe(500);
    expect($profile['queries'])->toBeGreaterThan(0);
    expect($profile['durationMs'])->toBeLessThan(15000);
})->group('perf-large');

it('profiles rendering comments markup for an owner', function() {
    $entry = CommentsFixtureFactory::entry();
    CommentsFixtureFactory::thread($entry, 3);

    $profile = QueryProfiler::profile(static function() use ($entry) {
        return (string)Comments::$plugin->getComments()->render($entry->id);
    });

    expect($profile['resultType'])->toBe('string');
    expect($profile['queries'])->toBeGreaterThan(0);
    expect($profile['durationMs'])->toBeLessThan(10000);
})->group('perf');

it('profiles Frontend::buildConversationView query count for 50 canonical roots', function() {
    $entry = CommentsFixtureFactory::entry('Perf conversation view owner');
    CommentsFixtureFactory::conversationWithRoots($entry, 50);

    $profile = QueryProfiler::profile(static function() use ($entry) {
        return Comments::$plugin->getFrontend()->buildConversationView(
            (int)$entry->id,
            (int)$entry->siteId,
            null,
            50,
        );
    });

    expect($profile['resultSize'])->toBeNull();
    expect($profile['queries'])->toBeLessThanOrEqual(120);
    // Reply previews batch via Conversations::getReplyPreviewMap(); avatar/author lookups remain per comment.
    expect($profile['duplicatePatterns'])->toBeLessThanOrEqual(100);
    expect($profile['durationMs'])->toBeLessThan(10000);
})->group('perf');

it('profiles a 10k-comment owner query using the direct corpus fixture', function() {
    $entry = CommentsFixtureFactory::entry('Perf large owner');
    PerformanceCorpusFixture::seedLarge($entry, 10_000);

    // Direct-insert corpora are validated at the SQL layer; Element hydration is out of scope here.
    $profile = QueryProfiler::profile(static function() use ($entry) {
        return (int)(new \craft\db\Query())
            ->from('{{%comments_comments}}')
            ->where(['ownerId' => $entry->id])
            ->count();
    });

    expect($profile['resultSize'])->toBe(10_000);
    expect($profile['queries'])->toBeGreaterThan(0);
    expect($profile['durationMs'])->toBeLessThan(30000);
})->group('perf-large');
