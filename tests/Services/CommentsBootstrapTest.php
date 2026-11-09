<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;

it('boots Comments and can create a threaded comment tree', function() {
    expect(Comments::$plugin)->not->toBeNull();

    $entry = CommentsFixtureFactory::entry();
    $root = CommentsFixtureFactory::thread($entry, 2);

    $comments = Comment::find()
        ->ownerId($entry->id)
        ->status(null)
        ->all();

    expect($comments)->toHaveCount(2);

    $reloaded = Comment::find()->id($root->id)->one();
    expect($reloaded)->not->toBeNull();
    expect((int)$reloaded->getChildren()->count())->toBe(1);
});
