<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

it('builds a canonical frontend conversation view without creating a Conversation record for empty owners', function() {
    $entry = CommentsFixtureFactory::entry('Frontend empty owner');

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);

    expect($view)->not->toBeNull();
    expect($view->contractVersion)->toBe(1);
    expect($view->roots)->toBe([]);
    expect($view->canComment)->toBeTrue();
});

it('builds root comments from v4 conversation reads for the frontend view', function() {
    $entry = CommentsFixtureFactory::entry('Frontend populated owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Frontend root comment',
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Approved);

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);

    expect($view)->not->toBeNull();
    expect($view->roots)->not->toBeEmpty();
    expect($view->roots[0]->content)->toContain('Frontend root comment');
    expect($view->roots[0]->state)->toBe(ModerationState::Approved->value);
});

it('renders canonical frontend markup with stable DOM hooks', function() {
    $entry = CommentsFixtureFactory::entry('Frontend render owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];
    Comments::$plugin->getSettings()->outputDefaultJs = false;
    Comments::$plugin->getSettings()->outputDefaultCss = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Rendered frontend comment',
        'userId' => (int)$admin->id,
    ]));

    $html = (string)Comments::$plugin->getComments()->render((int)$entry->id);

    expect($html)->toContain('data-comments-root');
    expect($html)->toContain('data-comments-contract="1"');
    expect($html)->toContain('data-comments-form="create"');
    expect($html)->toContain('Rendered frontend comment');
    expect($html)->toContain('commentsOperationId');
});
