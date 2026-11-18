<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\InteractionType;
use verbb\comments\models\CommentSubmission;

function makeApprovedComment(string $title, string $content = 'Interaction target')
{
    $entry = CommentsFixtureFactory::entry($title);
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)$admin->id,
    ]));

    return [$entry, $result->comment];
}

it('uses the explicit interaction mode', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = InteractionMode::UpDown->value;

    expect($settings->resolveInteractionMode())->toBe(InteractionMode::UpDown);
    expect($settings->resolveInteractionMode()->allowsDownvote())->toBeTrue();
});

it('omits interaction affordances entirely in none mode', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::None->value;

    [$entry, $comment] = makeApprovedComment('Interaction none mode');

    expect(Comments::$plugin->getApi()->thread($entry)['conversation']['interactionMode'])->toBe('none');
    expect(Comments::$plugin->getFrontend()->viewerStatesFor([$comment])[(int)$comment->id])->toMatchArray(['canVote' => false, 'canDownvote' => false]);
});

it('exposes an upvote-only affordance in like mode', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::Like->value;

    [$entry, $comment] = makeApprovedComment('Interaction like mode');

    expect(Comments::$plugin->getApi()->thread($entry)['conversation']['interactionMode'])->toBe('like');
    expect(Comments::$plugin->getFrontend()->viewerStatesFor([$comment])[(int)$comment->id])->toMatchArray(['canVote' => true, 'canDownvote' => false]);
});

it('exposes both affordances and a net score in updown mode', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::UpDown->value;

    [$entry, $comment] = makeApprovedComment('Interaction updown mode');
    $identityId = (int)$comment->identityId;

    Comments::$plugin->getInteractions()->toggle((int)$comment->id, $identityId, InteractionType::Upvote);

    $thread = Comments::$plugin->getApi()->thread($entry);

    expect($thread['conversation']['interactionMode'])->toBe('updown');
    expect($thread['comments'][0]['interaction'])->toMatchArray(['upvotes' => 1, 'downvotes' => 0]);
    expect(Comments::$plugin->getFrontend()->viewerStatesFor([$comment])[(int)$comment->id])->toMatchArray(['canVote' => true, 'canDownvote' => true, 'vote' => 'upvote']);
});

it('switches between up and down instead of clearing the interaction', function() {
    [, $comment] = makeApprovedComment('Interaction switch');
    $interactions = Comments::$plugin->getInteractions();
    $identityId = (int)$comment->identityId;
    $commentId = (int)$comment->id;

    expect($interactions->toggle($commentId, $identityId, InteractionType::Upvote))->toBe(InteractionType::Upvote);
    expect($interactions->toggle($commentId, $identityId, InteractionType::Downvote))->toBe(InteractionType::Downvote);
    expect($interactions->toggle($commentId, $identityId, InteractionType::Downvote))->toBeNull();
});

it('batches interaction totals for a page of comments', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::UpDown->value;

    [, $comment] = makeApprovedComment('Interaction batch');
    $state = Comments::$plugin->getInteractions()->getPresentationState([(int)$comment->id], null);

    expect($state)->toHaveKey((int)$comment->id);
    expect($state[(int)$comment->id]['viewer'])->toBeNull();
    expect($state[(int)$comment->id]['score'])->toBe(0);
});

it('tells a template whether the visitor has voted on a comment, and which way', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = InteractionMode::UpDown->value;
    [, $comment] = makeApprovedComment('Has voted owner');
    $voter = CommentsFixtureFactory::user('hasVoted');
    $interactions = Comments::$plugin->getInteractions();
    $identityId = (int)Comments::$plugin->getIdentities()->getOrCreateForUser($voter)->id;
    $votes = static fn(): array => [$comment->hasVoted(), $comment->hasUpvoted(), $comment->hasDownvoted()];
    Craft::$app->getUser()->setIdentity($voter);

    expect($votes())->toBe([false, false, false]);

    $interactions->set((int)$comment->id, $identityId, InteractionType::Upvote);
    expect($votes())->toBe([true, true, false]);

    $interactions->set((int)$comment->id, $identityId, InteractionType::Downvote);
    expect($votes())->toBe([true, false, true]);

    // Someone else's vote isn't the visitor's.
    Craft::$app->getUser()->setIdentity(CommentsFixtureFactory::user('hasNotVoted'));
    expect($votes())->toBe([false, false, false]);

    // A downvote from before the site switched to likes isn't one the visitor has.
    Craft::$app->getUser()->setIdentity($voter);
    $settings->interactionMode = InteractionMode::Like->value;
    expect($votes())->toBe([false, false, false]);

    $interactions->set((int)$comment->id, $identityId, InteractionType::Upvote);
    expect($votes())->toBe([true, true, false]);

    $settings->interactionMode = InteractionMode::None->value;
    expect($votes())->toBe([false, false, false]);
});

it('reuses the vote totals a query loaded, unless the votes changed since', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::UpDown->value;
    [$entry, $comment] = makeApprovedComment('Interaction loaded totals');
    $interactions = Comments::$plugin->getInteractions();
    $voter = (int)Comments::$plugin->getIdentities()->getOrCreateForUser(CommentsFixtureFactory::user('loadedTotals'))->id;
    // A vote from an earlier request, so this one hasn't seen it change.
    (new verbb\comments\records\Interaction(['commentId' => $comment->id, 'identityId' => $voter, 'type' => 'upvote', 'uid' => craft\helpers\StringHelper::UUID()]))->save(false);
    $totalsQueries = static function(Closure $run): int {
        $profile = Tests\Support\Performance\QueryProfiler::profile($run);

        return array_sum(array_filter($profile['topPatterns'], static fn(string $sql): bool => str_contains($sql, 'COUNT(*) AS `total`'), ARRAY_FILTER_USE_KEY));
    };

    // Loaded with the comment, so the totals aren't counted again, whether given the comment or its ID.
    $loaded = verbb\comments\elements\Comment::find()->id($comment->id)->one();
    $state = null;
    expect($totalsQueries(function() use ($interactions, $loaded, &$state) {
        $state = $interactions->getPresentationState([$loaded], null) + $interactions->getPresentationState([(int)$loaded->id], null);
    }))->toBe(0)
        ->and($state[(int)$comment->id]['upvotes'])->toBe(1)
        ->and($interactions->getPresentationState([999999999], null)[999999999]['upvotes'])->toBe(0);

    // A vote since then makes the loaded totals stale, so they're counted.
    $interactions->toggle((int)$comment->id, $voter, InteractionType::Downvote);
    expect($totalsQueries(function() use ($interactions, $loaded, $voter, &$state) {
        $state = $interactions->getPresentationState([$loaded], $voter);
    }))->toBe(1)
        ->and($state[(int)$comment->id])->toBe(['upvotes' => 0, 'downvotes' => 1, 'score' => -1, 'viewer' => 'downvote']);
});
