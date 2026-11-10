<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\controllers\FrontendApiController;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

/**
 * The thread as visitors and moderators see it: held comments in full for moderators, decisions in
 * place, a way in for guests, and quiet actions.
 */

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
});

afterEach(function() {
    unset($_SERVER['REQUEST_METHOD']);
    Craft::$app->getRequest()->getHeaders()->remove('Accept');
    Craft::$app->getRequest()->setBodyParams([]);
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

function threadRender($entry): string
{
    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);

    return $frontend->renderPartial('conversation', [
        'contractVersion' => 1,
        'instance' => $view->instanceId,
        'conversation' => $view,
        'viewer' => $frontend->buildViewerView($view),
        'editor' => $frontend->buildEditorView($view),
        'theme' => $view->theme,
        'apiBase' => Comments::$plugin->getFrontend()->getApiUrl(''),
        'sharedCache' => false,
        'reportReasons' => $frontend::reportReasons(),
    ]);
}

it('shows moderators a held comment in full, with why it waits and what they can decide', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('Thread held owner');
    $settings->requireModeration = true;
    threadPost($entry, 'Slow step-downs off a bench');

    $html = threadRender($entry);

    expect($html)->toContain('Slow step-downs off a bench')
        ->toContain('comments__comment--held')
        ->toContain('Held · Pre-moderation · only moderators see this')
        ->toContain('value="moderate-approve"')
        ->toContain('Moderator view.')
        ->toContain('1 held')
        ->not->toContain('Comment unavailable');
});

it('lets a moderator keep a reported comment from the site', function() {
    $entry = CommentsFixtureFactory::entry('Thread reported owner');
    $comment = threadPost($entry, 'Brunch on Saturday, 10% off');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam');

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);

    expect($view->roots[0]->reported)->toBeTrue()
        ->and($view->roots[0]->reviewLabel)->toBe('Reported once')
        ->and($view->roots[0]->moderationActions)->toBe(['moderate-keep', 'moderate-remove'])
        ->and($view->reportedCount)->toBe(1);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    Craft::$app->getRequest()->getHeaders()->set('Accept', 'application/json');
    Craft::$app->getRequest()->setBodyParams(['commentId' => $comment->id, 'moderationAction' => 'moderate-keep']);
    $response = (new FrontendApiController('frontend-api', Comments::$plugin))->actionModerate();

    expect($response->data['status'] ?? null)->toBe('ok')
        ->and(Comments::$plugin->getModeration()->hasOpenReports($comment))->toBeFalse()
        ->and(Comments::$plugin->getModeration()->currentState($comment))->toBe(ModerationState::Approved);

    // Approving isn't a decision for a comment that's already up.
    Craft::$app->getRequest()->setBodyParams(['commentId' => $comment->id, 'moderationAction' => 'moderate-approve']);
    expect((new FrontendApiController('frontend-api', Comments::$plugin))->actionModerate()->data['status'] ?? null)->toBe('error');
});

it('gives people with no avatar their initials on a colour that stays theirs', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->showAvatar = true;
    $entry = CommentsFixtureFactory::entry('Thread initials owner');
    $user = CommentsFixtureFactory::user('initials');
    $user->fullName = 'Mere Tawhiri';
    Craft::$app->getElements()->saveElement($user, false);
    threadPost($entry, 'Kia ora', (int)$user->id);

    $view = Comments::$plugin->getFrontend()->buildConversationView((int)$entry->id, (int)$entry->siteId);

    expect($view->roots[0]->authorInitials)->toBe('MT')
        ->and($view->roots[0]->avatarTone)->toBe(verbb\comments\services\Frontend::avatarTone('Mere Tawhiri'))
        ->and(threadRender($entry))->toContain('comments__avatar--initials');
});

it('puts the editor first, says who’s posting, and offers guests a way in', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->allowGuest = true;
    $entry = CommentsFixtureFactory::entry('Thread editor owner');
    threadPost($entry, 'First!');

    $html = threadRender($entry);
    $admin = Craft::$app->getUser()->getIdentity();

    expect(strpos($html, 'data-comments-region="editor"'))->toBeLessThan(strpos($html, 'data-comments-region="list"'))
        ->and($html)->toContain('Posting as <b>' . craft\helpers\Html::encode(verbb\comments\helpers\CommentsHelper::publicUserName($admin)) . '</b>');

    $user = Craft::$app->getUser();
    $user->setIdentity(null);

    try {
        $guestHtml = threadRender($entry);
    } finally {
        $user->setIdentity($admin);
    }

    expect($guestHtml)->toContain('Join the conversation')
        ->toContain('Comment as a guest')
        ->toContain('<details class="comments__join-guest">');

    // A guest following by email does it from the Join card, not with a bare address box in the header.
    $settings->subscriptionTypes = ['conversation'];
    $user->setIdentity(null);

    try {
        $followHtml = threadRender($entry);
    } finally {
        $user->setIdentity($admin);
    }

    preg_match('/<header class="comments__header".*?<\/header>/s', $followHtml, $header);
    preg_match('/<section class="comments__join".*?<\/section>/s', $followHtml, $join);

    expect($header)->not->toBeEmpty()
        ->and($join)->not->toBeEmpty()
        ->and($header[0])->not->toContain('name="email"')
        ->and($join[0])->toContain('Follow by email')
        ->and($join[0])->toContain('name="email"');

    // Closed to new comments, there's no Join card, but a guest can still follow.
    Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, true);
    $user->setIdentity(null);

    try {
        $closedHtml = threadRender($entry);
    } finally {
        $user->setIdentity($admin);
        Comments::$plugin->getConversations()->setClosed((int)$entry->id, (int)$entry->siteId, false);
    }

    expect($closedHtml)->not->toContain('Join the conversation')
        ->toContain('Follow by email')
        ->toContain('name="email"');
});

it('keeps actions quiet: no zero counts, and Report rather than Flag', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = 'like';
    $settings->allowFlagging = true;
    $entry = CommentsFixtureFactory::entry('Thread actions owner');
    threadPost($entry, 'Nobody has liked this');

    $html = threadRender($entry);

    expect($html)->not->toContain('comments__vote-count')
        ->toContain('comments__menu')
        ->toContain('Report…')
        ->toContain('Report this comment')
        ->toContain('Ads, scams or links that don’t belong')
        ->not->toMatch('/<option[^>]*>Spam<\/option>/');
});
