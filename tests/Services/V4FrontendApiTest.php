<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\models\CommentSubmission;

/**
 * Comment services the public thread relies on, whatever draws it: reply previews, an author removing
 * their own comment and a report's reason. The HTML fragment endpoints these once sat beside went with
 * the switch-over, and the data API's tests cover what replaced them.
 */

it('batches reply previews for multiple parents', function() {
    $entry = CommentsFixtureFactory::entry('API reply preview batch');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $roots = [];
    foreach (['Root A', 'Root B'] as $content) {
        $roots[] = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $content,
            'userId' => (int)$admin->id,
        ]))->comment;
    }

    foreach ($roots as $root) {
        Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'parentId' => (int)$root->id,
            'content' => 'Reply to ' . $root->comment,
            'userId' => (int)$admin->id,
        ]));
    }

    $map = Comments::$plugin->getConversations()->getReplyPreviewMap(
        array_map(static fn($root) => (int)$root->id, $roots),
    );

    expect($map)->toHaveCount(2);
    expect($map[(int)$roots[0]->id]['total'])->toBe(1);
    expect($map[(int)$roots[0]->id]['replies'])->toHaveCount(1);
});

it('author-removes a comment through the comments service', function() {
    $entry = CommentsFixtureFactory::entry('API author remove');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Delete me',
        'userId' => (int)$admin->id,
    ]))->comment;

    $removed = Comments::$plugin->getComments()->authorRemove((int)$comment->id);

    expect($removed->authorRemovedAt)->not->toBeNull();
});

it('stores report reason and details through Reports::toggle()', function() {
    $entry = CommentsFixtureFactory::entry('API report reason owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Report target',
        'userId' => (int)$admin->id,
    ]))->comment;

    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($admin);
    Comments::$plugin->getReports()->toggle(
        (int)$comment->id,
        (int)$identity->id,
        'spam',
        'Automated test report',
    );

    $open = Comments::$plugin->getReports()->getOpenForIdentity((int)$comment->id, (int)$identity->id);
    expect($open)->not->toBeNull();
    expect($open->reason)->toBe('spam');
    expect($open->details)->toBe('Automated test report');
});
