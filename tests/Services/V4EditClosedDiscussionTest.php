<?php
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\exceptions\ApiException;
use verbb\comments\models\CommentSubmission;

/**
 * An author's own comment in a discussion that's closed, by its Comments field or Close Comments
 * Globally, isn't offered for editing, and an edit is refused the same way, spam or not. An open
 * discussion and one closed by Auto-close take edits as before.
 */

function closedEditAs(?User $user): void
{
    Craft::$app->getUser()->setIdentity($user);
    Comments::$plugin->getIdentities()->clearCaches();
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getApi()->invalidateCaches();
}

function closedEditPost($entry, string $text, User $user): Comment
{
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => (int)$user->id,
    ]))->comment;
}

function closedEditError(callable $callback): ?string
{
    try {
        $callback();
    } catch (Throwable $e) {
        return Comments::$plugin->getApi()->errorFor($e)->errorCode;
    }

    return null;
}

function closedEditUpdate(Comment $comment, string $text): array
{
    return Comments::$plugin->getApi()->update(['operationId' => 'closed-edit-' . bin2hex(random_bytes(6)), 'uid' => $comment->uid, 'content' => ['format' => 'plain', 'text' => $text]]);
}

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $this->original = $settings->getAttributes();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->closed = false;
    $settings->autoCloseDays = null;
    $this->author = CommentsFixtureFactory::user('closedEditAuthor');
    $this->entry = CommentsFixtureFactory::entry('Closed edit ' . bin2hex(random_bytes(3)));
    $this->comment = closedEditPost($this->entry, 'Before', $this->author);
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
    closedEditAs(null);
});

it('lets an author edit their comment in an open discussion', function() {
    closedEditAs($this->author);

    expect(Comments::$plugin->getApi()->viewer($this->entry)['comments'][$this->comment->uid]['canEdit'])->toBeTrue()
        ->and(closedEditError(fn() => Comments::$plugin->getApi()->edit($this->comment->uid)))->toBeNull()
        ->and(closedEditUpdate($this->comment, 'After')['comment']['content']['text'])->toBe('After');
});

it('offers no edit, and refuses one, in a discussion closed by its field', function() {
    Comments::$plugin->getConversations()->setClosed((int)$this->entry->id, (int)$this->entry->siteId, true);
    closedEditAs($this->author);

    expect(Comments::$plugin->getApi()->viewer($this->entry)['comments'][$this->comment->uid]['canEdit'])->toBeFalse()
        ->and(closedEditError(fn() => Comments::$plugin->getApi()->edit($this->comment->uid)))->toBe('forbidden')
        ->and(closedEditError(fn() => closedEditUpdate($this->comment, 'After')))->toBe('forbidden');
});

it('offers no edit, and refuses one, while comments are closed globally', function() {
    Comments::$plugin->getSettings()->closed = true;
    closedEditAs($this->author);

    expect(Comments::$plugin->getApi()->viewer($this->entry)['comments'][$this->comment->uid]['canEdit'])->toBeFalse()
        ->and(closedEditError(fn() => Comments::$plugin->getApi()->edit($this->comment->uid)))->toBe('forbidden')
        ->and(closedEditError(fn() => closedEditUpdate($this->comment, 'After')))->toBe('forbidden');
});

it('answers an author’s spam comment in a closed discussion like any other', function() {
    Comments::$plugin->getModeration()->markSpam($this->comment);
    closedEditAs($this->author);

    // Open: it can be opened for editing, as one waiting can.
    expect(Comments::$plugin->getApi()->viewer($this->entry)['comments'][$this->comment->uid]['canEdit'] ?? null)->toBeTrue()
        ->and(closedEditError(fn() => Comments::$plugin->getApi()->edit($this->comment->uid)))->toBeNull();

    Comments::$plugin->getConversations()->setClosed((int)$this->entry->id, (int)$this->entry->siteId, true);
    closedEditAs($this->author);

    expect(Comments::$plugin->getApi()->viewer($this->entry)['comments'][$this->comment->uid]['canEdit'] ?? null)->toBeFalse()
        ->and(closedEditError(fn() => Comments::$plugin->getApi()->edit($this->comment->uid)))->toBe('forbidden');
});

it('still takes an edit in a discussion closed by Auto-close', function() {
    $this->entry->postDate = new DateTime('-60 days');
    expect(Craft::$app->getElements()->saveElement($this->entry))->toBeTrue();
    Comments::$plugin->getSettings()->autoCloseDays = 30;
    closedEditAs($this->author);

    expect(Comments::$plugin->getComments()->checkExpired($this->entry))->toBeTrue()
        ->and(Comments::$plugin->getApi()->viewer($this->entry)['comments'][$this->comment->uid]['canEdit'])->toBeTrue()
        ->and(closedEditUpdate($this->comment, 'After')['comment']['content']['text'])->toBe('After');
});
