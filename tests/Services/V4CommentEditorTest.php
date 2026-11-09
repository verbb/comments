<?php
use craft\base\Element;
use craft\db\Query;
use craft\elements\Entry;
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\elements\conditions\EmailConditionRule;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Commenters;
use yii\web\ForbiddenHttpException;

/**
 * Editing through Craft's element editor, and the Commenters area.
 */

function editorComment(?int $userId = null, ?Entry $entry = null): Comment
{
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $entry ??= CommentsFixtureFactory::entry('Editor owner ' . uniqid());

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Original',
        'userId' => $userId ?? (int)Craft::$app->getUser()->getIdentity()->id,
    ]))->comment;
}

function postEditorForm(Comment $comment, array $values): Comment
{
    $request = Craft::$app->getRequest();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request->setBodyParams($values + [
        'status' => $comment->status,
        'authorId' => [$comment->userId],
        'ownerType' => Entry::class,
        'ownerEntryId' => [$comment->ownerId],
        'contentFormat' => 'plain',
        'fields' => ['comment' => $comment->comment],
    ]);

    $editing = Comment::find()->id($comment->id)->status(null)->one();
    $editing->setAttributesFromRequest([]);
    $editing->setScenario(Element::SCENARIO_LIVE);
    Craft::$app->getElements()->saveElement($editing);

    return $editing;
}

function asUser(?User $user, callable $callback): mixed
{
    $session = Craft::$app->getUser();
    $previous = $session->getIdentity();
    $session->setIdentity($user);

    try {
        return $callback();
    } finally {
        $session->setIdentity($previous);
    }
}

afterEach(function() {
    unset($_SERVER['REQUEST_METHOD']);
    Craft::$app->getRequest()->setBodyParams([]);
});

it('follows the comment’s own fields with an Activity tab', function() {
    $comment = editorComment();
    $form = $comment->getFieldLayout()->createForm($comment);
    $labels = array_column($form->getTabMenu(), 'label');

    expect(array_slice($labels, -1))->toBe(['Activity'])
        ->and($labels)->not->toContain('Replies (0)')
        ->and($labels)->not->toContain('Notes')
        ->and($form->render())->toContain('data-comments-panel="activity"')
        ->toContain('data-comments-note-input')
        ->toContain('name="fields[comment]"');
});

it('always offers the comment body, even if the field layout leaves it out', function() {
    $comment = editorComment();
    $layout = new craft\models\FieldLayout(['type' => Comment::class]);
    $tab = new craft\models\FieldLayoutTab(['name' => 'Comment']);
    $tab->setLayout($layout);
    $tab->setElements([]);
    $layout->setTabs([$tab]);

    expect($layout->createForm($comment)->render())->toContain('name="fields[comment]"');
});

it('keeps moderation tabs from people who cannot moderate', function() {
    $comment = editorComment();
    $viewer = CommentsFixtureFactory::user('editorViewer');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$viewer->id, ['comments-view']);
    $viewer = User::find()->id($viewer->id)->status(null)->one();

    $labels = asUser($viewer, fn() => array_column($comment->getFieldLayout()->createForm($comment)->getTabMenu(), 'label'));

    expect($labels)->not->toContain('Activity');
});

it('renders a standard sidebar, with the element and author to change and a way to the comment on the site', function() {
    $comment = editorComment();
    $html = $comment->getSidebarHtml(false);
    $metadata = $comment->getMetadata();
    $meta = implode(' ', array_map(static fn($value) => is_callable($value) ? (string)$value() : (string)$value, $metadata));

    expect($html)->toContain('data-comments-status-menu')
        ->toContain('name="authorId')
        ->toContain('name="ownerEntryId')
        ->toContain('name="ownerType"')
        ->and($meta)->toContain('comments/commenters/' . $comment->identityId)
        ->and(array_keys($metadata))->toContain('Posted')
        // Craft drops rows whose value is false: the pickers replace these two.
        ->and($metadata['Entry'] ?? false)->toBeFalse()
        ->and($metadata['Author'] ?? false)->toBeFalse()
        ->and($comment->getPreviewTargets()[0]['url'])->toContain('comment=' . $comment->id)
        ->and($comment->getAdditionalButtons())->toContain('data-comments-preview');
});

it('keeps the element and author as links for replies, guests and people who cannot edit', function() {
    $root = editorComment();
    $reply = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $root->ownerId,
        'siteId' => $root->siteId,
        'parentId' => $root->id,
        'content' => 'A reply',
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
    ]))->comment;

    // Moving a reply would move its whole thread, so only the first comment offers it.
    expect($reply->getSidebarHtml(false))->not->toContain('name="ownerEntryId')
        ->toContain('name="authorId')
        ->and($reply->getMetadata()['Entry'] ?? false)->not->toBeFalse();

    $viewer = CommentsFixtureFactory::user('sidebarModerator');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$viewer->id, ['accessCp', 'comments-view', 'comments-moderate']);
    $viewer = User::find()->id($viewer->id)->status(null)->one();

    [$html, $metadata] = asUser($viewer, fn() => [$root->getSidebarHtml(false), $root->getMetadata()]);

    expect($html)->not->toContain('name="authorId')
        ->not->toContain('name="ownerEntryId')
        ->and($metadata['Entry'] ?? false)->not->toBeFalse()
        ->and($metadata['Author'] ?? false)->not->toBeFalse();
});

it('leaves a comment on an element type without a picker where it is', function() {
    $comment = editorComment();

    $saved = postEditorForm($comment, ['ownerType' => 'craft\\elements\\Tag', 'fields' => ['comment' => 'Edited in place']]);

    expect($saved->getErrors())->toBe([])
        ->and((new Query())->select(['ownerId'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->toEqual($comment->ownerId);
});

it('asks for an element when the picker is cleared', function() {
    $comment = editorComment();

    $saved = postEditorForm($comment, ['ownerEntryId' => '']);

    expect($saved->getFirstError('comment'))->toBe('Select an element.');
});

it('shows initials on a colour from the name when the author has no photo, outlined for guests', function() {
    $author = CommentsFixtureFactory::user('initialsAuthor');
    $comment = editorComment((int)$author->id);
    $name = (string)$comment->getAuthorName();

    expect($comment->getThumbHtml(32))->toContain('comments-avatar')
        ->toContain('data-tone="' . verbb\comments\services\Frontend::avatarTone($name) . '"')
        ->toContain('>' . Commenters::initials($name) . '<')
        ->not->toContain('comments-avatar--guest')
        ->and($comment->getChipLabelHtml())->toContain(htmlspecialchars($name, ENT_QUOTES))
        ->not->toContain('comments-guest-pill')
        ->and($comment->showStatusIndicator())->toBeFalse();
});

it('applies only the fields the editor offers', function() {
    $comment = editorComment();
    $before = $comment->uid;

    $saved = postEditorForm($comment, [
        'fields' => ['comment' => 'Edited'],
        'uid' => 'hacked',
        'commentDate' => '2001-01-01',
        'ipAddress' => '198.51.100.1',
    ]);

    expect($saved->comment)->toBe('Edited')
        ->and($saved->uid)->toBe($before)
        ->and((new Query())->select(['ipAddress'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->not->toBe('198.51.100.1')
        ->and(date('Y', $saved->commentDate->getTimestamp()))->not->toBe('2001');
});

it('holds moderation changes back until the comment passes validation', function() {
    $comment = editorComment();

    $invalid = postEditorForm($comment, [
        'status' => Comment::STATUS_SPAM,
        'guestAuthor' => '1',
        'name' => '',
        'email' => '',
    ]);

    expect($invalid->hasErrors())->toBeTrue()
        ->and((new Query())->select(['moderationState'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->toBe(ModerationState::Approved->value);

    postEditorForm($comment, ['status' => Comment::STATUS_SPAM]);

    expect((new Query())->select(['moderationState'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->toBe(ModerationState::Spam->value)
        ->and((new Query())->from('{{%comments_moderationactions}}')->where(['commentId' => $comment->id, 'action' => 'markSpam'])->exists())->toBeTrue();
});

it('refuses a status change from someone who cannot moderate', function() {
    $comment = editorComment();
    $editor = CommentsFixtureFactory::user('editorNoModerate');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$editor->id, ['comments-view', 'comments-edit']);
    $editor = User::find()->id($editor->id)->status(null)->one();

    expect($comment->canSave($editor))->toBeTrue();

    asUser($editor, fn() => expect(fn() => postEditorForm($comment, ['status' => Comment::STATUS_SPAM]))
        ->toThrow(ForbiddenHttpException::class));
});

it('only lets comment editors use the native save', function() {
    $comment = editorComment();
    $viewer = CommentsFixtureFactory::user('editorNoEdit');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$viewer->id, ['comments-view', 'comments-moderate']);
    $viewer = User::find()->id($viewer->id)->status(null)->one();

    expect($comment->canView($viewer))->toBeTrue()
        ->and($comment->canSave($viewer))->toBeFalse();
});

it('moves a comment to a new element from the editor, thread and all', function() {
    $comment = editorComment();
    $target = CommentsFixtureFactory::entry('Editor move target');

    postEditorForm($comment, ['ownerEntryId' => [$target->id]]);

    $row = (new Query())->from('{{%comments_comments}}')->where(['id' => $comment->id])->one();

    expect((int)$row['ownerId'])->toBe((int)$target->id)
        ->and((int)$row['conversationId'])->not->toBe((int)$comment->conversationId);
});

it('lets staff edit a guest comment even when guests can no longer post', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->allowGuest = true;
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->staffNotifications = [];
    $settings->participantNotifications = [];
    $entry = CommentsFixtureFactory::entry('Editor guest owner');
    $userService = Craft::$app->getUser();
    $admin = $userService->getIdentity();
    $userService->setIdentity(null);
    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'From a guest',
        'name' => 'A Guest',
        'email' => 'guest-editor@example.test',
    ]))->comment;
    $userService->setIdentity($admin);
    $settings->allowGuest = false;

    $saved = postEditorForm($comment, [
        'guestAuthor' => '1',
        'name' => 'A Guest',
        'email' => 'guest-editor@example.test',
        'authorId' => [],
        'fields' => ['comment' => 'Corrected by staff'],
    ]);

    expect($saved->getErrors())->toBe([])
        ->and((new Query())->select(['comment'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->toBe('Corrected by staff');
});

it('still edits a comment whose element has been deleted', function() {
    $entry = CommentsFixtureFactory::entry('Editor orphan owner');
    $comment = editorComment(null, $entry);
    Craft::$app->getElements()->deleteElement($entry, true);

    $saved = postEditorForm(Comment::find()->id($comment->id)->status(null)->one(), [
        'ownerEntryId' => [],
        'fields' => ['comment' => 'Edited orphan'],
    ]);

    expect($saved->getErrors())->toBe([])
        ->and((new Query())->select(['comment'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->toBe('Edited orphan');
});

it('shows a bad selection as a form error instead of failing the request', function() {
    $comment = editorComment();

    $saved = postEditorForm($comment, ['ownerEntryId' => [999999]]);

    expect($saved->getFirstError('comment'))->toBe('The selected element could not be found.')
        ->and((new Query())->select(['ownerId'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->scalar())->toEqual($comment->ownerId);
});

it('gives added tabs stable identifiers that survive their counts changing', function() {
    $comment = editorComment();
    $before = array_keys($comment->getFieldLayout()->createForm($comment)->getTabMenu());

    Comments::$plugin->getModeration()->addNote((int)$comment->id, 'A note', Craft::$app->getUser()->getIdentity());

    $form = $comment->getFieldLayout()->createForm($comment);

    expect(array_keys($form->getTabMenu()))->toBe($before)
        ->and($before)->toContain('comment-activity')
        ->and($form->render())->toContain('data-layout-tab="comment-activity"');
});

it('does not act on the form when Craft is only refreshing it', function() {
    $comment = editorComment();
    Craft::$app->requestedRoute = 'elements/update-field-layout';

    try {
        $saved = postEditorForm($comment, ['fields' => ['comment' => 'Unsaved edit'], 'status' => Comment::STATUS_SPAM]);

        expect($saved->editorChanges)->toBeNull();
    } finally {
        Craft::$app->requestedRoute = null;
    }
});

it('renders activity as a readable timeline, newest first, with reports and notes', function() {
    $comment = editorComment();
    $admin = Craft::$app->getUser()->getIdentity();
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam', 'Selling shoes');
    Comments::$plugin->getModeration()->setState($comment, ModerationState::Spam, $admin);
    Comments::$plugin->getModeration()->addNote((int)$comment->id, 'Leaving it for Josh.', $admin);

    $html = Comments::$plugin->getCommentEditor()->panelHtml($comment);

    expect($html)->toContain('marked as spam')
        ->toContain('posted this')
        ->toContain('href="' . $comment->getOwner()->getCpEditUrl() . '"')
        ->toContain('reported this')
        ->toContain('comments-timeline__chip--red">Spam<')
        ->toContain('comments-timeline__item--violet')
        ->toContain('comments/commenters/' . $reporter->id)
        ->toContain('Selling shoes')
        ->toContain($admin->getName())
        ->toContain('href="' . $admin->getCpEditUrl() . '"')
        ->toContain('left a note')
        ->toContain('comments-timeline__item--note')
        ->toContain('Leaving it for Josh.')
        ->not->toContain('markSpam')
        // Newest first: the note comes before the comment was posted.
        ->and(strpos($html, 'left a note'))->toBeLessThan(strpos($html, 'posted this'));
});

it('lists commenters with their comment counts and review setting, by source', function() {
    $author = CommentsFixtureFactory::user('commenterListed');
    $comment = editorComment((int)$author->id);
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getModeration()->setReviewSetting((int)$comment->identityId, verbb\comments\enums\ReviewSetting::Blocked, $admin, 'Spamming');

    $commenters = Comments::$plugin->getCommenters();
    $table = $commenters->getTableData(Commenters::SOURCE_BLOCKED, null, 1);

    expect($table['total'])->toBe(1)
        ->and($table['rows'][0]['id'])->toBe((int)$comment->identityId)
        ->and($table['rows'][0]['comments'])->toBe(1)
        ->and($table['rows'][0]['reviewSetting'])->toBe('blocked')
        ->and($table['rows'][0]['reviewSettingLabel'])->toBe('Blocked')
        ->and($table['rows'][0]['lastOn'])->toBe($comment->getOwner()->title)
        ->and($table['rows'][0]['url'])->toContain('comments/commenters/' . $comment->identityId)
        ->and($table['rows'][0]['guest'])->toBeFalse()
        ->and($table['rows'][0]['photo'])->toBeNull()
        ->and($table['rows'][0]['tone'])->toBe(verbb\comments\services\Frontend::avatarTone($table['rows'][0]['name']))
        ->and($commenters->getSources()['blocked']['count'])->toBe(1)
        ->and($commenters->getTableData(Commenters::SOURCE_TRUSTED, null, 1)['total'])->toBe(0);

    $guests = $commenters->getTableData(Commenters::SOURCE_GUESTS, null, 1);
    expect(array_column($guests['rows'], 'id'))->not->toContain((int)$comment->identityId);

    $history = $commenters->getHistory((int)$comment->identityId);
    expect($history[0]['text'])->toBe('set comments to blocked')
        ->and($history[0]['note'])->toBe('Spamming');
});

it('only searches email addresses for people allowed to see them', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->allowGuest = true;
    $entry = CommentsFixtureFactory::entry('Commenter search owner');
    $identity = Comments::$plugin->getIdentities()->createHistoricalGuest('Findable', 'hidden-address@example.test');
    $comment = editorComment(null, $entry);
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['identityId' => $identity->id], ['id' => $comment->id])->execute();

    $service = Comments::$plugin->getCommenters();
    $moderator = CommentsFixtureFactory::user('commenterModerator');
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$moderator->id, ['comments-view', 'comments-moderate']);
    $moderator = User::find()->id($moderator->id)->status(null)->one();

    $adminRow = $service->getTableData(Commenters::SOURCE_ALL, 'hidden-address', 1)['rows'][0] ?? [];
    $moderatorRows = asUser($moderator, fn() => $service->getTableData(Commenters::SOURCE_ALL, null, 1)['rows']);
    $metadataEmail = static fn() => array_key_exists('Email', array_filter($comment->getMetadata()));

    expect($adminRow['email'] ?? null)->toBe('hidden-address@example.test')
        ->and(array_filter(array_column($moderatorRows, 'email')))->toBe([])
        ->and($metadataEmail())->toBeTrue()
        ->and(asUser($moderator, $metadataEmail))->toBeFalse();

    expect($service->getTableData(Commenters::SOURCE_ALL, 'hidden-address', 1)['total'])->toBe(1)
        ->and(asUser($moderator, fn() => $service->getTableData(Commenters::SOURCE_ALL, 'hidden-address', 1)['total']))->toBe(0)
        ->and(asUser($moderator, fn() => $service->getDetail((int)$identity->id)['email']))->toBeNull()
        ->and($service->getDetail((int)$identity->id)['email'])->toBe('hidden-address@example.test');
});

it('summarises a commenter and their restriction history', function() {
    $author = CommentsFixtureFactory::user('commenterDetail');
    $comment = editorComment((int)$author->id);
    $moderation = Comments::$plugin->getModeration();
    $admin = Craft::$app->getUser()->getIdentity();
    $restriction = $moderation->moderateIdentity((int)$comment->identityId, null, $admin, 'Watching');
    $moderation->revokeRestriction((int)$restriction->id, $admin);
    $moderation->blockIdentity((int)$comment->identityId, null, $admin, 'Enough');

    $detail = Comments::$plugin->getCommenters()->getDetail((int)$comment->identityId);

    expect($detail['user']->id)->toBe($author->id)
        ->and($detail['counts']['approved'])->toBe(1)
        ->and($detail['restrictions'])->toHaveCount(2)
        ->and($detail['restrictions'][0]['label'])->toBe('Blocked')
        ->and($detail['restrictions'][0]['isActive'])->toBeTrue()
        ->and($detail['restrictions'][1]['isActive'])->toBeFalse()
        ->and($detail['restrictions'][1]['reason'])->toBe('Watching')
        ->and(Comments::$plugin->getCommenters()->getDetail(999999))->toBeNull();
});

it('closes an element to new comments from the Comments field, keeping existing ones', function() {
    $entry = CommentsFixtureFactory::entry('Closed field owner');
    $comment = editorComment(null, $entry);
    $conversations = Comments::$plugin->getConversations();

    $conversations->setClosed((int)$entry->id, (int)$entry->siteId, true);
    $config = $conversations->getConfigForOwner((int)$entry->id, (int)$entry->siteId);

    expect($config->enabled)->toBeTrue()
        ->and($config->closed)->toBeTrue()
        ->and($config->isCommentingEnabled())->toBeFalse()
        ->and(verbb\comments\fields\CommentsField::enabledOverride('closed'))->toBeNull()
        ->and((new verbb\comments\fields\CommentsField())->normalizeValue('closed'))->toBe('closed')
        ->and($conversations->getRootComments((int)$comment->conversationId)->comments)->toHaveCount(1);

    $conversations->setClosed((int)$entry->id, (int)$entry->siteId, false);

    expect($conversations->getConfigForOwner((int)$entry->id, (int)$entry->siteId)->isCommentingEnabled())->toBeTrue();
});

// Sweep 3: the sidebar's pickers, decisions and status, the Activity tab, and avatars.

/** Post the editor form with exactly these values, as the sidebar sends them, with no defaults added. */
function s3PostExactEditorForm(Comment $comment, array $values): Comment
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    Craft::$app->getRequest()->setBodyParams($values);

    $editing = Comment::find()->id($comment->id)->status(null)->one();
    $editing->setAttributesFromRequest([]);
    $editing->setScenario(Element::SCENARIO_LIVE);
    Craft::$app->getElements()->saveElement($editing);

    return $editing;
}

function s3EditorUser(string $prefix, array $permissions): User
{
    $user = CommentsFixtureFactory::user($prefix);
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, $permissions);

    return User::find()->id($user->id)->status(null)->one();
}

/** Craft builds Twig's globals, `currentUser` among them, once per environment; a real request has one person. */
function s3AsCpUser(User $user, callable $callback): mixed
{
    $refresh = static function(): void {
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode(craft\web\View::TEMPLATE_MODE_CP);
        $view->setTwig($view->createTwig());
        $view->setTemplateMode($mode);
    };

    $refresh();

    try {
        return asUser($user, function() use ($callback, $refresh) {
            $refresh();

            return $callback();
        });
    } finally {
        $refresh();
    }
}

function s3CommentRow(Comment $comment): array
{
    return (new Query())->from('{{%comments_comments}}')->where(['id' => $comment->id])->one();
}

function s3GuestComment(Entry $entry, string $name, string $content = 'From a guest', ?string $email = null): Comment
{
    $settings = Comments::$plugin->getSettings();
    $settings->allowGuest = true;
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->staffNotifications = [];
    $settings->participantNotifications = [];
    $session = Craft::$app->getUser();
    $admin = $session->getIdentity();
    $session->setIdentity(null);
    Comments::$plugin->getConversations()->clearCaches();

    try {
        return Comments::$plugin->getComments()->create(new CommentSubmission([
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
            'content' => $content,
            'name' => $name,
            'email' => $email ?? 'guest-' . uniqid() . '@example.test',
        ]))->comment;
    } finally {
        $session->setIdentity($admin);
    }
}

it('changes a comment’s author from the sidebar picker, identity and all', function() {
    $comment = editorComment();
    $newAuthor = CommentsFixtureFactory::user('pickedAuthor');
    $identity = Comments::$plugin->getIdentities()->getOrCreateForUser($newAuthor);

    $saved = postEditorForm($comment, ['authorId' => [$newAuthor->id]]);
    $row = s3CommentRow($comment);

    expect($saved->getErrors())->toBe([])
        ->and((int)$row['userId'])->toBe((int)$newAuthor->id)
        ->and((int)$row['identityId'])->toBe((int)$identity->id)
        ->and((int)$row['identityId'])->not->toBe((int)$comment->identityId);
});

it('refuses an author who does not exist or a cleared picker, and keeps the old author', function() {
    $comment = editorComment();

    foreach ([[999999], []] as $picked) {
        $saved = postEditorForm($comment, ['authorId' => $picked, 'fields' => ['comment' => 'Not saved']]);

        expect($saved->getFirstError('userId'))->toBe('Select an author.')
            ->and((int)s3CommentRow($comment)['userId'])->toBe((int)$comment->userId)
            ->and(s3CommentRow($comment)['comment'])->toBe('Original');
    }
});

it('keeps a thread together when a hand-made request moves one of its replies', function() {
    $root = editorComment();
    $reply = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $root->ownerId,
        'siteId' => $root->siteId,
        'parentId' => $root->id,
        'content' => 'A reply',
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
    ]))->comment;
    $target = CommentsFixtureFactory::entry('Reply move target');

    // The sidebar has no picker for a reply, but the save path still reads one if it's posted.
    postEditorForm($reply, ['ownerEntryId' => [$target->id]]);

    $rootRow = s3CommentRow($root);
    $replyRow = s3CommentRow($reply);

    expect((int)$replyRow['ownerId'])->toBe((int)$rootRow['ownerId'])
        ->and((int)$replyRow['conversationId'])->toBe((int)$rootRow['conversationId'])
        ->and((int)$replyRow['parentId'])->toBe((int)$root->id);
});

it('saves a comment on an element type without a picker as the sidebar posts it, and links its element', function() {
    $group = new craft\models\TagGroup(['name' => 'Comment tags', 'handle' => 'commentTags' . uniqid()]);
    expect(Craft::$app->getTags()->saveTagGroup($group))->toBeTrue();
    $tag = new craft\elements\Tag(['groupId' => $group->id, 'title' => 'Tagged owner']);
    expect(Craft::$app->getElements()->saveElement($tag))->toBeTrue();

    $comment = editorComment();
    Craft::$app->getDb()->createCommand()->update('{{%comments_comments}}', ['ownerId' => $tag->id], ['id' => $comment->id])->execute();
    $comment = Comment::find()->id($comment->id)->status(null)->one();

    $html = $comment->getSidebarHtml(false);
    $metadata = $comment->getMetadata();

    expect($comment->getOwner())->toBeInstanceOf(craft\elements\Tag::class)
        ->and($html)->not->toContain('name="ownerType"')
        ->not->toMatch('/name="owner[A-Z][a-z]+Id/')
        ->toContain('name="authorId')
        ->and((string)($metadata['Tag'] ?? ''))->toContain('Tagged owner');

    // The sidebar sends no element for this type, so the comment must stay where it is.
    $saved = s3PostExactEditorForm($comment, [
        'status' => $comment->status,
        'authorId' => [$comment->userId],
        'contentFormat' => 'plain',
        'fields' => ['comment' => 'Edited on a tag'],
    ]);

    expect($saved->getErrors())->toBe([])
        ->and((int)s3CommentRow($comment)['ownerId'])->toBe((int)$tag->id)
        ->and(s3CommentRow($comment)['comment'])->toBe('Edited on a tag');

    Craft::$app->getTags()->deleteTagGroupById((int)$group->id);
});

it('offers the decisions for the comment’s state in the sidebar, and Delete permanently only for spam and deleters', function() {
    $author = CommentsFixtureFactory::user('sidebarDecisionAuthor');
    $comment = editorComment((int)$author->id);
    $moderator = s3EditorUser('sidebarDecider', ['accessCp', 'comments-view', 'comments-moderate', 'comments-trash', 'comments-delete']);
    $noDelete = s3EditorUser('sidebarNoDelete', ['accessCp', 'comments-view', 'comments-moderate']);
    $viewer = s3EditorUser('sidebarViewer', ['accessCp', 'comments-view']);
    $sidebar = static fn(User $user): string => s3AsCpUser($user, fn() => Comment::find()->id($comment->id)->status(null)->one()->getSidebarHtml(false));

    // Approved with nothing open: Approved in Craft's teal, and no Actions.
    expect($sidebar($moderator))->toContain('<span class="status teal"></span>')
        ->not->toContain('comments-sidebar-actions');

    Comments::$plugin->getModeration()->setState($comment, ModerationState::Pending);
    $pending = $sidebar($moderator);

    expect($pending)->toContain('comments-sidebar-actions')
        ->toMatch('/class="btn submit" data-comments-decision="approve"/')
        ->toMatch('/class="btn" data-comments-decision="spam"/')
        ->toContain('data-comments-remove-toggle')
        ->toContain('name="removalReason"')
        ->toContain('value="harassment"')
        ->not->toContain('data-comments-delete-permanently')
        ->and($sidebar($noDelete))->toContain('data-comments-decision="approve"')
        ->not->toContain('data-comments-remove-toggle')
        ->not->toContain('name="removalReason"')
        ->and($sidebar($viewer))->not->toContain('comments-sidebar-actions')
        ->not->toContain('data-comments-decision');

    Comments::$plugin->getModeration()->markSpam($comment);

    expect($sidebar($moderator))->toMatch('/class="btn submit" data-comments-decision="not-spam"/')
        ->toContain('data-comments-delete-permanently="' . $comment->id . '"')
        ->and($sidebar($noDelete))->toContain('data-comments-decision="not-spam"')
        ->not->toContain('data-comments-delete-permanently');
});

it('keeps decisions out of the sidebar on a moderator’s own comment', function() {
    $comment = editorComment();
    Comments::$plugin->getModeration()->setState($comment, ModerationState::Pending);

    expect(Comment::find()->id($comment->id)->status(null)->one()->getSidebarHtml(false))->not->toContain('comments-sidebar-actions');
});

it('orders the Activity tab by when things happened, newest first', function() {
    $author = CommentsFixtureFactory::user('activityOrderAuthor');
    $comment = editorComment((int)$author->id);
    $admin = Craft::$app->getUser()->getIdentity();
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Order Reporter', null);
    $report = Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam', 'Newest event');
    Comments::$plugin->getModeration()->markSpam($comment, $admin);
    $note = Comments::$plugin->getModeration()->addNote((int)$comment->id, 'Oldest note', $admin);
    $db = Craft::$app->getDb();
    $at = static fn(string $ago): string => craft\helpers\Db::prepareDateForDb(new DateTime($ago));

    // Recorded in one order, dated in another: posted, then the note, the spam mark and the report.
    $db->createCommand()->update('{{%comments_comments}}', ['commentDate' => $at('-4 hours')], ['id' => $comment->id])->execute();
    $db->createCommand()->update('{{%comments_moderatornotes}}', ['dateCreated' => $at('-3 hours')], ['id' => $note->id])->execute();
    $db->createCommand()->update('{{%comments_moderationactions}}', ['dateCreated' => $at('-2 hours')], ['commentId' => $comment->id])->execute();
    $db->createCommand()->update('{{%comments_reports}}', ['dateCreated' => $at('-1 hour')], ['id' => $report->id])->execute();

    $fresh = Comment::find()->id($comment->id)->status(null)->one();
    $html = Comments::$plugin->getCommentEditor()->panelHtml($fresh);
    $positions = array_map(static fn(string $text): int|false => strpos($html, $text), ['reported this', 'marked as spam', 'Oldest note', 'posted this']);

    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(array_values(array_unique($positions)))
        ->and($positions)->toBe(array_values(collect($positions)->sort()->all()));
});

it('escapes what commenters, reporters and moderators wrote in the Activity tab', function() {
    $entry = CommentsFixtureFactory::entry('<i>Hostile title</i>');
    $comment = editorComment(null, $entry);
    $admin = Craft::$app->getUser()->getIdentity();
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('<script>alert("name")</script>', null);
    Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam', '<img src=x onerror=alert("details")>');
    Comments::$plugin->getModeration()->addNote((int)$comment->id, '<b onclick="alert(1)">note</b>', $admin);

    // Set the title directly, so nothing on the way in tidies it.
    Craft::$app->getDb()->createCommand()->update(craft\db\Table::ELEMENTS_SITES, ['title' => '<i>Hostile title</i>'], ['elementId' => $entry->id])->execute();
    $comment->setOwner(Entry::find()->id($entry->id)->status(null)->one());

    $html = Comments::$plugin->getCommentEditor()->panelHtml($comment);

    expect($html)->not->toContain('<script>alert')
        ->not->toContain('<img src=x')
        ->not->toContain('<b onclick')
        ->not->toContain('<i>Hostile title</i>')
        ->toContain('&lt;script&gt;')
        ->toContain('&lt;img src=x')
        ->toContain('&lt;b onclick')
        ->toContain('&lt;i&gt;Hostile title&lt;/i&gt;');
});

it('still shows activity once the element and the moderator are gone', function() {
    $author = CommentsFixtureFactory::user('activityOrphanAuthor');
    $entry = CommentsFixtureFactory::entry('Activity orphan owner');
    $comment = editorComment((int)$author->id, $entry);
    $moderator = s3EditorUser('activityGoneModerator', ['accessCp', 'comments-view', 'comments-moderate']);
    Comments::$plugin->getModeration()->markSpam($comment, $moderator);
    Comments::$plugin->getModeration()->addNote((int)$comment->id, 'Note from someone who left', $moderator);
    Craft::$app->getElements()->deleteElement($entry, true);
    Craft::$app->getElements()->deleteElement($moderator, true);

    $fresh = Comment::find()->id($comment->id)->status(null)->one();
    $html = Comments::$plugin->getCommentEditor()->panelHtml($fresh);

    expect($html)->toContain('posted this')
        ->not->toContain('on Activity orphan owner')
        ->toContain('marked as spam')
        ->toContain('Unknown user')
        ->toContain('Note from someone who left')
        ->not->toContain($moderator->getCpEditUrl());
});

it('outlines a guest’s initials and marks them as a guest in the index', function() {
    $comment = s3GuestComment(CommentsFixtureFactory::entry('Guest avatar owner'), 'Robin Guest');

    expect($comment->getThumbHtml(32))->toContain('comments-avatar--guest')
        ->toContain('data-tone="' . verbb\comments\services\Frontend::avatarTone('Robin Guest') . '"')
        ->toContain('>RG<')
        ->and($comment->getChipLabelHtml())->toContain('comments-guest-pill');
});

it('gives commenter rows and comment thumbnails the person’s photo, and marks guest rows', function() {
    $handle = 'commentsPhotos' . bin2hex(random_bytes(4));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local(['name' => $handle, 'handle' => $handle, 'path' => $path, 'hasUrls' => true, 'url' => 'https://assets.example.test/' . $handle . '/']);
    expect(Craft::$app->getFs()->saveFilesystem($filesystem))->toBeTrue();
    $volume = new craft\models\Volume(['name' => $handle, 'handle' => $handle, 'fsHandle' => $handle]);
    expect(Craft::$app->getVolumes()->saveVolume($volume))->toBeTrue();

    try {
        $file = tempnam(sys_get_temp_dir(), 'comments-photo-');
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $photo = new craft\elements\Asset([
            'tempFilePath' => $file,
            'filename' => 'face.png',
            'newFolderId' => Craft::$app->getAssets()->getRootFolderByVolumeId((int)$volume->id)->id,
            'volumeId' => $volume->id,
            'avoidFilenameConflicts' => true,
        ]);
        $photo->setScenario(craft\elements\Asset::SCENARIO_CREATE);
        expect(Craft::$app->getElements()->saveElement($photo))->toBeTrue();

        $author = CommentsFixtureFactory::user('photoAuthor');
        Craft::$app->getDb()->createCommand()->update(craft\db\Table::USERS, ['photoId' => $photo->id], ['id' => $author->id])->execute();
        $comment = editorComment((int)$author->id);
        $guest = s3GuestComment($comment->getOwner(), 'Row Guest');

        $rows = array_column(Comments::$plugin->getCommenters()->getTableData(Commenters::SOURCE_ALL, null, 1)['rows'], null, 'id');
        $fresh = Comment::find()->id($comment->id)->status(null)->one();

        expect($rows[(int)$comment->identityId]['photo'])->toBeString()->toContain('transform')
            ->and($rows[(int)$comment->identityId]['guest'])->toBeFalse()
            ->and($rows[(int)$guest->identityId]['guest'])->toBeTrue()
            ->and($rows[(int)$guest->identityId]['type'])->toBe('Guest')
            ->and($rows[(int)$guest->identityId]['photo'])->toBeNull()
            ->and($rows[(int)$guest->identityId]['initials'])->toBe('RG')
            ->and($rows[(int)$guest->identityId]['tone'])->toBe(verbb\comments\services\Frontend::avatarTone('Row Guest'))
            ->and($fresh->getThumbHtml(32))->not->toContain('comments-avatar')
            ->toContain('data-srcset');
    } finally {
        Craft::$app->getVolumes()->deleteVolume($volume);
        Craft::$app->getFs()->removeFilesystem($filesystem);
        craft\helpers\FileHelper::removeDirectory($path);
    }
});

it('keeps addresses out of the Comments index search for people who cannot see them', function() {
    $comment = s3GuestComment(CommentsFixtureFactory::entry('Index address search owner'), 'Probe Guest', 'From a guest', 'zqprobe-address@example.test');
    $moderator = s3EditorUser('indexSearchModerator', ['accessCp', 'comments-view', 'comments-moderate']);
    $search = static fn(): array => Comment::find()->status(null)->search('zqprobe')->ids();

    // An admin may search by address, which also shows the address is in the search index.
    expect($search())->toContain($comment->id)
        // D1: addresses are admin-only, including as a search term, as the Commenters screen does.
        ->and(asUser($moderator, $search))->not->toContain($comment->id)
        // Ranked by score, Craft takes a different path through its search service.
        ->and(asUser($moderator, static fn(): array => Comment::find()->status(null)->search('zqprobe')->orderBy('score')->ids()))->not->toContain($comment->id)
        // Anything else they can search for still finds it.
        ->and(asUser($moderator, static fn(): array => Comment::find()->status(null)->search('Probe Guest')->ids()))->toContain($comment->id);
});

it('keeps filtering, sorting and listing by address to admins', function() {
    $comment = s3GuestComment(CommentsFixtureFactory::entry('Index address filter owner'), 'Filter Guest', 'From a guest', 'zqfilter-address@example.test');
    $moderator = s3EditorUser('indexFilterModerator', ['accessCp', 'comments-view', 'comments-moderate']);
    $byEmail = static fn(): array => Comment::find()->status(null)->email('zqfilter-address@example.test')->ids();
    $emailRule = static fn(): bool => in_array(
        EmailConditionRule::class,
        array_map(static fn($rule): string => $rule::class, Comment::createCondition()->getSelectableConditionRules()),
        true,
    );
    $column = static fn(): string => (string)Comment::find()->id($comment->id)->status(null)->one()->getAttributeHtml('email');

    expect($byEmail())->toBe([$comment->id])
        ->and($emailRule())->toBeTrue()
        ->and(array_keys(Comment::tableAttributes()))->toContain('email')
        ->and(array_keys(Comment::sortOptions()))->toContain('email')
        ->and($column())->toContain('zqfilter-address@example.test')
        // A moderator gets no matches rather than a yes or no about an address.
        ->and(asUser($moderator, $byEmail))->toBe([])
        ->and(asUser($moderator, $emailRule))->toBeFalse()
        ->and(asUser($moderator, static fn(): array => array_keys(Comment::tableAttributes())))->not->toContain('email')
        ->and(asUser($moderator, static fn(): array => array_keys(Comment::sortOptions())))->not->toContain('email')
        ->and(asUser($moderator, $column))->not->toContain('zqfilter');

    // A rule posted in an index filter is dropped for them, so it can't narrow the results.
    $condition = asUser($moderator, static function(): array {
        $condition = Comment::createCondition();
        $condition->setConditionRules([['class' => EmailConditionRule::class, 'operator' => '=', 'value' => 'zqfilter-address@example.test']]);

        return $condition->getConditionRules();
    });

    expect($condition)->toBe([]);
});
