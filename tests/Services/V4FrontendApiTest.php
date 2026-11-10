<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\FrontendEnvelope;

it('builds contract v1 envelope arrays with typed fragments', function() {
    $entry = CommentsFixtureFactory::entry('API envelope owner');
    $frontend = Comments::$plugin->getFrontend();

    $conversation = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    expect($conversation)->not->toBeNull();

    $viewer = $frontend->buildViewerView($conversation);
    $editor = $frontend->buildEditorView($conversation);
    $fragments = $frontend->conversationReadFragments($conversation, $viewer, $editor);

    $envelope = $frontend->buildEnvelope('ok', $fragments, [
        'resource' => $conversation->conversationUid,
        'rootsNext' => $conversation->rootsNext,
    ]);

    expect($envelope['contractVersion'])->toBe(1);
    expect($envelope['status'])->toBe('ok');
    expect($envelope['resource'])->toBe($conversation->conversationUid);
    expect($envelope['fragments'])->toHaveCount(2);
    expect($envelope['fragments'][0]['type'])->toBe('conversationHeader');
    expect($envelope['fragments'][0])->toHaveKeys(['type', 'mode', 'resourceUid', 'html']);
    expect($envelope['fragments'][1]['type'])->toBe('rootList');
    expect($envelope['fragments'][0]['html'])->toContain('comments__header');
});

it('paginates roots via cursor through the frontend conversation view', function() {
    $entry = CommentsFixtureFactory::entry('API roots pagination owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    foreach (['First root', 'Second root', 'Third root'] as $content) {
        Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $content,
            'userId' => (int)$admin->id,
        ]));
    }

    $frontend = Comments::$plugin->getFrontend();
    $pageOne = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, null, 2);

    expect($pageOne)->not->toBeNull();
    expect($pageOne->roots)->toHaveCount(2);
    expect($pageOne->rootsNext)->not->toBeNull();

    $pageTwo = $frontend->buildConversationView(
        (int)$entry->id,
        (int)$entry->siteId,
        null,
        2,
        $pageOne->rootsNext,
    );

    expect($pageTwo)->not->toBeNull();
    expect($pageTwo->roots)->toHaveCount(1);

    $envelope = $frontend->buildEnvelope('ok', [
        $frontend->renderFragment('rootList', 'append', $pageTwo->conversationUid, '_conversation/list', [
            'contractVersion' => $frontend::CONTRACT_VERSION,
            'instance' => $pageTwo->instanceId,
            'conversation' => $pageTwo,
            'viewer' => $frontend->buildViewerView($pageTwo),
            'editor' => $frontend->buildEditorView($pageTwo),
            'theme' => $pageTwo->theme,
        ]),
    ], [
        'rootsNext' => $pageTwo->rootsNext,
    ]);

    expect($envelope['rootsNext'])->toBeNull();
    expect($envelope['fragments'][0]['type'])->toBe('rootList');
    expect($envelope['fragments'][0]['html'])->toContain('First root');
});

it('builds create-response envelope fragments after Comments::create()', function() {
    $entry = CommentsFixtureFactory::entry('API create owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;
    Comments::$plugin->getSettings()->participantNotifications = [];
    Comments::$plugin->getSettings()->staffNotifications = [];

    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'API created comment',
        'userId' => (int)$admin->id,
    ]));

    expect($result->moderationState)->toBe(ModerationState::Approved);

    $frontend = Comments::$plugin->getFrontend();
    $conversation = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $viewer = $frontend->buildViewerView($conversation);
    $editor = $frontend->buildEditorView($conversation);
    $comment = $result->comment;

    $context = [
        'contractVersion' => $frontend::CONTRACT_VERSION,
        'instance' => $conversation->instanceId,
        'conversation' => $conversation,
        'viewer' => $viewer,
        'editor' => $editor,
        'theme' => $conversation->theme,
        'comment' => $frontend->buildCommentViewPublic($comment, 0),
    ];

    $envelope = $frontend->buildEnvelope('ok', [
        $frontend->renderFragment('comment', 'insert', (string)$comment->uid, '_comment/comment', $context),
        $frontend->renderFragment('editor', 'replace', $conversation->conversationUid, '_editor/form', [
            ...$context,
            'form' => 'create',
        ]),
    ], [
        'operationId' => 'test-operation-id',
        'resource' => (string)$comment->uid,
        'announcement' => 'Comment posted.',
    ]);

    expect($envelope['operationId'])->toBe('test-operation-id');
    expect($envelope['resource'])->toBe((string)$comment->uid);
    expect($envelope['fragments'][0]['type'])->toBe('comment');
    expect($envelope['fragments'][0]['html'])->toContain('API created comment');
    expect($envelope['fragments'][1]['type'])->toBe('editor');
    expect($editor->saveAction)->toContain('comments/frontend-api/create')->not->toContain('api/v1');
    expect($conversation->saveAction)->toContain('comments/comments/save');
});

it('builds reply and edit editor views with parent and prefill metadata', function() {
    $entry = CommentsFixtureFactory::entry('API editor modes');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $root = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Editor parent',
        'userId' => (int)$admin->id,
    ]))->comment;

    $frontend = Comments::$plugin->getFrontend();
    $conversation = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    expect($conversation)->not->toBeNull();

    $replyView = $frontend->buildEditorView($conversation, 'reply', $root);
    expect($replyView->mode)->toBe('reply');
    expect($replyView->parentCommentId)->toBe((int)$root->id);

    $editView = $frontend->buildEditorView($conversation, 'edit', $root);
    expect($editView->mode)->toBe('edit');
    expect($editView->editCommentId)->toBe((int)$root->id);
    expect($editView->initialPlainContent)->toBe('Editor parent');
});

it('batches reply previews for multiple parents', function() {
    $entry = CommentsFixtureFactory::entry('API reply preview batch');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $roots = [];
    foreach (['Root A', 'Root B'] as $content) {
        $roots[] = Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $content,
            'userId' => (int)$admin->id,
        ]))->comment;
    }

    foreach ($roots as $root) {
        Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'parentId' => (int)$root->id,
            'content' => 'Reply to ' . $root->comment,
            'userId' => (int)$admin->id,
        ]));
    }

    $map = Comments::$plugin->getConversations()->getReplyPreviewMap(
        array_map(static fn($root) => (int)$root->id, $roots),
    );

    expect($map)->toHaveCount(2);
    expect($map[(int)$roots[0]->id]['total'])->toBe(1);
    expect($map[(int)$roots[0]->id]['replies'])->toHaveCount(1);
});

it('author-removes a comment through the comments service', function() {
    $entry = CommentsFixtureFactory::entry('API author remove');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Delete me',
        'userId' => (int)$admin->id,
    ]))->comment;

    $removed = Comments::$plugin->getComments()->authorRemove((int)$comment->id);

    expect($removed->authorRemovedAt)->not->toBeNull();
});

it('generates action URLs for the browser runtime through the frontend helper', function() {
    $frontend = Comments::$plugin->getFrontend();

    expect($frontend->getApiUrl(''))->toContain('comments/frontend-api')->not->toContain('api/v1');
    expect($frontend->getApiUrl('create'))->toContain('comments/frontend-api/create')->not->toContain('api/v1');
    expect($frontend->getApiUrl('conversation', ['ownerId' => 5, 'siteId' => 1]))
        ->toContain('ownerId=5')
        ->toContain('siteId=1');
});

it('builds error envelopes through FrontendEnvelope', function() {
    $envelope = FrontendEnvelope::error([[
        'category' => 'validation',
        'code' => 'validation_failed',
        'message' => 'Invalid input.',
        'recoverable' => true,
    ]])->toArray();

    expect($envelope['contractVersion'])->toBe(1);
    expect($envelope['status'])->toBe('error');
    expect($envelope['errors'][0]['code'])->toBe('validation_failed');
});

it('strips viewer actions from shared-cache conversation shells', function() {
    $entry = CommentsFixtureFactory::entry('API shared cache owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Shared cache comment',
        'userId' => (int)$admin->id,
    ]));

    $frontend = Comments::$plugin->getFrontend();
    $shared = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, null, 20, null, true);
    $private = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId, null, 20, null, false);

    expect($shared)->not->toBeNull();
    expect($private)->not->toBeNull();
    expect($shared->roots[0]->actions)->toBe([]);
    expect($private->roots[0]->actions)->not->toBeEmpty();
    expect($shared->roots[0]->permalink)->toBe('#comment-' . $private->roots[0]->id);
});

it('builds viewer-control hydration fragments with private actions and editor', function() {
    $entry = CommentsFixtureFactory::entry('API viewer hydrate owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Hydrate me',
        'userId' => (int)$admin->id,
    ]));

    $frontend = Comments::$plugin->getFrontend();
    $conversation = $frontend->buildConversationView((int)$entry->id, (int)$entry->siteId);
    $viewer = $frontend->buildViewerView($conversation);
    $editor = $frontend->buildEditorView($conversation);
    $fragments = $frontend->viewerControlFragments($conversation, $viewer, $editor);

    expect($fragments)->toHaveCount(4);
    expect($fragments[3]['type'])->toBe('reportDialog');
    expect($fragments[0]['type'])->toBe('conversationHeader');
    expect($fragments[1]['type'])->toBe('rootList');
    expect($fragments[1]['html'])->toContain('data-comments-action="reply"');
    expect($fragments[2]['type'])->toBe('viewerControls');
    expect($fragments[2]['html'])->toContain('data-comments-form="create"');
});

it('stores report reason and details through Reports::toggle()', function() {
    $entry = CommentsFixtureFactory::entry('API report reason owner');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Report target',
        'userId' => (int)$admin->id,
    ]))->comment;

    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($admin);
    Comments::$plugin->getReports()->toggle(
        (int)$comment->id,
        (int)$identity->id,
        'spam',
        'Automated test report',
    );

    $open = Comments::$plugin->getReports()->getOpenForIdentity((int)$comment->id, (int)$identity->id);
    expect($open)->not->toBeNull();
    expect($open->reason)->toBe('spam');
    expect($open->details)->toBe('Automated test report');
});
