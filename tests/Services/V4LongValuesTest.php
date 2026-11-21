<?php
use craft\db\Query;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\models\CommentSubmission;

/**
 * Values longer than their columns: a long User-Agent is shortened, and a long guest name or comment is
 * refused with a field error rather than failing in the database.
 */

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $this->original = $settings->getAttributes();
    $settings->allowGuest = true;
    $settings->requireModeration = false;
    $settings->enableSpamChecks = false;
    $settings->securityMaxLength = null;
    $this->entry = CommentsFixtureFactory::entry('Long values');
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
});

function longValuesPost(array $values, ?string $userAgent = null): Comment
{
    $user = Craft::$app->getUser();
    $signedIn = $user->getIdentity();
    $user->setIdentity(null);

    try {
        return Comments::$plugin->getComments()->create(new CommentSubmission($values), function(Comment $comment) use ($userAgent): void {
            $comment->userAgent = $userAgent;
        })->comment;
    } finally {
        $user->setIdentity($signedIn);
    }
}

/** The field errors a refusal carries, keyed as `CommentValidationException` keys them (`fields[name]`). */
function longValuesErrors(callable $post): array
{
    try {
        $post();
    } catch (CommentValidationException $e) {
        return $e->fieldErrors;
    }

    return [];
}

it('saves a comment with a User-Agent longer than the column, shortened', function() {
    $agent = str_repeat('Mozilla/5.0 (Linux; Android 14) ', 9);
    $comment = longValuesPost(['ownerId' => $this->entry->id, 'siteId' => $this->entry->siteId, 'content' => 'Long agent', 'name' => 'Guest', 'email' => 'guest@example.test'], $agent);
    $stored = (new Query())->select(['userAgent'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar();

    expect(mb_strlen($agent))->toBeGreaterThan(255)
        ->and($stored)->toBe(mb_substr($agent, 0, 255));
});

it('refuses a guest name longer than 255 characters with a field error', function() {
    $errors = longValuesErrors(fn() => longValuesPost(['ownerId' => $this->entry->id, 'siteId' => $this->entry->siteId, 'content' => 'Long name', 'name' => str_repeat('n', 256), 'email' => 'guest@example.test']));

    expect($errors)->toHaveKey('fields[name]')
        ->and(longValuesPost(['ownerId' => $this->entry->id, 'siteId' => $this->entry->siteId, 'content' => 'Long enough name', 'name' => str_repeat('n', 255), 'email' => 'guest@example.test'])->id)->not->toBeNull();
});

it('refuses a comment over 65,535 bytes with a field error', function() {
    $errors = longValuesErrors(fn() => longValuesPost(['ownerId' => $this->entry->id, 'siteId' => $this->entry->siteId, 'content' => str_repeat('a', 70000), 'name' => 'Guest', 'email' => 'guest@example.test']));

    expect($errors)->toHaveKey('fields[comment]')
        ->and(longValuesPost(['ownerId' => $this->entry->id, 'siteId' => $this->entry->siteId, 'content' => str_repeat('a', 65000), 'name' => 'Guest', 'email' => 'guest@example.test'])->id)->not->toBeNull();
});
