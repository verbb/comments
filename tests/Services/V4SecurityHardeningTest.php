<?php
use craft\base\Element;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\controllers\CommentsController;
use verbb\comments\controllers\FrontendApiController;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\services\Protection;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Comments as CommentsService;
use verbb\comments\services\Moderation;

use craft\behaviors\SessionBehavior;
use craft\elements\Entry;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\models\FieldLayoutTab;
use craft\models\GqlSchema;

use yii\base\UserException;
use yii\validators\RequiredValidator;

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->allowGuest = true;
    $settings->allowFlagging = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    Craft::$app->getConfig()->getGeneral()->enableGraphqlCaching = false;
});

afterEach(function() {
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Craft::$app->getGql()->flushCaches();
    Craft::$app->getRequest()->setBodyParams([]);
    Craft::$app->getRequest()->setIsCpRequest(false);
    (new ReflectionProperty(\craft\web\Request::class, '_ipAddress'))->setValue(Craft::$app->getRequest(), null);
    Craft::$app->getRequest()->setIsConsoleRequest(true);
    unset($_SERVER['REQUEST_METHOD']);
});

function hardeningComment(Entry $entry, User $author, string $content = 'Hardening comment', ?int $parentId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'parentId' => $parentId,
        'content' => $content,
        'userId' => (int)$author->id,
    ]))->comment;
}

function hardeningUser(string $prefix): User
{
    return CommentsFixtureFactory::user($prefix . bin2hex(random_bytes(3)));
}

function hardeningGql(array $scope, string $query, bool $public = false): array
{
    $schema = new GqlSchema(['name' => 'Hardening', 'scope' => $scope, 'isPublic' => $public]);
    Craft::$app->getGql()->flushCaches();
    Craft::$app->getGql()->setActiveSchema($schema);

    return Craft::$app->getGql()->executeQuery($schema, $query, debugMode: true);
}

/**
 * Runs as GraphQL Authentication does: the token names the user, and there's no session.
 */
function hardeningAsTokenUser(?User $user, Closure $run): mixed
{
    $plugin = Comments::$plugin;
    $original = $plugin->getService();
    $plugin->set('service', new class($user) extends verbb\comments\services\Service {
        public function __construct(private ?User $tokenUser)
        {
            parent::__construct();
        }

        public function getUser(): ?User
        {
            return $this->tokenUser;
        }
    });
    Craft::$app->getUser()->setIdentity(null);

    try {
        return $run();
    } finally {
        $plugin->set('service', $original);
    }
}

function hardeningGuestIdentityCount(): int
{
    return (int)(new craft\db\Query())->from('{{%comments_identities}}')->where(['type' => 'guest'])->count();
}

it('removes the retired v3 frontend surface', function() {
    expect(method_exists(CommentsController::class, 'actionTrash'))->toBeFalse()
        ->and(method_exists(CommentsController::class, 'actionGetJsVariables'))->toBeFalse()
        ->and(method_exists(CommentsService::class, 'renderComment'))->toBeFalse()
        ->and(method_exists(CommentsService::class, 'getRenderVariables'))->toBeFalse()
        ->and(is_dir(Craft::getAlias('@verbb/comments/templates/_special')))->toBeFalse();
});

it('stops authors editing their own spam and removed comments', function() {
    $entry = CommentsFixtureFactory::entry('Edit state owner');
    $author = hardeningUser('editstate');
    $comment = hardeningComment($entry, $author);
    $moderation = Comments::$plugin->getModeration();

    Craft::$app->getUser()->setIdentity($author);
    expect($comment->canEdit())->toBeTrue();

    $spam = $moderation->markSpam($comment, $this->admin);
    expect($spam->canEdit())->toBeFalse();

    $removed = $moderation->remove($spam, $this->admin);
    expect($removed->canEdit())->toBeFalse();
});

it('keeps a held comment held when its author edits it under post-moderation', function() {
    $entry = CommentsFixtureFactory::entry('Edit held owner');
    $author = hardeningUser('editheld');
    $settings = Comments::$plugin->getSettings();

    $settings->requireModeration = true;
    $comment = hardeningComment($entry, $author);
    expect($comment->moderationState)->toBe(ModerationState::Pending->value);

    $settings->requireModeration = false;
    Comments::$plugin->getConversations()->clearCaches();
    Craft::$app->getUser()->setIdentity($author);

    expect(Comments::$plugin->getComments()->applyEditModerationState($comment))->toBe(ModerationState::Pending)
        ->and($comment->status)->toBe(Comment::STATUS_PENDING);
});

it('applies participation restrictions to edits', function() {
    $entry = CommentsFixtureFactory::entry('Edit restricted owner');
    $author = hardeningUser('editrestricted');
    $comment = hardeningComment($entry, $author);
    $moderation = Comments::$plugin->getModeration();
    $service = Comments::$plugin->getComments();

    $restriction = $moderation->moderateIdentity((int)$comment->identityId, null, $this->admin);
    expect($service->applyEditModerationState($comment))->toBe(ModerationState::Pending);
    $moderation->revokeRestriction((int)$restriction->id, $this->admin);

    $moderation->blockIdentity((int)$comment->identityId, null, $this->admin);
    $service->applyEditModerationState($comment);
})->throws(UserException::class, 'You are not permitted to comment');

it('records the state change when an edit sends a comment back for review', function() {
    $entry = CommentsFixtureFactory::entry('Edit history owner');
    $author = hardeningUser('edithistory');
    $comment = hardeningComment($entry, $author, 'Published text');
    $moderation = Comments::$plugin->getModeration();
    $service = Comments::$plugin->getComments();

    Comments::$plugin->getSettings()->requireModeration = true;
    Comments::$plugin->getConversations()->clearCaches();
    Craft::$app->getUser()->setIdentity($author);

    $comment->comment = 'Edited text';
    $service->applyEditModerationState($comment);
    $service->savePublicUpdate($comment, null);

    $actions = $moderation->getActions((int)$comment->id);
    $last = end($actions);

    expect($last->action)->toBe(Moderation::ACTION_AUTHOR_EDIT)
        ->and($last->fromState)->toBe(ModerationState::Approved->value)
        ->and($last->toState)->toBe(ModerationState::Pending->value);

    Comments::$plugin->getConversations()->clearCaches();
    expect((int)Comments::$plugin->getConversations()->getById((int)$comment->conversationId)->commentCount)->toBe(0);
});

it('only lets GraphQL edits change content', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL edit owner');
    $other = CommentsFixtureFactory::entry('GraphQL other owner');
    $author = hardeningUser('gqledit');
    $sibling = hardeningComment($entry, $author, 'Sibling');
    $comment = hardeningComment($entry, $author, 'Original');

    Craft::$app->getUser()->setIdentity($author);

    $result = hardeningGql(['comments:read', 'comments:edit', 'comments:save'], <<<GQL
        mutation { saveComment(id: {$comment->id}, comment: "Edited", ownerId: {$other->id}, parentId: {$sibling->id}, name: "Spoofed") { id } }
    GQL);

    expect($result['errors'] ?? [])->toBe([]);

    $saved = Comment::find()->id($comment->id)->status(null)->one();

    expect($saved->comment)->toBe('Edited')
        ->and((int)$saved->ownerId)->toBe((int)$entry->id)
        ->and($saved->parentId)->toBeNull()
        ->and((int)$saved->userId)->toBe((int)$author->id);
});

it('no longer accepts author or date arguments on GraphQL mutations', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL arguments owner');
    $author = hardeningUser('gqlargs');
    $comment = hardeningComment($entry, $author);

    Craft::$app->getUser()->setIdentity($author);

    $result = hardeningGql(['comments:read', 'comments:edit', 'comments:save'], <<<GQL
        mutation { saveComment(id: {$comment->id}, comment: "Edited", userId: {$this->admin->id}) { id } }
    GQL);

    expect($result)->toHaveKey('errors');
    expect((int)Comment::find()->id($comment->id)->status(null)->one()->userId)->toBe((int)$author->id);
});

it('stops GraphQL authors republishing their own spam', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL spam owner');
    $author = hardeningUser('gqlspam');
    $comment = hardeningComment($entry, $author);
    Comments::$plugin->getModeration()->markSpam($comment, $this->admin);

    Craft::$app->getUser()->setIdentity($author);

    $result = hardeningGql(['comments:read', 'comments:edit', 'comments:save'], <<<GQL
        mutation { saveComment(id: {$comment->id}, comment: "Back again") { id } }
    GQL);

    expect($result)->toHaveKey('errors');
    expect(Comment::find()->id($comment->id)->status(null)->one()->status)->toBe(Comment::STATUS_SPAM);
});

it('only exposes published comments on public GraphQL schemas', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL public owner');
    $author = hardeningUser('gqlpublic');
    $moderation = Comments::$plugin->getModeration();

    $published = hardeningComment($entry, $author, 'Published');
    $spam = hardeningComment($entry, $author, 'Spam');
    $moderation->markSpam($spam, $this->admin);
    $removedParent = hardeningComment($entry, $author, 'Removed parent');
    $child = hardeningComment($entry, $author, 'Child', (int)$removedParent->id);
    $hiddenChild = hardeningComment($entry, $author, 'Hidden child', (int)$published->id);
    $moderation->markSpam($hiddenChild, $this->admin);
    $moderation->remove($removedParent, $this->admin);

    // The `status` argument only exists on schemas allowed to query inactive elements.
    $public = hardeningGql(['comments:read', 'elements.inactive:read'], <<<GQL
        { comments(ownerId: {$entry->id}, status: ["approved", "spam", "removed", "pending"]) { id parent { id } children(status: ["spam"]) { id } } }
    GQL, true);

    expect($public['errors'] ?? [])->toBe([]);

    $rows = collect($public['data']['comments'])->keyBy('id');

    expect($rows->keys()->map(fn($id) => (int)$id)->sort()->values()->all())->toBe([(int)$published->id, (int)$child->id])
        ->and($rows[(string)$published->id]['children'])->toBe([])
        ->and($rows[(string)$child->id]['parent'])->toBeNull();

    $private = hardeningGql(['comments:read', 'elements.inactive:read'], <<<GQL
        { comments(ownerId: {$entry->id}, status: "spam") { id } }
    GQL);

    expect(collect($private['data']['comments'])->pluck('id')->map(fn($id) => (int)$id)->sort()->values()->all())
        ->toBe([(int)$spam->id, (int)$hiddenChild->id]);
});

it('does not expose or filter by guest emails over GraphQL', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL email owner');
    $field = hardeningGql(['comments:read'], "{ comments(ownerId: {$entry->id}) { email } }", true);
    $argument = hardeningGql(['comments:read'], '{ comments(email: "guest@example.test") { id } }', true);

    expect($field)->toHaveKey('errors')
        ->and($argument)->toHaveKey('errors');
});

it('scopes GraphQL deletes to the author and to comments', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL delete owner');
    $author = hardeningUser('gqldelete');
    $comment = hardeningComment($entry, $author);

    Craft::$app->getUser()->setIdentity($author);
    $notComment = hardeningGql(['comments:read', 'comments:delete'], "mutation { deleteComment(id: {$entry->id}) }");

    expect($notComment['errors'] ?? [])->toBe([])
        ->and(Entry::find()->id($entry->id)->exists())->toBeTrue();

    Craft::$app->getUser()->setIdentity(hardeningUser('gqldeleteother'));
    $notAuthor = hardeningGql(['comments:read', 'comments:delete'], "mutation { deleteComment(id: {$comment->id}) }");

    expect($notAuthor)->toHaveKey('errors');

    Craft::$app->getUser()->setIdentity($author);
    $own = hardeningGql(['comments:read', 'comments:delete'], "mutation { deleteComment(id: {$comment->id}) }");

    expect($own['errors'] ?? [])->toBe([])
        ->and(Comment::find()->id($comment->id)->status(null)->one()->authorRemovedAt)->not->toBeNull();
});

it('lets someone signed in only by a GraphQL token delete their own comment, and no one else’s', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL token delete owner');
    $author = hardeningUser('gqltoken');
    $other = hardeningUser('gqltokenother');
    $comment = hardeningComment($entry, $author);
    $delete = static fn(): array => hardeningGql(['comments:read', 'comments:delete'], "mutation { deleteComment(id: {$comment->id}) }");

    expect(hardeningAsTokenUser($other, $delete)['errors'][0]['message'] ?? null)->toBe('You may only delete your own comments.');

    $own = hardeningAsTokenUser($author, $delete);

    expect($own['errors'] ?? [])->toBe([])
        ->and($own['data']['deleteComment'] ?? null)->toBeTrue()
        ->and(Comment::find()->id($comment->id)->status(null)->one()->authorRemovedAt)->not->toBeNull();
});

it('votes and reports as the person a GraphQL token signs in, not as a guest', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = 'updown';
    $settings->allowGuestVoting = false;
    $settings->allowGuestFlagging = false;
    $entry = CommentsFixtureFactory::entry('GraphQL token engagement owner');
    $comment = hardeningComment($entry, hardeningUser('gqltokenengaged'));
    $member = hardeningUser('gqltokenvoter');
    $identityId = (int)Comments::$plugin->getIdentities()->getOrCreateForUser($member)->id;
    $guests = hardeningGuestIdentityCount();

    [$vote, $flag] = hardeningAsTokenUser($member, static fn(): array => [
        hardeningGql(['comments:read', 'comments:save'], "mutation { voteComment(id: {$comment->id}, upvote: true) { upvote } }"),
        hardeningGql(['comments:read', 'comments:save'], "mutation { flagComment(id: {$comment->id}) { commentId } }"),
    ]);

    expect($vote['errors'] ?? [])->toBe([])
        ->and($vote['data']['voteComment'])->toBe(['upvote' => true])
        ->and(Comments::$plugin->getInteractions()->getForIdentity((int)$comment->id, $identityId)?->type)->toBe('upvote')
        ->and($flag['errors'] ?? [])->toBe([])
        ->and(Comments::$plugin->getReports()->getOpenForIdentity((int)$comment->id, $identityId))->not->toBeNull()
        ->and(hardeningGuestIdentityCount())->toBe($guests);

    // With no token and no session, it's a guest, and guests can't vote or report here.
    [$guestVote, $guestFlag] = hardeningAsTokenUser(null, static fn(): array => [
        hardeningGql(['comments:read', 'comments:save'], "mutation { voteComment(id: {$comment->id}, downvote: true) { downvote } }"),
        hardeningGql(['comments:read', 'comments:save'], "mutation { flagComment(id: {$comment->id}) { commentId } }"),
    ]);

    expect($guestVote['errors'][0]['message'] ?? null)->toBe('Must be logged in to vote.')
        ->and($guestFlag['errors'][0]['message'] ?? null)->toBe('Must be logged in to flag comments.')
        ->and(Comments::$plugin->getInteractions()->getForIdentity((int)$comment->id, $identityId)?->type)->toBe('upvote');
});

it('lets someone signed in only by a GraphQL token edit their own comment, and no one else’s', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('GraphQL token edit owner');
    $author = hardeningUser('gqltokeneditor');
    $other = hardeningUser('gqltokenintruder');
    $comment = hardeningComment($entry, $author, 'Before');
    $edit = static fn(string $text): array => hardeningGql(['comments:read', 'comments:save'], "mutation { saveComment(id: {$comment->id}, comment: \"{$text}\") { id comment } }");
    $stored = static fn(): string => (string)(new craft\db\Query())->select(['comment'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar();

    expect(hardeningAsTokenUser($other, static fn(): array => $edit('Not yours'))['errors'][0]['message'] ?? null)->toBe('You do not have permission to edit this comment.')
        ->and(hardeningAsTokenUser(null, static fn(): array => $edit('Nobody'))['errors'] ?? [])->not->toBe([])
        ->and($stored())->toBe('Before');

    // Under pre-moderation the edit waits for review again, and the history says who made it.
    $settings->requireModeration = true;
    Comments::$plugin->getConversations()->clearCaches();
    $own = hardeningAsTokenUser($author, static fn(): array => $edit('After'));

    expect($own['errors'] ?? [])->toBe([])
        ->and($stored())->toBe('After')
        ->and((new craft\db\Query())->select(['userId', 'actorType'])->from('{{%comments_moderationactions}}')->where(['commentId' => $comment->id, 'action' => Moderation::ACTION_AUTHOR_EDIT])->one())
        ->toBe(['userId' => (int)$author->id, 'actorType' => Moderation::ACTOR_USER]);
});

it('lets an author edit their own comment through a schema that belongs to them, and no one else’s', function() {
    // Craft 5.12 gives OAuth users a schema of their own, and checks it before an element changes.
    if (!property_exists(GqlSchema::class, 'user')) {
        $this->markTestSkipped('User-bound schemas need Craft 5.12.');
    }

    $entry = CommentsFixtureFactory::entry('GraphQL user schema edit owner');
    $author = hardeningUser('gqlschemaauthor');
    $other = hardeningUser('gqlschemaother');
    $comment = hardeningComment($entry, $author, 'Before');
    $edit = static function(User $user, string $text) use ($comment): array {
        Craft::$app->getUser()->setIdentity($user);
        $schema = new GqlSchema(['name' => 'Authenticated Schema', 'scope' => ['comments:read', 'comments:save'], 'user' => $user]);
        Craft::$app->getGql()->flushCaches();
        Craft::$app->getGql()->setActiveSchema($schema);

        return Craft::$app->getGql()->executeQuery($schema, "mutation { saveComment(id: {$comment->id}, comment: \"{$text}\") { comment } }", debugMode: true);
    };

    expect($edit($other, 'Not yours')['errors'][0]['message'] ?? null)->toBe('Unable to perform the action.')
        ->and($edit($author, 'After'))->toBe(['data' => ['saveComment' => ['comment' => 'After']]]);
});

it('votes in the direction GraphQL asks, on the site it names', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->interactionMode = 'updown';
    $settings->allowGuestVoting = false;
    $entry = CommentsFixtureFactory::entry('GraphQL vote owner');
    $comment = hardeningComment($entry, hardeningUser('gqlvoteauthor'));
    $voter = hardeningUser('gqlvoter');
    Craft::$app->getUser()->setIdentity($voter);
    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($voter);
    $vote = static fn(string $arguments): array => hardeningGql(['comments:read', 'comments:save'], "mutation { voteComment(id: {$comment->id}{$arguments}) { upvote downvote } }");
    $stored = static fn(): ?string => Comments::$plugin->getInteractions()->getForIdentity((int)$comment->id, (int)$identity->id)?->type;
    $secondary = Tests\Support\Fixtures\MultiSiteFixtureFactory::secondarySite();

    $down = $vote(', downvote: true');

    expect($down['errors'] ?? [])->toBe([])
        ->and($down['data']['voteComment'])->toBe(['upvote' => null, 'downvote' => true])
        ->and($stored())->toBe('downvote');

    $up = $vote(', upvote: true, siteId: ' . $entry->siteId);

    expect($up['errors'] ?? [])->toBe([])
        ->and($up['data']['voteComment'])->toBe(['upvote' => true, 'downvote' => null])
        ->and($stored())->toBe('upvote');

    // Neither or both is a mistake, not a downvote.
    expect($vote('')['errors'][0]['message'] ?? null)->toBe('Choose to upvote or downvote.')
        ->and($vote(', upvote: true, downvote: true')['errors'][0]['message'] ?? null)->toBe('Choose to upvote or downvote.')
        // The comment isn't on that site.
        ->and($vote(', downvote: true, siteId: ' . $secondary->id)['errors'][0]['message'] ?? null)->toBe('No comment with the ID “' . $comment->id . '”')
        ->and($stored())->toBe('upvote');
});

it('reports on the site GraphQL names, and says who reported it', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL flag owner');
    $comment = hardeningComment($entry, hardeningUser('gqlflagauthor'));
    $reporter = hardeningUser('gqlreporter');
    Craft::$app->getUser()->setIdentity($reporter);
    $secondary = Tests\Support\Fixtures\MultiSiteFixtureFactory::secondarySite();
    $flag = static fn(int $siteId): array => hardeningGql(
        ['comments:read', 'comments:save', 'usergroups.everyone:read'],
        "mutation { flagComment(id: {$comment->id}, siteId: {$siteId}) { commentId userId user { id } } }",
    );

    expect($flag((int)$secondary->id)['errors'][0]['message'] ?? null)->toBe('No comment with the ID “' . $comment->id . '”')
        ->and(Comments::$plugin->getReports()->getOpenForComment((int)$comment->id))->toBe([]);

    $reported = $flag((int)$entry->siteId);

    expect($reported['errors'] ?? [])->toBe([])
        ->and($reported['data']['flagComment'])->toBe(['commentId' => (string)$comment->id, 'userId' => (int)$reporter->id, 'user' => ['id' => (string)$reporter->id]])
        ->and(Comments::$plugin->getReports()->getOpenForComment((int)$comment->id))->toHaveCount(1);
});

it('keeps no name or address from GraphQL on a signed-in person’s comment', function() {
    $entry = CommentsFixtureFactory::entry('GraphQL create identity owner');
    $member = hardeningUser('gqlcreator');
    Craft::$app->getUser()->setIdentity($member);

    $result = hardeningGql(['comments:read', 'comments:edit'], "mutation { createComment(ownerId: {$entry->id}, siteId: {$entry->siteId}, comment: \"Signed in\", name: \"Someone Else\", email: \"someone-else@example.test\") { id } }");

    expect($result['errors'] ?? [])->toBe([]);

    $comment = Comment::find()->id((int)$result['data']['createComment']['id'])->status(null)->one();

    expect($comment->name)->toBeNull()
        ->and($comment->email)->toBeNull()
        ->and((int)$comment->userId)->toBe((int)$member->id)
        ->and($comment->getAuthorEmail())->toBe($member->email)
        ->and($comment->getAuthorName())->not->toBe('Someone Else');
});

it('leaves control panel deletes to Craft, which checks the element being deleted', function() {
    expect(method_exists(CommentsController::class, 'actionDeleteComment'))->toBeFalse();
});

it('emails nobody when the moderator group no longer exists', function() {
    hardeningUser('moderatorgroup');
    Comments::$plugin->getSettings()->moderatorUserGroup = 'a-group-that-was-deleted';

    expect(Comments::$plugin->getComments()->getModeratorRecipients())->toBe([]);
});

it('only takes votes and flags on published comments', function() {
    $entry = CommentsFixtureFactory::entry('Engagement target owner');
    $author = hardeningUser('engagementtarget');
    $comment = hardeningComment($entry, $author);
    $service = Comments::$plugin->getComments();

    expect((int)$service->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_INTERACTION)->id)->toBe((int)$comment->id);

    Comments::$plugin->getModeration()->markSpam($comment, $this->admin);
    $service->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_INTERACTION);
})->throws(UserException::class, 'not available');

it('rejects votes on missing comments as a normal error', function() {
    Comments::$plugin->getComments()->requireEngagementTarget(999999999, Protection::ENGAGEMENT_INTERACTION);
})->throws(UserException::class);

it('rate limits flags per user', function() {
    $entry = CommentsFixtureFactory::entry('Engagement limit owner');
    $comment = hardeningComment($entry, $this->admin);
    Craft::$app->getUser()->setIdentity(hardeningUser('engagementlimit'));
    $service = Comments::$plugin->getComments();

    foreach (range(1, 3) as $attempt) {
        $service->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_REPORT);
    }

    try {
        $service->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_REPORT);
        $this->fail('The fourth flag should be rate limited.');
    } catch (ProtectionException $e) {
        expect($e->httpStatus)->toBe(429)
            ->and($e->retryAfter)->toBeGreaterThan(0);
    }
});

it('rate limits guest engagement by network rather than identity', function() {
    $entry = CommentsFixtureFactory::entry('Engagement guest owner');
    $comment = hardeningComment($entry, $this->admin);
    Craft::$app->getUser()->setIdentity(null);

    // Protect takes the connection's own address, because the visitor controls the forwarded ones.
    Craft::$app->getRequest()->setIsConsoleRequest(false);
    $_SERVER['REMOTE_ADDR'] = '192.0.2.' . random_int(1, 254);
    $service = Comments::$plugin->getComments();

    foreach (range(1, 3) as $attempt) {
        $service->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_REPORT);
    }

    $service->requireEngagementTarget((int)$comment->id, Protection::ENGAGEMENT_REPORT);
})->throws(ProtectionException::class);

it('honours the flagging setting and reports withdrawals accurately through the API', function() {
    $entry = CommentsFixtureFactory::entry('Report API owner');
    $comment = hardeningComment($entry, $this->admin);
    Craft::$app->getUser()->setIdentity(hardeningUser('reportapi'));

    $request = Craft::$app->getRequest();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // Set here rather than inherited from an earlier test, so this runs the same on its own.
    $request->getHeaders()->set('Accept', 'application/json');
    $request->setBodyParams(['commentId' => $comment->id]);
    $controller = new FrontendApiController('frontend-api', Comments::$plugin);

    Comments::$plugin->getSettings()->allowFlagging = false;
    expect($controller->actionReport()->data['status'])->toBe('error');

    Comments::$plugin->getSettings()->allowFlagging = true;
    expect($controller->actionReport()->data['announcement'] ?? null)->toBe('Thanks for reporting this. A moderator will take a look.');
    expect($controller->actionReport()->data['announcement'] ?? null)->toBe('Report withdrawn.');
});

it('hides the remove action and rejects it without the trash permission', function() {
    $moderator = hardeningUser('moderateonly');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['comments-view', 'comments-moderate']);
    $moderation = Comments::$plugin->getModeration();

    expect($moderation->canRemoveComment($moderator))->toBeFalse()
        ->and($moderation->canModerateComment($moderator))->toBeTrue();

    $trusted = hardeningUser('moderatetrash');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$trusted->id, ['comments-view', 'comments-moderate', 'comments-trash']);

    expect($moderation->canRemoveComment($trusted))->toBeTrue();
});

it('only accepts public submissions for fields explicitly allowed in settings', function() {
    $fields = Craft::$app->getFields();
    $suffix = bin2hex(random_bytes(3));
    $publicField = new PlainText(['name' => 'Public note', 'handle' => 'publicNote' . $suffix]);
    $privateField = new PlainText(['name' => 'Private note', 'handle' => 'privateNote' . $suffix]);

    expect($fields->saveField($publicField))->toBeTrue()
        ->and($fields->saveField($privateField))->toBeTrue();

    $layout = $fields->getLayoutByType(Comment::class);
    $tabs = $layout->getTabs();
    $tab = $tabs[0] ?? new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $tab->setElements([...$tab->getElements(), new CustomField($publicField), new CustomField($privateField, ['required' => true])]);
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);

    expect($fields->saveLayout($layout, false))->toBeTrue();

    Comments::$plugin->getSettings()->publicFields = [$publicField->handle];

    $_SERVER['REQUEST_METHOD'] = 'POST';
    Craft::$app->getRequest()->setBodyParams(['fields' => [
        $publicField->handle => 'Visitor value',
        $privateField->handle => 'Injected value',
    ]]);

    $comment = new Comment();
    $comment->setPublicFieldValuesFromRequest();

    expect($comment->getFieldValue($publicField->handle))->toBe('Visitor value')
        ->and($comment->getFieldValue($privateField->handle))->toBeNull();

    // A required staff-only field can't block visitors, who have no way to fill it in.
    $isRequiredOn = static fn(string $handle, string $scenario): bool => array_filter(
        iterator_to_array($comment->getValidators()),
        static fn($validator) => $validator instanceof RequiredValidator
            && in_array('field:' . $handle, $validator->getAttributeNames(), true)
            && $validator->isActive($scenario),
    ) !== [];

    expect($isRequiredOn($privateField->handle, Comment::SCENARIO_FRONT_END))->toBeFalse()
        ->and($isRequiredOn($privateField->handle, Comment::SCENARIO_CP))->toBeTrue();

    $tab->setElements(array_values(array_filter(
        $tab->getElements(),
        static fn($element) => !$element instanceof CustomField || !in_array($element->getField()->handle, [$publicField->handle, $privateField->handle], true),
    )));
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
    $fields->saveLayout($layout, false);
});

it('saves control panel edits and status changes through the canonical lifecycle', function() {
    $entry = CommentsFixtureFactory::entry('CP save owner');
    $author = hardeningUser('cpsave');
    $comment = hardeningComment($entry, $author, 'Before');

    $request = Craft::$app->getRequest();
    $request->setIsCpRequest(true);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    Craft::$app->getSession()->attachBehavior('hardeningFlashes', SessionBehavior::class);
    $request->setBodyParams([
        'elementId' => $comment->id,
        'siteId' => $comment->siteId,
        'status' => Comment::STATUS_SPAM,
        'authorId' => [$author->id],
        'ownerType' => Entry::class,
        'ownerEntryId' => [$entry->id],
        'contentFormat' => 'plain',
        'fields' => ['comment' => 'After'],
    ]);

    $editing = Comment::find()->id($comment->id)->status(null)->one();
    $editing->setAttributesFromRequest([]);
    $editing->setScenario(Element::SCENARIO_LIVE);
    expect(Craft::$app->getElements()->saveElement($editing))->toBeTrue();

    $saved = Comment::find()->id($comment->id)->status(null)->one();

    expect($saved->comment)->toBe('After')
        ->and($saved->moderationState)->toBe(ModerationState::Spam->value)
        ->and($saved->status)->toBe(Comment::STATUS_SPAM);
});
