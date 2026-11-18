<?php
use craft\db\Query;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\CommentSort;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationState;
use verbb\comments\gql\arguments\CommentArguments;
use verbb\comments\models\CommentSubmission;

it('installs the canonical v4 prepare schema', function() {
    foreach ([
        'comments_conversations',
        'comments_identities',
        'comments_interactions',
        'comments_reports',
        'comments_subscriptions',
        'comments_mentions',
        'comments_assets',
        'comments_moderationactions',
        'comments_moderatornotes',
        'comments_restrictions',
    ] as $table) {
        expect(Craft::$app->getDb()->tableExists("{{%{$table}}}"))->toBeTrue();
    }

    expect(Craft::$app->getDb()->tableExists('{{%comments_conversation_summaries}}'))->toBeFalse();
    expect(Craft::$app->getDb()->tableExists('{{%comments_outbox}}'))->toBeFalse();
    expect(Craft::$app->getDb()->tableExists('{{%comments_comment_search}}'))->toBeFalse();
    expect(Craft::$app->getDb()->tableExists('{{%comments_votes}}'))->toBeFalse();
    expect(Craft::$app->getDb()->tableExists('{{%comments_flags}}'))->toBeFalse();
    expect(Craft::$app->getDb()->tableExists('{{%comments_subscribe}}'))->toBeFalse();

    expect(Craft::$app->getDb()->columnExists('{{%comments_comments}}', 'conversationId'))->toBeTrue();
    expect(Craft::$app->getDb()->columnExists('{{%comments_comments}}', 'identityId'))->toBeTrue();
    expect(Craft::$app->getDb()->columnExists('{{%comments_comments}}', 'parentId'))->toBeTrue();
    expect(Craft::$app->getDb()->columnExists('{{%comments_comments}}', 'moderationState'))->toBeTrue();
    expect(Craft::$app->getDb()->columnExists('{{%comments_comments}}', 'threadOrdinal'))->toBeFalse();
    expect(property_exists(\verbb\comments\models\Settings::class, 'structureUid'))->toBeFalse();
});

it('does not expose retired Craft Structure filters in the GraphQL comment query', function() {
    // Compatibility mode keeps Comments 3's, which `V4CompatibilityTest` covers.
    Comments::$plugin->getSettings()->compatibilityMode = false;
    $arguments = CommentArguments::getArguments();

    expect($arguments)->not->toHaveKeys([
        'structureId',
        'level',
        'hasDescendants',
        'ancestorOf',
        'descendantOf',
        'leaves',
        'nextSiblingOf',
        'prevSiblingOf',
        'positionedAfter',
        'positionedBefore',
    ]);
    expect($arguments)->toHaveKeys([
        'conversationId',
        'identityId',
        'moderationState',
        'rootId',
        'parentId',
    ]);
});

it('applies security policy before a canonical comment is committed', function() {
    $entry = CommentsFixtureFactory::entry('V4 security owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->securitySpamlist = 'buy cheap watches';
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'You should buy cheap watches today.',
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Spam);
    expect($result->comment->status)->toBe(\verbb\comments\elements\Comment::STATUS_SPAM);

    $settings->securityMaxLength = 5;

    expect(fn() => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Longer than five characters',
        'userId' => (int)$admin->id,
    ])))->toThrow(\yii\base\UserException::class);
});

it('keeps historical gif-only comments valid through a save', function() {
    $entry = CommentsFixtureFactory::entry('V4 gif-only owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Will become a GIF',
        'userId' => (int)$admin->id,
    ]))->comment;

    $comment->comment = '';
    $comment->bodyContent = '';
    $comment->gifUrl = 'https://media.giphy.com/media/example/giphy.gif';

    expect(Craft::$app->getElements()->saveElement($comment))->toBeTrue();
    expect((string)$comment->comment)->toBe('');
});

it('creates a comment through Comments::create', function() {
    $entry = CommentsFixtureFactory::entry('V4 create owner');
    $admin = Craft::$app->getUser()->getIdentity();

    // Disable moderation so create lands approved for aggregate coverage.
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Canonical create comment',
        'contentFormat' => ContentFormat::Plain,
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Approved);
    expect($result->comment->id)->toBeGreaterThan(0);
    expect($result->comment->conversationId)->not->toBeNull();
    expect($result->comment->identityId)->not->toBeNull();
    expect($result->comment->rootId)->toBe((int)$result->comment->id);
    expect((int)$result->comment->depth)->toBe(0);
    expect($result->comment->contentFormat)->toBe(ContentFormat::Plain->value);

    $row = (new Query())
        ->from('{{%comments_comments}}')
        ->where(['id' => $result->comment->id])
        ->one();

    expect($row['content'])->toBe('Canonical create comment');
    expect($row['moderationState'])->toBe('approved');
    expect((int)(new Query())
        ->from('{{%structureelements}}')
        ->where(['elementId' => $result->comment->id])
        ->count())
        ->toBe(0);

    $summary = Comments::$plugin->getConversations()->getSummary((int)$result->comment->conversationId);
    expect($summary)->not->toBeNull();
    expect($summary->commentCount)->toBe(1);

    $page = Comments::$plugin->getConversations()->getRootComments(
        (int)$result->comment->conversationId,
        CommentSort::Newest,
        10,
    );
    expect($page->comments)->toHaveCount(1);
    expect($page->next)->toBeNull();
});

it('creates a reply with parent/root/depth', function() {
    $entry = CommentsFixtureFactory::entry('V4 reply owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $root = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Root comment',
        'userId' => (int)$admin->id,
    ]));

    $reply = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'parentId' => $root->comment->id,
        'content' => 'Reply comment',
        'userId' => (int)$admin->id,
    ]));

    expect($reply->comment->parentId)->toBe((int)$root->comment->id);
    expect($reply->comment->rootId)->toBe((int)$root->comment->id);
    expect((int)$reply->comment->depth)->toBe(1);

    $replies = Comments::$plugin->getConversations()->getReplies((int)$root->comment->id);
    expect($replies)->toHaveCount(1);
    expect((int)$replies[0]->id)->toBe((int)$reply->comment->id);
});

it('reassigns a complete thread to another owner conversation', function() {
    $source = CommentsFixtureFactory::entry('V4 source owner');
    $target = CommentsFixtureFactory::entry('V4 target owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $root = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $source->id,
        'siteId' => $source->siteId,
        'content' => 'Thread to move',
        'userId' => (int)$admin->id,
    ]))->comment;

    $reply = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $source->id,
        'siteId' => $source->siteId,
        'parentId' => $root->id,
        'content' => 'Reply that moves with its root',
        'userId' => (int)$admin->id,
    ]))->comment;

    $sourceConversationId = (int)$root->conversationId;
    $targetConversation = Comments::$plugin->getConversations()->getOrCreateForOwner(
        (int)$target->id,
        (int)$target->siteId,
    );
    $root->ownerId = (int)$target->id;
    $root->ownerSiteId = (int)$target->siteId;
    $root->conversationId = (int)$targetConversation->id;
    Comments::$plugin->getConversations()->reassignThread(
        $root,
        $target,
        (int)$target->siteId,
        $sourceConversationId,
    );

    $rows = (new Query())
        ->select(['id', 'ownerId', 'ownerSiteId', 'conversationId'])
        ->from('{{%comments_comments}}')
        ->where(['id' => [$root->id, $reply->id]])
        ->indexBy('id')
        ->all();

    expect((int)$rows[$root->id]['ownerId'])->toBe((int)$target->id)
        ->and((int)$rows[$reply->id]['ownerId'])->toBe((int)$target->id)
        ->and((int)$rows[$root->id]['conversationId'])->toBe((int)$rows[$reply->id]['conversationId'])
        ->and((int)Comments::$plugin->getConversations()->getById($sourceConversationId)->commentCount)->toBe(0)
        ->and((int)Comments::$plugin->getConversations()->getById((int)$rows[$root->id]['conversationId'])->commentCount)->toBe(2);
});
