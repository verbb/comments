<?php
use verbb\comments\helpers\EditorContexts;
use verbb\editor\Editor;
use verbb\editor\models\EditorConfig;
use verbb\editor\models\MentionConfig;

function editorTransportRequest(array $body): void
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request = Craft::$app->getRequest();
    $request->getHeaders()->set('Accept', 'application/json');
    $request->setBodyParams($body);
}

function dispatchEditorMentionSearch(): yii\web\Response
{
    $controller = new \verbb\editor\controllers\MentionsController('mentions', Editor::getInstance());

    return $controller->runAction('search');
}

afterEach(function() {
    Craft::$app->getRequest()->enableCsrfValidation = false;
    Craft::$app->getRequest()->setBodyParams([]);
    unset($_SERVER['REQUEST_METHOD']);
});

it('rejects guest resource dispatch before running an Editor action', function() {
    EditorContexts::refresh();
    $identity = Craft::$app->getUser()->getIdentity();
    Craft::$app->getUser()->setIdentity(null);
    editorTransportRequest(['context' => 'comments.reply', 'query' => 'someone']);

    try {
        dispatchEditorMentionSearch();
    } finally {
        Craft::$app->getUser()->setIdentity($identity);
    }
})->throws(yii\web\ForbiddenHttpException::class);

it('rejects an invalid CSRF token through Craft request dispatch', function() {
    EditorContexts::refresh();
    $request = Craft::$app->getRequest();
    $request->enableCsrfValidation = true;
    editorTransportRequest(['context' => 'comments.reply', 'query' => 'someone']);

    dispatchEditorMentionSearch();
})->throws(yii\web\BadRequestHttpException::class);

it('returns stable non-leaking JSON for an unknown context', function() {
    EditorContexts::refresh();
    editorTransportRequest(['context' => 'transport.unknown', 'query' => 'someone']);
    Craft::$app->getResponse()->setStatusCode(200);
    $response = dispatchEditorMentionSearch();

    expect($response->statusCode)->toBe(404);
    expect($response->data['message'])->toBe('This editor is no longer available.');
    expect(json_encode($response->data))->not->toContain('transport.unknown');
});

it('refuses a search in a context with mentions turned off', function() {
    EditorContexts::refresh();
    Editor::getInstance()->getEditorConfigs()->registerConfig(new EditorConfig([
        'handle' => 'transport.disabled',
        'mentions' => new MentionConfig(['enabled' => false]),
    ]), true);
    editorTransportRequest(['context' => 'transport.disabled', 'query' => 'someone']);
    Craft::$app->getResponse()->setStatusCode(200);
    $response = dispatchEditorMentionSearch();

    expect($response->statusCode)->toBe(400);
    expect($response->data['message'])->toBe('Mentions are disabled for this editor context.');
});

it('bounds search input before dispatching to a provider', function() {
    EditorContexts::refresh();
    $entry = Tests\Support\Fixtures\CommentsFixtureFactory::entry('Transport bounds');
    editorTransportRequest([
        'context' => 'comments.reply',
        'query' => str_repeat('x', 201),
        'scope' => ['ownerId' => (int)$entry->id, 'siteId' => (int)$entry->siteId],
    ]);
    Craft::$app->getResponse()->setStatusCode(200);
    $response = dispatchEditorMentionSearch();

    expect($response->statusCode)->toBe(400);
    expect($response->data['message'])->toBe('The search is too long.');
});

it('sends the browser’s mention searches to the data API’s action, under its sign-in tiers', function() {
    EditorContexts::refresh();
    $entry = Tests\Support\Fixtures\CommentsFixtureFactory::entry('Transport options');
    $craft = EditorContexts::browserOptions('comments.reply', (int)$entry->id, (int)$entry->siteId)['craft'];
    $url = $craft['endpoints']['mentions'];
    $generalConfig = Craft::$app->getConfig()->getGeneral();

    expect($url)->toContain($generalConfig->actionTrigger . '/comments/api/mentions');
    expect($url)->not->toContain('verbb-editor');
    expect($craft['csrfTokenName'])->toBe($generalConfig->csrfTokenName);
});
