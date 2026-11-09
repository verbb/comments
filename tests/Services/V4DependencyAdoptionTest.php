<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationState;
use verbb\comments\exceptions\ProtectionException;
use verbb\comments\helpers\EditorContexts;
use verbb\comments\models\CommentSubmission;
use verbb\editor\Editor;
use verbb\protect\models\{ProtectionEvidence, RequestSignals};

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->allowGuest = true;
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowMentions = true;
    $settings->allowAttachments = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->turnstileEnabled = false;
    $settings->protectionHostnames = [];
    $settings->protectionFailClosed = false;
    EditorContexts::refresh();
    $this->admin = Craft::$app->getUser()->getIdentity();
});

afterEach(function() {
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->enableSpamChecks = false;
    Comments::$plugin->getSettings()->turnstileEnabled = false;
    Craft::$app->getRequest()->setBodyParams([]);
    unset($_SERVER['REQUEST_METHOD']);
});

function adoptionMember(string $name): craft\elements\User
{
    $user = CommentsFixtureFactory::user($name . '-' . bin2hex(random_bytes(3)));
    $user->admin = false;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUser()->setIdentity($user);

    return $user;
}

function adoptionComment($entry, craft\elements\User $user, string $text = 'A comment'): verbb\comments\elements\Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => (int)$user->id,
    ]))->comment;
}

function adoptionMentionSearch(array $scope, string $query, string $context = 'comments.reply'): yii\web\Response
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request = Craft::$app->getRequest();
    $request->getHeaders()->set('Accept', 'application/json');
    $request->setBodyParams(['context' => $context, 'query' => $query, 'scope' => $scope]);
    Craft::$app->getResponse()->setStatusCode(200);

    return (new verbb\editor\controllers\MentionsController('mentions', Editor::getInstance()))->runAction('search');
}

it('holds comments for review when a provider is misconfigured rather than publishing them unchecked', function() {
    $entry = CommentsFixtureFactory::entry('Adoption configuration error');
    $settings = Comments::$plugin->getSettings();
    $settings->enableSpamChecks = true;
    $settings->honeypotEnabled = false;
    $settings->javascriptCheckEnabled = false;
    $settings->minimumSubmitTime = 0;
    $settings->replayProtectionEnabled = false;
    $settings->rateLimitEnabled = false;
    $settings->challengeAuthenticated = true;
    $settings->turnstileEnabled = true;
    $settings->turnstileKey = 'site';
    $settings->turnstileSecret = '$COMMENTS_TEST_UNSET_SECRET';
    $settings->protectionHostnames = ['example.com'];
    $submit = function() use ($entry) {
        $preparation = Comments::$plugin->getProtection()->prepare($entry->id, $entry->siteId, headless: true);

        return Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'userId' => (int)$this->admin->id,
            'content' => 'Checked by nothing',
            'headless' => true,
            'protectionEvidence' => new ProtectionEvidence($preparation->browserManifest()->preparation, challenges: ['turnstile' => 'token']),
            'protectionRequest' => new RequestSignals('192.0.2.77'),
        ]));
    };

    expect($submit()->moderationState)->toBe(ModerationState::Pending);

    $settings->protectionFailClosed = true;
    expect($submit)->toThrow(ProtectionException::class);
});

it('reports settings that can’t protect a site when they’re saved', function() {
    $settings = Comments::$plugin->getSettings();
    $site = Craft::$app->getSites()->getPrimarySite();
    $baseUrl = $site->getBaseUrl(false);
    $settings->enableSpamChecks = true;
    $settings->turnstileEnabled = true;
    $settings->turnstileKey = 'site';
    $settings->turnstileSecret = 'secret';
    $site->setBaseUrl(null);

    try {
        expect($settings->validate(['enableSpamChecks']))->toBeFalse();
        expect($settings->getFirstError('enableSpamChecks'))->toContain('hostnames');

        $settings->clearErrors();
        $settings->protectionHostnames = ['example.com'];
        expect($settings->validate(['enableSpamChecks']))->toBeTrue();
    } finally {
        $site->setBaseUrl($baseUrl);
    }
});

it('doesn’t take the page down when the challenge’s site key is an unset environment variable', function() {
    $entry = CommentsFixtureFactory::entry('Adoption broken plan');
    $settings = Comments::$plugin->getSettings();
    $settings->enableSpamChecks = true;
    $settings->challengeAuthenticated = true;
    $settings->turnstileEnabled = true;
    $settings->turnstileKey = '$COMMENTS_TEST_UNSET_KEY';
    $settings->turnstileSecret = 'secret';
    $settings->protectionHostnames = ['example.com'];

    expect(Comments::$plugin->getProtection()->getFields((int)$entry->id, (int)$entry->siteId))->toBe('');
    expect(fn() => Comments::$plugin->getProtection()->prepare((int)$entry->id, (int)$entry->siteId))->toThrow(ProtectionException::class);
});

it('gives guests an editor with no endpoints, token or scope', function() {
    $entry = CommentsFixtureFactory::entry('Adoption guest options');
    Craft::$app->getUser()->setIdentity(null);

    $options = EditorContexts::browserOptions('comments.reply', (int)$entry->id, (int)$entry->siteId);

    expect($options['craft'])->toBeFalse();
    expect($options['features']['mention'])->toBeFalse();
    expect(json_encode($options))->not->toContain('csrf');
});

it('gives members the endpoints and scope their mentions need', function() {
    $entry = CommentsFixtureFactory::entry('Adoption member options');
    adoptionMember('adoption-options');

    $options = EditorContexts::browserOptions('comments.reply', (int)$entry->id, (int)$entry->siteId);

    expect($options['features']['mention'])->toBeTrue();
    expect($options['craft']['context'])->toBe('comments.reply');
    expect($options['craft']['endpoints'])->toHaveKey('mentions');
    expect($options['craft']['scope'])->toBe(['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId]);
    expect($options['placeholder'])->toBe('Write a comment…');
});

it('only lets the browser’s scope through for somewhere its user could comment', function() {
    $entry = CommentsFixtureFactory::entry('Adoption scope');
    $member = adoptionMember('adoption-scope');
    $config = EditorContexts::replyConfig();
    $scope = ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId];

    // Nothing else the browser sends reaches the providers.
    expect(EditorContexts::resolveScope([...$scope, 'extra' => 'value'], $member, $config))->toBe($scope);
    expect(EditorContexts::resolveScope(['ownerId' => 999999, 'siteId' => (int)$entry->siteId], $member, $config))->toBeNull();
    expect(EditorContexts::resolveScope([], $member, $config))->toBeNull();

    $entry->enabled = false;
    Craft::$app->getElements()->saveElement($entry, false);
    expect(EditorContexts::resolveScope($scope, $member, $config))->toBeNull();
});

it('only lets someone use the edit context for a comment they can edit', function() {
    $entry = CommentsFixtureFactory::entry('Adoption edit scope');
    $author = adoptionMember('adoption-author');
    $comment = adoptionComment($entry, $author);
    $config = EditorContexts::editConfig();
    $scope = ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId, 'commentId' => (int)$comment->id];
    $expected = ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId];

    expect(EditorContexts::resolveScope($scope, $author, $config))->toBe($expected);
    expect(EditorContexts::resolveScope(['commentId' => null] + $scope, $author, $config))->toBeNull();

    $other = adoptionMember('adoption-other');
    expect(EditorContexts::resolveScope($scope, $other, $config))->toBeNull();

    // A comment from somewhere else doesn't unlock this owner.
    $elsewhere = CommentsFixtureFactory::entry('Adoption edit scope elsewhere');
    Craft::$app->getUser()->setIdentity($author);
    expect(EditorContexts::resolveScope(['ownerId' => (int)$elsewhere->id] + $scope, $author, $config))->toBeNull();
});

it('finds conversation participants for a member with no control panel access', function() {
    $entry = CommentsFixtureFactory::entry('Adoption mention search');
    $participant = CommentsFixtureFactory::user('adoptionparticipant' . bin2hex(random_bytes(2)));
    $participant->fullName = 'Findable Participant';
    Craft::$app->getElements()->saveElement($participant, false);
    adoptionComment($entry, $participant);
    $outsider = CommentsFixtureFactory::user('adoptionoutsider' . bin2hex(random_bytes(2)));
    $outsider->fullName = 'Findable Outsider';
    Craft::$app->getElements()->saveElement($outsider, false);
    adoptionMember('adoption-searcher');
    $scope = ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId];

    $response = adoptionMentionSearch($scope, 'Findable');
    expect($response->statusCode)->toBe(200);
    expect(array_column($response->data['results'], 'id'))->toBe([(int)$participant->id]);

    // Another conversation's participants aren't searchable through this one's scope.
    $response = adoptionMentionSearch(['ownerId' => 999999, 'siteId' => (int)$entry->siteId], 'Findable');
    expect($response->statusCode)->toBe(403);
});

it('renders a page of rich comments the same way as one at a time', function() {
    $entry = CommentsFixtureFactory::entry('Adoption batch render');
    $author = adoptionMember('adoption-batch');
    $comments = [];

    foreach (['First', 'Second'] as $text) {
        $comments[] = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => $text]]]]]),
            'contentFormat' => ContentFormat::Editor,
            'userId' => (int)$author->id,
        ]))->comment;
    }

    $expected = array_map(static fn($comment) => (string)$comment->getRenderedBodyHtml(), $comments);
    expect($expected[0])->toContain('<strong>First</strong>');

    $views = Comments::$plugin->getFrontend()->buildCommentViewsForComments($comments, 0);

    expect(array_map(static fn($view) => $view->contentHtml, $views))->toBe($expected);
});

it('refuses an empty rich comment and accepts the empty document Editor sends for one', function() {
    $entry = CommentsFixtureFactory::entry('Adoption empty');
    $author = adoptionMember('adoption-empty');

    foreach (['{"type":"doc","content":[]}', '{"type":"doc","content":[{"type":"paragraph"}]}'] as $empty) {
        expect(fn() => Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $empty,
            'contentFormat' => ContentFormat::Editor,
            'userId' => (int)$author->id,
        ])))->toThrow(yii\base\UserException::class, 'Comment content cannot be empty.');
    }
});

it('posts the plain box when a rich form is sent without JavaScript', function() {
    $entry = CommentsFixtureFactory::entry('Adoption no script');
    $author = adoptionMember('adoption-noscript');
    Craft::$app->getRequest()->setBodyParams([
        'elementId' => $entry->id,
        'siteId' => $entry->siteId,
        'contentFormat' => 'editor',
        'fields' => ['comment' => 'Typed without JavaScript', 'editorContent' => ''],
    ]);

    $result = Comments::$plugin->getComments()->createFromPost();

    expect($result->comment->contentFormat)->toBe(ContentFormat::Plain->value);
    expect($result->comment->comment)->toBe('Typed without JavaScript');
    expect((int)$result->comment->userId)->toBe((int)$author->id);
});

it('doesn’t let GraphQL change a comment’s format or blank a rich comment’s text', function() {
    $entry = CommentsFixtureFactory::entry('Adoption GraphQL format');
    $author = adoptionMember('adoption-gql');
    $plain = adoptionComment($entry, $author, 'Plain words');
    $rich = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Rich words']]]]]),
        'contentFormat' => ContentFormat::Editor,
        'userId' => (int)$author->id,
    ]))->comment;
    $save = static function(int $id, string $arguments) {
        $result = gapsGql(['comments:save', 'comments:read'], "mutation { saveComment(id: {$id}, {$arguments}) { id } }");
        Craft::$app->getGql()->setActiveSchema(null);

        return $result;
    };
    $reload = static fn(int $id) => Comments::$plugin->getComments()->getCommentById($id, (int)$entry->siteId);

    $plainResult = $save((int)$plain->id, 'contentFormat: "editor", comment: ""');
    expect($plainResult['errors'][0]['message'] ?? '')->toContain('blank');
    expect($reload((int)$plain->id)->comment)->toBe('Plain words');

    foreach (['comment: ""', 'comment: null', 'comment: "Only the plain text"'] as $arguments) {
        expect($save((int)$rich->id, $arguments)['errors'][0]['message'] ?? '')->toContain('cannot be converted');
        expect($reload((int)$rich->id)->comment)->toBe('Rich words');
    }

    // The way that's allowed, so the refusals above are about what was sent.
    $document = addslashes(json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Edited words']]]]]));
    expect($save((int)$rich->id, "editorContent: \"{$document}\"")['errors'] ?? [])->toBe([]);
    expect($reload((int)$rich->id)->comment)->toBe('Edited words');
});
