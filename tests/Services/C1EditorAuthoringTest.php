<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;
use verbb\editor\RichText;

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
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Approved);
    expect($result->comment->contentFormat)->toBe(ContentFormat::Rich->value);
    expect($result->comment->comment)->toBe('Hello world');
    expect($result->comment->bodyContent)->toContain('"type":"paragraph"');

    $html = $result->comment->getRenderedBodyHtml()->__toString();
    expect($html)->toContain('<strong>world</strong>');
});

it('defaults new installs to rich authoring', function() {
    expect((new verbb\comments\models\Settings())->authoringMode)->toBe(AuthoringMode::Rich->value)
        ->and((new verbb\comments\models\Settings())->isRichAuthoringEnabled())->toBeTrue();
});

it('writes plain text without loading Editor when authoring mode is plain', function() {
    $entry = CommentsFixtureFactory::entry('C1 plain editor owner');
    Comments::$plugin->getSettings()->authoringMode = AuthoringMode::Plain->value;
    Comments::$plugin->getSettings()->securityMaxLength = '500';

    $api = Comments::$plugin->getApi();
    $thread = $api->thread($entry);
    $viewer = $api->viewer($entry);

    expect($thread['editor'])->toBe(['authoring' => 'plain', 'maxLength' => 500, 'richEditor' => null])
        ->and($viewer['editor']['richEditor'])->toBeNull()
        ->and($viewer['editor']['draftKey'])->toBeString();
});

it('names Editor for the browser to load once it\'s wanted when authoring mode is rich', function() {
    $entry = CommentsFixtureFactory::entry('C1 rich editor owner');
    Comments::$plugin->getSettings()->authoringMode = AuthoringMode::Rich->value;

    $api = Comments::$plugin->getApi();
    $editor = $api->thread($entry)['editor'];
    $options = $api->viewer($entry)['editor']['richEditor']['options'] ?? [];

    expect($editor['authoring'])->toBe('rich')
        ->and($editor['richEditor']['module'] ?? null)->toContain('editor.js')
        ->and($options['toolbar'] ?? null)->not->toBeFalse()
        ->and(array_filter($options['features'] ?? []))->not->toBe([]);
});

it('never registers Editor with the page, in either mode', function(string $mode) {
    $entry = CommentsFixtureFactory::entry('C1 asset owner ' . $mode);
    Comments::$plugin->getSettings()->authoringMode = $mode;

    $view = Craft::$app->getView();
    $view->assetBundles = [];
    $html = (string)Comments::$plugin->getComments()->render((int)$entry->id);

    // The script loads it on intent, from where the thread's data says it is.
    expect(array_keys($view->assetBundles))->not->toContain(verbb\editor\web\assets\editor\EditorAsset::class)
        ->toContain(verbb\comments\web\assets\frontend\CommentsScriptAsset::class)
        ->and($html)->not->toContain('editor.js');
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
        'contentFormat' => ContentFormat::Rich,
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
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));

    expect($reply->comment->parentId)->toBe((int)$root->comment->id);
    expect($reply->comment->contentFormat)->toBe(ContentFormat::Rich->value);
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
        'contentFormat' => ContentFormat::Rich,
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
        'contentFormat' => ContentFormat::Rich->value,
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
    expect($comment->contentFormat)->toBe(ContentFormat::Rich->value);
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

it('lets a moderator make a plain comment rich in the control panel while rich authoring is on, and keeps it plain when its text isn’t touched', function() {
    $entry = CommentsFixtureFactory::entry('C1 plain to rich owner');
    $admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Plain->value;
    $settings->requireModeration = false;

    $created = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => "First line\nSecond line",
        'contentFormat' => ContentFormat::Plain,
        'userId' => (int)$admin->id,
    ]));
    $id = (int)$created->comment->id;
    $load = fn() => Comments::$plugin->getComments()->getCommentById($id, (int)$entry->siteId);

    // Plain authoring keeps the plain box. Rich authoring opens it in Editor, starting from its text.
    expect($load()->editsInEditor())->toBeFalse();

    $settings->authoringMode = AuthoringMode::Rich->value;
    $comment = $load();
    expect($comment->editsInEditor())->toBeTrue()
        ->and(RichText::from($comment->getEditorFormContent())->toPlainText())->toContain('First line')
        ->toContain('Second line');

    // Saved without touching the text, as the edit page sends it: no document, so it stays plain.
    Craft::$app->getRequest()->setBodyParams([
        'contentFormat' => ContentFormat::Rich->value,
        'fields' => ['editorContent' => '', 'comment' => "First line\nSecond line"],
    ]);
    Comments::$plugin->getComments()->applyBodyFromRequest($comment);
    expect(Craft::$app->getElements()->saveElement($comment, true, false))->toBeTrue()
        ->and($load()->contentFormat)->toBe(ContentFormat::Plain->value);

    // Edited in Editor, it becomes rich.
    $comment = $load();
    Craft::$app->getRequest()->setBodyParams([
        'contentFormat' => ContentFormat::Rich->value,
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'fields' => ['editorContent' => json_encode([[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Now '], ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'bold']],
        ]], JSON_THROW_ON_ERROR)],
    ]);
    Comments::$plugin->getComments()->applyBodyFromRequest($comment);
    expect(Craft::$app->getElements()->saveElement($comment, true, false))->toBeTrue();

    $comment = $load();
    expect($comment->contentFormat)->toBe(ContentFormat::Rich->value)
        ->and($comment->comment)->toBe('Now bold')
        ->and((string)$comment->getRenderedBodyHtml())->toContain('<strong>bold</strong>');
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
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]));
    $html = $result->comment->getRenderedBodyHtml()->__toString();

    expect($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
    expect($html)->not->toContain('<img');
});

it('gives the edit form Editor with the comment being edited', function() {
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
        'contentFormat' => ContentFormat::Rich,
        'userId' => (int)$admin->id,
    ]))->comment;

    $edit = Comments::$plugin->getApi()->edit($comment->uid);
    $options = $edit['editor']['richEditor']['options'] ?? [];

    expect($edit['comment']['content']['format'])->toBe('rich')
        ->and($edit['comment']['content']['document'][0]['content'][0]['text'] ?? null)->toBe('Editor prefill')
        ->and($edit['editor']['richEditor']['context'] ?? null)->toBe('comments.edit')
        ->and($options['craft'] ?? null)->toBeArray();
});
