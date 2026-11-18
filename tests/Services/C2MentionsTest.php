<?php
use craft\db\Query;
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\enums\ContentFormat;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\services\Notifications;

function c2MentionPayload(int $userId, string $label = 'Pat'): string
{
    return json_encode([
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'Hey '],
                [
                    'type' => 'mention',
                    'attrs' => [
                        'id' => $userId,
                        'type' => User::class,
                        'label' => $label,
                    ],
                ],
                ['type' => 'text', 'text' => ' there.'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

it('persists eligible mentions when creating a rich comment', function() {
    $entry = CommentsFixtureFactory::entry('C2 mention create owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Seed thread',
        'userId' => (int)$admin->id,
    ]));

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => c2MentionPayload((int)$admin->id, (string)$admin->username),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    $adminIdentity = Comments::$plugin->getIdentities()->getOrCreateForUser($admin);
    $storedIds = Comments::$plugin->getMentions()->getIdentityIdsForComment((int)$result->comment->id);

    expect($storedIds)->toContain((int)$adminIdentity->id);
    expect($result->comment->comment)->toContain('Hey');
});

it('rejects mentions for users outside the conversation eligibility set', function() {
    $entry = CommentsFixtureFactory::entry('C2 mention reject owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Only admin here',
        'userId' => (int)$admin->id,
    ]));

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => c2MentionPayload(999999),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));
})->throws(\yii\base\UserException::class);

it('syncs mention rows when a rich comment body is edited', function() {
    $entry = CommentsFixtureFactory::entry('C2 mention edit owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Seed',
        'userId' => (int)$admin->id,
    ]));

    $created = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => json_encode([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'No mentions yet']]],
        ], JSON_THROW_ON_ERROR),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    expect(Comments::$plugin->getMentions()->getIdentityIdsForComment((int)$created->comment->id))->toBe([]);

    $normalized = Comments::$plugin->getComments()->normalizeEditorPayload(
        c2MentionPayload((int)$admin->id),
        'comments.edit',
        (int)$admin->id,
        (int)$entry->id,
        (int)$entry->siteId,
    );

    Comments::$plugin->getComments()->finalizeCommentMentions(
        $created->comment,
        [],
        $normalized['mentionIdentityIds'],
    );

    $adminIdentity = Comments::$plugin->getIdentities()->getOrCreateForUser($admin);
    $storedIds = Comments::$plugin->getMentions()->getIdentityIdsForComment((int)$created->comment->id);

    expect($storedIds)->toContain((int)$adminIdentity->id);
});

it('rejects plain format tampering when editing rich comments', function() {
    $entry = CommentsFixtureFactory::entry('C2 format tamper owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->requireModeration = false;

    $created = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => json_encode([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Rich body']]],
        ], JSON_THROW_ON_ERROR),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    $comment = Comment::find()->id($created->comment->id)->status(null)->one();
    expect($comment)->toBeInstanceOf(Comment::class);

    Craft::$app->getRequest()->setBodyParams([
        'contentFormat' => ContentFormat::Plain->value,
        'fields' => [
            'comment' => 'Attempted flatten',
        ],
    ]);

    Comments::$plugin->getComments()->applyBodyFromRequest($comment);
})->throws(\yii\base\UserException::class);

it('queues mention notifications for newly mentioned users on approved rich creates', function() {
    $admin = Craft::$app->getUser()->getIdentity();

    $mentionedUser = CommentsFixtureFactory::user('mentiontarget');

    $entry = CommentsFixtureFactory::entry('C2 mention notify owner');

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->requireModeration = false;
    $settings->useQueueForNotifications = true;
    $settings->participantNotifications = [Settings::NOTIFICATION_MENTION];
    $settings->subscriptionTypes = [];
    $settings->staffNotifications = [];

    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Seed thread for mentions',
        'userId' => (int)$admin->id,
    ]));

    // Make the mention target eligible via prior participation (not entry authorship).
    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Prior participation',
        'userId' => (int)$mentionedUser->id,
    ]));

    $mentionedIdentity = Comments::$plugin->getIdentities()->getOrCreateForUser($mentionedUser);

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => c2MentionPayload((int)$mentionedUser->id, (string)$mentionedUser->username),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    $jobs = (new Query())->from('{{%queue}}')->all();
    $mentionJobs = [];

    foreach ($jobs as $job) {
        $payload = unserialize($job['job']);

        if ($payload instanceof \verbb\comments\queue\jobs\SendNotification
            && $payload->type === Notifications::TYPE_MENTION
            && (int)$payload->commentId === (int)$result->comment->id
        ) {
            $mentionJobs[] = $payload;
        }
    }

    expect($mentionJobs)->toHaveCount(1);
    expect($mentionJobs[0]->mentionedIdentityId)->toBe((int)$mentionedIdentity->id);
});

it('queues mention notifications when a pending rich comment is approved', function() {
    $admin = Craft::$app->getUser()->getIdentity();
    $mentionedUser = CommentsFixtureFactory::user('pendingmention');
    $entry = CommentsFixtureFactory::entry('C2 pending mention owner');

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    // Only someone with an approved comment on the thread can be mentioned (SD-46).
    $settings->requireModeration = false;
    $settings->useQueueForNotifications = true;
    $settings->participantNotifications = [Settings::NOTIFICATION_MENTION];
    $settings->subscriptionTypes = [];
    $settings->staffNotifications = [];

    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Seed thread',
        'userId' => (int)$admin->id,
    ]));

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Prior participation',
        'userId' => (int)$mentionedUser->id,
    ]));

    $mentionedIdentity = Comments::$plugin->getIdentities()->getOrCreateForUser($mentionedUser);
    $settings->requireModeration = true;
    Comments::$plugin->getConversations()->clearCaches();
    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();

    $pending = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => c2MentionPayload((int)$mentionedUser->id, (string)$mentionedUser->username),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    expect($pending->moderationState)->toBe(\verbb\comments\enums\ModerationState::Pending);

    $jobsAfterCreate = (new Query())->from('{{%queue}}')->count();
    expect((int)$jobsAfterCreate)->toBe(0);

    Comments::$plugin->getModeration()->approve($pending->comment, $admin);

    $mentionJobs = [];

    foreach ((new Query())->from('{{%queue}}')->all() as $job) {
        $payload = unserialize($job['job']);

        if ($payload instanceof \verbb\comments\queue\jobs\SendNotification
            && $payload->type === Notifications::TYPE_MENTION
            && (int)$payload->commentId === (int)$pending->comment->id
        ) {
            $mentionJobs[] = $payload;
        }
    }

    expect($mentionJobs)->toHaveCount(1);
    expect($mentionJobs[0]->mentionedIdentityId)->toBe((int)$mentionedIdentity->id);
});

it('returns null from mention resolve for users outside conversation eligibility', function() {
    $outsider = CommentsFixtureFactory::user('c2-resolve-outsider');
    $entry = CommentsFixtureFactory::entry('C2 resolve owner');
    $config = \verbb\comments\helpers\EditorContexts::replyConfig();
    $config->referenceContext = ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId];

    expect(\verbb\editor\Editor::getInstance()->getMentions()->resolve([
        'id' => (int)$outsider->id,
        'type' => User::class,
    ], $config))->toBeNull();
});

it('applies Comments mention eligibility only on Comments contexts', function() {
    $editor = \verbb\editor\Editor::getInstance();
    \verbb\comments\helpers\EditorContexts::refresh();
    $other = new \verbb\editor\models\EditorConfig([
        'handle' => 'another.consumer',
        'features' => ['mention' => true],
        'mentions' => new \verbb\editor\models\MentionConfig(['enabled' => true]),
    ]);
    $editor->getEditorConfigs()->registerConfig($other, true);

    expect($editor->getEditorConfigs()->requireConfigByHandle('comments.reply')->mentions->eligibleIds)->toBeCallable();
    expect($editor->getEditorConfigs()->requireConfigByHandle('another.consumer')->mentions->eligibleIds)->toBeNull();
    expect($editor->getMentions()->search('ad', $other))->toBe([]);
});

it('stops treating a mention as a person once its target becomes inaccessible', function() {
    $admin = Craft::$app->getUser()->getIdentity();
    $target = CommentsFixtureFactory::user('private-mention-target');
    $entry = CommentsFixtureFactory::entry('C2 inaccessible mention owner');
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Prior participant',
        'userId' => (int)$target->id,
    ]));
    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => c2MentionPayload((int)$target->id, 'Private label'),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    Craft::$app->getUsers()->suspendUser($target);
    $html = $result->comment->getRenderedBodyHtml()->__toString();

    // Editor keeps what the author wrote as plain text, because nothing can vouch for the person now.
    expect($html)->toContain('@Private label');
    expect($html)->not->toContain('data-type="mention"');
    expect($html)->not->toContain('verbb-editor-mention');
});

it('only lets commenters mention a moderator once they’ve commented publicly on the thread (SD-46)', function() {
    $entry = CommentsFixtureFactory::entry('C2 mention moderator owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $group = new craft\models\UserGroup(['name' => 'Mention moderators ' . bin2hex(random_bytes(3)), 'handle' => 'mentionModerators' . bin2hex(random_bytes(3))]);
    expect(Craft::$app->getUserGroups()->saveGroup($group, false))->toBeTrue();
    $moderator = CommentsFixtureFactory::user('mentionModerator');
    $outsider = CommentsFixtureFactory::user('mentionOutsider');
    Craft::$app->getUsers()->assignUserToGroups((int)$moderator->id, [(int)$group->id]);

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->requireModeration = false;
    $settings->moderatorUserGroup = $group->uid;
    $mention = static fn() => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => c2MentionPayload((int)$moderator->id, 'Mod'),
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    try {
        // Being in the moderator group alone doesn't make someone mentionable.
        expect(Comments::$plugin->getMentions()->eligibleUserIdsForOwner((int)$entry->id, (int)$entry->siteId))
            ->not->toContain((int)$moderator->id)
            ->not->toContain((int)$outsider->id)
            ->and($mention)->toThrow(yii\base\UserException::class, 'One or more mentions are not allowed.');

        Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => 'A moderator joins in',
            'userId' => (int)$moderator->id,
        ]));
        $result = $mention();

        expect(Comments::$plugin->getMentions()->getIdentityIdsForComment((int)$result->comment->id))
            ->toBe([(int)Comments::$plugin->getIdentities()->getOrCreateForUser($moderator)->id]);
    } finally {
        Craft::$app->getUserGroups()->deleteGroup($group);
    }
});

it('suggests only people with an approved comment on the thread and the element’s author, on every path (SD-46)', function() {
    $entry = CommentsFixtureFactory::entry('C2 mention eligibility owner');
    $author = CommentsFixtureFactory::user('mentionEntryAuthor');
    $entry->setAuthorIds([(int)$author->id]);
    Craft::$app->getElements()->saveElement($entry, false);
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $post = static function(User $user, string $text, bool $held = false) use ($entry, $settings): Comment {
        $settings->requireModeration = $held;
        Comments::$plugin->getConversations()->clearCaches();

        return Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $text,
            'userId' => (int)$user->id,
        ]))->comment;
    };
    $approved = CommentsFixtureFactory::user('mentionApprovedOne');
    $pending = CommentsFixtureFactory::user('mentionHiddenPending');
    $spam = CommentsFixtureFactory::user('mentionHiddenSpam');
    $removed = CommentsFixtureFactory::user('mentionHiddenRemoved');
    $post($approved, 'Approved');
    $post($pending, 'Waiting', true);
    $spammed = $post($spam, 'Spammy');
    Comments::$plugin->getModeration()->markSpam($spammed, Craft::$app->getUser()->getIdentity());
    $gone = $post($removed, 'Removed');
    Comments::$plugin->getModeration()->remove($gone, Craft::$app->getUser()->getIdentity());
    $settings->requireModeration = false;

    $eligible = Comments::$plugin->getMentions()->eligibleUserIdsForOwner((int)$entry->id, (int)$entry->siteId);

    expect($eligible)->toContain((int)$approved->id)
        ->toContain((int)$author->id)
        ->not->toContain((int)$pending->id)
        ->not->toContain((int)$spam->id)
        ->not->toContain((int)$removed->id);

    // A search through the data API, as every tier makes it, offers the same people.
    $scope = ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId];
    $names = static fn(string $query): array => array_column(Comments::$plugin->getApi()->mentions('comments.reply', $scope, $query)['results'], 'id');

    expect(array_map('intval', $names('mentionHidden')))->toBe([])
        ->and(array_map('intval', $names('mentionApproved')))->toBe([(int)$approved->id]);
});

it('keeps someone a comment already mentions mentionable when its author edits it (SD-46)', function() {
    $entry = CommentsFixtureFactory::entry('C2 mention edit owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->requireModeration = false;
    $mentioned = CommentsFixtureFactory::user('mentionLaterHidden');
    $theirs = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id, 'siteId' => $entry->siteId, 'content' => 'Mine', 'userId' => (int)$mentioned->id,
    ]))->comment;
    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id, 'siteId' => $entry->siteId, 'content' => c2MentionPayload((int)$mentioned->id), 'contentFormat' => ContentFormat::Rich, 'userId' => (int)$admin->id,
    ]))->comment;

    // Their only comment is removed, so they're no longer offered, but the comment that mentions them
    // can still be edited with the mention in it.
    Comments::$plugin->getModeration()->remove($theirs, $admin);
    $comment = Comment::find()->id($comment->id)->status(null)->one();

    expect(Comments::$plugin->getMentions()->eligibleUserIdsForOwner((int)$entry->id, (int)$entry->siteId))->not->toContain((int)$mentioned->id)
        ->and(Comments::$plugin->getMentions()->eligibleUserIdsForOwner((int)$entry->id, (int)$entry->siteId, (int)$comment->id))->toContain((int)$mentioned->id);

    Comments::$plugin->getComments()->applyBody($comment, ContentFormat::Rich, c2MentionPayload((int)$mentioned->id, 'Still Pat'));

    expect($comment->comment)->toContain('Still Pat');
});
