<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Fixtures\PerformanceCorpusFixture;
use Tests\Support\Performance\QueryProfiler;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;

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

it('profiles the data API’s thread read query count for 50 canonical roots', function() {
    $entry = CommentsFixtureFactory::entry('Perf conversation view owner');
    CommentsFixtureFactory::conversationWithRoots($entry, 50);
    Craft::$app->getCache()->flush();

    $profile = QueryProfiler::profile(static fn() => Comments::$plugin->getApi()->thread($entry, null, 50));

    // Reply previews, authors, report counts and vote totals are each fetched once for the page.
    expect($profile['queries'])->toBeLessThanOrEqual(20);
    expect($profile['duplicatePatterns'])->toBe(0);
    expect($profile['durationMs'])->toBeLessThan(10000);
})->group('perf');

it('keeps authors’ photos, report counts and reply checks batched for a moderator', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $handle = 'perfPhotos' . bin2hex(random_bytes(4));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local(['name' => $handle, 'handle' => $handle, 'path' => $path, 'hasUrls' => true, 'url' => 'https://assets.example.test/' . $handle . '/']);
    expect(Craft::$app->getFs()->saveFilesystem($filesystem))->toBeTrue();
    $volume = new craft\models\Volume(['name' => $handle, 'handle' => $handle, 'fsHandle' => $handle]);
    expect(Craft::$app->getVolumes()->saveVolume($volume))->toBeTrue();

    try {
        $entry = CommentsFixtureFactory::entry('Perf photos owner');

        // Ten authors with photos, two comments each, read by the admin, who moderates.
        for ($i = 0; $i < 10; $i++) {
            $file = tempnam(sys_get_temp_dir(), 'comments-perf-photo-');
            file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
            $photo = new craft\elements\Asset([
                'tempFilePath' => $file,
                'filename' => "face{$i}.png",
                'newFolderId' => Craft::$app->getAssets()->getRootFolderByVolumeId((int)$volume->id)->id,
                'volumeId' => $volume->id,
                'avoidFilenameConflicts' => true,
            ]);
            $photo->setScenario(craft\elements\Asset::SCENARIO_CREATE);
            expect(Craft::$app->getElements()->saveElement($photo))->toBeTrue();

            $author = CommentsFixtureFactory::user('perfPhotoAuthor');
            Craft::$app->getDb()->createCommand()->update(craft\db\Table::USERS, ['photoId' => $photo->id], ['id' => $author->id])->execute();

            foreach ([1, 2] as $n) {
                Comments::$plugin->getComments()->create(new CommentSubmission([
                    'ownerId' => $entry->id,
                    'siteId' => $entry->siteId,
                    'content' => "Comment {$n} by author {$i}",
                    'userId' => (int)$author->id,
                ]));
            }
        }

        // Reply checks count the viewer's comments when this is set.
        $settings->maxUserComments = 100;
        Comments::$plugin->getConversations()->clearCaches();

        Craft::$app->getCache()->flush();
        $api = Comments::$plugin->getApi();
        // The page as a moderator reads it: the public thread, then their private `viewer` read.
        $thread = QueryProfiler::profile(static fn() => $api->thread($entry, null, 50));
        $viewer = QueryProfiler::profile(static fn() => $api->viewer($entry, null, 50));
        $matching = static fn(array $profile, string $needle): int => array_sum(array_filter($profile['patterns'], static fn(string $sql): bool => str_contains($sql, $needle), ARRAY_FILTER_USE_KEY));

        // One query loads every author's photo, and each author's avatar URL is built once rather than
        // once per comment, because every transformed URL looks up the transform index.
        expect($matching($thread, 'INNER JOIN `assets` `assets`'))->toBe(1)
            ->and($matching($thread, 'FROM `imagetransformindex` WHERE'))->toBe(10)
            // Moderators' Keep and Remove use the report counts loaded with the comments.
            ->and($matching($viewer, 'FROM `comments_reports` WHERE (`commentId`=?)'))->toBe(0)
            // Whether the viewer can comment counts their comments once for the page, not once per comment.
            ->and($matching($viewer, '`comments_comments`.`userId`'))->toBeLessThanOrEqual(3);
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
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
