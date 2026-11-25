<?php
use craft\events\ModelEvent;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\exceptions\ApiException;
use verbb\comments\models\CommentSubmission;
use yii\base\Event;

/**
 * A `beforeSave` listener that refuses a comment, on posting or editing, gives the visitor its own
 * message beside the field, as a validation error does, rather than the comment's errors as JSON.
 */

function beforeSaveRefusal(callable $callback): ?ApiException
{
    try {
        $callback();
    } catch (Throwable $e) {
        return Comments::$plugin->getApi()->errorFor($e);
    }

    return null;
}

function beforeSaveRefusalPost($entry, int $userId, string $text): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => $userId,
    ]))->comment;
}

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;

    $this->addsError = true;
    $this->listener = function(ModelEvent $event) {
        /** @var Comment $comment */
        $comment = $event->sender;

        if (str_contains((string)$comment->comment, 'http')) {
            if ($this->addsError) {
                $comment->addError('comment', 'Links aren’t allowed in comments.');
            }

            $event->isValid = false;
        }
    };
    Event::on(Comment::class, Comment::EVENT_BEFORE_SAVE, $this->listener);

    $this->entry = CommentsFixtureFactory::entry('Before save refusal ' . bin2hex(random_bytes(3)));
    $this->author = CommentsFixtureFactory::user('beforeSaveRefusal' . bin2hex(random_bytes(3)));
});

afterEach(function() {
    Event::off(Comment::class, Comment::EVENT_BEFORE_SAVE, $this->listener);
});

it('shows a listener’s error beside the field when it refuses a new comment', function() {
    $error = beforeSaveRefusal(fn() => beforeSaveRefusalPost($this->entry, (int)$this->author->id, 'See http://example.com'));

    expect($error)->not->toBeNull()
        ->and($error->errorCode)->toBe('validation_failed')
        ->and($error->getMessage())->toBe('Links aren’t allowed in comments.')
        ->and($error->fieldErrors)->toBe(['content' => ['Links aren’t allowed in comments.']])
        ->and(Comment::find()->ownerId($this->entry->id)->status(null)->ids())->toBe([]);
});

it('gives a plain message when a listener refuses a new comment without saying why', function() {
    $this->addsError = false;

    $error = beforeSaveRefusal(fn() => beforeSaveRefusalPost($this->entry, (int)$this->author->id, 'See http://example.com'));

    expect($error)->not->toBeNull()
        ->and($error->errorCode)->toBe('validation_failed')
        ->and($error->getMessage())->toBe('Could not save comment.');
});

it('shows a listener’s error beside the field when it refuses an edit', function() {
    $comment = beforeSaveRefusalPost($this->entry, (int)$this->author->id, 'A first draft');
    Craft::$app->getUser()->setIdentity($this->author);
    Comments::$plugin->getIdentities()->clearCaches();

    $comment->comment = 'Now with http://example.com';
    $error = beforeSaveRefusal(fn() => Comments::$plugin->getComments()->savePublicUpdate($comment, null));

    expect($error)->not->toBeNull()
        ->and($error->errorCode)->toBe('validation_failed')
        ->and($error->getMessage())->toBe('Links aren’t allowed in comments.')
        ->and($error->fieldErrors)->toBe(['content' => ['Links aren’t allowed in comments.']]);
});
