<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\elements\Comment;

it('builds repeatable wide and deep thread fixtures', function() {
    $entry = CommentsFixtureFactory::entry();
    $wideRoot = CommentsFixtureFactory::wideThread($entry, 4);
    $deepRoot = CommentsFixtureFactory::deepThread($entry, 4);

    $wideRoot = Comment::find()->id($wideRoot->id)->status(null)->one();
    $deepRoot = Comment::find()->id($deepRoot->id)->status(null)->one();

    expect((int)$wideRoot->getChildren()->count())->toBe(4);
    expect((int)$deepRoot->getDescendants()->count())->toBe(3);
    expect(Comment::find()->ownerId($entry->id)->status(null)->count())->toBe('9');
});

it('builds a v3 moderation and guest status mix', function() {
    $entry = CommentsFixtureFactory::entry();
    $comments = CommentsFixtureFactory::moderationMix($entry);

    expect($comments)->toHaveKeys([
        Comment::STATUS_APPROVED,
        Comment::STATUS_PENDING,
        Comment::STATUS_SPAM,
        Comment::STATUS_REMOVED,
    ]);

    foreach ($comments as $status => $comment) {
        expect($comment->status)->toBe($status);
    }

    expect($comments[Comment::STATUS_SPAM]->isGuest())->toBeTrue();
    expect($comments[Comment::STATUS_REMOVED]->isGuest())->toBeTrue();
});
