<?php
use craft\db\Query;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Fixtures\MultiSiteFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;

/**
 * Deleting Comments must keep the hierarchy foreign keys satisfiable and the
 * Conversation aggregate honest.
 */

function quietSettings(): void
{
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
}

function newComment($entry, string $content, ?int $parentId = null): Comment
{
    $admin = Craft::$app->getUser()->getIdentity();

    return Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)$admin->id,
        'parentId' => $parentId,
    ], static fn($v) => $v !== null)))->comment;
}

it('hard-deletes a root comment despite the self-referencing rootId', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('Delete root owner');
    $comment = newComment($entry, 'A lone root comment');
    $id = (int)$comment->id;

    // Roots store their own id in rootId.
    expect((int)$comment->rootId)->toBe($id);

    expect(Craft::$app->getElements()->deleteElement($comment, true))->toBeTrue();
    expect(Comment::find()->id($id)->status(null)->trashed(null)->one())->toBeNull();
});

it('refuses to hard-delete a comment that still has replies', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('Delete parent owner');
    $root = newComment($entry, 'Root with a reply');
    newComment($entry, 'The reply', (int)$root->id);

    expect(Craft::$app->getElements()->deleteElement($root, true))->toBeFalse();
    expect($root->getErrors('id'))->not->toBeEmpty();

    // The root must survive a refused delete.
    expect(Comment::find()->id((int)$root->id)->status(null)->one())->not->toBeNull();
});

it('hard-deletes a whole thread when replies go first', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('Delete thread owner');
    $root = newComment($entry, 'Thread root');
    $reply = newComment($entry, 'Thread reply', (int)$root->id);

    $elements = Craft::$app->getElements();

    expect($elements->deleteElement($reply, true))->toBeTrue();
    expect($elements->deleteElement($root, true))->toBeTrue();

    $conversationId = (int)$root->conversationId;
    expect((int)Comments::$plugin->getConversations()->publicCommentQuery($conversationId)->count())->toBe(0);
});

it('decrements the conversation aggregate when a comment is deleted', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('Delete aggregate owner');
    $first = newComment($entry, 'First');
    newComment($entry, 'Second');

    $conversations = Comments::$plugin->getConversations();
    $conversationId = (int)$first->conversationId;

    expect((int)$conversations->getById($conversationId)->commentCount)->toBe(2);

    Craft::$app->getElements()->deleteElement($first, true);

    expect((int)$conversations->getById($conversationId)->commentCount)->toBe(1);
});

it('excludes trashed comments from the aggregate and restores them on undelete', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('Trash aggregate owner');
    $comment = newComment($entry, 'Trash me');

    $conversations = Comments::$plugin->getConversations();
    $conversationId = (int)$comment->conversationId;
    $elements = Craft::$app->getElements();

    expect((int)$conversations->getById($conversationId)->commentCount)->toBe(1);

    // Soft delete keeps the row and its approved state, so the count must come from a
    // query that also honours elements.dateDeleted.
    $elements->deleteElement($comment);
    expect((int)$conversations->getById($conversationId)->commentCount)->toBe(0);

    $trashed = Comment::find()->id((int)$comment->id)->status(null)->trashed()->one();
    $elements->restoreElement($trashed);

    expect((int)$conversations->getById($conversationId)->commentCount)->toBe(1);
});

it('reports no aggregate drift once counts are correct', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('Drift owner');
    $comment = newComment($entry, 'Counted');

    Craft::$app->getElements()->deleteElement($comment);

    // The drift check and the rebuild share one query, so a clean bill of health here
    // means the two definitions genuinely agree.
    expect(Comments::$plugin->getDiagnostics()->getHealthSnapshot()['aggregateDrift'])->toBe(0);
});

function purgeTrash(): void
{
    $gc = Craft::$app->getGc();
    $gc->deleteAllTrashed = true;
    ob_start();

    try {
        $gc->run(true);
    } finally {
        ob_end_clean();
        $gc->deleteAllTrashed = false;
    }
}

it('survives garbage collection of a trashed comment that still has replies', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('GC thread owner');
    $trashedEntry = CommentsFixtureFactory::entry('GC bystander');
    $root = newComment($entry, 'Root');
    $parent = newComment($entry, 'Parent', (int)$root->id);
    $reply = newComment($entry, 'Reply', (int)$parent->id);
    $deep = newComment($entry, 'Deep reply', (int)$reply->id);

    Craft::$app->getElements()->deleteElement($parent);
    Craft::$app->getElements()->deleteElement($trashedEntry);

    purgeTrash();

    $row = fn(int $id) => (new Query())->from('{{%comments_comments}}')->where(['id' => $id])->one();

    // One failed delete would otherwise block every element type from being purged.
    expect((new Query())->from('{{%elements}}')->where(['id' => [$parent->id, $trashedEntry->id]])->exists())->toBeFalse()
        ->and($row((int)$reply->id))->toMatchArray(['parentId' => $root->id, 'rootId' => $root->id, 'depth' => 1])
        ->and($row((int)$deep->id))->toMatchArray(['parentId' => $reply->id, 'rootId' => $root->id, 'depth' => 2]);
});

it('starts a new thread when garbage collection removes a whole root', function() {
    quietSettings();
    $entry = CommentsFixtureFactory::entry('GC root owner');
    $root = newComment($entry, 'Root');
    $lone = newComment($entry, 'Lone root');
    $reply = newComment($entry, 'Reply', (int)$root->id);
    $deep = newComment($entry, 'Deep reply', (int)$reply->id);

    Craft::$app->getElements()->deleteElement($root);
    Craft::$app->getElements()->deleteElement($lone);

    purgeTrash();

    $row = fn(int $id) => (new Query())->from('{{%comments_comments}}')->where(['id' => $id])->one();

    expect($row((int)$root->id))->toBeNull()
        ->and($row((int)$lone->id))->toBeNull()
        ->and($row((int)$reply->id))->toMatchArray(['parentId' => null, 'rootId' => $reply->id, 'depth' => 0])
        ->and($row((int)$deep->id))->toMatchArray(['parentId' => $reply->id, 'rootId' => $reply->id, 'depth' => 1]);
});

it('can delete a site that has threaded comments', function() {
    quietSettings();
    $pair = MultiSiteFixtureFactory::sameOwnerDifferentSites('Site delete owner');
    $secondary = $pair['secondary'];
    $admin = Craft::$app->getUser()->getIdentity();

    $submit = fn(?int $parentId, $owner) => Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $owner->id,
        'siteId' => $owner->siteId,
        'content' => 'Comment',
        'userId' => (int)$admin->id,
        'parentId' => $parentId,
    ], static fn($v) => $v !== null)))->comment;

    $keptRoot = $submit(null, $pair['primary']);
    $root = $submit(null, $secondary);
    $reply = $submit((int)$root->id, $secondary);

    expect(Craft::$app->getSites()->deleteSiteById($secondary->siteId))->toBeTrue();

    // Craft soft-deletes the site, so its rows only cascade when the trash is purged.
    purgeTrash();

    $ids = (new Query())->select(['id'])->from('{{%comments_comments}}')->column();

    expect(array_map('intval', $ids))->toContain((int)$keptRoot->id)
        ->not->toContain((int)$root->id)
        ->not->toContain((int)$reply->id);
});

it('uses the deletion-safe foreign keys on a fresh install', function() {
    $schema = Craft::$app->getDb();
    $rules = (new Query())
        ->select(['DELETE_RULE'])
        ->from('information_schema.REFERENTIAL_CONSTRAINTS')
        ->where(['CONSTRAINT_SCHEMA' => new \yii\db\Expression('DATABASE()')])
        ->andWhere(['TABLE_NAME' => $schema->getSchema()->getRawTableName('{{%comments_comments}}')])
        ->andWhere(['REFERENCED_TABLE_NAME' => $schema->getSchema()->getRawTableName('{{%comments_comments}}')])
        ->column();

    expect($rules)->toBe(['SET NULL', 'SET NULL']);
});
