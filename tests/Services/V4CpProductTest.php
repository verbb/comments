<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\web\assets\frontend\FrontEndAsset;
use verbb\comments\web\assets\frontend\FrontEndCssAsset;
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
    expect($tabs)->toHaveKeys(['authoring', 'fields', 'commenting', 'interactions', 'notifications', 'moderation', 'spam', 'appearance', 'templates']);
    expect($tabs)->not->toHaveKeys(['permissions', 'security', 'frontend', 'advanced']);

    $navItems = \verbb\comments\helpers\SettingsTabs::navItems();
    expect($navItems)->toHaveKey('general');
    expect($navItems)->not->toHaveKey('contentHeading');
    expect($navItems['general']['title'])->toBe('General Settings');
    expect($navItems['authoring']['title'])->toBe('Authoring');
    expect($navItems['fields']['title'])->toBe('Comment Fields');
    expect($navItems['moderation']['title'])->toBe('Moderation');
    expect($navItems['spam']['title'])->toBe('Spam');
    expect($navItems['appearance']['title'])->toBe('Appearance');
    expect($navItems['templates']['title'])->toBe('Templates & Resources');
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('fields'))->toBe('fields');
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('security'))->toBe('spam');
    expect(\verbb\comments\helpers\SettingsTabs::canonicalHandle('frontend'))->toBe('appearance');
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
    $runtime = new FrontEndAsset();
    $stylesheet = new FrontEndCssAsset();
    $editor = new EditorAsset();

    $resourcePath = Craft::getAlias('@verbb/comments/resources/dist');

    expect($runtime->sourcePath)->toBe($resourcePath);
    expect($runtime->js)->toBe(['js/comments-frontend.js']);
    expect($stylesheet->sourcePath)->toBe($resourcePath);
    expect($stylesheet->css)->toBe(['js/comments-frontend.css']);
    expect($editor->js)->toBe(['editor.js']);
    expect($editor->jsOptions)->toBe(['type' => 'module']);

    $settings = new Settings([
        'assetBaseUrl' => 'https://legacy.example.test/comments',
        'assetBasePath' => '/legacy/comments',
    ]);
    expect($settings->toArray())->not->toHaveKeys(['assetBaseUrl', 'assetBasePath']);

    $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/_panes/templates.html');

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
    $reportForm = file_get_contents($templatesPath . '/frontend/_report/form.twig');
    $commentActions = file_get_contents($templatesPath . '/frontend/_comment/actions.twig');
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
    expect($reportForm)
        ->toContain("{{ 'Report this comment' | t('comments') }}")
        ->toContain("{{ 'Send report' | t('comments') }}")
        ->not->toContain('Flag');
    expect($commentActions)
        ->toContain("{{ 'Report…' | t('comments') }}")
        ->not->toContain("'Flag'");
    expect($fields)->toContain('customizableTabs: true');
});
