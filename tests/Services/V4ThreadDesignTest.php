<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\exceptions\ApiException;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

/**
 * The thread's rules for moderators and visitors that the data API serialises: why a held comment
 * waits and what a moderator can decide, keeping a reported comment from the site, and initials on a
 * colour for people without an avatar. The renderer's browser checks cover how they're drawn.
 */

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
});

function threadPost($entry, string $content, ?int $userId = null): verbb\comments\elements\Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => $userId ?? (int)CommentsFixtureFactory::user('threadAuthor')->id,
    ]))->comment;
}

it('shows moderators a held comment in full, with why it waits and what they can decide', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('Thread held owner');
    $settings->requireModeration = true;
    $comment = threadPost($entry, 'Slow step-downs off a bench');
    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildDetachedCommentViews([$comment], true)[(int)$comment->id];

    expect($view->content)->toBe('Slow step-downs off a bench')
        ->and($view->state)->toBe(ModerationState::Pending->value)
        ->and($frontend->reviewLabel($comment))->toBe('Held · Pre-moderation · only moderators see this')
        ->and($frontend->moderationDecisions($comment, Craft::$app->getUser()->getIdentity()))->toContain('approve');
});

it('lets a moderator keep a reported comment from the site', function() {
    $entry = CommentsFixtureFactory::entry('Thread reported owner');
    $comment = threadPost($entry, 'Brunch on Saturday, 10% off');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam');

    $frontend = Comments::$plugin->getFrontend();
    $admin = Craft::$app->getUser()->getIdentity();

    expect($frontend->reviewLabel($comment))->toBe('Reported once')
        ->and($frontend->moderationDecisions($comment, $admin))->toContain('keep', 'remove')->not->toContain('approve');

    $answer = Comments::$plugin->getApi()->moderate(['uid' => $comment->uid, 'action' => 'keep']);

    expect($answer['announcement'])->toBeString()
        ->and(Comments::$plugin->getModeration()->hasOpenReports($comment))->toBeFalse()
        ->and(Comments::$plugin->getModeration()->currentState($comment))->toBe(ModerationState::Approved);

    // Approving isn't a decision for a comment that's already up.
    expect(fn() => Comments::$plugin->getApi()->moderate(['uid' => $comment->uid, 'action' => 'approve']))->toThrow(ApiException::class);
});

it('gives people with no avatar their initials on a colour that stays theirs', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->showAvatar = true;
    $entry = CommentsFixtureFactory::entry('Thread initials owner');
    $user = CommentsFixtureFactory::user('initials');
    $user->fullName = 'Mere Tawhiri';
    Craft::$app->getElements()->saveElement($user, false);
    $comment = threadPost($entry, 'Kia ora', (int)$user->id);

    $view = Comments::$plugin->getFrontend()->buildDetachedCommentViews([$comment])[(int)$comment->id];

    expect($view->authorInitials)->toBe('MT')
        ->and($view->avatarTone)->toBe(verbb\comments\services\Frontend::avatarTone('Mere Tawhiri'))
        ->and($view->authorAvatarUrl)->toBeNull();
});
