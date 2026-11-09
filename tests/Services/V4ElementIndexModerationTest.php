<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\actions\ApproveComments;
use verbb\comments\elements\actions\KeepComments;
use verbb\comments\elements\actions\MarkCommentsSpam;
use verbb\comments\elements\actions\RemoveComments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;

use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\User;

it('exposes report filtering and owner type sources to the native element index', function() {
    $owner = CommentsFixtureFactory::entry('Reported element-index owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'content' => 'Reported element-index comment',
        'userId' => (int)$admin->id,
    ]));

    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($admin);
    Comments::$plugin->getReports()->create((int)$result->comment->id, (int)$identity->id, 'other');

    $flaggedIds = Comment::find()
        ->status(null)
        ->hasOpenReports(true)
        ->ids();

    $sources = array_filter(Comment::sources('index'), fn(array $source) => isset($source['key']));
    $sourcesByKey = array_column($sources, null, 'key');
    $sourceHeadings = array_values(array_filter(Comment::sources('index'), fn(array $source) => isset($source['heading'])));
    $tableAttributes = Comment::tableAttributes();
    $defaultTableAttributes = Comment::defaultTableAttributes('*');
    $sortOptions = Comment::sortOptions();
    $sortLabels = array_map(
        fn(array|string $option) => is_array($option) ? $option['label'] : $option,
        $sortOptions,
    );

    expect($flaggedIds)->toContain((int)$result->comment->id);
    expect(array_keys($sourcesByKey))->toBe([
        '*',
        'needs-review',
        'reported',
        'spam',
        'owner-type:entries',
        'owner-type:categories',
        'owner-type:assets',
        'owner-type:users',
    ]);
    expect($sourcesByKey['owner-type:entries']['criteria'])->toBe(['ownerType' => Entry::class]);
    expect($sourcesByKey['owner-type:categories']['criteria'])->toBe(['ownerType' => Category::class]);
    expect($sourcesByKey['owner-type:assets']['criteria'])->toBe(['ownerType' => Asset::class]);
    expect($sourcesByKey['owner-type:users']['criteria'])->toBe(['ownerType' => User::class]);
    expect(array_column($sourceHeadings, 'heading'))->toBe(['Moderation', 'Element Type']);
    expect($sourcesByKey['needs-review']['criteria'])->toBe(['moderationState' => 'pending']);
    expect($sourcesByKey['needs-review']['defaultSort'])->toBe(['commentDate', 'asc']);
    expect($sourcesByKey['reported']['criteria'])->toBe(['moderationState' => 'approved', 'hasOpenReports' => true]);
    expect($sourcesByKey['spam']['criteria'])->toBe(['moderationState' => 'spam']);
    expect($sourcesByKey['owner-type:entries']['criteria'])->not->toHaveKey('status');
    expect($sourcesByKey['*']['defaultSort'])->toBe(['commentDate', 'desc']);
    expect($tableAttributes['commentDate']['label'])->toBe('Date Posted');
    expect($tableAttributes['dateUpdated']['label'])->toBe('Date Updated');
    expect($tableAttributes)->not->toHaveKey('dateCreated');
    expect($defaultTableAttributes)->toBe(['ownerId', 'commentDate']);
    expect($sortLabels)->toContain('Date Posted', 'Date Updated');
    expect($sortLabels)->toContain('Thread');
    expect(Comment::statuses()[Comment::STATUS_APPROVED]['color']->value)->toBe('teal');
    expect(Comment::statuses()[Comment::STATUS_SPAM]['color']->value)->toBe('red');
    $chipLabelHtml = $result->comment->getChipLabelHtml();

    expect($chipLabelHtml)
        ->toContain('Reported element-index comment')
        ->toContain('comments-comment-label')
        ->toContain('comments-comment-excerpt')
        ->toContain('comments-comment-name')
        ->not->toContain('display: inline-block');
    expect($result->comment->getCpEditUrl())->toContain('/comments/' . $result->comment->id);
    expect($result->comment->getCpEditUrl())->not->toContain('/moderation/');
});

it('uses adjacency hierarchy for edit-screen parent and reply queries', function() {
    $owner = CommentsFixtureFactory::entry('Element-index thread owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $root = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'content' => 'Thread root',
        'userId' => (int)$admin->id,
    ]))->comment;
    $reply = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'parentId' => (int)$root->id,
        'content' => 'Thread reply',
        'userId' => (int)$admin->id,
    ]))->comment;

    expect($reply->getParentComment()?->id)->toBe($root->id);
    expect($root->getReplies()->ids())->toBe([(int)$reply->id]);
    expect($reply->getFieldLayout()->createForm($reply)->render())->toContain('In reply to');
});

it('distinguishes named and anonymous guests in native index labels', function() {
    $owner = CommentsFixtureFactory::entry('Element-index guest owner');
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->allowGuest = true;
    $settings->guestRequireEmailName = false;

    $namedIdentity = Comments::$plugin->getIdentities()->createHistoricalGuest('Alex Morgan', null);
    $namedGuest = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'identityId' => (int)$namedIdentity->id,
        'name' => 'Alex Morgan',
        'content' => 'Named guest comment',
    ]))->comment;

    $anonymousIdentity = Comments::$plugin->getIdentities()->createHistoricalGuest(null, null);
    $anonymousGuest = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'identityId' => (int)$anonymousIdentity->id,
        'content' => 'Anonymous guest comment',
    ]))->comment;

    expect($namedGuest->getChipLabelHtml())
        ->toContain('Alex Morgan')
        ->not->toContain('<strong class="comments-comment-author">Guest</strong>');
    expect($anonymousGuest->getChipLabelHtml())
        ->toContain('<strong class="comments-comment-author">Guest</strong>');
});

it('validates the selected control-panel author mode', function() {
    $owner = CommentsFixtureFactory::entry('CP author validation owner');
    $comment = CommentsFixtureFactory::comment($owner, 'CP author validation comment');
    $comment->setScenario(Comment::SCENARIO_CP);
    $comment->cpAuthorType = 'guest';
    $comment->userId = null;
    $comment->setUser(null);
    $comment->name = null;
    $comment->email = null;

    expect($comment->validate())->toBeFalse();
    expect($comment->getErrors())->toHaveKeys(['name', 'email']);

    $comment->cpAuthorType = 'user';

    expect($comment->validate())->toBeFalse();
    expect($comment->getFirstError('userId'))->toBe('Select an author.');

    $comment->cpAuthorType = 'guest';
    $comment->name = 'Taylor Guest';
    $comment->email = 'not-an-email';

    expect($comment->validate())->toBeFalse();
    expect($comment->getErrors())->toHaveKey('email');
});

it('keeps a visible excerpt for long unbroken comment text', function() {
    $owner = CommentsFixtureFactory::entry('Element-index long comment owner');
    $comment = CommentsFixtureFactory::comment($owner, str_repeat('LongUnbrokenText', 20));

    expect($comment->getExcerpt(0, 40))
        ->toStartWith('LongUnbrokenText')
        ->toEndWith('…')
        ->not->toBe('…');
});

it('routes element status actions through the v4 moderation lifecycle', function() {
    $owner = CommentsFixtureFactory::entry('Bulk moderation owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = true;

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'content' => 'Pending native bulk-action comment',
        'userId' => (int)$admin->id,
    ]));

    // Someone else's comment, as moderators can't decide their own.
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['userId' => null, 'identityId' => Comments::$plugin->getIdentities()->createHistoricalGuest('Bulk guest', null)->id], ['id' => $result->comment->id])->execute();
    $action = new ApproveComments();

    $query = Comment::find()
        ->status(null)
        ->id((int)$result->comment->id);

    expect($action->performAction($query))->toBeTrue();

    $comment = Comment::find()
        ->status(null)
        ->id((int)$result->comment->id)
        ->one();

    expect($comment->status)->toBe(Comment::STATUS_APPROVED);
    expect($comment->moderationState)->toBe(ModerationState::Approved->value);
    expect(Comments::$plugin->getModeration()->getActions((int)$comment->id)[0]->action)
        ->toBe('approve');
});

it('uses canonical index sorting and translates the legacy structure sort', function() {
    $defaults = new Settings();
    $legacy = new Settings([
        'sortDefaultKey' => 'structure',
        'sortDefaultDirection' => 'asc',
    ]);
    $settings = Comments::$plugin->getSettings();
    $originalKey = $settings->sortDefaultKey;
    $originalDirection = $settings->sortDefaultDirection;

    try {
        $settings->sortDefaultKey = 'dateUpdated';
        $settings->sortDefaultDirection = 'asc';
        // Element sources are memoized per context for the request, so use a dedicated
        // context after changing the in-memory settings in this test.
        $sources = array_filter(Comment::sources('sorting-test'), fn(array $source) => ($source['key'] ?? null) === '*');
        $allComments = reset($sources);

        expect($defaults->sortDefaultKey)->toBe('commentDate');
        expect($defaults->sortDefaultDirection)->toBe('desc');
        expect($legacy->sortDefaultKey)->toBe('commentDate');
        expect($legacy->sortDefaultDirection)->toBe('desc');
        expect($allComments['defaultSort'])->toBe(['dateUpdated', 'asc']);
    } finally {
        $settings->sortDefaultKey = $originalKey;
        $settings->sortDefaultDirection = $originalDirection;
    }
});

it('offers the moderation decisions each source needs, and never a permanent delete', function() {
    $classes = static fn(string $source): array => array_map(
        static fn($action) => is_array($action) ? $action['type'] : (is_string($action) ? $action : $action::class),
        (new ReflectionMethod(Comment::class, 'defineActions'))->invoke(null, $source),
    );

    expect($classes('needs-review'))->toContain(ApproveComments::class, MarkCommentsSpam::class, RemoveComments::class)
        ->and($classes('reported'))->toContain(KeepComments::class, RemoveComments::class)
        ->and($classes('reported'))->not->toContain(ApproveComments::class)
        ->and($classes('spam'))->toContain(verbb\comments\elements\actions\MarkCommentsNotSpam::class);

    foreach (['*', 'needs-review', 'spam'] as $source) {
        foreach ((new ReflectionMethod(Comment::class, 'defineActions'))->invoke(null, $source) as $action) {
            if (is_array($action) && $action['type'] === craft\elements\actions\Delete::class) {
                expect($action['hard'] ?? false)->toBeFalse();
            }
        }
    }
});

it('keeps reported comments in bulk and counts what waits for the nav badge', function() {
    $owner = CommentsFixtureFactory::entry('Bulk keep owner');
    $before = Comments::$plugin->getModeration()->getQueueCounts();
    Comments::$plugin->getSettings()->requireModeration = false;
    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => (int)$owner->id,
        'siteId' => (int)$owner->siteId,
        'content' => 'Reported but fine',
        'userId' => (int)CommentsFixtureFactory::user('bulkKeep')->id,
    ]))->comment;
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam');

    expect(Comments::$plugin->getModeration()->getQueueCounts()['reported'])->toBe($before['reported'] + 1)
        ->and(Comments::$plugin->getCpNavItem()['badgeCount'] ?? 0)->toBe($before['pending'] + $before['reported'] + 1);

    expect((new KeepComments())->performAction(Comment::find()->status(null)->id($comment->id)))->toBeTrue()
        ->and(Comments::$plugin->getModeration()->getQueueCounts()['reported'])->toBe($before['reported']);
});

