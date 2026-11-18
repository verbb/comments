<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\Fixtures\MultiSiteFixtureFactory;
use Tests\Support\V3Site;
use verbb\comments\Comments;
use verbb\comments\enums\LoadMode;
use verbb\comments\migrations\m400000_000007_load_mode;
use verbb\comments\migrations\support\V3Schema;
use verbb\comments\migrations\support\V3SettingsReview;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\services\Frontend;
use verbb\comments\variables\CommentsVariable;
use verbb\comments\web\assets\frontend\CommentsStyleAsset;

/**
 * Stage A of the front-end packages plan (10 November 2026): the Load Comments setting and its
 * upgrade, the CSS Layer setting, the new `render()` signature, the mount point, the removed
 * `comments/api/v1` routes, and the rule that front-end code never talks about a "runtime" or a
 * "contract".
 */

function foundationPath(string $path): string
{
    return dirname(__DIR__, 2) . '/' . $path;
}

/**
 * Run the `loadMode` migration against these plugin settings, and give back what it left. Project config
 * and its YAML are put back exactly as they were, which the test app's later runs depend on.
 */
function foundationMigrate(array $settings, string $schemaVersion = '4.0.4', array $configFile = []): array
{
    $projectConfig = Craft::$app->getProjectConfig();
    $originalSettings = $projectConfig->get('plugins.comments.settings');
    $originalVersion = $projectConfig->get('plugins.comments.schemaVersion');

    try {
        // The migration reads the schema version from the YAML, as it would when config is applied.
        $projectConfig->set('plugins.comments.schemaVersion', $schemaVersion);
        $projectConfig->remove('plugins.comments.settings');
        $projectConfig->set('plugins.comments.settings', $settings);
        V3Site::writeProjectConfig();

        foundationWithConfigFile($configFile, static fn() => (new m400000_000007_load_mode(['db' => Craft::$app->getDb()]))->safeUp());

        // What's saved, read back from the database and YAML, not only what's in memory.
        V3Site::writeProjectConfig();

        return $projectConfig->get('plugins.comments.settings') ?? [];
    } finally {
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
            ->and($projectConfig->get('plugins.comments.settings', true))->toBe($originalSettings)
            ->and($projectConfig->get('plugins.comments.schemaVersion', true))->toBe($originalVersion);
    }
}

/** Run a callback with `config/comments.php` holding these values, then remove the file. */
function foundationWithConfigFile(array $values, callable $callback): mixed
{
    if ($values === []) {
        return $callback();
    }

    $path = Craft::$app->getConfig()->getConfigFilePath('comments');

    if (file_exists($path)) {
        throw new RuntimeException('The test app already has a config/comments.php.');
    }

    file_put_contents($path, '<?php return ' . var_export($values, true) . ';');

    try {
        return $callback();
    } finally {
        unlink($path);
    }
}

function foundationComment($entry, string $text): void
{
    $settings = Comments::$plugin->getSettings();
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];

    Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $text,
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
    ]));
}

// Load Comments
// =========================================================================

it('loads comments when they’re scrolled into view by default, and keeps the CSS unlayered', function() {
    $settings = new Settings();

    expect($settings->loadMode)->toBe('visible')
        ->and($settings->resolveLoadMode())->toBe(LoadMode::Visible)
        ->and($settings->useCssLayers)->toBeFalse()
        ->and(property_exists($settings, 'lazyLoad'))->toBeFalse();
});

it('reads the old lazyLoad switch as a load mode, and lets loadMode win', function() {
    expect((new Settings(['lazyLoad' => true]))->loadMode)->toBe('visible')
        ->and((new Settings(['lazyLoad' => false]))->loadMode)->toBe('eager')
        ->and((new Settings(['lazyLoad' => '1']))->loadMode)->toBe('visible')
        ->and((new Settings(['lazyLoad' => false, 'loadMode' => 'button']))->loadMode)->toBe('button')
        ->and((new Settings(['lazyLoad' => true]))->toArray())->not->toHaveKey('lazyLoad');

    $settings = new Settings();
    $settings->setAttributes(['lazyLoad' => false], false);

    expect($settings->loadMode)->toBe('eager');
});

it('refuses a load mode it doesn’t know, and falls back to the default when one slips through', function() {
    $settings = new Settings(['loadMode' => 'later']);

    expect($settings->validate(['loadMode']))->toBeFalse()
        ->and($settings->resolveLoadMode())->toBe(LoadMode::Visible);

    foreach (LoadMode::cases() as $mode) {
        expect((new Settings(['loadMode' => $mode->value]))->validate(['loadMode']))->toBeTrue();
    }

    expect(array_column(LoadMode::options(), 'value'))->toBe(['visible', 'button', 'eager']);
});

it('moves lazyLoad to loadMode in project config', function(mixed $lazyLoad, string $expected) {
    $settings = foundationMigrate(['pluginName' => 'Comments', 'lazyLoad' => $lazyLoad]);

    expect($settings)->not->toHaveKey('lazyLoad')
        ->and($settings['loadMode'] ?? null)->toBe($expected)
        ->and($settings['pluginName'])->toBe('Comments');
})->with([
    'on' => [true, 'visible'],
    'off' => [false, 'eager'],
]);

it('keeps a loadMode that’s already set, and leaves sites without lazyLoad alone', function() {
    expect(foundationMigrate(['lazyLoad' => true, 'loadMode' => 'button']))->toBe(['loadMode' => 'button']);

    // Comments 3 never had lazyLoad, so an upgraded site gets the new default and nothing is written.
    expect(foundationMigrate(['pluginName' => 'Comments']))->toBe(['pluginName' => 'Comments']);
});

it('leaves loadMode unwritten when config/comments.php still sets lazyLoad, so the file keeps winning', function() {
    expect(foundationMigrate(['lazyLoad' => false], '4.0.4', ['lazyLoad' => true]))->toBe([]);
});

it('lets a config file’s lazyLoad win over a saved loadMode, and logs that it’s deprecated', function() {
    $deprecated = static fn(): array => array_filter(
        Craft::$app->getDeprecator()->getRequestLogs(),
        static fn($log): bool => $log->key === 'comments.lazyLoad',
    );

    // Without a file, a saved loadMode wins over a stray saved lazyLoad, and nothing is deprecated.
    expect((new Settings(['loadMode' => 'eager', 'lazyLoad' => true]))->loadMode)->toBe('eager')
        ->and($deprecated())->toBe([]);

    // Craft hands the model the saved settings with the file's values over them.
    foundationWithConfigFile(['lazyLoad' => true], static function() use ($deprecated): void {
        expect((new Settings(['loadMode' => 'eager', 'lazyLoad' => true]))->loadMode)->toBe('visible')
            ->and($deprecated())->not->toBe([]);
    });

    // A file that sets loadMode as well has said what it wants.
    foundationWithConfigFile(['lazyLoad' => true, 'loadMode' => 'button'], static function(): void {
        expect((new Settings(['loadMode' => 'button', 'lazyLoad' => true]))->loadMode)->toBe('button');
    });
});

it('leaves project config to the YAML once another environment has run the migration', function() {
    expect(foundationMigrate(['lazyLoad' => false], '4.0.5'))->toBe(['lazyLoad' => false]);
    expect(version_compare(Comments::$plugin->schemaVersion, '4.0.5', '>='))->toBeTrue();
});

it('tells a site upgrading from Comments 3 that comments now load as visitors scroll near them', function() {
    $db = Craft::$app->getDb();
    $created = !$db->tableExists(V3Schema::LOG);

    if ($created) {
        $db->createCommand()->createTable(V3Schema::LOG, ['code' => 'string(191) PRIMARY KEY', 'severity' => 'string(16)', 'count' => 'integer', 'samples' => 'text'])->execute();
    }

    $record = static function() use ($db): array {
        $db->createCommand()->delete(V3Schema::LOG)->execute();
        $log = new V3UpgradeLog($db);
        V3SettingsReview::record($log);
        $log->flush();

        return array_column(V3UpgradeLog::issues($db), 'label', 'code');
    };

    try {
        $issues = $record();

        expect($issues)->toHaveKey(V3SettingsReview::LOAD_MODE_NOTICE)
            ->and($issues[V3SettingsReview::LOAD_MODE_NOTICE])->toContain('scrolls near them')->toContain('Load Comments');

        // A site that already chose when comments load doesn't need telling.
        expect(foundationWithConfigFile(['loadMode' => 'eager'], $record))->not->toHaveKey(V3SettingsReview::LOAD_MODE_NOTICE);
    } finally {
        $db->createCommand()->delete(V3Schema::LOG)->execute();

        if ($created) {
            $db->createCommand()->dropTable(V3Schema::LOG)->execute();
        }
    }
});

it('offers the load modes and the CSS Layer setting on the settings page', function() {
    $template = file_get_contents(foundationPath('src/templates/settings/_panes/css-javascript.html'));

    expect($template)->toContain("name: 'loadMode'")
        ->toContain('craft.comments.loadModeOptions')
        ->toContain("name: 'useCssLayers'")
        ->toContain("configWarning('lazyLoad', 'comments')")
        ->not->toContain("name: 'lazyLoad'")
        ->not->toContain('Load Comments When Reached')
        ->and((new CommentsVariable())->getLoadModeOptions())->toBe(LoadMode::options());
});

it('warns on the settings page when config/comments.php sets loadMode or the old lazyLoad', function(array $file, ?string $setting) {
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode($view::TEMPLATE_MODE_CP);

    try {
        $html = foundationWithConfigFile($file, static fn() => $view->renderTemplate('comments/settings/_panes/css-javascript', [
            'settings' => Comments::$plugin->getSettings(),
        ]));
    } finally {
        $view->setTemplateMode($mode);
    }

    // The field's warning is Markdown, so the setting's name arrives in a `<code>` element.
    if ($setting) {
        expect($html)->toContain('overridden by the <code>' . $setting . '</code> setting');
    } else {
        expect($html)->not->toContain('overridden by the');
    }
})->with([
    'no file' => [[], null],
    'loadMode' => [['loadMode' => 'button'], 'loadMode'],
    'lazyLoad' => [['lazyLoad' => true], 'lazyLoad'],
]);

// CSS Layer
// =========================================================================

it('loads the stylesheet wrapped in a cascade layer when CSS Layer is on', function() {
    $settings = Comments::$plugin->getSettings();

    $settings->useCssLayers = false;
    expect((new CommentsStyleAsset())->css)->toBe(['css/comments.css']);

    $settings->useCssLayers = true;
    expect((new CommentsStyleAsset())->css)->toBe(['css/comments.layered.css']);

    foreach (['comments', 'comments-base'] as $name) {
        $plain = trim(file_get_contents(foundationPath("src/web/assets/frontend/dist/css/{$name}.css")));
        $layered = trim(file_get_contents(foundationPath("src/web/assets/frontend/dist/css/{$name}.layered.css")));

        // `@import` and `@charset` aren't allowed inside a layer block, so the wrap only works without them.
        expect($layered)->toBe('@layer comments{' . $plain . '}')
            ->not->toContain('@import')
            ->not->toContain('@charset');
    }
});

// render() and the mount point
// =========================================================================

it('renders from the element or its ID, with the element’s own site, and nothing for no element', function() {
    $entry = CommentsFixtureFactory::entry('Foundation render owner');
    foundationComment($entry, 'Drawn either way');
    $comments = Comments::$plugin->getComments();

    $fromElement = (string)$comments->render($entry, ['loadMode' => 'eager']);
    $fromId = (string)$comments->render((int)$entry->id, ['loadMode' => 'eager']);

    expect($fromElement)->toContain('data-comments-owner-id="' . $entry->id . '"')
        ->toContain('id="comments-' . $entry->id . '-' . $entry->siteId . '"')
        ->and($fromId)->toBe($fromElement)
        ->and((string)$comments->render(null))->toBe('')
        ->and((string)$comments->render(0))->toBe('')
        ->and((new ReflectionMethod(CommentsVariable::class, 'render'))->getNumberOfParameters())->toBe(2)
        ->and((new ReflectionMethod($comments, 'render'))->getNumberOfParameters())->toBe(2);
});

it('takes the element’s own site, lets siteId override it, and treats an ID as the current site', function() {
    ['primary' => $primary, 'secondary' => $secondary] = MultiSiteFixtureFactory::sameOwnerDifferentSites('Foundation multi-site owner');
    $comments = Comments::$plugin->getComments();
    $root = static fn($entry): string => 'id="comments-' . $entry->id . '-' . $entry->siteId . '"';

    expect((string)$comments->render($secondary, ['loadMode' => 'eager']))->toContain($root($secondary))
        ->and((string)$comments->render((int)$secondary->id, ['loadMode' => 'eager']))->toContain($root($primary))
        ->and((string)$comments->render($secondary, ['loadMode' => 'eager', 'siteId' => $primary->siteId]))->toContain($root($primary))
        ->and((string)$comments->render((int)$primary->id, ['loadMode' => 'eager', 'siteId' => $secondary->siteId]))->toContain($root($secondary));
});

it('ignores options that aren’t an array, and Comments 3’s third argument', function() {
    $entry = CommentsFixtureFactory::entry('Foundation loose arguments owner');
    foundationComment($entry, 'Still drawn');
    Comments::$plugin->getSettings()->loadMode = 'eager';
    $comments = Comments::$plugin->getComments();
    $variable = new CommentsVariable();

    $mount = 'data-comments-owner-id="' . $entry->id . '"';

    expect((string)$comments->render($entry, null))->toContain($mount)
        ->and((string)$comments->render($entry, 'oldest'))->toContain($mount)->toContain('&quot;sort&quot;:&quot;newest&quot;')
        ->and((string)$variable->render((int)$entry->id, null))->toContain($mount)
        ->and((string)$variable->render((int)$entry->id, ['limit' => 5], ['jsSetting' => true]))->toContain($mount)->toContain('&quot;limit&quot;:5');
});

it('shows the comments of the entry a draft belongs to', function() {
    $entry = CommentsFixtureFactory::entry('Foundation draft owner');
    foundationComment($entry, 'On the canonical entry');
    $draft = Craft::$app->getDrafts()->createDraft($entry, (int)Craft::$app->getUser()->getIdentity()->id);

    expect($draft->id)->not->toBe($entry->id)
        ->and((string)Comments::$plugin->getComments()->render($draft, ['loadMode' => 'eager']))
        ->toContain('data-comments-owner-id="' . $entry->id . '"')
        ->toContain('>1 comment</h2>')
        ->toContain('id="comments-' . $entry->id . '-' . $entry->siteId . '"');
});

it('describes the thread for the renderer on its root, with the API version and load mode', function() {
    $entry = CommentsFixtureFactory::entry('Foundation root owner');
    foundationComment($entry, 'On the root');

    $html = (string)Comments::$plugin->getComments()->render($entry, ['loadMode' => 'button']);

    expect($html)->toMatch('/<section[^>]*data-comments-root[^>]*data-comments-api-version="1"[^>]*data-comments-load-mode="button"/s')
        ->not->toContain('data-comments-contract')
        ->not->toContain('data-comments-lazy=');
});

it('builds the mount point the renderer will draw into', function() {
    $entry = CommentsFixtureFactory::entry('Foundation mount owner');

    foreach (range(1, 5) as $index) {
        foundationComment($entry, "Comment {$index}");
    }

    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildMountPointView((int)$entry->id, (int)$entry->siteId);
    $html = (string)$frontend->renderMountPoint($view, LoadMode::Button, [
        'themeConfig' => ['primaryButton' => ['resetClass' => true, 'attributes' => ['class' => 'btn "primary"']]],
    ]);
    $id = 'comments-' . $entry->id . '-' . $entry->siteId;

    expect($html)->toStartWith('<section id="' . $id . '" class="comments comments--reserve-3" aria-labelledby="' . $id . '-heading" data-comments-root')
        ->toContain('data-comments-api-version="1"')
        ->toContain('data-comments-endpoint="' . craft\helpers\UrlHelper::actionUrl('comments/api') . '"')
        ->toContain('data-comments-owner-id="' . $entry->id . '"')
        ->toContain('data-comments-site-id="' . $entry->siteId . '"')
        ->toContain('data-comments-load-mode="button"')
        ->toContain('data-comments-theme="light"')
        ->toContain('<h2 id="' . $id . '-heading" class="comments__heading">5 comments</h2>')
        ->toContain('<noscript><p class="comments__noscript">Turn on JavaScript to read and post comments.</p></noscript>')
        ->not->toContain('style=')
        ->not->toContain('<form')
        ->not->toContain('Comment 1');

    // The config is only data, escaped into its attribute.
    preg_match('/data-comments-config="([^"]*)"/', $html, $config);

    expect(json_decode(html_entity_decode($config[1] ?? ''), true))->toBe([
        'sort' => 'newest',
        'limit' => 20,
        'themeConfig' => ['primaryButton' => ['resetClass' => true, 'attributes' => ['class' => 'btn "primary"']]],
    ]);
});

it('reserves space for the thread in a few steps, never with an inline style', function() {
    expect(array_map(Frontend::reserveBucket(...), [0, 1, 2, 3, 4, 6, 7, 42, 5000]))->toBe([0, 1, 2, 2, 3, 3, 4, 4, 4]);

    $entry = CommentsFixtureFactory::entry('Foundation single mount owner');
    foundationComment($entry, 'Just one');
    $frontend = Comments::$plugin->getFrontend();
    $view = $frontend->buildMountPointView((int)$entry->id, (int)$entry->siteId);

    expect((string)$frontend->renderMountPoint($view, LoadMode::Visible))->toContain('comments--reserve-1')
        ->toContain('>1 comment</h2>')
        ->toContain('data-comments-config="{&quot;sort&quot;:&quot;newest&quot;,&quot;limit&quot;:20}"');
});

it('resolves render()’s load mode from the option, then the setting', function() {
    $frontend = Comments::$plugin->getFrontend();
    Comments::$plugin->getSettings()->loadMode = 'eager';

    expect($frontend->resolveLoadMode('button'))->toBe(LoadMode::Button)
        ->and($frontend->resolveLoadMode(null))->toBe(LoadMode::Eager)
        ->and($frontend->resolveLoadMode('soon'))->toBe(LoadMode::Eager)
        ->and($frontend->resolveLoadMode(['visible']))->toBe(LoadMode::Eager);
});

// Names
// =========================================================================

it('names the API version rather than a contract', function() {
    expect(Frontend::API_VERSION)->toBe(1)
        ->and(defined(Frontend::class . '::CONTRACT_VERSION'))->toBeFalse();

    foreach (['FrontendConversationView', 'FrontendCommentView'] as $view) {
        expect(property_exists('verbb\\comments\\models\\' . $view, 'contractVersion'))->toBeFalse();
    }
});

/**
 * Every text file the terminology rule covers: the plugin's source (but not the control panel's built
 * bundles, which hold third-party code), its docs, the README and the marketing copy.
 */
function foundationTerminologyPaths(): array
{
    $root = foundationPath('');
    $paths = ['README.md'];

    foreach (['src', 'docs', 'marketing'] as $folder) {
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root . $folder, FilesystemIterator::SKIP_DOTS),
            static fn(SplFileInfo $file): bool => !in_array($file->getFilename(), ['node_modules', 'cache', '.vitepress'], true)
                && !str_ends_with(str_replace('\\', '/', $file->getPathname()), 'src/web/assets/cp/dist'),
        ));

        foreach ($files as $file) {
            if (in_array($file->getExtension(), ['php', 'ts', 'js', 'css', 'twig', 'html', 'md', 'json'], true)) {
                $paths[] = substr($file->getPathname(), strlen($root));
            }
        }
    }

    return $paths;
}

/** Each use of either word in `$text`, apart from the ones that aren't about the front end. */
function foundationTerminologyOffences(string $text): array
{
    // Server-side uses the plan leaves alone: PHP's exception, Craft's storage folder, the test harness
    // folder, and the "runtime config" of a resolved conversation.
    $allowed = '/RuntimeException|storage\/runtime|tests\/runtime|resolved runtime config|Runtime code only uses/i';
    $offences = [];

    foreach (preg_split('/\R/', $text) as $line) {
        $line = preg_replace($allowed, '', $line);

        // Case-sensitive, so a camelCase part such as `CommentsRuntime` or `commentsContract` is caught.
        if (preg_match_all('/(?:runtime|Runtime|RUNTIME)|(?:contract|Contract|CONTRACT)/', $line, $matches)) {
            array_push($offences, ...$matches[0]);
        }
    }

    return $offences;
}

it('never calls front-end code a runtime or a contract', function() {
    $offenders = [];

    foreach (foundationTerminologyPaths() as $path) {
        if ($found = foundationTerminologyOffences(file_get_contents(foundationPath($path)))) {
            $offenders[$path] = array_values(array_unique($found));
        }
    }

    expect(foundationTerminologyPaths())->toContain('src/web/assets/frontend/src/js/comments.ts')
        ->toContain('src/web/assets/frontend/dist/js/comments.js')
        ->toContain('docs/template-guides/rendering-comments.md')
        ->toContain('marketing/features.md')
        ->not->toContain('src/web/assets/cp/dist/commenters.js')
        ->and($offenders)->toBe([]);
});

it('catches the old names inside identifiers, and leaves the server-side uses alone', function() {
    expect(foundationTerminologyOffences('export interface CommentsRuntime {}'))->toBe(['Runtime'])
        ->and(foundationTerminologyOffences('function createRuntime(); root.dataset.commentsContract'))->toBe(['Runtime', 'Contract'])
        ->and(foundationTerminologyOffences('const CONTRACT_VERSION = 1; browserRuntime'))->toBe(['CONTRACT', 'Runtime'])
        ->and(foundationTerminologyOffences('throw new RuntimeException(); @storage/runtime; tests/runtime/bootstrap.php'))->toBe([])
        ->and(foundationTerminologyOffences(' * Fully resolved runtime config for a Conversation owner'))->toBe([]);
});
