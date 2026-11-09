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

    [$entry] = makeApprovedComment('Interaction none mode');

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $comment = $view->roots[0];

    expect($view->interactionMode)->toBe('none');
    expect($comment->actions)->not->toContain('interact');
    expect($comment->interaction)->toBeNull();
});

it('exposes an upvote-only affordance in like mode', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::Like->value;

    [$entry] = makeApprovedComment('Interaction like mode');

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $comment = $view->roots[0];

    expect($comment->actions)->toContain('interact');
    expect($comment->actions)->not->toContain('interact-down');
    expect($comment->interaction['mode'])->toBe('like');
    expect($comment->interaction['allowsDownvote'])->toBeFalse();
    expect($comment->interaction['usesScore'])->toBeFalse();
});

it('exposes both affordances and a net score in updown mode', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::UpDown->value;

    [$entry, $comment] = makeApprovedComment('Interaction updown mode');
    $identityId = (int)$comment->identityId;

    Comments::$plugin->getInteractions()->toggle((int)$comment->id, $identityId, InteractionType::Upvote);

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $row = $view->roots[0];

    expect($row->actions)->toContain('interact');
    expect($row->actions)->toContain('interact-down');
    expect($row->interaction['usesScore'])->toBeTrue();
    expect($row->interaction['upvotes'])->toBe(1);
    expect($row->interaction['downvotes'])->toBe(0);
    expect($row->interaction['score'])->toBe(1);
    expect($row->interaction['viewer'])->toBe('upvote');
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

it('renders up and down controls with pressed state in updown mode', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = InteractionMode::UpDown->value;
    $settings->outputDefaultJs = false;
    $settings->outputDefaultCss = false;

    [$entry, $comment] = makeApprovedComment('Interaction render');
    Comments::$plugin->getInteractions()->toggle(
        (int)$comment->id,
        (int)$comment->identityId,
        InteractionType::Downvote,
    );

    $html = (string)Comments::$plugin->getComments()->render((int)$entry->id);

    expect($html)->toContain('data-comments-interaction-type="upvote"');
    expect($html)->toContain('data-comments-interaction-type="downvote"');
    expect($html)->toContain('comments__score');
    // The viewer's own downvote is reflected as the pressed control.
    expect($html)->toMatch('/comments__vote--down.*?aria-pressed="true"/s');
});

it('batches interaction totals for a page of comments', function() {
    Comments::$plugin->getSettings()->interactionMode = InteractionMode::UpDown->value;

    [, $comment] = makeApprovedComment('Interaction batch');
    $state = Comments::$plugin->getInteractions()->getPresentationState([(int)$comment->id], null);

    expect($state)->toHaveKey((int)$comment->id);
    expect($state[(int)$comment->id]['viewer'])->toBeNull();
    expect($state[(int)$comment->id]['score'])->toBe(0);
});
