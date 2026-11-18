<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Fixtures\PerformanceCorpusFixture;
use Tests\Support\Performance\QueryProfiler;
use verbb\comments\Comments;

/**
 * Gate 2's budget for the uncacheable `viewer` read: a fixed number of queries and a p95 under 100 ms
 * against a 50,000-comment conversation, for a signed-in member and a guest with no session.
 */
it('answers viewer within the gate 2 budget on a 50,000-comment conversation', function() {
    $entry = CommentsFixtureFactory::entry('Perf viewer owner');
    PerformanceCorpusFixture::seedLarge($entry, 50_000);
    $api = Comments::$plugin->getApi();
    $member = CommentsFixtureFactory::user('perfViewer');
    $results = [];

    foreach (['member' => $member, 'slim guest' => null] as $label => $user) {
        Craft::$app->getUser()->setIdentity($user ? Craft::$app->getUsers()->getUserById((int)$user->id) : null);
        $timings = [];
        $queries = 0;

        for ($i = 0; $i < 30; $i++) {
            Comments::$plugin->getConversations()->clearCaches();
            Comments::$plugin->getIdentities()->clearCaches();
            $profile = QueryProfiler::profile(static fn() => $api->viewer($api->resolveOwner($entry->id, null, null), slim: $user === null));
            $timings[] = $profile['durationMs'];
            $queries = $profile['queries'];
        }

        sort($timings);
        $results[$label] = ['p95' => $timings[(int)ceil(0.95 * count($timings)) - 1], 'median' => $timings[14], 'queries' => $queries];
    }

    fwrite(STDERR, 'viewer on 50,000 comments: ' . json_encode($results) . "\n");

    expect($results['member']['queries'])->toBeLessThanOrEqual(15)
        ->and($results['slim guest']['queries'])->toBeLessThanOrEqual(5)
        ->and($results['member']['p95'])->toBeLessThan(100)
        ->and($results['slim guest']['p95'])->toBeLessThan(100);
})->group('perf-large');
