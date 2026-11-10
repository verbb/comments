<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

it('creates a rich comment through Editor normalization', function() {
    $entry = CommentsFixtureFactory::entry('C1 rich create owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->requireModeration = false;

    $payload = json_encode([
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'Hello '],
                ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'world'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $payload,
        'contentFormat' => ContentFormat::Editor,
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Approved);
    expect($result->comment->contentFormat)->toBe(ContentFormat::Editor->value);
    expect($result->comment->comment)->toBe('Hello world');
    expect($result->comment->bodyContent)->toContain('"type":"paragraph"');

    $html = $result->comment->getRenderedBodyHtml()->__toString();
    expect($html)->toContain('<strong>world</strong>');
});

it('defaults new installs to rich authoring', function() {
    expect((new verbb\comments\models\Settings())->authoringMode)->toBe(AuthoringMode::Rich->value)
        ->and((new verbb\comments\models\Settings())->isRichAuthoringEnabled())->toBeTrue();
});

it('writes plain text in the plugin\'s own editor, without loading Editor, when authoring mode is plain', function() {
    $entry = CommentsFixtureFactory::entry('C1 plain editor owner');
    Comments::$plugin->getSettings()->authoringMode = AuthoringMode::Plain->value;
    Comments::$plugin->getSettings()->securityMaxLength = '500';

    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $editor = $frontend->buildEditorView($view);
    $html = $frontend->renderPartial('_editor/form', ['instance' => 'c1', 'conversation' => $view, 'viewer' => $frontend->buildViewerView($view), 'editor' => $editor, 'form' => 'create']);
    $page = (string)$frontend->render((int)$entry->id);

    expect($editor->editorAsset)->toBeNull()
        ->and($html)->not->toContain('<verbb-editor')
        ->not->toContain('data-comments-editor>')
        ->not->toContain('data-comments-editor-module')
        ->not->toContain('name="fields[editorContent]"')
        ->toContain('<textarea')
        ->toContain('name="fields[comment]"')
        ->toContain('data-comments-collapsible')
        ->toContain('data-comments-limit="500"')
        ->toContain('data-comments-draft-key="' . $editor->draftKey . '"')
        ->and($page)->not->toContain('data-comments-editor-module');
});

it('keeps Editor in a template until it\'s wanted when authoring mode is rich', function() {
    $entry = CommentsFixtureFactory::entry('C1 rich editor owner');
    Comments::$plugin->getSettings()->authoringMode = AuthoringMode::Rich->value;

    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $editor = $frontend->buildEditorView($view);
    $html = $frontend->renderPartial('_editor/form', ['instance' => 'c1', 'conversation' => $view, 'viewer' => $frontend->buildViewerView($view), 'editor' => $editor, 'form' => 'create']);

    preg_match('/<template data-comments-editor>\s*<verbb-editor\b[^>]*data-options="([^"]*)"/s', $html, $match);
    $options = json_decode(html_entity_decode($match[1] ?? ''), true);

    expect($match)->not->toBeEmpty()
        ->and($html)->toContain('<textarea')
        ->toContain('name="fields[editorContent]"')
        ->toContain('data-comments-editor-module="' . ($editor->editorAsset['module'] ?? 'missing') . '"')
        ->and($options['toolbar'] ?? null)->not->toBeFalse()
        ->and(array_filter($options['features'] ?? []))->not->toBe([]);
});

it('only names Editor for the browser to load later, and never in plain mode', function(string $mode) {
    $entry = CommentsFixtureFactory::entry('C1 asset owner ' . $mode);
    Comments::$plugin->getSettings()->authoringMode = $mode;

    $view = Craft::$app->getView();
    $view->assetBundles = [];
    $html = (string)Comments::$plugin->getComments()->render((int)$entry->id);

    // Editor isn't registered with the page in either mode. A rich thread says where the script can load it from.
    expect(array_keys($view->assetBundles))->not->toContain(verbb\editor\web\assets\editor\EditorAsset::class)
        ->toContain(verbb\comments\web\assets\frontend\FrontEndAsset::class)
        ->and(str_contains($html, 'data-comments-editor-module'))->toBe($mode === AuthoringMode::Rich->value);
})->with([AuthoringMode::Plain->value, AuthoringMode::Rich->value]);

it('rejects rich submissions when authoring mode is plain', function() {
    $entry = CommentsFixtureFactory::entry('C1 rich rejected owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Plain->value;
    $settings->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => '[{"type":"paragraph","content":[{"type":"text","text":"Nope"}]}]',
        'contentFormat' => ContentFormat::Editor,
        'userId' => (int)$admin->id,
    ]));
})->throws(\yii\base\UserException::class);

it('creates a rich reply with preserved hierarchy', function() {
    $entry = CommentsFixtureFactory::entry('C1 rich reply owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->requireModeration = false;

    $root = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Root comment',
        'userId' => (int)$admin->id,
    ]));

    $replyPayload = json_encode([
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'Reply ', 'marks' => []],
                ['type' => 'text', 'marks' => [['type' => 'italic']], 'text' => 'body'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $reply = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'parentId' => $root->comment->id,
        'content' => $replyPayload,
        'contentFormat' => ContentFormat::Editor,
        'userId' => (int)$admin->id,
    ]));

    expect($reply->comment->parentId)->toBe((int)$root->comment->id);
    expect($reply->comment->contentFormat)->toBe(ContentFormat::Editor->value);
    expect($reply->comment->comment)->toBe('Reply body');
});

it('updates rich comment bodies through applyBodyFromRequest using the edit context', function() {
    $entry = CommentsFixtureFactory::entry('C1 rich edit owner');
    $admin = Craft::$app->getUser()->getIdentity();

    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->requireModeration = false;

    $initialPayload = json_encode([
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'Before edit'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $created = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $initialPayload,
        'contentFormat' => ContentFormat::Editor,
        'userId' => (int)$admin->id,
    ]));

    $updatedPayload = json_encode([
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'After '],
                ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'edit'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    Craft::$app->getRequest()->setBodyParams([
        'contentFormat' => ContentFormat::Editor->value,
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => [
            'editorContent' => $updatedPayload,
        ],
    ]);

    $comment = Comments::$plugin->getComments()->getCommentById((int)$created->comment->id, (int)$entry->siteId);
    Comments::$plugin->getComments()->applyBodyFromRequest($comment);
    expect(Craft::$app->getElements()->saveElement($comment, true, false))->toBeTrue();

    $comment = Comments::$plugin->getComments()->getCommentById((int)$created->comment->id, (int)$entry->siteId);
    expect($comment->contentFormat)->toBe(ContentFormat::Editor->value);
    expect($comment->comment)->toBe('After edit');
    expect($comment->getRenderedBodyHtml()->__toString())->toContain('<strong>edit</strong>');
});

it('updates both canonical and compatibility text when a plain comment is edited', function() {
    $entry = CommentsFixtureFactory::entry('C1 plain edit owner');
    $admin = Craft::$app->getUser()->getIdentity();

    Comments::$plugin->getSettings()->authoringMode = AuthoringMode::Plain->value;
    Comments::$plugin->getSettings()->requireModeration = false;

    $created = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Before plain edit',
        'contentFormat' => ContentFormat::Plain,
        'userId' => (int)$admin->id,
    ]));

    Craft::$app->getRequest()->setBodyParams([
        'contentFormat' => ContentFormat::Plain->value,
        'fields' => [
            'comment' => 'After plain edit',
        ],
    ]);

    $comment = Comments::$plugin->getComments()->getCommentById((int)$created->comment->id, (int)$entry->siteId);
    Comments::$plugin->getComments()->applyBodyFromRequest($comment);
    expect(Craft::$app->getElements()->saveElement($comment, true, false))->toBeTrue();

    $comment = Comments::$plugin->getComments()->getCommentById((int)$created->comment->id, (int)$entry->siteId);

    expect($comment->contentFormat)->toBe(ContentFormat::Plain->value);
    expect($comment->bodyContent)->toBe('After plain edit');
    expect($comment->comment)->toBe('After plain edit');
});

it('keeps literal markup escaped in rendered rich comments', function() {
    $entry = CommentsFixtureFactory::entry('C1 safe rendering owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->requireModeration = false;
    $payload = json_encode([[
        'type' => 'paragraph',
        'content' => [['type' => 'text', 'text' => '<img src=x onerror=alert(1)>']],
    ]], JSON_THROW_ON_ERROR);

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $payload,
        'contentFormat' => ContentFormat::Editor,
        'userId' => (int)$admin->id,
    ]));
    $html = $result->comment->getRenderedBodyHtml()->__toString();

    expect($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
    expect($html)->not->toContain('<img');
});

it('renders Editor, with the comment being edited, its limit and a per-person draft', function() {
    $entry = CommentsFixtureFactory::entry('C1 editor owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->requireModeration = false;
    $settings->securityMaxLength = '500';

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => json_encode([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Editor prefill']]]], JSON_THROW_ON_ERROR),
        'contentFormat' => ContentFormat::Editor,
        'userId' => (int)$admin->id,
    ]))->comment;

    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $editor = $frontend->buildEditorView($view, 'edit', $comment);
    $html = html_entity_decode($frontend->renderPartial('_editor/form', [
        'instance' => 'c1',
        'conversation' => $view,
        'viewer' => $frontend->buildViewerView($view),
        'editor' => $editor,
        'form' => 'edit',
    ]));

    preg_match('/<verbb-editor\b[^>]*data-options="([^"]*)"/s', $frontend->renderPartial('_editor/form', ['instance' => 'c1', 'conversation' => $view, 'viewer' => $frontend->buildViewerView($view), 'editor' => $editor, 'form' => 'edit']), $match);
    $options = json_decode(html_entity_decode($match[1] ?? ''), true);

    expect($html)->toContain('<verbb-editor')
        ->toContain('submit-label="Save comment"')
        ->toContain('limit="500"')
        ->toContain('draft-key="comments:' . $entry->siteId . ':' . $entry->id . ':edit:' . $comment->id . ':' . $admin->id . '"')
        ->toContain('data-comments-action="cancel-editor"')
        ->and($options['content'][0]['content'][0]['text'] ?? null)->toBe('Editor prefill')
        ->and($options['craft'] ?? null)->toBeArray();
});
