<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\models\CommentSubmission;

/**
 * The Comments page explains how to add comments to templates until the first comment exists, and a trashed test
 * comment doesn't bring that page back.
 */

it('shows the first-run page until the first comment, and keeps it away once that comment is trashed', function() {
    $comments = Comments::$plugin->getComments();

    expect($comments->hasAnyComment())->toBeFalse();

    Comments::$plugin->getSettings()->requireModeration = false;
    $entry = CommentsFixtureFactory::entry('First-run owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $comment = $comments->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'The first comment',
        'userId' => (int)$admin->id,
    ]))->comment;

    expect($comments->hasAnyComment())->toBeTrue();

    expect(Craft::$app->getElements()->deleteElement($comment))->toBeTrue();
    expect($comments->hasAnyComment())->toBeTrue();
});
