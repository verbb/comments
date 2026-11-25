<?php

declare(strict_types=1);

use craft\elements\User;
use craft\helpers\UrlHelper;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\V3Site;
use verbb\comments\compatibility\twig\LegacyRenderOptions;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\migrations\m400000_000010_template_overrides;
use verbb\comments\migrations\support\V3Schema;
use verbb\comments\migrations\support\V3SettingsReview;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\web\assets\frontend\CommentsScriptAsset;
use verbb\comments\web\assets\frontend\CommentsStyleAsset;

/**
 * The switch-over: the JavaScript renderer draws every thread, so `render()` outputs only the mount
 * point, and the Twig thread, its HTML fragment endpoints, Template Folder Override, `sharedCache` and
 * the no-JavaScript path are gone. The Comments 3 form actions stay as a bridge (SD-37), and the guest
 * subscription page stays a server-rendered page.
 */

function switchOverPath(string $path): string
{
    return dirname(__DIR__, 2) . '/' . $path;
}

function switchOverComment($entry, string $text, ?User $user = null): Comment
{
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    Comments::$plugin->getConversations()->clearCaches();

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => $user ? (int)$user->id : (int)Craft::$app->getUsers()->getUserByUsernameOrEmail('admin')->id,
    ]))->comment;
}

/** The deprecation warnings logged in this request with `$key`. */
function switchOverDeprecations(string $key): array
{
    return array_values(array_filter(Craft::$app->getDeprecator()->getRequestLogs(), static fn($log): bool => $log->key === $key));
}

/**
 * Run the template overrides migration against these plugin settings, with `config/comments.php`
 * holding `$configFile`, and give back what it left in project config. Project config and its YAML are
 * put back exactly as they were.
 */
function switchOverMigrate(array $settings, array $configFile = [], string $schemaVersion = '4.0.7', bool $readOnly = false): array
{
    $projectConfig = Craft::$app->getProjectConfig();
    $originalSettings = $projectConfig->get('plugins.comments.settings');
    $originalVersion = $projectConfig->get('plugins.comments.schemaVersion');
    $configPath = Craft::$app->getConfig()->getConfigFilePath('comments');

    if ($configFile !== [] && file_exists($configPath)) {
        throw new RuntimeException('The test app already has a config/comments.php.');
    }

    try {
        $projectConfig->set('plugins.comments.schemaVersion', $schemaVersion);
        $projectConfig->remove('plugins.comments.settings');
        $projectConfig->set('plugins.comments.settings', $settings);
        V3Site::writeProjectConfig();

        if ($configFile !== []) {
            file_put_contents($configPath, '<?php return ' . var_export($configFile, true) . ';');
        }

        // With `allowAdminChanges` off, Craft applies project config read-only.
        $projectConfig->readOnly = $readOnly;

        try {
            (new m400000_000010_template_overrides(['db' => Craft::$app->getDb()]))->safeUp();
        } finally {
            $projectConfig->readOnly = false;
        }

        // What's saved, read back from the database and YAML, not only what's in memory.
        V3Site::writeProjectConfig();

        return [$projectConfig->get('plugins.comments.settings') ?? [], $projectConfig->get('plugins.comments.settings', true) ?? []];
    } finally {
        if ($configFile !== []) {
            unlink($configPath);
        }

        // Craft doesn't save the removal of a `false`, so each key gets another value before it goes.
        foreach (array_keys($projectConfig->get('plugins.comments.settings') ?? []) as $key) {
            $projectConfig->set("plugins.comments.settings.{$key}", 'removed');
            $projectConfig->remove("plugins.comments.settings.{$key}");
        }

        $projectConfig->remove('plugins.comments.settings');

        if ($originalSettings !== null) {
            $projectConfig->set('plugins.comments.settings', $originalSettings, null, true, true);
        }

        $projectConfig->set('plugins.comments.schemaVersion', $originalVersion);
        V3Site::writeProjectConfig();

        expect($projectConfig->get('plugins.comments.settings'))->toBe($originalSettings)
            ->and($projectConfig->get('plugins.comments.settings', true))->toBe($originalSettings);
    }
}

/**
 * Run `$callback` with these files in the site's templates folder and an Upgrade Report table to
 * write to, then remove both, leaving the table as it was.
 */
function switchOverWithTemplates(array $files, callable $callback): mixed
{
    $root = Craft::$app->getPath()->getSiteTemplatesPath();
    $db = Craft::$app->getDb();
    $created = !$db->tableExists(V3Schema::LOG);

    foreach ($files as $file) {
        craft\helpers\FileHelper::writeToFile($root . '/' . $file, '{# A Comments 3 override #}');
    }

    try {
        return $callback();
    } finally {
        foreach ($files as $file) {
            craft\helpers\FileHelper::removeDirectory($root . '/' . explode('/', $file)[0]);
        }

        if ($db->tableExists(V3Schema::LOG)) {
            if ($created) {
                $db->createCommand()->dropTable(V3Schema::LOG)->execute();
            } else {
                $db->createCommand()->delete(V3Schema::LOG, ['code' => [m400000_000010_template_overrides::NOTICE, m400000_000010_template_overrides::NOTICE_CONVENTIONAL]])->execute();
            }
        }
    }
}

function switchOverReport(string $code = m400000_000010_template_overrides::NOTICE): ?array
{
    foreach (V3UpgradeLog::issues(Craft::$app->getDb()) as $issue) {
        if ($issue['code'] === $code) {
            return $issue;
        }
    }

    return null;
}

// render() and the mount point
// =========================================================================

it('outputs only the mount point, exactly as the browser checks load it, and registers the new script and stylesheet', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over mount point owner');
    switchOverComment($entry, 'Drawn by the renderer');
    switchOverComment($entry, 'Drawn by the renderer too');
    Comments::$plugin->getSettings()->theme = 'light';
    Comments::$plugin->getSettings()->useCssLayers = false;
    Craft::$app->getAssetManager()->bundles = [];

    $view = Craft::$app->getView();
    $view->assetBundles = [];
    $html = (string)Comments::$plugin->getComments()->render($entry, ['loadMode' => 'visible']);

    // `tests/browser/load-modes.mjs` serves this same markup to the built script, so the browser checks
    // and the server can't drift apart.
    $fixture = strtr(trim(file_get_contents(switchOverPath('tests/browser/mount-point.html'))), [
        '{ownerId}' => (string)$entry->id,
        '{siteId}' => (string)$entry->siteId,
        '{endpoint}' => UrlHelper::actionUrl('comments/api'),
    ]);

    expect($html)->toBe($fixture)
        ->not->toContain('Drawn by the renderer')
        ->not->toContain('<form')
        ->not->toContain('<a ')
        ->not->toContain('style=')
        ->and(array_keys($view->assetBundles))->toContain(CommentsScriptAsset::class)
        ->toContain(CommentsStyleAsset::class)
        ->and($view->assetBundles[CommentsStyleAsset::class]->css)->toBe(['css/comments.css'])
        ->and($view->assetBundles[CommentsScriptAsset::class]->js)->toBe(['js/comments.js']);
});

it('outputs the same mount point for a guest and a member, so a cached page suits everyone', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->allowGuest = false;
    Comments::$plugin->getConversations()->clearCaches();
    $user = Craft::$app->getUser();
    $admin = $user->getIdentity();

    // A new entry with no conversation yet, which a guest couldn't start.
    $fresh = CommentsFixtureFactory::entry('Switch-over guest and member owner');
    $busy = CommentsFixtureFactory::entry('Switch-over guest and member busy owner');
    switchOverComment($busy, 'Already talking');

    try {
        foreach ([$fresh, $busy] as $entry) {
            $user->setIdentity(null);
            $guest = (string)Comments::$plugin->getComments()->render($entry);
            $user->setIdentity(CommentsFixtureFactory::user('switchOverRenderMember'));
            $member = (string)Comments::$plugin->getComments()->render($entry);
            $user->setIdentity($admin);
            $moderator = (string)Comments::$plugin->getComments()->render($entry);

            expect($guest)->toContain('data-comments-root')
                ->and($member)->toBe($guest)
                ->and($moderator)->toBe($guest);
        }
    } finally {
        $user->setIdentity($admin);
    }
});

it('carries the render() options the renderer applies in the mount point', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over options owner');
    switchOverComment($entry, 'One');

    $html = (string)Comments::$plugin->getComments()->render($entry, [
        'loadMode' => 'button',
        'sort' => 'oldest',
        'limit' => 500,
        'themeConfig' => ['primaryButton' => ['resetClass' => true]],
        'fields' => ['rating' => ['input' => 'star-rating']],
    ]);

    preg_match('/data-comments-config="([^"]*)"/', $html, $config);

    expect($html)->toContain('data-comments-load-mode="button"')
        ->toContain('class="comments comments--reserve-1"')
        ->and(json_decode(html_entity_decode($config[1] ?? ''), true))->toBe([
            'sort' => 'oldest',
            'limit' => 50,
            'themeConfig' => ['primaryButton' => ['resetClass' => true]],
            'fields' => ['rating' => ['input' => 'star-rating']],
        ]);
});

it('ignores the removed sharedCache and experimentalRenderer options, with a deprecation warning', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over removed options owner');
    switchOverComment($entry, 'Still a mount point');
    $comments = Comments::$plugin->getComments();

    expect(switchOverDeprecations('comments.render.sharedCache'))->toBe([]);

    $plain = (string)$comments->render($entry, ['loadMode' => 'eager']);
    $shared = (string)$comments->render($entry, ['loadMode' => 'eager', 'sharedCache' => true]);
    $experimental = (string)$comments->render($entry, ['loadMode' => 'eager', 'experimentalRenderer' => false]);

    expect($shared)->toBe($plain)
        ->and($experimental)->toBe($plain)
        ->and(switchOverDeprecations('comments.render.sharedCache'))->not->toBe([])
        ->and(switchOverDeprecations('comments.render.experimentalRenderer'))->not->toBe([])
        ->and(array_keys(LegacyRenderOptions::REMOVED))->toBe(['sharedCache', 'experimentalRenderer']);
});

it('ignores the page’s old no-JavaScript parameters', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over page parameters owner');
    $comment = switchOverComment($entry, 'Linked');
    $comments = Comments::$plugin->getComments();
    $request = Craft::$app->getRequest();
    $plain = (string)$comments->render($entry);
    $original = $request->getQueryParams();

    try {
        $request->setQueryParams(['comment' => $comment->id, 'commentsCompose' => 'reply', 'commentsSort' => 'oldest', 'commentsRootsNext' => 'x', 'commentsFlag' => 1, 'commentsLoad' => 1]);

        expect((string)$comments->render($entry))->toBe($plain);
    } finally {
        $request->setQueryParams($original);
    }
});

it('passes a nonce to the script and stylesheet, and loads only the base CSS for theme None', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over nonce owner');
    Comments::$plugin->getSettings()->theme = 'none';
    Comments::$plugin->getSettings()->useCssLayers = true;

    // The asset manager keeps one bundle per class for the request, made with the settings of the time.
    Craft::$app->getAssetManager()->bundles = [];
    $view = Craft::$app->getView();
    $view->assetBundles = [];
    $html = (string)Comments::$plugin->getComments()->render($entry, ['nonce' => 'abc123']);

    expect($html)->toContain('data-comments-theme="none"')
        ->and($view->assetBundles[CommentsScriptAsset::class]->jsOptions)->toMatchArray(['type' => 'module', 'nonce' => 'abc123'])
        ->and($view->assetBundles[CommentsStyleAsset::class]->cssOptions)->toMatchArray(['nonce' => 'abc123'])
        ->and($view->assetBundles[CommentsStyleAsset::class]->css)->toBe(['css/comments-base.layered.css']);
});

it('outputs and registers nothing for an element without a thread', function() {
    $view = Craft::$app->getView();
    $comments = Comments::$plugin->getComments();

    $disabled = CommentsFixtureFactory::entry('Switch-over disabled owner');
    $disabled->enabled = false;
    Craft::$app->getElements()->saveElement($disabled, false);

    $view->assetBundles = [];

    expect((string)$comments->render($disabled))->toBe('')
        ->and((string)$comments->render(999999999))->toBe('')
        ->and($view->assetBundles)->toBe([]);
});

it('leaves out the bundled assets when they’re turned off', function() {
    $entry = CommentsFixtureFactory::entry('Switch-over no assets owner');
    Comments::$plugin->getSettings()->outputDefaultCss = false;
    Comments::$plugin->getSettings()->outputDefaultJs = false;
    $view = Craft::$app->getView();
    $view->assetBundles = [];

    expect((string)Comments::$plugin->getComments()->render($entry))->toContain('data-comments-root')
        ->and($view->assetBundles)->toBe([]);
});

it('ships the built script and stylesheets, and nothing of the Twig thread', function() {
    $dist = Craft::getAlias('@verbb/comments/web/assets/frontend/dist');

    foreach (['js/comments.js', 'css/comments.css', 'css/comments-base.css', 'css/comments.layered.css', 'css/comments-base.layered.css'] as $file) {
        expect(is_file($dist . '/' . $file))->toBeTrue($file);
    }

    // The reserved space for each bucket the mount point can name.
    $css = file_get_contents($dist . '/css/comments-base.css');

    foreach (range(1, 4) as $bucket) {
        expect($css)->toContain('.comments--reserve-' . $bucket);
    }

    expect(file_get_contents($dist . '/js/comments.js'))->not->toMatch('/prosemirror|tiptap/i')
        ->and(is_dir(switchOverPath('src/resources')))->toBeFalse()
        ->and(array_map('basename', glob(switchOverPath('src/templates/frontend/*'))))->toBe(['manage.twig'])
        ->and(class_exists('verbb\\comments\\controllers\\FrontendApiController'))->toBeFalse()
        ->and(class_exists('verbb\\comments\\models\\FrontendEnvelope'))->toBeFalse()
        ->and(class_exists('verbb\\comments\\models\\FrontendEditorView'))->toBeFalse()
        ->and(class_exists('verbb\\comments\\models\\FrontendViewerView'))->toBeFalse()
        ->and(class_exists('verbb\\comments\\web\\twig\\FrontendTemplateLoader'))->toBeFalse()
        ->and(class_exists('verbb\\comments\\web\\assets\\frontend\\FrontEndAsset'))->toBeFalse()
        ->and(class_exists('verbb\\comments\\web\\assets\\frontend\\FrontEndCssAsset'))->toBeFalse()
        ->and(method_exists(Comments::$plugin->getFrontend(), 'renderPartial'))->toBeFalse()
        ->and(method_exists(Comments::$plugin->getFrontend(), 'renderExperimental'))->toBeFalse();
});

// Template Folder Override
// =========================================================================

it('no longer has Template Folder Override, and says so when a config file still sets it', function() {
    $settings = new Settings(['templateFolderOverride' => '_comments']);

    expect(property_exists($settings, 'templateFolderOverride'))->toBeFalse()
        ->and($settings->toArray())->not->toHaveKey('templateFolderOverride')
        ->and(switchOverDeprecations('comments.templateFolderOverride'))->toBe([])
        ->and(file_get_contents(switchOverPath('src/templates/settings/_panes/css-javascript.html')))->not->toContain('templateFolderOverride')
        ->and(V3SettingsReview::dispositions()['templateFolderOverride'])->toBe(V3SettingsReview::RETIRED)
        // The template listing says more than "the setting has gone", so the review leaves it to that.
        ->and(V3SettingsReview::retiredInUse(['templateFolderOverride' => '_comments']))->toBe([]);

    $path = Craft::$app->getConfig()->getConfigFilePath('comments');
    file_put_contents($path, '<?php return ' . var_export(['templateFolderOverride' => '_comments'], true) . ';');

    try {
        expect((new Settings(['templateFolderOverride' => '_comments']))->toArray())->not->toHaveKey('templateFolderOverride')
            ->and(switchOverDeprecations('comments.templateFolderOverride'))->not->toBe([]);
    } finally {
        unlink($path);
    }
});

it('lists the files in the folder the setting named in the Upgrade Report, and drops the setting', function() {
    $files = ['_site-comments/_includes/comment.html', '_site-comments/comments.html', '_site-comments/form-fields/fields/assets.html', '_site-comments/notes.txt'];

    switchOverWithTemplates($files, function(): void {
        [$saved, $yaml] = switchOverMigrate(['templateFolderOverride' => '_site-comments', 'pluginName' => 'Comments']);
        $issue = switchOverReport();

        expect($saved)->toBe(['pluginName' => 'Comments'])
            ->and($yaml)->toBe(['pluginName' => 'Comments'])
            ->and($issue['count'] ?? null)->toBe(3)
            ->and($issue['items'] ?? null)->toBe(['_site-comments/_includes/comment.html', '_site-comments/comments.html', '_site-comments/form-fields/fields/assets.html'])
            ->and($issue['url'] ?? null)->toBe('https://verbb.io/craft-plugins/comments/docs/v4/get-started/upgrading-from-v3#template-overrides')
            ->and($issue['label'] ?? '')->toContain('overrides the thread’s templates');
    });
});

it('looks in templates/_comments when the setting was empty, and drops an empty setting too', function() {
    switchOverWithTemplates(['_comments/frontend/_comment/author.twig', '_comments/frontend/conversation.twig'], function(): void {
        [$saved] = switchOverMigrate(['templateFolderOverride' => '', 'pluginName' => 'Comments']);

        $issue = switchOverReport(m400000_000010_template_overrides::NOTICE_CONVENTIONAL);

        // Files there may be the site's own form partials for the form actions, so it says so gently.
        expect($saved)->toBe(['pluginName' => 'Comments'])
            ->and(switchOverReport())->toBeNull()
            ->and($issue['items'] ?? null)->toBe(['_comments/frontend/_comment/author.twig', '_comments/frontend/conversation.twig'])
            ->and($issue['url'] ?? null)->toBe(m400000_000010_template_overrides::GUIDE_URL)
            ->and($issue['label'] ?? '')->toContain('check whether these were overrides')->toContain('keep working');
    });
});

it('takes the folder from config/comments.php over project config', function() {
    switchOverWithTemplates(['_from-file/comments.html', '_from-config/comments.html'], function(): void {
        switchOverMigrate(['templateFolderOverride' => '_from-config'], ['templateFolderOverride' => '_from-file']);

        expect(switchOverReport()['items'] ?? null)->toBe(['_from-file/comments.html']);
    });
});

it('reports nothing for a site without overrides, and never looks outside the templates folder', function() {
    switchOverWithTemplates([], function(): void {
        [$saved] = switchOverMigrate(['templateFolderOverride' => '../config', 'pluginName' => 'Comments']);

        expect($saved)->toBe(['pluginName' => 'Comments'])
            ->and(switchOverReport())->toBeNull()
            ->and(m400000_000010_template_overrides::overriddenFiles('../config'))->toBe([])
            ->and(m400000_000010_template_overrides::overriddenFiles('_nothing-here'))->toBe([]);
    });
});

it('leaves project config alone when it’s applied from an environment that already ran it', function() {
    switchOverWithTemplates(['_comments/comments.html'], function(): void {
        [$saved] = switchOverMigrate(['templateFolderOverride' => '_comments'], [], '4.0.8');

        // The files are still listed, because each environment has its own templates and report.
        expect($saved)->toBe(['templateFolderOverride' => '_comments'])
            ->and(switchOverReport()['items'] ?? null)->toBe(['_comments/comments.html']);
    });
});

it('leaves project config alone where admin changes are off, and still lists the files', function() {
    switchOverWithTemplates(['_read-only/comments.html'], function(): void {
        [$saved] = switchOverMigrate(['templateFolderOverride' => '_read-only'], [], '4.0.7', true);

        expect($saved)->toBe(['templateFolderOverride' => '_read-only'])
            ->and(switchOverReport()['items'] ?? null)->toBe(['_read-only/comments.html']);
    });
});

it('reads the setting through an environment variable', function() {
    putenv('COMMENTS_SWITCH_OVER_FOLDER=_from-env');
    $_SERVER['COMMENTS_SWITCH_OVER_FOLDER'] = $_ENV['COMMENTS_SWITCH_OVER_FOLDER'] = '_from-env';

    try {
        switchOverWithTemplates(['_from-env/comments.html'], function(): void {
            switchOverMigrate(['templateFolderOverride' => '$COMMENTS_SWITCH_OVER_FOLDER']);

            expect(switchOverReport()['items'] ?? null)->toBe(['_from-env/comments.html']);
        });
    } finally {
        putenv('COMMENTS_SWITCH_OVER_FOLDER');
        unset($_SERVER['COMMENTS_SWITCH_OVER_FOLDER'], $_ENV['COMMENTS_SWITCH_OVER_FOLDER']);
    }
});

it('lists up to 200 files with how many more there are, and keeps the stored list within its column', function() {
    $files = array_map(static fn(int $n): string => sprintf('_many/partials/template-%03d.twig', $n), range(1, 230));

    switchOverWithTemplates($files, function() use ($files): void {
        switchOverMigrate(['templateFolderOverride' => '_many']);

        $issue = switchOverReport();
        $html = Craft::$app->getView()->renderTemplate('comments/settings/_panes/upgrade', [], craft\web\View::TEMPLATE_MODE_CP);

        expect($issue['count'] ?? null)->toBe(230)
            ->and($issue['items'] ?? [])->toHaveCount(200)
            ->and($issue['items'][0] ?? null)->toBe($files[0])
            ->and($html)->toContain('And 30 more.');
    });

    // Long paths are cut from the end of the list rather than overflow the TEXT column.
    $log = new V3UpgradeLog(Craft::$app->getDb());
    $long = array_map(static fn(int $n): string => '_long/' . str_repeat('x', 900) . "-{$n}.twig", range(1, 200));
    $log->addItems('templates.test', V3UpgradeLog::WARNING, $long);
    $entries = (new ReflectionProperty($log, 'entries'))->getValue($log);

    expect($entries['templates.test']['count'])->toBe(200)
        ->and(strlen(craft\helpers\Json::encode($entries['templates.test']['samples'])))->toBeLessThanOrEqual(60000)
        ->and(count($entries['templates.test']['samples']))->toBeLessThan(200)->toBeGreaterThan(0);
});

it('bounds the walk through a site’s templates folder', function() {
    expect(m400000_000010_template_overrides::MAX_ENTRIES)->toBe(5000)
        ->and(m400000_000010_template_overrides::MAX_FILES)->toBe(1000);

    $files = array_map(static fn(int $n): string => sprintf('_bounded/t-%04d.twig', $n), range(1, 1005));

    switchOverWithTemplates($files, function() use ($files): void {
        // The first 1,000 by path, whatever order the folder is read in.
        expect(m400000_000010_template_overrides::overriddenFiles('_bounded'))->toBe(array_slice($files, 0, 1000));
    });
});

it('shows the listed files and the guide’s link on the Upgrade Report page', function() {
    switchOverWithTemplates(['_comments/comments.html'], function(): void {
        switchOverMigrate(['pluginName' => 'Comments']);

        $html = Craft::$app->getView()->renderTemplate('comments/settings/_panes/upgrade', [], craft\web\View::TEMPLATE_MODE_CP);

        expect($html)->toContain('<code>_comments/comments.html</code>')
            ->toContain('href="' . m400000_000010_template_overrides::GUIDE_URL . '"');
    });
});
