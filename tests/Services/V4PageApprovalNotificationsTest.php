<?php
use craft\base\Element;
use craft\db\Query;
use craft\elements\Entry;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\queue\jobs\SendNotification;

/**
 * Approving a held comment from its page, with the Approve decision or the Status menu, queues each
 * notification once, as the index and the entry panel do. A developer's direct save from pending to
 * approved still queues them.
 */

function pageApprovalHeldReply(): Comment
{
    $settings = Comments::$plugin->getSettings();
    $admin = Craft::$app->getUser()->getIdentity();
    $member = CommentsFixtureFactory::user('pageApprovalMember');
    $entry = CommentsFixtureFactory::entry('Page approval ' . uniqid());

    $settings->requireModeration = false;
    $parent = Comments::$plugin->getComments()->create(new CommentSubmission(['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'content' => 'Parent', 'userId' => (int)$admin->id]))->comment;
    $settings->requireModeration = true;
    // Each request works out an owner's settings once; this stands in for the next request.
    Comments::$plugin->getConversations()->clearCaches();

    $reply = Comments::$plugin->getComments()->create(new CommentSubmission(['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'parentId' => $parent->id, 'content' => 'Held reply', 'userId' => (int)$member->id]))->comment;
    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();
    expect($reply->status)->toBe(Comment::STATUS_PENDING);

    return $reply;
}

/** The notification types queued for a comment, one entry per job. */
function pageApprovalJobTypes(Comment $comment): array
{
    $types = [];

    foreach ((new Query())->from('{{%queue}}')->all() as $row) {
        $job = unserialize(is_resource($row['job']) ? stream_get_contents($row['job']) : $row['job']);

        if ($job instanceof SendNotification && (int)$job->commentId === (int)$comment->id) {
            $types[] = $job->type;
        }
    }

    sort($types);

    return $types;
}

function pageApprovalPost(Comment $comment, array $values): void
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    Craft::$app->getRequest()->setBodyParams($values + [
        'status' => $comment->status,
        'authorId' => [$comment->userId],
        'ownerType' => Entry::class,
        'ownerEntryId' => [$comment->ownerId],
        'contentFormat' => 'plain',
        'fields' => ['comment' => $comment->comment],
    ]);

    $editing = Comment::find()->id($comment->id)->status(null)->one();
    $editing->setAttributesFromRequest([]);
    $editing->setScenario(Element::SCENARIO_LIVE);
    expect(Craft::$app->getElements()->saveElement($editing))->toBeTrue();
}

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $this->original = $settings->getAttributes();
    $settings->useQueueForNotifications = true;
    $settings->participantNotifications = [Settings::NOTIFICATION_REPLY, Settings::NOTIFICATION_APPROVED];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
});

afterEach(function() {
    unset($_SERVER['REQUEST_METHOD']);
    Craft::$app->getRequest()->setBodyParams([]);
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
    Craft::$app->getDb()->createCommand()->delete('{{%queue}}')->execute();
});

it('queues each notification once when the page’s Approve decides a held comment', function() {
    $reply = pageApprovalHeldReply();
    pageApprovalPost($reply, ['decision' => 'approve']);
    $types = pageApprovalJobTypes($reply);

    expect(Comment::find()->id($reply->id)->status(null)->one()->status)->toBe(Comment::STATUS_APPROVED)
        ->and($types)->not->toBeEmpty()
        ->and($types)->toBe(array_values(array_unique($types)));
});

it('queues each notification once when the page’s Status menu approves a held comment', function() {
    $reply = pageApprovalHeldReply();
    pageApprovalPost($reply, ['status' => Comment::STATUS_APPROVED]);
    $types = pageApprovalJobTypes($reply);

    expect(Comment::find()->id($reply->id)->status(null)->one()->status)->toBe(Comment::STATUS_APPROVED)
        ->and($types)->not->toBeEmpty()
        ->and($types)->toBe(array_values(array_unique($types)));
});

it('still queues the notifications for a direct save from pending to approved', function() {
    $reply = pageApprovalHeldReply();
    $saving = Comment::find()->id($reply->id)->status(null)->one();
    $saving->status = Comment::STATUS_APPROVED;
    expect(Craft::$app->getElements()->saveElement($saving))->toBeTrue();
    $types = pageApprovalJobTypes($reply);

    expect($types)->not->toBeEmpty()
        ->and($types)->toBe(array_values(array_unique($types)));
});
