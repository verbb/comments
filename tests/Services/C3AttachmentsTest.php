<?php
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\AuthoringMode;
use verbb\comments\helpers\EditorContexts;

function c3AttachmentVolume(): array
{
    $handle = 'commentsAttachments' . bin2hex(random_bytes(4));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local([
        'name' => $handle,
        'handle' => $handle,
        'path' => $path,
        'hasUrls' => true,
        'url' => 'https://assets.example.test/' . $handle . '/',
    ]);
    expect(Craft::$app->getFs()->saveFilesystem($filesystem))->toBeTrue();
    $volume = new craft\models\Volume([
        'name' => $handle,
        'handle' => $handle,
        'fsHandle' => $handle,
    ]);
    expect(Craft::$app->getVolumes()->saveVolume($volume))->toBeTrue();

    return [$filesystem, $volume, $path];
}

function c3TemporaryUpload(int $volumeId, string $contextHandle = 'comments.reply'): array
{
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowAttachments = true;
    $settings->attachmentVolumeId = $volumeId;
    $settings->attachmentExtensions = ['txt'];
    EditorContexts::refresh();
    $path = tempnam(sys_get_temp_dir(), 'comments-editor-upload-');
    file_put_contents($path, 'Attachment lifecycle fixture');
    $file = new yii\web\UploadedFile([
        'name' => 'fixture.txt',
        'tempName' => $path,
        'type' => 'text/plain',
        'size' => filesize($path),
        'error' => UPLOAD_ERR_OK,
    ]);
    $editor = \verbb\editor\Editor::getInstance();
    $context = $editor->getEditorConfigs()->requireConfigByHandle($contextHandle);

    return $editor->getAssets()->upload($file, $context, 'attachment');
}

function c3AttachmentPayload(int $assetId): string
{
    return json_encode([[
        'type' => 'paragraph',
        'content' => [
            ['type' => 'text', 'text' => 'Attached file'],
            ['type' => 'attachment', 'attrs' => ['id' => $assetId, 'filename' => 'fixture.txt']],
        ],
    ]], JSON_THROW_ON_ERROR);
}

function c3UploadedFile(string $name, string $contents): yii\web\UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'comments-editor-policy-');
    file_put_contents($path, $contents);

    return new yii\web\UploadedFile([
        'name' => $name,
        'tempName' => $path,
        'type' => 'application/octet-stream',
        'size' => filesize($path),
        'error' => UPLOAD_ERR_OK,
    ]);
}

it('enables Editor attachment features when allowAttachments is on', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowAttachments = true;

    EditorContexts::refresh();
    $config = EditorContexts::replyConfig();
    $features = EditorContexts::replyFeatures(true, true);

    expect($config->assets?->enabled)->toBeTrue();
    expect($features['image'])->toBeTrue();
    expect($features['attachment'])->toBeTrue();
});

it('extracts asset ids from rich text when attachments are enabled', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->authoringMode = AuthoringMode::Rich->value;
    $settings->allowAttachments = true;

    $editor = \verbb\editor\Editor::getInstance();
    EditorContexts::refresh();
    $config = $editor->getEditorConfigs()->requireConfigByHandle('comments.reply');
    $richText = $editor->getDocuments()->normalize([
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'editorAsset', 'attrs' => ['id' => 42, 'filename' => 'photo.jpg']],
            ],
        ],
    ], $config);

    $assetIds = Comments::$plugin->getCommentAssets()->resolveAssetIdsFromRichText($richText);

    expect($assetIds)->toBe([42]);
});

it('returns no asset ids when attachments are disabled', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->allowAttachments = false;

    $editor = \verbb\editor\Editor::getInstance();
    EditorContexts::refresh();
    $config = $editor->getEditorConfigs()->requireConfigByHandle('comments.reply');
    $richText = $editor->getDocuments()->normalize([
        [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'editorAsset', 'attrs' => ['id' => 42]],
            ],
        ],
    ], $config);

    expect(Comments::$plugin->getCommentAssets()->resolveAssetIdsFromRichText($richText))->toBe([]);
});

it('syncs comment asset junction rows', function() {
    $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 asset sync owner');
    $admin = Craft::$app->getUser()->getIdentity();

    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Plain comment for asset sync',
        'userId' => (int)$admin->id,
    ]));

    Comments::$plugin->getCommentAssets()->syncAssetIds((int)$result->comment->id, []);

    expect(Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$result->comment->id))->toBe([]);
});

it('creates a temporary Craft Asset and finalises it during rich comment creation', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();

    try {
        $upload = c3TemporaryUpload((int)$volume->id);
        $temporary = craft\elements\Asset::find()->id($upload['id'])->status(null)->one();
        expect($temporary)->not->toBeNull();
        expect($temporary->volumeId)->toBeNull();

        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 lifecycle owner');
        $admin = Craft::$app->getUser()->getIdentity();
        Comments::$plugin->getSettings()->requireModeration = false;
        $result = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => c3AttachmentPayload((int)$upload['id']),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)$admin->id,
        ]));
        $finalized = craft\elements\Asset::find()->id($upload['id'])->status(null)->one();

        expect($finalized->volumeId)->toBe((int)$volume->id);
        expect(Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$result->comment->id))->toBe([(int)$upload['id']]);

        $references = [['id' => (int)$upload['id'], 'kind' => 'attachment']];
        expect(Comments::$plugin->getCommentAssets()->finalizeReferences($references, 'comments.reply', (int)$admin->id))->toBe([(int)$upload['id']]);
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('enforces attachment content, size, image and count policy before persistence', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();

    try {
        $settings = Comments::$plugin->getSettings();
        $settings->authoringMode = AuthoringMode::Rich->value;
        $settings->allowAttachments = true;
        $settings->attachmentVolumeId = (int)$volume->id;
        EditorContexts::refresh();
        $editor = \verbb\editor\Editor::getInstance();
        $context = $editor->getEditorConfigs()->requireConfigByHandle('comments.reply');
        $context->assets->allowedExtensions = ['pdf'];

        expect(fn() => $editor->getAssets()->upload(c3UploadedFile('spoofed.pdf', 'plain text'), $context, 'attachment'))
            ->toThrow(yii\web\BadRequestHttpException::class);
        $context->assets->allowedExtensions = [];
        expect(fn() => $editor->getAssets()->upload(c3UploadedFile('not-image.txt', 'plain text'), $context, 'inlineImage'))
            ->toThrow(yii\web\BadRequestHttpException::class);

        $context->assets->allowedExtensions = ['txt'];
        $context->assets->maxFileSize = 4;
        expect(fn() => $editor->getAssets()->upload(c3UploadedFile('large.txt', 'five!'), $context, 'attachment'))
            ->toThrow(yii\web\BadRequestHttpException::class);

        // The count is per document, so it's checked when the comment saves rather than at each upload.
        $context->assets->maxFileSize = null;
        $context->assets->maxAttachments = 1;
        $first = $editor->getAssets()->upload(c3UploadedFile('first.txt', 'one'), $context, 'attachment');
        $second = $editor->getAssets()->upload(c3UploadedFile('second.txt', 'two'), $context, 'attachment');
        expect($first['temporary'])->toBeTrue();
        expect(fn() => $editor->getDocuments()->normalize([
            ['type' => 'attachment', 'attrs' => ['id' => (int)$first['id']]],
            ['type' => 'attachment', 'attrs' => ['id' => (int)$second['id']]],
        ], $context))->toThrow(yii\base\UserException::class);
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('rejects an upload folder from a different configured volume', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    [$otherFilesystem, $otherVolume, $otherPath] = c3AttachmentVolume();

    try {
        $settings = Comments::$plugin->getSettings();
        $settings->authoringMode = AuthoringMode::Rich->value;
        $settings->allowAttachments = true;
        $settings->attachmentVolumeId = (int)$volume->id;
        EditorContexts::refresh();
        $editor = \verbb\editor\Editor::getInstance();
        $context = $editor->getEditorConfigs()->requireConfigByHandle('comments.reply');
        $context->assets->folderId = Craft::$app->getAssets()->getRootFolderByVolumeId((int)$otherVolume->id)->id;

        expect(fn() => $editor->getAssets()->upload(c3UploadedFile('fixture.txt', 'text'), $context, 'attachment'))
            ->toThrow(yii\web\BadRequestHttpException::class);
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getVolumes()->deleteVolume($otherVolume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        Craft::$app->getFs()->removeFilesystem($otherFilesystem);
        craft\helpers\FileHelper::removeDirectory($path);
        craft\helpers\FileHelper::removeDirectory($otherPath);
    }
});

it('leaves an upload temporary after a failed comment save and finalises it on retry', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();

    try {
        $upload = c3TemporaryUpload((int)$volume->id);
        $admin = Craft::$app->getUser()->getIdentity();
        $submission = new \verbb\comments\models\CommentSubmission([
            'ownerId' => 999999,
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'content' => c3AttachmentPayload((int)$upload['id']),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)$admin->id,
        ]);

        expect(fn() => Comments::$plugin->getComments()->create($submission))->toThrow(InvalidArgumentException::class);
        expect(craft\elements\Asset::find()->id($upload['id'])->status(null)->one()->volumeId)->toBeNull();

        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 retry owner');
        $submission->ownerId = (int)$entry->id;
        $submission->siteId = (int)$entry->siteId;
        $result = Comments::$plugin->getComments()->create($submission);

        expect(craft\elements\Asset::find()->id($upload['id'])->status(null)->one()->volumeId)->toBe((int)$volume->id);
        expect(Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$result->comment->id))->toContain((int)$upload['id']);
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('rejects attachment finalisation by another actor or disabled Comments policy', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();

    try {
        $upload = c3TemporaryUpload((int)$volume->id);
        $other = \Tests\Support\Fixtures\CommentsFixtureFactory::user('asset-owner-mismatch');
        $references = [['id' => (int)$upload['id'], 'kind' => 'attachment']];

        expect(fn() => Comments::$plugin->getCommentAssets()->finalizeReferences($references, 'comments.reply', (int)$other->id))
            ->toThrow(yii\web\BadRequestHttpException::class);
        expect(craft\elements\Asset::find()->id($upload['id'])->status(null)->one()->volumeId)->toBeNull();

        Comments::$plugin->getSettings()->allowAttachments = false;
        expect(fn() => Comments::$plugin->getCommentAssets()->finalizeReferences($references, 'comments.reply', (int)Craft::$app->getUser()->getId()))
            ->toThrow(yii\base\UserException::class);
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('syncs removed references on canonical edit without deleting permanent assets', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();

    try {
        $upload = c3TemporaryUpload((int)$volume->id);
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 attachment edit owner');
        $admin = Craft::$app->getUser()->getIdentity();
        Comments::$plugin->getSettings()->requireModeration = false;
        $created = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => c3AttachmentPayload((int)$upload['id']),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)$admin->id,
        ]));

        Craft::$app->getRequest()->setBodyParams([
            'commentId' => (int)$created->comment->id,
            'siteId' => (int)$entry->siteId,
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich->value,
            'fields' => [
                'editorContent' => json_encode([[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Attachment removed']],
                ]], JSON_THROW_ON_ERROR),
            ],
        ]);
        Comments::$plugin->getComments()->updateFromPost((int)$created->comment->id);

        $asset = craft\elements\Asset::find()->id($upload['id'])->status(null)->one();
        expect(Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$created->comment->id))->toBe([]);
        expect($asset)->not->toBeNull();
        expect($asset->volumeId)->toBe((int)$volume->id);
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('takes an upload through to a rendered comment for a member with no asset permissions, and keeps it on edit', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    $admin = Craft::$app->getUser()->getIdentity();

    try {
        $member = \Tests\Support\Fixtures\CommentsFixtureFactory::user('c3-member-' . bin2hex(random_bytes(3)));
        $member->admin = false;
        Craft::$app->getElements()->saveElement($member, false);
        Craft::$app->getUser()->setIdentity($member);
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 member owner');
        $upload = c3TemporaryUpload((int)$volume->id);
        $settings = Comments::$plugin->getSettings();
        $settings->requireModeration = false;
        $result = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'content' => c3AttachmentPayload((int)$upload['id']),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)$member->id,
        ]));
        $comment = $result->comment;

        expect(Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$comment->id))->toBe([(int)$upload['id']]);
        expect(craft\elements\Asset::find()->id($upload['id'])->status(null)->one()->volumeId)->toBe((int)$volume->id);
        // Only the reference is stored, and rendering looks the rest up.
        expect($comment->bodyContent)->not->toContain('fixture.txt');
        expect((string)$comment->getRenderedBodyHtml())->toContain('fixture.txt');
        expect($comment->getEditorFormContent())->toContain('fixture.txt');

        // A later edit keeps what's already attached, including for another editor.
        $references = [['id' => (int)$upload['id'], 'kind' => 'attachment']];
        $other = \Tests\Support\Fixtures\CommentsFixtureFactory::user('c3-other-' . bin2hex(random_bytes(3)));
        expect(Comments::$plugin->getCommentAssets()->finalizeReferences($references, 'comments.edit', (int)$other->id, [(int)$upload['id']]))->toBe([(int)$upload['id']]);
        expect(fn() => Comments::$plugin->getCommentAssets()->finalizeReferences($references, 'comments.edit', (int)$other->id))->toThrow(yii\web\BadRequestHttpException::class);
    } finally {
        Craft::$app->getUser()->setIdentity($admin);
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('accepts a comment that is only an image', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();

    try {
        $upload = c3TemporaryUpload((int)$volume->id);
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 attachment only');
        Comments::$plugin->getSettings()->requireModeration = false;
        $result = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'content' => json_encode(['type' => 'doc', 'content' => [['type' => 'attachment', 'attrs' => ['id' => (int)$upload['id']]]]]),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)Craft::$app->getUser()->getId(),
        ]));

        expect($result->comment->id)->not->toBeNull();
        expect((string)$result->comment->getRenderedBodyHtml())->toContain('fixture.txt');
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

function c3ImageUpload(): array
{
    // The smallest valid PNG, because uploads are checked against what the file really is.
    $path = tempnam(sys_get_temp_dir(), 'comments-editor-image-');
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $file = new yii\web\UploadedFile([
        'name' => 'pixel.png',
        'tempName' => $path,
        'type' => 'image/png',
        'size' => filesize($path),
        'error' => UPLOAD_ERR_OK,
    ]);
    $editor = \verbb\editor\Editor::getInstance();

    return $editor->getAssets()->upload($file, $editor->getEditorConfigs()->requireConfigByHandle('comments.reply'), 'inlineImage');
}

function c3RichDocument(int $imageId, ?craft\elements\User $mention = null, string $text = 'Rich body'): string
{
    $inline = [['type' => 'text', 'text' => $text]];

    if ($mention) {
        $inline[] = ['type' => 'mention', 'attrs' => ['id' => (int)$mention->id, 'type' => craft\elements\User::class, 'label' => 'Stale label']];
    }

    return json_encode(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => $inline],
        ['type' => 'editorAsset', 'attrs' => ['id' => $imageId, 'alt' => 'A pixel']],
    ]], JSON_THROW_ON_ERROR);
}

function c3RichMember(string $name): craft\elements\User
{
    $user = \Tests\Support\Fixtures\CommentsFixtureFactory::user($name . '-' . bin2hex(random_bytes(3)));
    $user->admin = false;
    Craft::$app->getElements()->saveElement($user, false);

    return $user;
}

it('shows mentions and images to a guest and a member, with a hostile name escaped', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    $admin = Craft::$app->getUser()->getIdentity();

    try {
        $settings = Comments::$plugin->getSettings();
        $settings->requireModeration = false;
        $settings->allowMentions = true;
        c3TemporaryUpload((int)$volume->id);
        $settings->attachmentExtensions = ['png', 'txt'];
        EditorContexts::refresh();
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 render audience');
        $mentioned = c3RichMember('c3-mentioned');
        $mentioned->fullName = '<img src=x onerror=alert(1)>Pat';
        Craft::$app->getElements()->saveElement($mentioned, false);
        Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'content' => 'Prior participant',
            'userId' => (int)$mentioned->id,
        ]));
        $author = c3RichMember('c3-author');
        Craft::$app->getUser()->setIdentity($author);
        $image = c3ImageUpload();
        $comment = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'content' => c3RichDocument((int)$image['id'], $mentioned),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)$author->id,
        ]))->comment;

        foreach ([null, c3RichMember('c3-reader')] as $viewer) {
            Craft::$app->getUser()->setIdentity($viewer);
            $html = (string)Comment::find()->id($comment->id)->status(null)->one()->getRenderedBodyHtml();

            expect($html)->toContain('<img');
            expect($html)->toContain('pixel');
            expect($html)->toContain('Pat');
            expect($html)->not->toContain('Stale label');
            expect($html)->not->toContain('<img src=x');
            expect($html)->toContain('&lt;img src=x');
        }
    } finally {
        Craft::$app->getUser()->setIdentity($admin);
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('keeps existing media through an author’s edit and a moderator’s edit', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    $admin = Craft::$app->getUser()->getIdentity();
    $request = Craft::$app->getRequest();

    try {
        $settings = Comments::$plugin->getSettings();
        $settings->requireModeration = false;
        $settings->enableSpamChecks = false;
        c3TemporaryUpload((int)$volume->id);
        $settings->attachmentExtensions = ['png', 'txt'];
        EditorContexts::refresh();
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 edit keeps media');
        $author = c3RichMember('c3-editing-author');
        Craft::$app->getUser()->setIdentity($author);
        $image = c3ImageUpload();
        $comment = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'content' => c3RichDocument((int)$image['id']),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)$author->id,
        ]))->comment;
        $assets = Comments::$plugin->getCommentAssets();

        $request->setBodyParams([
            'commentId' => $comment->id,
            'siteId' => $entry->siteId,
            'contentFormat' => 'rich',
            'fields' => ['editorContent' => c3RichDocument((int)$image['id'], text: 'Edited by the author')],
        ]);
        $edited = Comments::$plugin->getComments()->updateFromPost((int)$comment->id);

        expect($edited->comment)->toContain('Edited by the author');
        expect($assets->getAssetIdsForComment((int)$comment->id))->toBe([(int)$image['id']]);
        expect((string)$edited->getRenderedBodyHtml())->toContain('<img');

        // Through Craft's element editor, by someone who didn't upload it.
        Craft::$app->getUser()->setIdentity($admin);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->setBodyParams([
            'status' => $edited->status,
            'authorId' => [$author->id],
            'ownerType' => $edited->getOwnerType(),
            'ownerEntryId' => [$entry->id],
            'contentFormat' => 'rich',
            'fields' => ['editorContent' => c3RichDocument((int)$image['id'], text: 'Edited by a moderator')],
        ]);
        $moderated = Comment::find()->id($comment->id)->status(null)->one();
        $moderated->setAttributesFromRequest([]);

        expect(Craft::$app->getElements()->saveElement($moderated))->toBeTrue();
        expect(Comment::find()->id($comment->id)->status(null)->one()->comment)->toContain('Edited by a moderator');
        expect($assets->getAssetIdsForComment((int)$comment->id))->toBe([(int)$image['id']]);
    } finally {
        unset($_SERVER['REQUEST_METHOD']);
        $request->setBodyParams([]);
        Craft::$app->getUser()->setIdentity($admin);
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

// Asset IDs from public forms
// =========================================================================

/** An asset already on the site, which a visitor didn't upload. */
function c3ForeignAsset(int $volumeId, string $name = 'foreign.txt'): craft\elements\Asset
{
    $path = tempnam(sys_get_temp_dir(), 'comments-foreign-asset-');
    file_put_contents($path, 'Someone else’s file');
    $asset = new craft\elements\Asset();
    $asset->tempFilePath = $path;
    $asset->setFilename($name);
    $asset->newFolderId = (int)Craft::$app->getAssets()->getRootFolderByVolumeId($volumeId)->id;
    $asset->setVolumeId($volumeId);
    $asset->avoidFilenameConflicts = true;
    $asset->setScenario(craft\elements\Asset::SCENARIO_CREATE);
    expect(Craft::$app->getElements()->saveElement($asset))->toBeTrue();

    return $asset;
}

/** A public Assets field on the comment layout for the length of a callback. */
function c3WithPublicAssetsField(callable $callback): void
{
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    $fields = Craft::$app->getFields();
    $field = new craft\fields\Assets(['name' => 'Photos', 'handle' => 'photos' . bin2hex(random_bytes(3)), 'sources' => '*']);
    expect($fields->saveField($field))->toBeTrue();
    $layout = $fields->getLayoutByType(Comment::class);
    $tabs = $layout->getTabs();
    $tab = $tabs[0] ?? new craft\models\FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $original = $tab->getElements();
    $tab->setElements([...$original, new craft\fieldlayoutelements\CustomField($field)]);
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
    expect($fields->saveLayout($layout, false))->toBeTrue();
    $settings = Comments::$plugin->getSettings();
    $settings->publicFields = [$field->handle];
    $settings->requireModeration = false;

    try {
        $callback($field, $volume);
    } finally {
        Craft::$app->getRequest()->setBodyParams([]);
        $tab->setElements($original);
        $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
        $fields->saveLayout($layout, false);
        $fields->deleteField($field);
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
}

it('refuses an asset ID posted to a public Assets field on a new comment', function() {
    c3WithPublicAssetsField(function(craft\fields\Assets $field, craft\models\Volume $volume) {
        $foreign = c3ForeignAsset((int)$volume->id);
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 foreign asset create');
        Craft::$app->getRequest()->setBodyParams([
            'elementId' => $entry->id,
            'siteId' => $entry->siteId,
            'fields' => ['comment' => 'With someone else’s file', $field->handle => [(string)$foreign->id]],
        ]);

        expect(fn() => Comments::$plugin->getComments()->createFromPost())
            ->toThrow(\verbb\comments\exceptions\CommentValidationException::class, 'Only files you upload here can be added.');
        expect(Comment::find()->ownerId($entry->id)->status(null)->exists())->toBeFalse()
            ->and(craft\elements\Asset::find()->relatedTo(['targetElement' => $foreign->id, 'field' => $field->id])->exists())->toBeFalse();

        // The same form with no file posts as normal.
        Craft::$app->getRequest()->setBodyParams([
            'elementId' => $entry->id,
            'siteId' => $entry->siteId,
            'fields' => ['comment' => 'No file', $field->handle => ''],
        ]);

        expect(Comments::$plugin->getComments()->createFromPost()->comment->id)->not->toBeNull();
    });
});

it('keeps a comment’s own files on a public edit and refuses anyone else’s', function() {
    c3WithPublicAssetsField(function(craft\fields\Assets $field, craft\models\Volume $volume) {
        $own = c3ForeignAsset((int)$volume->id, 'own.txt');
        $foreign = c3ForeignAsset((int)$volume->id);
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 foreign asset edit');
        $comment = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => 'Has a file',
            'userId' => (int)Craft::$app->getUser()->getId(),
        ]))->comment;
        $comment->setFieldValue($field->handle, [$own->id]);
        expect(Craft::$app->getElements()->saveElement($comment, false))->toBeTrue();

        $edit = static function(array $assetIds) use ($comment, $entry, $field) {
            Craft::$app->getRequest()->setBodyParams([
                'commentId' => $comment->id,
                'siteId' => $entry->siteId,
                'fields' => ['comment' => 'Edited', $field->handle => array_map('strval', $assetIds)],
            ]);

            return Comments::$plugin->getComments()->updateFromPost((int)$comment->id);
        };
        $related = static fn(): array => craft\elements\Asset::find()->relatedTo(['sourceElement' => $comment->id, 'field' => $field->id])->status(null)->ids();

        expect($edit([$own->id])->getFieldValue($field->handle)->ids())->toBe([(int)$own->id]);
        expect(fn() => $edit([$own->id, $foreign->id]))
            ->toThrow(\verbb\comments\exceptions\CommentValidationException::class, 'Only files you upload here can be added.');
        expect($related())->toBe([(int)$own->id]);

        // GraphQL edits follow the same rule.
        $schema = new craft\models\GqlSchema(['name' => 'Foreign asset', 'scope' => ['comments:read', 'comments:save', 'volumes.' . $volume->uid . ':read']]);
        Craft::$app->getGql()->flushCaches();
        Craft::$app->getGql()->setActiveSchema($schema);
        $gql = static fn(int $assetId): array => Craft::$app->getGql()->executeQuery($schema, "mutation { saveComment(id: {$comment->id}, comment: \"Via GraphQL\", {$field->handle}: [{$assetId}]) { id } }", debugMode: true);

        try {
            $refused = $gql((int)$foreign->id);
            $afterRefused = $related();
            $kept = $gql((int)$own->id);
        } finally {
            Craft::$app->getGql()->setActiveSchema(null);
            // The schema built here would otherwise be reused by later tests.
            Craft::$app->getGql()->flushCaches();
        }

        // Craft 5.10 sets the field through `setFieldValue()` and later versions through `setFieldValueFromRequest()`.
        expect($afterRefused)->toBe([(int)$own->id])
            ->and($refused['errors'][0]['message'] ?? null)->toBe('Only files you upload here can be added.')
            ->and($kept['errors'] ?? [])->toBe([])
            ->and($related())->toBe([(int)$own->id]);
    });
});

it('refuses a guest’s rich comment that points at a file nobody uploaded with it', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    $admin = Craft::$app->getUser()->getIdentity();

    try {
        // An attachment that's been finalised, then made to look anonymously uploaded, as a guest's
        // Assets field upload into the same folder would be.
        $member = c3RichMember('c3-anon-source');
        Craft::$app->getUser()->setIdentity($member);
        $upload = c3TemporaryUpload((int)$volume->id);
        $references = [['id' => (int)$upload['id'], 'kind' => 'attachment']];
        Comments::$plugin->getCommentAssets()->finalizeReferences($references, 'comments.reply', (int)$member->id);
        Craft::$app->getDb()->createCommand()->update(craft\db\Table::ASSETS, ['uploaderId' => null], ['id' => $upload['id']])->execute();

        $settings = Comments::$plugin->getSettings();
        $settings->allowGuest = true;
        $settings->requireModeration = false;
        $settings->guestRequireEmailName = true;
        Craft::$app->getUser()->setIdentity(null);
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 guest foreign attachment');

        expect(fn() => Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'content' => c3AttachmentPayload((int)$upload['id']),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'name' => 'Guest',
            'email' => 'c3-guest-' . bin2hex(random_bytes(3)) . '@example.test',
        ])))->toThrow(yii\base\UserException::class, 'One or more comment attachments could not be finalised.');
        expect(Comment::find()->ownerId($entry->id)->status(null)->exists())->toBeFalse();

        // What's already on a comment stays usable, so this only stops new files.
        expect(Comments::$plugin->getCommentAssets()->finalizeReferences($references, 'comments.edit', 0, [(int)$upload['id']]))->toBe([(int)$upload['id']]);
    } finally {
        Craft::$app->getUser()->setIdentity($admin);
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('puts a comment’s files in its Attachment Subfolder, made as it’s needed', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    $settings = Comments::$plugin->getSettings();

    try {
        $upload = c3TemporaryUpload((int)$volume->id);
        $settings->attachmentSubpath = 'comments/{owner.slug}';
        $settings->requireModeration = false;
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('C3 subfolder owner');
        $admin = Craft::$app->getUser()->getIdentity();
        $result = Comments::$plugin->getComments()->create(new \verbb\comments\models\CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => c3AttachmentPayload((int)$upload['id']),
            'contentFormat' => \verbb\comments\enums\ContentFormat::Rich,
            'userId' => (int)$admin->id,
        ]));
        $asset = craft\elements\Asset::find()->id($upload['id'])->status(null)->one();

        expect($asset->volumeId)->toBe((int)$volume->id);
        expect($asset->getFolder()->path)->toBe('comments/' . $entry->slug . '/');
        expect(Comments::$plugin->getCommentAssets()->getAssetIdsForComment((int)$result->comment->id))->toBe([(int)$upload['id']]);

        // An edit's new file goes to the same folder.
        $second = c3TemporaryUpload((int)$volume->id, 'comments.edit');
        $settings->attachmentSubpath = 'comments/{owner.slug}';
        Comments::$plugin->getCommentAssets()->syncEditedReferences($result->comment, [
            ['id' => (int)$upload['id'], 'kind' => 'attachment'],
            ['id' => (int)$second['id'], 'kind' => 'attachment'],
        ], (int)$admin->id);

        expect(craft\elements\Asset::find()->id($second['id'])->status(null)->one()->getFolder()->path)->toBe('comments/' . $entry->slug . '/');
    } finally {
        $settings->attachmentSubpath = null;
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('keeps a comment’s files in the volume’s root when its subfolder can’t be worked out or would leave the volume', function() {
    [$filesystem, $volume, $path] = c3AttachmentVolume();
    $settings = Comments::$plugin->getSettings();
    $settings->allowAttachments = true;
    $settings->attachmentVolumeId = (int)$volume->id;
    $root = (int)Craft::$app->getAssets()->getRootFolderByVolumeId((int)$volume->id)->id;
    $commentAssets = Comments::$plugin->getCommentAssets();

    try {
        $entry = \Tests\Support\Fixtures\CommentsFixtureFactory::entry('../../../etc');
        $entry->title = '../../../etc';
        $guestComment = new Comment(['ownerId' => $entry->id, 'siteId' => $entry->siteId]);

        // A guest has no user, so `{user.id}` comes out empty.
        $settings->attachmentSubpath = 'members/{user.id}';
        expect($commentAssets->getAttachmentFolderId($guestComment))->toBeNull();

        // Each part is cleaned like a filename, so `..` can't climb out of the volume.
        $settings->attachmentSubpath = 'comments/{owner.title}';
        $guestComment->setOwner($entry);
        expect($commentAssets->getAttachmentFolderId($guestComment))->toBeNull();

        // A template that can't compile, as `config/comments.php` could set without the settings' check.
        $settings->attachmentSubpath = '{owner.id|notAFilter}';
        expect($commentAssets->getAttachmentFolderId($guestComment))->toBeNull();

        // Blank is the root, with no folder looked up.
        $settings->attachmentSubpath = '';
        expect($commentAssets->getAttachmentFolderId($guestComment))->toBeNull();

        // A plain folder name needs no comment.
        $settings->attachmentSubpath = 'comment-files';
        $folderId = $commentAssets->getAttachmentFolderId(null);
        expect($folderId)->not->toBeNull()->not->toBe($root);
        expect(Craft::$app->getAssets()->getFolderById($folderId)->path)->toBe('comment-files/');
    } finally {
        $settings->attachmentSubpath = null;
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('checks the Attachment Subfolder’s Twig as the settings are saved', function() {
    $settings = new \verbb\comments\models\Settings(['pluginName' => 'Comments', 'attachmentSubpath' => 'comments/{{ owner.id']);
    $settings->validate(['attachmentSubpath']);
    expect($settings->getErrors('attachmentSubpath'))->not->toBeEmpty();

    foreach (['comments/{owner.section.handle}', "{commentDate|date('Y/m')}", 'plain/folder', ''] as $subpath) {
        $settings = new \verbb\comments\models\Settings(['pluginName' => 'Comments', 'attachmentSubpath' => $subpath]);
        $settings->validate(['attachmentSubpath']);
        expect($settings->getErrors('attachmentSubpath'))->toBe([]);
    }
});
