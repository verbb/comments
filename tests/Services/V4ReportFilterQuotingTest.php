<?php

declare(strict_types=1);

use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;

/**
 * The open-report filters join a derived table of reported comment IDs. Its column is quoted, so the
 * filters work on PostgreSQL, which folds an unquoted `commentId` to `commentid`.
 */

it('quotes the reported comment column in the open-report filters', function() {
    $quoted = 'SELECT DISTINCT ' . Craft::$app->getDb()->quoteColumnName('commentId') . ' FROM';

    foreach ([
        Comment::find()->hasOpenReports(true),
        Comment::find()->hasOpenReports(false),
        Comment::find()->isFlagged(true),
        Comment::find()->isFlagged(false),
    ] as $query) {
        expect($query->status(null)->createCommand()->getRawSql())->toContain($quoted);
    }
});

it('finds comments with and without open reports', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $entry = CommentsFixtureFactory::entry('Report filter owner');
    $author = CommentsFixtureFactory::user('reportFilterAuthor');
    $post = static fn(string $content): Comment => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)$author->id,
    ]))->comment;
    $reported = $post('Reported');
    $quiet = $post('Not reported');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$reported->id, (int)$reporter->id, 'spam');

    $ids = static fn($query): array => array_map('intval', $query->ownerId($entry->id)->status(null)->ids());

    expect($ids(Comment::find()->hasOpenReports(true)))->toBe([(int)$reported->id])
        ->and($ids(Comment::find()->isFlagged(true)))->toBe([(int)$reported->id])
        ->and($ids(Comment::find()->hasOpenReports(false)))->toBe([(int)$quiet->id])
        ->and($ids(Comment::find()->isFlagged(false)))->toBe([(int)$quiet->id]);
});
