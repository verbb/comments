<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

it('builds an empty owner’s mount point without creating a Conversation record', function() {
    $entry = CommentsFixtureFactory::entry('Frontend empty owner');

    $view = Comments::$plugin->getFrontend()->buildMountPointView((int)$entry->id, (int)$entry->siteId);

    expect($view)->not->toBeNull();
    // The API version belongs on the response, not on each object in it.
    expect(property_exists($view, 'contractVersion'))->toBeFalse();
    expect($view->commentCount)->toBe(0);
    expect(Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId))->toBeNull();
});

it('builds the public view of a comment the data API serialises', function() {
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

    $views = Comments::$plugin->getFrontend()->buildDetachedCommentViews([$result->comment]);
    $view = $views[(int)$result->comment->id] ?? null;

    expect($view)->not->toBeNull();
    expect($view->content)->toContain('Frontend root comment');
    expect($view->state)->toBe(ModerationState::Approved->value);
    expect($view->placeholder)->toBeFalse();
});
