<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\protect\Protect;
use verbb\protect\models\{ProtectionEvidence, RequestSignals};
use verbb\protect\integrations\classifiers\Akismet;
use verbb\protect\integrations\challenges\Turnstile;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function() {
    $s = Comments::$plugin->getSettings();
    $s->enableSpamChecks = true;
    $s->minimumSubmitTime = 0;
    $s->requireModeration = false;
    $s->allowGuest = true;
    $s->participantNotifications = [];
    $s->staffNotifications = [];
    // Rate tests opt into a unique identity/window; other cases focus on their own outcomes.
    $s->rateLimitEnabled = false;
});
afterEach(function() {
    Protect::getInstance()->getPreparations()->clock = null;
    Craft::$container->clear(Akismet::class);
    Craft::$container->clear(Turnstile::class);
    Craft::$app->setModule(Protect::ID, null);
    Craft::$app->getRequest()->setBodyParams([]);
});

function protectedSubmission($entry, bool $headless = true): CommentSubmission
{
    $p = Comments::$plugin->getProtection()->prepare($entry->id, $entry->siteId, headless: $headless);

    return new CommentSubmission([
        'ownerId' => $entry->id, 'siteId' => $entry->siteId, 'userId' => Craft::$app->getUser()->getIdentity()?->id,
        'content' => 'Protected public comment', 'name' => 'Visitor', 'email' => 'visitor@example.test',
        'headless' => $headless, 'protectionEvidence' => new ProtectionEvidence($p->browserManifest()->preparation),
        'protectionRequest' => new RequestSignals('192.0.2.55', 'Test browser'),
    ]);
}

it('requires evidence for canonical headless creation and never saves rejected attempts', function() {
    $entry = CommentsFixtureFactory::entry('Protect canonical headless');
    $submission = protectedSubmission($entry);
    $evidence = $submission->protectionEvidence;
    $submission->protectionEvidence = null;
    expect(fn() => Comments::$plugin->getComments()->create($submission))->toThrow(ProtectionException::class);
    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(0);
    $submission->protectionEvidence = $evidence;
    expect(Comments::$plugin->getComments()->create($submission)->moderationState)->toBe(ModerationState::Approved);
    expect(fn() => Comments::$plugin->getComments()->create($submission))->toThrow(ProtectionException::class);
    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(1);
});

it('checks browser form evidence through the canonical POST service', function() {
    $entry = CommentsFixtureFactory::entry('Protect form POST');
    $submission = protectedSubmission($entry, false);
    Craft::$app->getRequest()->setBodyParams([
        'elementId' => $entry->id, 'siteId' => $entry->siteId,
        'fields' => ['comment' => 'Server rendered form comment'],
        '__protect' => ['version' => '1', 'preparation' => $submission->protectionEvidence->preparation],
    ]);
    $result = Comments::$plugin->getComments()->createFromPost();
    expect($result->moderationState)->toBe(ModerationState::Approved);
    expect(fn() => Comments::$plugin->getComments()->createFromPost())->toThrow(ProtectionException::class);
});

it('rejects filled honeypots without persisting a comment', function() {
    $entry = CommentsFixtureFactory::entry('Protect bot form');
    $p = Comments::$plugin->getProtection()->prepare($entry->id, $entry->siteId);
    $submission = protectedSubmission($entry, false);
    $submission->protectionEvidence = new ProtectionEvidence($p->browserManifest()->preparation, [$p->browserManifest()->honeypot => 'filled']);
    expect(fn() => Comments::$plugin->getComments()->create($submission))->toThrow(ProtectionException::class);
    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(0);
});

it('supports guest headless evidence without disabling replay or classification', function() {
    $entry = CommentsFixtureFactory::entry('Protect guest');
    Craft::$app->getUser()->setIdentity(null);
    $submission = protectedSubmission($entry);
    expect(Comments::$plugin->getComments()->create($submission)->comment->userId)->toBeNull();
    expect(fn() => Comments::$plugin->getComments()->create($submission))->toThrow(ProtectionException::class);
});

it('persists classifier spam only when Comments policy permits it', function() {
    $entry = CommentsFixtureFactory::entry('Protect classifier');
    $s = Comments::$plugin->getSettings();
    $s->spamClassifier = Settings::CLASSIFIER_AKISMET;
    $s->akismetApiKey = 'test-key';
    Craft::$container->set(Akismet::class, ['client' => new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], 'true'), new Response(200, [], 'true')]))])]);
    $result = Comments::$plugin->getComments()->create(protectedSubmission($entry));
    expect($result->moderationState)->toBe(ModerationState::Spam);
    expect($result->comment->status)->toBe(Comment::STATUS_SPAM);
    // What each check said stays on the comment, so a moderator can see why.
    $stored = Comment::find()->id($result->comment->id)->status(null)->one();
    $lines = Comments::$plugin->getProtection()->describe($stored->protection);
    expect($stored->heldReason)->toBe('protection')
        ->and(array_column($lines, 'label'))->toContain('Akismet')
        ->and(implode(' ', array_column($lines, 'detail')))->toContain('says spam')
        ->and($stored->protection)->not->toContain('visitor@example.test')->not->toContain('192.0.2.55');
    $s->saveSpam = false;
    expect(fn() => Comments::$plugin->getComments()->create(protectedSubmission($entry)))->toThrow(ProtectionException::class);
    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(1);
});

it('fails open on provider errors by default and can fail closed without marking spam', function() {
    $entry = CommentsFixtureFactory::entry('Protect provider errors');
    $s = Comments::$plugin->getSettings();
    $s->spamClassifier = Settings::CLASSIFIER_AKISMET;
    $s->akismetApiKey = 'test-key';
    Craft::$container->set(Akismet::class, ['client' => new Client(['handler' => HandlerStack::create(new MockHandler([new Response(500), new Response(500)]))])]);
    expect(Comments::$plugin->getComments()->create(protectedSubmission($entry))->moderationState)->toBe(ModerationState::Approved);
    $s->protectionFailClosed = true;
    expect(fn() => Comments::$plugin->getComments()->create(protectedSubmission($entry)))->toThrow(ProtectionException::class);
    expect((int)Comment::find()->ownerId($entry->id)->status('spam')->count())->toBe(0);
});

it('enforces rate limits on headless attempts and returns a retry interval', function() {
    $entry = CommentsFixtureFactory::entry('Protect rate');
    $user = CommentsFixtureFactory::user('protect-rate-' . bin2hex(random_bytes(3)));
    Craft::$app->getUser()->setIdentity($user);
    $s = Comments::$plugin->getSettings();
    $s->rateLimitEnabled = true;
    $s->rateLimitCount = 1;
    Comments::$plugin->getComments()->create(protectedSubmission($entry));

    try {
        Comments::$plugin->getComments()->create(protectedSubmission($entry));
        $this->fail('Rate limit was bypassed.');
    } catch (ProtectionException $e) {
        expect($e->httpStatus)->toBe(429);
        expect($e->retryAfter)->toBeGreaterThan(0);
    }
});

it('requires preparation for public edits but not deterministic element resaves', function() {
    $entry = CommentsFixtureFactory::entry('Protect edits');
    $comment = Comments::$plugin->getComments()->create(protectedSubmission($entry))->comment;
    $comment->comment = 'Edited publicly';
    expect(fn() => Comments::$plugin->getComments()->savePublicUpdate($comment, null))->toThrow(ProtectionException::class);
    $p = Comments::$plugin->getProtection()->prepare($entry->id, $entry->siteId, $comment->id, true);
    expect(Comments::$plugin->getComments()->savePublicUpdate($comment, new ProtectionEvidence($p->browserManifest()->preparation))->comment)->toBe('Edited publicly');
    $comment->setScenario(Comment::SCENARIO_LIVE);
    expect($comment->validate())->toBeTrue();
});

it('renders only the reserved protocol and validates the challenge provider', function() {
    $entry = CommentsFixtureFactory::entry('Protect markup');
    $html = Comments::$plugin->getProtection()->getFields($entry->id, $entry->siteId);
    expect($html)->toContain('__protect[preparation]')->toContain('data-comments-protection')->not->toContain('__UAHASH')->not->toContain('__JSCHK')->not->toContain('<script');
    $s = Comments::$plugin->getSettings();
    $s->challengeProvider = 'unknown';
    expect($s->validate())->toBeFalse();
    expect($s->getErrors('challengeProvider'))->not->toBeEmpty();
    $s->challengeProvider = '';
    expect(\verbb\comments\gql\arguments\mutations\Comment::getArguments()['protection']['type']->name)->toBe('CommentsProtectionEvidence');
});

it('runs the data API through protection and reports rate limits as HTTP 429', function() {
    $entry = CommentsFixtureFactory::entry('Protect data API');
    $user = CommentsFixtureFactory::user('protect-api-' . bin2hex(random_bytes(3)));
    Craft::$app->getUser()->setIdentity($user);
    $s = Comments::$plugin->getSettings();
    $s->rateLimitEnabled = true;
    $s->rateLimitCount = 1;
    $api = Comments::$plugin->getApi();
    $post = static function() use ($api, $entry): array {
        $p = Comments::$plugin->getProtection()->prepare($entry->id, $entry->siteId);

        return $api->create(['ownerId' => $entry->id, 'siteId' => $entry->siteId, 'content' => ['format' => 'plain', 'text' => 'API protected comment'], 'operationId' => craft\helpers\StringHelper::UUID(), 'protection' => ['version' => 1, 'preparation' => $p->browserManifest()->preparation]]);
    };

    expect($post()['comment']['uid'] ?? null)->toBeString();

    try {
        $post();
        $this->fail('Expected the rate limit.');
    } catch (\verbb\comments\exceptions\ProtectionException $e) {
        $error = $api->errorFor($e);

        expect($e->httpStatus)->toBe(429)
            ->and($e->retryAfter)->not->toBeNull()
            ->and($error->httpStatus)->toBe(429)->and($error->retryAfter)->toBe($e->retryAfter);
    }

    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(1);
});

it('requires evidence through the real GraphQL mutation resolver', function() {
    $entry = CommentsFixtureFactory::entry('Protect GraphQL');
    $schema = new \craft\models\GqlSchema(['name' => 'Protect integration', 'scope' => ['comments:read', 'comments:edit', 'comments:save']]);
    Craft::$app->getGql()->setActiveSchema($schema);
    $query = 'mutation($owner: ID!, $proof: CommentsProtectionEvidence) { createComment(ownerId: $owner, comment: "GraphQL protected comment", protection: $proof) { id } }';
    // Comments 3's clients sent no evidence, which only compatibility mode accepts.
    Comments::$plugin->getSettings()->compatibilityMode = false;
    Craft::$app->getGql()->flushCaches();

    try {
        $denied = Craft::$app->getGql()->executeQuery($schema, $query, ['owner' => $entry->id]);
    } finally {
        Comments::$plugin->getSettings()->compatibilityMode = true;
        Craft::$app->getGql()->flushCaches();
    }

    expect($denied)->toHaveKey('errors');
    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(0);
    $p = Comments::$plugin->getProtection()->prepare($entry->id, $entry->siteId, headless: true);
    $allowed = Craft::$app->getGql()->executeQuery($schema, $query, ['owner' => $entry->id, 'proof' => ['version' => 1, 'preparation' => $p->browserManifest()->preparation]], debugMode: true);
    expect($allowed['errors'] ?? [])->toBe([]);
    expect($allowed['data']['createComment']['id'])->not->toBeNull();

    // In compatibility mode, a Comments 3 client's mutation without evidence is checked without a preparation.
    $legacy = Craft::$app->getGql()->executeQuery($schema, $query, ['owner' => $entry->id], debugMode: true);
    expect($legacy['errors'] ?? [])->toBe([]);
    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(2);
});

it('maps challenge success failure and error through Comments persistence', function() {
    $entry = CommentsFixtureFactory::entry('Protect challenges');
    $s = Comments::$plugin->getSettings();
    $s->challengeProvider = Settings::CHALLENGE_TURNSTILE;
    $s->turnstileKey = 'site';
    $s->turnstileSecret = 'secret';
    $s->protectionHostnames = ['example.com'];
    Craft::$container->set(Turnstile::class, ['client' => new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode(['success' => true, 'action' => 'comments_create', 'hostname' => 'example.com', 'challenge_ts' => gmdate('c')])),
        new Response(200, [], '{"success":false,"error-codes":["invalid-input-response"]}'),
        new Response(200, [], '{"success":false,"error-codes":["invalid-input-secret"]}'),
    ]))])]);
    $submission = protectedSubmission($entry);
    $submission->protectionEvidence = new ProtectionEvidence($submission->protectionEvidence->preparation, challenges: ['turnstile' => 'token']);
    expect(Comments::$plugin->getComments()->create($submission)->moderationState)->toBe(ModerationState::Approved);
    $submission = protectedSubmission($entry);
    $submission->protectionEvidence = new ProtectionEvidence($submission->protectionEvidence->preparation, challenges: ['turnstile' => 'token']);
    expect(fn() => Comments::$plugin->getComments()->create($submission))->toThrow(ProtectionException::class);
    $submission = protectedSubmission($entry);
    $submission->protectionEvidence = new ProtectionEvidence($submission->protectionEvidence->preparation, challenges: ['turnstile' => 'token']);
    // A rejected secret is a configuration error, which is held rather than waved through like an outage.
    expect(Comments::$plugin->getComments()->create($submission)->moderationState)->toBe(ModerationState::Pending);
    expect((int)Comment::find()->ownerId($entry->id)->status(null)->count())->toBe(2);
});

it('checks native POST edits through the actual public controller', function() {
    $entry = CommentsFixtureFactory::entry('Protect native edit');
    $comment = Comments::$plugin->getComments()->create(protectedSubmission($entry))->comment;
    $controller = new \verbb\comments\controllers\CommentsController('comments', Comments::$plugin);
    $request = Craft::$app->getRequest();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request->getHeaders()->set('Accept', 'application/json');
    $body = ['commentId' => $comment->id, 'siteId' => $entry->siteId, 'fields' => ['comment' => 'Native edited comment']];
    $request->setBodyParams($body);
    expect($controller->actionSave()->data['success'])->toBeFalse();
    expect(Comment::find()->id($comment->id)->status(null)->one()->comment)->toBe('Protected public comment');
    $p = Comments::$plugin->getProtection()->prepare($entry->id, $entry->siteId, $comment->id);
    $body['__protect'] = ['version' => '1', 'preparation' => $p->browserManifest()->preparation];
    $request->setBodyParams($body);
    expect($controller->actionSave()->data['success'])->toBeTrue();
    expect(Comment::find()->id($comment->id)->status(null)->one()->comment)->toBe('Native edited comment');
    unset($_SERVER['REQUEST_METHOD']);
});

it('issues guest continuity cookies using Craft cookie configuration', function() {
    $identities = Comments::$plugin->getIdentities();
    $identities->issueGuestCookie('guest-cookie-test');
    $cookie = Craft::$app->getResponse()->getCookies()->get(\verbb\comments\services\Identities::GUEST_COOKIE_NAME);
    expect($cookie->value)->toBe('guest-cookie-test');
    expect($cookie->httpOnly)->toBeTrue();
    expect($cookie->path)->toBe('/');
    expect($cookie->domain)->toBe(Craft::$app->getConfig()->getGeneral()->defaultCookieDomain);
});

it('still applies spam checks to trusted commenters', function() {
    $entry = CommentsFixtureFactory::entry('Protect trusted');
    $s = Comments::$plugin->getSettings();
    $s->requireModeration = true;
    $s->spamClassifier = Settings::CLASSIFIER_AKISMET;
    $s->akismetApiKey = 'test-key';
    $member = CommentsFixtureFactory::user('protectTrusted');
    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($member);
    Comments::$plugin->getModeration()->setReviewSetting((int)$identity->id, verbb\comments\enums\ReviewSetting::Trusted);
    Craft::$container->set(Akismet::class, ['client' => new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], 'true')]))])]);
    $submission = protectedSubmission($entry);
    $submission->userId = (int)$member->id;
    $submission->name = null;
    $submission->email = null;

    expect(Comments::$plugin->getComments()->create($submission)->moderationState)->toBe(ModerationState::Spam);
});
