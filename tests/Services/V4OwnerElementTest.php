<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\fields\CommentsField;
use verbb\comments\models\CommentSubmission;
use verbb\comments\services\Commenters;

use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayoutTab;
use craft\web\View;

function commentsFieldFixture(): CommentsField
{
    $fields = Craft::$app->getFields();
    $field = $fields->getFieldByHandle('commentsOverride');

    if (!$field instanceof CommentsField) {
        $field = new CommentsField([
            'name' => 'Comments',
            'handle' => 'commentsOverride',
        ]);

        expect($fields->saveField($field))->toBeTrue();
    }

    $entryType = CommentsFixtureFactory::section()->getEntryTypes()[0];
    $layout = $entryType->getFieldLayout();
    $tabs = $layout->getTabs();
    $tab = $tabs[0] ?? new FieldLayoutTab([
        'layout' => $layout,
        'name' => 'Content',
        'sortOrder' => 1,
    ]);
    $elements = $tab->getElements();

    foreach ($elements as $element) {
        if ($element instanceof CustomField && $element->getField()->id === $field->id) {
            return $field;
        }
    }

    $tab->setElements([...$elements, new CustomField($field)]);
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);

    expect($fields->saveLayout($layout, false))->toBeTrue();

    return $field;
}

/** The field's input as an entry's edit page renders it, which is a control panel template. */
function renderCommentsFieldInput(CommentsField $field, mixed $value, Entry $entry): string
{
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode(View::TEMPLATE_MODE_CP);

    try {
        return $field->getInputHtml($value, $entry);
    } finally {
        $view->setTemplateMode($mode);
    }
}

it('normalizes legacy comment options into the three-state field', function() {
    $field = new CommentsField([
        'name' => 'Comments',
        'handle' => 'comments',
    ]);

    expect($field->normalizeValue(null))->toBe(CommentsField::STATE_INHERIT)
        ->and($field->normalizeValue(['commentEnabled' => true]))->toBe(CommentsField::STATE_INHERIT)
        ->and($field->normalizeValue(['commentEnabled' => false]))->toBe(CommentsField::STATE_DISABLED)
        ->and($field->normalizeValue(CommentsField::STATE_ENABLED))->toBe(CommentsField::STATE_ENABLED)
        ->and(CommentsField::enabledOverride(CommentsField::STATE_INHERIT))->toBeNull()
        ->and(CommentsField::enabledOverride(CommentsField::STATE_ENABLED))->toBeTrue()
        ->and(CommentsField::enabledOverride(CommentsField::STATE_DISABLED))->toBeFalse();
});

it('allows the field to opt an element in or out of its source setting', function() {
    $field = commentsFieldFixture();
    $section = CommentsFixtureFactory::section();
    $entryType = $section?->getEntryTypes()[0] ?? null;

    expect($field)->toBeInstanceOf(CommentsField::class)
        ->and($section)->not->toBeNull()
        ->and($entryType)->not->toBeNull();

    $entry = new Entry([
        'sectionId' => $section->id,
        'typeId' => $entryType->id,
        'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        'title' => 'Field override owner',
        'slug' => 'field-override-owner-' . uniqid(),
        'enabled' => true,
    ]);
    $entry->setFieldValue($field->handle, CommentsField::STATE_ENABLED);

    expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();

    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [
        Entry::class => ['not-the-owner-section-uid'],
    ];
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    Comments::$plugin->getConversations()->clearCaches();

    $config = Comments::$plugin->getConversations()->getConfigForOwner((int)$entry->id, (int)$entry->siteId);

    expect($config->enabled)->toBeTrue();

    $admin = Craft::$app->getUser()->getIdentity();
    $result = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Allowed by the element override.',
        'userId' => (int)$admin->id,
    ]));

    expect($result->comment->id)->not->toBeNull();

    $entry->setFieldValue($field->handle, CommentsField::STATE_DISABLED);

    expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();

    $config = Comments::$plugin->getConversations()->getConfigForOwner((int)$entry->id, (int)$entry->siteId);

    expect($config->enabled)->toBeFalse();
});

it('builds the compact field summary with comment counts', function() {
    $entry = CommentsFixtureFactory::entry('Comments field summary entry');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    $comment = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Comments field summary comment',
        'userId' => (int)$admin->id,
    ]))->comment;

    $conversation = Comments::$plugin->getConversations()->findForOwner((int)$entry->id, (int)$entry->siteId);

    if ($conversation) {
        Comments::$plugin->getConversations()->rebuildAggregates((int)$conversation->id);
    }

    $summary = Comments::$plugin->getOwnerElement()->getFieldSummary((int)$entry->id, (int)$entry->siteId);

    expect($comment->id)->not->toBeNull()
        ->and($summary)->toHaveKeys(['status', 'statusLabel', 'counts', 'manageUrl'])
        ->and($summary['status'])->toBe('open')
        ->and($summary['counts']['approved'])->toBeGreaterThan(0)
        ->and($summary['counts'])->toHaveKeys(['pending', 'openReports']);
});

it('shows Open, Closed and Off following the source setting until the entry overrides it', function() {
    $field = commentsFieldFixture();
    $entry = CommentsFixtureFactory::entry('Panel state owner');
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->closed = false;
    Comments::$plugin->getConversations()->clearCaches();

    $inherited = renderCommentsFieldInput($field, CommentsField::STATE_INHERIT, $entry);

    // Inheriting selects the first option, which names the state the source gives.
    expect(Comments::$plugin->getOwnerElement()->getSourceStatus($entry))->toBe('open')
        ->and($inherited)->toContain('<select')
        ->not->toContain('btngroup')
        ->toMatch('/name="commentsOverride"/')
        ->toMatch('/<option value="inherit"[^>]*selected[^>]*>Default \(Open\)<\/option>/')
        ->not->toContain('comments-field__title')
        ->toContain('Visitors can read and post.');

    $closed = renderCommentsFieldInput($field, CommentsField::STATE_CLOSED, $entry);

    // An override selects its own option and stores the same value as before.
    expect($closed)->toMatch('/<option value="closed"[^>]*selected/')
        ->not->toMatch('/<option value="inherit"[^>]*selected/');

    // The source can turn comments off, or the global setting can close everything.
    $settings->permissions = [Entry::class => ['not-the-owner-section-uid']];
    Comments::$plugin->getConversations()->clearCaches();

    expect(Comments::$plugin->getOwnerElement()->getSourceStatus($entry))->toBe('disabled')
        ->and(renderCommentsFieldInput($field, CommentsField::STATE_INHERIT, $entry))
        ->toContain('Default (Off)');

    $settings->permissions = [];
    $settings->closed = true;
    Comments::$plugin->getConversations()->clearCaches();

    expect(Comments::$plugin->getOwnerElement()->getSourceStatus($entry))->toBe('closed');
});

it('offers Approve and Spam on held comments and Keep and Remove on reported ones', function() {
    $field = commentsFieldFixture();
    $entry = CommentsFixtureFactory::entry('Panel waiting owner');
    $author = CommentsFixtureFactory::user('panelAuthor');
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $post = static fn(string $content): verbb\comments\elements\Comment => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)$author->id,
    ]))->comment;

    $reported = $post('Reported in the entry panel');
    $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter', null);
    Comments::$plugin->getReports()->create((int)$reported->id, (int)$reporter->id, 'spam');

    $settings->requireModeration = true;
    Comments::$plugin->getConversations()->clearCaches();
    $held = $post('Held in the entry panel');

    $summary = Comments::$plugin->getOwnerElement()->getFieldSummary((int)$entry->id, (int)$entry->siteId);
    $byKind = array_column($summary['waiting'], null, 'kind');
    $name = $held->getAuthorName();

    expect($summary['waiting'])->toHaveCount(2)
        ->and($byKind['pending']['decisions'])->toBe(['approve', 'spam'])
        ->and($byKind['reported']['decisions'])->toBe(['keep', 'remove'])
        ->and($byKind['reported']['reason'])->toBe('Reported')
        ->and($byKind['pending']['author'])->toMatchArray([
            'name' => $name,
            'initials' => Commenters::initials($name),
            'photoUrl' => null,
        ])
        ->and($summary['latest'][0]['comment']->id)->toBe($reported->id)
        ->and($summary['latest'][0]['age'])->toBe('now');

    $html = renderCommentsFieldInput($field, CommentsField::STATE_INHERIT, $entry);

    expect($html)->toContain('1 waiting for review · 1 reported')
        ->toMatch('/data-comments-field-decide="approve"\s+data-comment-id="' . $held->id . '"/')
        ->toMatch('/data-comments-field-decide="keep"\s+data-comment-id="' . $reported->id . '"/')
        ->not->toMatch('/data-comments-field-decide="remove"\s+data-comment-id="' . $held->id . '"/')
        ->toContain('comments-field__pill--reported')
        ->toMatch('/<span class="comments-avatar comments-avatar--small" data-tone="\d" aria-hidden="true">' . preg_quote(Commenters::initials($name), '/') . '<\/span>/')
        ->toContain('Latest')
        ->toContain('comments-field__footer')
        ->toMatch('/data-comments-field-clear hidden/');
});

it('counts reported comments, not reports, in the field’s header', function() {
    $field = commentsFieldFixture();
    $entry = CommentsFixtureFactory::entry('Panel report count owner');
    $author = CommentsFixtureFactory::user('panelCountAuthor');
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $post = static fn(string $content): verbb\comments\elements\Comment => Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)$author->id,
    ]))->comment;
    $report = static function(verbb\comments\elements\Comment $comment, int $times): void {
        foreach (range(1, $times) as $i) {
            $reporter = Comments::$plugin->getIdentities()->createHistoricalGuest('Reporter ' . $i, null);
            Comments::$plugin->getReports()->create((int)$comment->id, (int)$reporter->id, 'spam');
        }
    };

    // Three people reported one comment, and one person another, which a moderator then removed.
    $reported = $post('Reported three times');
    $report($reported, 3);
    $removed = $post('Reported, then removed');
    $report($removed, 1);
    Comments::$plugin->getModeration()->remove($removed, Craft::$app->getUser()->getIdentity());

    $summary = Comments::$plugin->getOwnerElement()->getFieldSummary((int)$entry->id, (int)$entry->siteId);
    $reportedCards = array_filter($summary['waiting'], static fn(array $item): bool => $item['kind'] === 'reported');

    // One reported comment to decide, which is one card: the field's script steps the count down by one per card.
    expect($summary['counts']['openReports'])->toBe(1)
        ->and($reportedCards)->toHaveCount(1)
        ->and(renderCommentsFieldInput($field, CommentsField::STATE_INHERIT, $entry))->toContain('data-reported="1"')->toContain('1 reported');
});

it('shows only the setting when the field hides the comments summary', function() {
    $field = commentsFieldFixture();
    $entry = CommentsFixtureFactory::entry('Panel without summary');

    expect($field->showSummary)->toBeTrue()
        ->and(renderCommentsFieldInput($field, CommentsField::STATE_INHERIT, $entry))->toContain('comments-field__card-box');

    // A lightswitch posts an empty string when off.
    $hidden = new CommentsField(['name' => 'Comments', 'handle' => 'commentsOverride', 'showSummary' => '']);
    $html = renderCommentsFieldInput($hidden, CommentsField::STATE_INHERIT, $entry);

    expect($hidden->showSummary)->toBeFalse()
        ->and($html)->toContain('<select')
        ->not->toContain('comments-field__card-box')
        ->and($hidden->getSettingsHtml())->toContain('name="showSummary"');
});

// Sweep 3: who sees the summary, and the select saving through the entry.

function s3FieldPanelEntry(string $title): array
{
    $entry = CommentsFixtureFactory::entry($title);
    $author = CommentsFixtureFactory::user('s3PanelAuthor');
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->requireModeration = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    Comments::$plugin->getConversations()->clearCaches();

    $held = Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Private held words',
        'userId' => (int)$author->id,
    ]))->comment;

    return [$entry, $held];
}

function s3FieldPanelUser(string $prefix, array $permissions): craft\elements\User
{
    $user = CommentsFixtureFactory::user($prefix);
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, $permissions);

    return craft\elements\User::find()->id($user->id)->status(null)->one();
}

function s3RenderFieldAs(craft\elements\User $user, CommentsField $field, Entry $entry): string
{
    $session = Craft::$app->getUser();
    $previous = $session->getIdentity();
    $session->setIdentity($user);

    try {
        return renderCommentsFieldInput($field, CommentsField::STATE_INHERIT, $entry);
    } finally {
        $session->setIdentity($previous);
    }
}

it('shows an entry editor without Comments access only the setting, never waiting comments', function() {
    $field = commentsFieldFixture();
    [$entry, $held] = s3FieldPanelEntry('Panel for a writer');
    $writer = s3FieldPanelUser('s3PanelWriter', ['accessCp']);

    $html = s3RenderFieldAs($writer, $field, $entry);
    $session = Craft::$app->getUser();
    $previous = $session->getIdentity();
    $session->setIdentity($writer);
    $summary = Comments::$plugin->getOwnerElement()->getFieldSummary((int)$entry->id, (int)$entry->siteId);
    $session->setIdentity($previous);

    expect($summary)->toBeNull()
        ->and($html)->toContain('<select')
        ->not->toContain('comments-field__card-box')
        ->not->toContain('Private held words')
        ->not->toContain('data-comments-field-decide')
        // The same field shows the card to an admin, so the check above isn't empty.
        ->and(renderCommentsFieldInput($field, CommentsField::STATE_INHERIT, $entry))->toContain('Private held words');
});

it('shows waiting comments without decisions to someone who can view but not moderate', function() {
    $field = commentsFieldFixture();
    [$entry, $held] = s3FieldPanelEntry('Panel for a viewer');
    $viewer = s3FieldPanelUser('s3PanelViewer', ['accessCp', 'comments-view']);
    $moderator = s3FieldPanelUser('s3PanelModerator', ['accessCp', 'comments-view', 'comments-moderate']);

    $viewerHtml = s3RenderFieldAs($viewer, $field, $entry);

    expect($viewerHtml)->toContain('Private held words')
        ->toContain('href="' . $held->getCpEditUrl() . '"')
        ->not->toContain('data-comments-field-decide')
        ->and(s3RenderFieldAs($moderator, $field, $entry))->toMatch('/data-comments-field-decide="approve"\s+data-comment-id="' . $held->id . '"/');
});

it('closes, reopens and turns off comments when the entry is saved with the field’s select', function() {
    $field = commentsFieldFixture();
    $entry = CommentsFixtureFactory::entry('Panel select save');
    $settings = Comments::$plugin->getSettings();
    $settings->permissions = [];
    $settings->closed = false;
    $conversations = Comments::$plugin->getConversations();
    $saveAs = static function(string $value) use ($entry, $field, $conversations): verbb\comments\models\ConversationConfig {
        $entry->setFieldValue($field->handle, $value);
        expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();
        $conversations->clearCaches();

        return $conversations->getConfigForOwner((int)$entry->id, (int)$entry->siteId);
    };

    $closed = $saveAs(CommentsField::STATE_CLOSED);

    expect($closed->enabled)->toBeTrue()
        ->and($closed->closed)->toBeTrue()
        ->and($closed->isCommentingEnabled())->toBeFalse()
        ->and(Entry::find()->id($entry->id)->one()->getFieldValue($field->handle))->toBe(CommentsField::STATE_CLOSED);

    $inherited = $saveAs(CommentsField::STATE_INHERIT);

    expect($inherited->closed)->toBeFalse()
        ->and($inherited->isCommentingEnabled())->toBeTrue();

    expect($saveAs(CommentsField::STATE_DISABLED)->enabled)->toBeFalse();
});
