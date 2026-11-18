<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\web\assets\frontend\CommentsScriptAsset;
use verbb\comments\web\assets\frontend\CommentsStyleAsset;
use verbb\editor\web\assets\editor\EditorAsset;

it('returns a diagnostics health snapshot', function() {
    $health = Comments::$plugin->getDiagnostics()->getHealthSnapshot();

    expect($health)->toHaveKeys(['conversations', 'comments', 'pending', 'aggregateDrift', 'queueFailures', 'schemaVersion']);
});

it('runs integrity scans and rebuilds conversation aggregates', function() {
    $entry = CommentsFixtureFactory::entry('Diagnostics rebuild entry');
    $admin = Craft::$app->getUser()->getIdentity();
    Comments::$plugin->getSettings()->requireModeration = false;

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => 'Diagnostics aggregate comment',
        'userId' => (int)$admin->id,
    ]));

    $report = Comments::$plugin->getDiagnostics()->runIntegrityScan();
    expect($report)->toHaveKeys(['upgrade', 'v4', 'hasErrors']);

    $result = Comments::$plugin->getDiagnostics()->rebuildAllAggregates();
    expect($result['rebuilt'])->toBeGreaterThan(0);

    $health = Comments::$plugin->getDiagnostics()->getHealthSnapshot();
    expect($health['aggregateDrift'])->toBe(0);
});

it('defines a settings tab for every pane template', function() {
    $tabs = \verbb\comments\helpers\SettingsTabs::all();

    expect($tabs)->not->toBeEmpty();
    expect($tabs)->toHaveKey('general');
    expect($tabs)->toHaveKeys(['authoring', 'fields', 'commenting', 'interactions', 'notifications', 'moderation', 'appearance', 'css-javascript']);
    // Spam Protection is a page of its own, not a settings tab.
    expect($tabs)->not->toHaveKeys(['permissions', 'security', 'spam', 'frontend', 'advanced', 'templates']);

    $navItems = \verbb\comments\helpers\SettingsTabs::navItems();
    expect($navItems)->toHaveKey('general');
    expect($navItems)->not->toHaveKey('contentHeading');
    expect($navItems['general']['title'])->toBe('General Settings');
    expect($navItems['authoring']['title'])->toBe('Authoring');
    expect($navItems['fields']['title'])->toBe('Comment Fields');
    expect($navItems['moderation']['title'])->toBe('Moderation');
    expect($navItems)->not->toHaveKey('spam');
    expect($navItems['appearance']['title'])->toBe('Appearance');
    expect($navItems['css-javascript']['title'])->toBe('CSS & JavaScript');
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('fields'))->toBe('fields');
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('security'))->toBe('spam');
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('frontend'))->toBe('appearance');
    // Development builds called the CSS & JavaScript pane Templates & Resources.
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('templates'))->toBe('css-javascript');
    // The pane that chooses where comments are allowed was called Permissions.
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('permissions'))->toBe('commenting');
    expect($navItems['commenting']['title'])->toBe('Commenting');

    // Every tab that claims to render inline must have a pane template, otherwise the
    // sidebar links to a page that throws.
    $templatesPath = dirname(__DIR__, 2) . '/src/templates/settings/_panes';

    foreach ($tabs as $handle => $tab) {
        if (!\verbb\comments\helpers\SettingsTabs::isPane($handle)) {
            continue;
        }

        expect(file_exists($templatesPath . '/' . $handle . '.html'))
            ->toBeTrue("Missing pane template for settings tab “{$handle}”.");
    }
});

it('keeps frontend asset bundles static and the resource settings focused', function() {
    Comments::$plugin->getSettings()->useCssLayers = false;
    Comments::$plugin->getSettings()->theme = 'light';
    $script = new CommentsScriptAsset();
    $stylesheet = new CommentsStyleAsset();
    $editor = new EditorAsset();

    $resourcePath = Craft::getAlias('@verbb/comments/web/assets/frontend/dist');

    expect($script->sourcePath)->toBe($resourcePath);
    expect($script->js)->toBe(['js/comments.js']);
    expect($stylesheet->sourcePath)->toBe($resourcePath);
    expect($stylesheet->css)->toBe(['css/comments.css']);
    expect($editor->js)->toBe(['editor.js']);
    expect($editor->jsOptions)->toBe(['type' => 'module']);

    $settings = new Settings([
        'assetBaseUrl' => 'https://legacy.example.test/comments',
        'assetBasePath' => '/legacy/comments',
    ]);
    expect($settings->toArray())->not->toHaveKeys(['assetBaseUrl', 'assetBasePath']);

    $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/_panes/css-javascript.html');

    expect($template)->toContain('note warning');
    expect($template)->toContain('Load Bundled CSS');
    expect($template)->toContain('Load Bundled JavaScript');
    expect($template)->not->toContain('assetBaseUrl');
    expect($template)->not->toContain('assetBasePath');
    expect($template)->not->toContain('comments-publish-assets');
});

it('says Report rather than Flag, and keeps note forms from leaving the editor', function() {
    $templatesPath = dirname(__DIR__, 2) . '/src/templates';
    $notes = file_get_contents($templatesPath . '/comments/_editor-activity.html');
    $interactionSettings = file_get_contents($templatesPath . '/settings/_panes/interactions.html');
    $labels = Comments::$plugin->getApi()->labels();
    $fields = file_get_contents($templatesPath . '/settings/_panes/fields.html');

    // Notes save on their own, so adding one never submits whatever's being edited.
    expect($notes)
        ->toContain('data-comments-panel-action')
        ->not->toContain('formsubmit')
        ->not->toContain('name="');

    // Reporting is something visitors do, so it sits with likes and votes.
    expect($interactionSettings)
        ->toContain("<h3>{{ 'Reporting' | t('comments') }}</h3>")
        ->not->toContain("'Allow Comment Flagging'")
        ->not->toContain("'Flag Reasons'");
    // The renderer's words come from the data API's labels.
    expect($labels)->toMatchArray(['report' => 'Report…', 'reportTitle' => 'Report this comment', 'sendReport' => 'Send report'])
        ->and(implode(' ', array_filter($labels, 'is_string')))->not->toContain('Flag');
    expect($fields)->toContain('customizableTabs: true');
});
