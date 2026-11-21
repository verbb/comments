<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Moderation;

/** The comment IDs a Comments index source lists for one owner. */
function authorDeletedSourceIds(string $key, int $ownerId): array
{
    $sources = array_column(array_filter(Comment::sources('index'), static fn(array $source): bool => isset($source['key'])), null, 'key');
    $query = Comment::find()->status(null)->ownerId($ownerId);
    Craft::configure($query, $sources[$key]['criteria'] ?? []);

    return array_map('intval', $query->ids());
}

/** The queue counts as a new request reads them, without this request's memo. */
function authorDeletedCounts(): array
{
    return (new Moderation())->getQueueCounts();
}

function authorDeletedCreate($owner, string $text): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'content' => $text,
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
    ]))->comment;
}

it('takes a pending comment its author deleted out of Needs review, its counts and the entry panel', function() {
    $owner = CommentsFixtureFactory::entry('Author-deleted queue owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $previous = $settings->requireModeration;
    $settings->requireModeration = true;

    try {
        $waiting = authorDeletedCreate($owner, 'Still waiting for a moderator');
        $deleted = authorDeletedCreate($owner, 'Deleted by its author while waiting');

        expect($waiting->moderationState)->toBe(ModerationState::Pending->value)
            ->and($deleted->moderationState)->toBe(ModerationState::Pending->value)
            ->and(authorDeletedSourceIds('needs-review', (int)$owner->id))->toContain((int)$deleted->id);

        $before = authorDeletedCounts()['pending'];
        Comments::$plugin->getComments()->authorRemove((int)$deleted->id, (int)$owner->siteId, $admin);

        expect(authorDeletedCounts()['pending'])->toBe($before - 1)
            ->and(authorDeletedSourceIds('needs-review', (int)$owner->id))->toBe([(int)$waiting->id]);

        $summary = Comments::$plugin->getOwnerElement()->getFieldSummary((int)$owner->id, (int)$owner->siteId);

        expect($summary['counts']['pending'])->toBe(1)
            ->and(array_map(static fn(array $row): int => (int)$row['comment']->id, $summary['waiting']))->toBe([(int)$waiting->id]);

        // It's still under All, and findable with its own status.
        expect(authorDeletedSourceIds('*', (int)$owner->id))->toContain((int)$deleted->id)
            ->and(Comment::find()->status(Comment::STATUS_AUTHOR_REMOVED)->ownerId((int)$owner->id)->ids())->toContain($deleted->id);
    } finally {
        $settings->requireModeration = $previous;
    }
});

it('keeps a reported comment its author deleted under Reported', function() {
    $owner = CommentsFixtureFactory::entry('Author-deleted reported owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $previous = $settings->requireModeration;
    $settings->requireModeration = false;

    try {
        $comment = authorDeletedCreate($owner, 'Reported, then deleted by its author');
        $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($admin);
        Comments::$plugin->getReports()->create((int)$comment->id, (int)$identity->id, 'other');
        $before = authorDeletedCounts()['reported'];

        Comments::$plugin->getComments()->authorRemove((int)$comment->id, (int)$owner->siteId, $admin);

        expect(authorDeletedCounts()['reported'])->toBe($before)
            ->and(authorDeletedSourceIds('reported', (int)$owner->id))->toBe([(int)$comment->id]);
    } finally {
        $settings->requireModeration = $previous;
    }
});
