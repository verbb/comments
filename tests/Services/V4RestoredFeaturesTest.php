<?php
use craft\models\GqlSchema;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\InteractionType;
use verbb\comments\helpers\CommentsHelper;
use verbb\comments\helpers\Gql as CommentsGql;
use verbb\comments\models\CommentSubmission;

/**
 * Comments 3 features brought back in SD-57: comments folded away after reports or downvotes, author
 * scores, and commenters' addresses on private GraphQL schemas.
 */

function restoredComment($entry, string $text, ?int $userId = null): Comment
{
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => $userId ?? (int)Craft::$app->getUser()->getIdentity()->id,
    ]))->comment;
}

function restoredThreadComment($entry, Comment $comment): array
{
    Comments::$plugin->getApi()->invalidateCaches();
    $items = Comments::$plugin->getApi()->thread($entry)['comments'];

    return array_values(array_filter($items, static fn(array $item): bool => $item['uid'] === $comment->uid))[0];
}

function restoredIdentity(): int
{
    return (int)Comments::$plugin->getIdentities()->getOrCreateForUser(CommentsFixtureFactory::user('restored'))->id;
}

function restoredGql(array $scope, string $query, bool $public = false): array
{
    $schema = new GqlSchema(['name' => 'Restored', 'scope' => $scope, 'isPublic' => $public]);
    Craft::$app->getGql()->flushCaches();
    Craft::$app->getGql()->setActiveSchema($schema);

    return Craft::$app->getGql()->executeQuery($schema, $query, debugMode: true);
}

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->allowFlagging = true;
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Craft::$app->getUser()->setIdentity($this->admin);
    Craft::$app->getGql()->setActiveSchema(null);
    Craft::$app->getGql()->flushCaches();
});

// Folding
// =========================================================================

it('folds a comment away once it has enough open reports, and unfolds it when they’re settled', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->flaggedCommentLimit = 2;
    $entry = CommentsFixtureFactory::entry('Folded by reports');
    $comment = restoredComment($entry, 'Rude');
    $reports = Comments::$plugin->getReports();

    $reports->create((int)$comment->id, restoredIdentity(), 'spam');
    expect(restoredThreadComment($entry, $comment)['folded'])->toBeNull();

    $second = $reports->create((int)$comment->id, restoredIdentity(), 'harassment');
    expect(restoredThreadComment($entry, $comment)['folded'])->toBe('reports')
        // Still public and approved: only the thread folds it.
        ->and(restoredThreadComment($entry, $comment)['state'])->toBe('approved');

    $reports->dismiss((int)$second->id);
    expect(restoredThreadComment($entry, $comment)['folded'])->toBeNull();

    $settings->flaggedCommentLimit = '';
    $reports->create((int)$comment->id, restoredIdentity(), 'spam');
    expect(restoredThreadComment($entry, $comment)['folded'])->toBeNull();
});

it('folds a comment away after enough downvotes, only with up and down voting', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->downvoteCommentLimit = 2;
    $settings->interactionMode = 'updown';
    $entry = CommentsFixtureFactory::entry('Folded by downvotes');
    $comment = restoredComment($entry, 'Unpopular');
    $interactions = Comments::$plugin->getInteractions();

    $interactions->set((int)$comment->id, restoredIdentity(), InteractionType::Downvote);
    $interactions->set((int)$comment->id, restoredIdentity(), InteractionType::Downvote);

    expect(restoredThreadComment($entry, $comment)['folded'])->toBe('downvotes')
        ->and(Comment::find()->id($comment->id)->one()->isPoorlyRated())->toBeTrue();

    $settings->interactionMode = 'like';
    expect(restoredThreadComment($entry, $comment)['folded'])->toBeNull();
});

// Author scores
// =========================================================================

it('shows a signed-in author’s net votes across their approved comments, when on', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = 'updown';
    $settings->showAuthorScore = true;
    $entry = CommentsFixtureFactory::entry('Author scores');
    $author = CommentsFixtureFactory::user('scored');
    $first = restoredComment($entry, 'First', (int)$author->id);
    $second = restoredComment($entry, 'Second', (int)$author->id);
    $interactions = Comments::$plugin->getInteractions();

    $interactions->set((int)$first->id, restoredIdentity(), InteractionType::Upvote);
    $interactions->set((int)$first->id, restoredIdentity(), InteractionType::Upvote);
    $interactions->set((int)$second->id, restoredIdentity(), InteractionType::Downvote);

    expect(restoredThreadComment($entry, $second)['author']['score'])->toBe(1)
        ->and(Comment::find()->id($first->id)->one()->getAuthorScore())->toBe(1);

    $settings->showAuthorScore = false;
    expect(restoredThreadComment($entry, $second)['author']['score'])->toBeNull();
});

it('gives guests no score', function() {
    Comments::$plugin->getSettings()->showAuthorScore = true;
    $entry = CommentsFixtureFactory::entry('Guest score');
    $guest = CommentsFixtureFactory::guestComment($entry, 'A guest');

    expect(restoredThreadComment($entry, $guest)['author']['score'])->toBeNull();
});

// GraphQL addresses
// =========================================================================

it('gives guests’ addresses only to a private schema allowed to read them', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL addresses');
    CommentsFixtureFactory::guestComment($entry, 'Guest with an address', 'Guest', 'guest-gql@example.test');
    $query = "{ comments(ownerId: {$entry->id}, email: \"guest-gql@example.test\") { email } }";
    Craft::$app->getUser()->setIdentity(null);

    $private = restoredGql(['comments:read', CommentsGql::EMAILS_SCOPE], $query);
    expect($private['errors'] ?? [])->toBe([])
        ->and(array_column($private['data']['comments'], 'email'))->toBe(['guest-gql@example.test']);

    // Without the permission, or on a public schema, there's no such field or argument.
    expect(restoredGql(['comments:read'], $query))->toHaveKey('errors')
        ->and(restoredGql(['comments:read', CommentsGql::EMAILS_SCOPE], $query, true))->toHaveKey('errors');
});

it('only treats a private schema with the permission as able to see addresses', function() {
    Craft::$app->getUser()->setIdentity(null);
    $gql = Craft::$app->getGql();

    $gql->setActiveSchema(new GqlSchema(['scope' => ['comments:read', CommentsGql::EMAILS_SCOPE]]));
    expect(CommentsHelper::canViewAddresses())->toBeTrue();

    $gql->setActiveSchema(new GqlSchema(['scope' => ['comments:read', CommentsGql::EMAILS_SCOPE], 'isPublic' => true]));
    expect(CommentsHelper::canViewAddresses())->toBeFalse();

    $gql->setActiveSchema(null);
    expect(CommentsHelper::canViewAddresses())->toBeFalse();
});
