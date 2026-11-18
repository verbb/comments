<?php

declare(strict_types=1);

use craft\elements\Asset;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\SpamSettingsStore;
use Tests\Support\HttpClient;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\services\FormFields;

/**
 * Uploads for Assets custom fields through `comments/api/upload` with a `field`, in real requests: the
 * field's kinds and limits and Comments' upload rules, a member's and a guest's own temporary uploads
 * under each tier, posting a comment with them, refusing anyone else's, Craft's own upload path closed
 * off, and garbage collection (the threat model's "Upload abuse" and "Collected custom field values
 * leaking" rows in plan section 5.5).
 */

const FFH_PASSWORD = 'Comments-FormFields-Http-1!';
const FFH_GUESTS = 'https://guests.form-fields.example.test';
const FFH_MEMBERS = 'https://members.form-fields.example.test';
const FFH_KEY = 'form-fields-test-member-token-key-0123456789abcdef';

function ffhUser(string $prefix): User
{
    $user = CommentsFixtureFactory::user($prefix);
    $user->newPassword = FFH_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    return $user;
}

function ffhBrowser(?User $user = null): HttpClient
{
    // Craft limits sign-ins and Comments limits uploads per address, and every browser here is one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();

    if ($user) {
        $browser->login((string)$user->username, FFH_PASSWORD);
    }

    return $browser;
}

function ffhCsrf(HttpClient $browser, array $headers = []): string
{
    $response = $browser->get('/actions/comments/api/session', [], $headers);
    expect($response['status'])->toBe(200, $response['body']);

    return (string)($response['data']['csrf']['value'] ?? '');
}

function ffhCode(array $response): ?string
{
    return $response['data']['errors'][0]['code'] ?? null;
}

function ffhPng(): string
{
    $image = imagecreatetruecolor(3, 2);
    ob_start();
    imagepng($image);

    return (string)ob_get_clean();
}

function ffhUpload(HttpClient $browser, string $name, string $contents, array $params, array $headers = [], ?string $csrf = null): array
{
    $multipart = [['name' => 'file', 'contents' => $contents, 'filename' => $name]];

    foreach ($params as $key => $value) {
        $multipart[] = ['name' => $key, 'contents' => (string)$value];
    }

    if ($csrf !== null) {
        $headers['X-CSRF-Token'] = $csrf;
    }

    return $browser->request('POST', '/actions/comments/api/upload', ['multipart' => $multipart, 'headers' => $headers]);
}

function ffhCreate(HttpClient $browser, $entry, string $text, array $fields, array $headers = [], ?string $csrf = null, bool $guest = false): array
{
    $body = [
        'operationId' => StringHelper::UUID(),
        'ownerId' => (int)$entry->id,
        'siteId' => (int)$entry->siteId,
        'content' => ['format' => 'plain', 'text' => $text],
        'fields' => $fields,
    ];

    if ($guest) {
        $body['guest'] = ['name' => 'Guest Uploader', 'email' => 'guest-' . bin2hex(random_bytes(3)) . '@example.test'];
    }

    return $browser->postJson('/actions/comments/api/create', $body, $headers, $csrf);
}

function ffhApplySettings(array $settings): void
{
    $projectConfig = Craft::$app->getProjectConfig();

    foreach ($settings as $name => $value) {
        $projectConfig->set('plugins.comments.settings.' . $name, $value);
    }

    $projectConfig->saveModifiedConfigData();
    SpamSettingsStore::apply($settings);
    Comments::$plugin->getSettings()->setAttributes($settings, false);
}

beforeAll(function() {
    putenv('COMMENTS_FFH_KEY=' . FFH_KEY);
    $projectConfig = Craft::$app->getProjectConfig();
    $projectConfig->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    $GLOBALS['ffhConfig'] = $projectConfig->get('plugins.comments.settings');
    $GLOBALS['ffhSpam'] = SpamSettingsStore::snapshot();
    $GLOBALS['ffhSettings'] = Comments::$plugin->getSettings()->getAttributes();
    $GLOBALS['ffhLayout'] = Craft::$app->getFields()->getLayoutByType(Comment::class)->getConfig();

    // A volume served from its own host, and an Assets field for images on the Comment layout.
    $handle = 'ffhUploads' . bin2hex(random_bytes(4));
    $path = sys_get_temp_dir() . '/' . $handle;
    $filesystem = new craft\fs\Local(['name' => $handle, 'handle' => $handle, 'path' => $path, 'hasUrls' => true, 'url' => 'https://uploads.example.test/' . $handle . '/']);
    Craft::$app->getFs()->saveFilesystem($filesystem);
    $volume = new craft\models\Volume(['name' => $handle, 'handle' => $handle, 'fsHandle' => $handle]);
    Craft::$app->getVolumes()->saveVolume($volume);
    $field = new Assets([
        'name' => 'Photos',
        'handle' => 'ffhPhotos' . bin2hex(random_bytes(3)),
        'sources' => '*',
        'defaultUploadLocationSource' => 'volume:' . $volume->uid,
        'restrictFiles' => true,
        'allowedKinds' => ['image'],
        'maxRelations' => 2,
    ]);
    Craft::$app->getFields()->saveField($field);
    $fields = Craft::$app->getFields();
    $layout = $fields->getLayoutByType(Comment::class);
    $tabs = $layout->getTabs();
    $tab = $tabs[0] ?? new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'sortOrder' => 1]);
    $tab->setElements([...$tab->getElements(), new CustomField($field)]);
    $layout->setTabs([$tab, ...array_slice($tabs, 1)]);
    $fields->saveLayout($layout, false);
    $element = null;

    foreach ($fields->getLayoutByType(Comment::class)->getCustomFieldElements() as $candidate) {
        if ($candidate->getField()->handle === $field->handle) {
            $element = $candidate;
        }
    }

    $GLOBALS['ffhVolume'] = [$filesystem, $volume, $path];
    $GLOBALS['ffhField'] = $field;
    $GLOBALS['ffhElement'] = $element;

    ffhApplySettings([
        'enableSpamChecks' => false,
        'requireModeration' => false,
        'allowGuest' => true,
        'guestRequireEmailName' => false,
        'guestShowEmailName' => true,
        'participantNotifications' => [],
        'staffNotifications' => [],
        'subscriptionTypes' => [],
        'attachmentMaxSize' => 1,
        'allowedOrigins' => [['origin' => FFH_GUESTS], ['origin' => FFH_MEMBERS, 'memberTokens' => true]],
        'memberTokenKey' => '$COMMENTS_FFH_KEY',
        'formFields' => [(string)$element->uid => ['mode' => 'collect-and-show', 'placement' => 'details', 'replies' => false]],
        'allowGuestFieldUploads' => true,
    ]);

    HttpClient::stopServer();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
    SpamSettingsStore::restore($GLOBALS['ffhSpam']);
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = $GLOBALS['ffhConfig'];
    $projectConfig->remove('plugins.comments.settings.formFields');
    $projectConfig->remove('plugins.comments.settings.allowedOrigins');
    $projectConfig->remove('plugins.comments.settings.memberTokenKey');
    $projectConfig->remove('plugins.comments.settings.allowGuestFieldUploads');

    if ($previous === null) {
        $projectConfig->remove('plugins.comments.settings');
    } else {
        $projectConfig->set('plugins.comments.settings', $previous);
    }

    $fields = Craft::$app->getFields();
    $layout = FieldLayout::createFromConfig($GLOBALS['ffhLayout']);
    $current = $fields->getLayoutByType(Comment::class);
    $layout->id = $current->id;
    $layout->uid = $current->uid;
    $layout->type = Comment::class;
    $fields->saveLayout($layout, false);
    $fields->deleteField($GLOBALS['ffhField']);
    [$filesystem, $volume, $path] = $GLOBALS['ffhVolume'];
    Craft::$app->getVolumes()->deleteVolume($volume);
    Craft::$app->getFs()->removeFilesystem($filesystem);
    craft\helpers\FileHelper::removeDirectory($path);
    $projectConfig->saveModifiedConfigData();
    Comments::$plugin->getSettings()->setAttributes($GLOBALS['ffhSettings'], false);
    Comments::$plugin->getSignIn()->reset();
    putenv('COMMENTS_FFH_KEY');
});

beforeEach(function() {
    Craft::$app->getCache()->flush();
    Comments::$plugin->getSignIn()->reset();
});

it('takes a member’s upload for an Assets field, posts it with their comment and shows it (upload abuse)', function() {
    $entry = CommentsFixtureFactory::entry('Form field upload owner');
    $member = ffhUser('ffhMember');
    $browser = ffhBrowser($member);
    $csrf = ffhCsrf($browser);
    $handle = $GLOBALS['ffhField']->handle;
    $params = ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId];

    $viewer = $browser->get('/actions/comments/api/viewer', ['ownerId' => $entry->id])['data']['editor']['fields']['comment'][0] ?? [];
    $upload = ffhUpload($browser, 'photo.png', ffhPng(), $params, [], $csrf);
    $id = (int)($upload['data']['file']['id'] ?? 0);
    $asset = Asset::find()->id($id)->status(null)->one();

    expect($viewer)->toMatchArray(['handle' => $handle, 'type' => 'file', 'multiple' => true, 'maxFiles' => 2, 'maxSize' => 1024 * 1024])
        ->and($viewer['accept'])->toContain('.png')->not->toContain('.pdf')->not->toContain('.svg')
        ->and($upload['status'])->toBe(200, $upload['body'])
        ->and($upload['data'])->toMatchArray(['field' => $handle])
        ->and($upload['data']['file'])->toMatchArray(['filename' => 'photo.png', 'kind' => 'image', 'width' => 3, 'height' => 2])
        ->and($asset->volumeId)->toBeNull()
        ->and((int)$asset->uploaderId)->toBe((int)$member->id);

    $created = ffhCreate($browser, $entry, 'With a photo', [$handle => [$id]], [], $csrf);
    $comment = Comment::find()->uid($created['data']['comment']['uid'] ?? '')->status(null)->one();
    $moved = Asset::find()->id($id)->status(null)->one();
    $thread = (new HttpClient())->get('/actions/comments/api/thread', ['ownerId' => $entry->id]);
    $shown = $thread['data']['comments'][0]['fields'][0] ?? [];

    expect($created['status'])->toBe(200, $created['body'])
        ->and(array_values(array_unique($comment->getFieldValue($handle)->ids())))->toBe([$id])
        ->and((int)$moved->volumeId)->toBe((int)$GLOBALS['ffhVolume'][1]->id)
        ->and($shown)->toMatchArray(['handle' => $handle, 'type' => 'file', 'text' => 'photo.png'])
        ->and($shown['value'][0])->toMatchArray(['filename' => 'photo.png', 'kind' => 'image', 'width' => 3, 'height' => 2])
        ->and($shown['value'][0]['url'])->toStartWith('https://uploads.example.test/')
        ->and($shown['value'][0])->not->toHaveKey('id');

    // Its author's edit form keeps the file by sending its ID back.
    $edit = $browser->get('/actions/comments/api/edit', ['uid' => $comment->uid]);

    expect($edit['data']['fields'][$handle][0])->toMatchArray(['id' => $id, 'filename' => 'photo.png']);
});

it('refuses uploads outside the field’s kinds, the size limit, its file limit and Comments’ upload rules', function() {
    $entry = CommentsFixtureFactory::entry('Form field upload refusals');
    $member = ffhUser('ffhRefused');
    $browser = ffhBrowser($member);
    $csrf = ffhCsrf($browser);
    $handle = $GLOBALS['ffhField']->handle;
    $params = ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId];

    foreach ([
        'a page' => ['page.html', '<!doctype html><html><body><script>alert(1)</script></body></html>', 'file_type_not_allowed'],
        'a drawing' => ['drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'file_type_not_allowed'],
        'a page passed off as an image' => ['photo.png', '<!doctype html><html><body>x</body></html>', 'file_type_not_allowed'],
        'a document, which the field doesn’t take' => ['notes.txt', 'Plain notes', 'file_type_not_allowed'],
        'too large' => ['big.png', ffhPng() . str_repeat("\0", 1024 * 1024), 'file_too_large'],
    ] as $label => [$name, $contents, $code]) {
        $response = ffhUpload($browser, $name, $contents, $params, [], $csrf);

        expect($response['status'])->toBe(422, $label . ' ' . $response['body'])
            ->and(ffhCode($response))->toBe($code, $label);
    }

    // A field that isn't on the form for a reply, or isn't a field at all.
    $root = ffhCreate($browser, $entry, 'Root', [], [], $csrf);
    $reply = ffhUpload($browser, 'photo.png', ffhPng(), [...$params, 'parentUid' => $root['data']['comment']['uid']], [], $csrf);
    $unknown = ffhUpload($browser, 'photo.png', ffhPng(), [...$params, 'field' => 'notAField'], [], $csrf);

    expect(ffhCode($reply))->toBe('validation_failed')
        ->and($reply['data']['errors'][0]['fieldErrors'])->toHaveKey('fields.' . $handle)
        ->and(ffhCode($unknown))->toBe('validation_failed');

    // More files than the field takes.
    $ids = array_map(static fn(int $i): int => (int)ffhUpload($browser, "photo$i.png", ffhPng(), $params, [], $csrf)['data']['file']['id'], [1, 2, 3]);
    $tooMany = ffhCreate($browser, $entry, 'Too many', [$handle => $ids], [], $csrf);

    expect(ffhCode($tooMany))->toBe('too_many_files')
        ->and($tooMany['status'])->toBe(422);
});

it('lets guests upload into their own folder and attach only their own uploads (upload abuse)', function() {
    $entry = CommentsFixtureFactory::entry('Form field guest uploads');
    $handle = $GLOBALS['ffhField']->handle;
    $params = ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId];

    // Tier 1, by cookie.
    $guest = ffhBrowser();
    $csrf = ffhCsrf($guest);
    $upload = ffhUpload($guest, 'mine.png', ffhPng(), $params, [], $csrf);
    $id = (int)($upload['data']['file']['id'] ?? 0);

    expect($upload['status'])->toBe(200, $upload['body'])
        ->and(Asset::find()->id($id)->status(null)->one()->uploaderId)->toBeNull();

    // Another guest, and a member, can't attach it.
    $other = ffhBrowser();
    $otherCsrf = ffhCsrf($other);
    $stolen = ffhCreate($other, $entry, 'Not mine', [$handle => [$id]], [], $otherCsrf, true);
    $member = ffhBrowser(ffhUser('ffhNotGuest'));
    $memberCsrf = ffhCsrf($member);
    $byMember = ffhCreate($member, $entry, 'Not mine either', [$handle => [$id]], [], $memberCsrf);

    expect(ffhCode($stolen))->toBe('validation_failed')
        ->and($stolen['data']['errors'][0]['message'])->toBe('Only files you upload here can be added.')
        ->and(ffhCode($byMember))->toBe('validation_failed');

    $posted = ffhCreate($guest, $entry, 'Mine', [$handle => [$id]], [], $csrf, true);

    expect($posted['status'])->toBe(200, $posted['body'])
        ->and(array_values(array_unique(Comment::find()->uid($posted['data']['comment']['uid'])->status(null)->one()->getFieldValue($handle)->ids())))->toBe([$id]);

    // Tier 3, by guest session token: none yet is `session_expired`, as for a write.
    $remote = ffhBrowser();
    $headers = ['Origin' => FFH_GUESTS, 'X-Comments-Profile' => 'cross-origin-public'];
    $withoutSession = ffhUpload($remote, 'remote.png', ffhPng(), $params, $headers);
    $session = (string)($remote->get('/actions/comments/api/session', [], $headers)['headers']['x-comments-session'][0] ?? '');
    $remoteUpload = ffhUpload($remote, 'remote.png', ffhPng(), $params, [...$headers, 'X-Comments-Session' => $session]);
    $session = (string)($remoteUpload['headers']['x-comments-session'][0] ?? $session);
    $remotePosted = ffhCreate($remote, $entry, 'From another site', [$handle => [(int)($remoteUpload['data']['file']['id'] ?? 0)]], [...$headers, 'X-Comments-Session' => $session], null, true);

    expect(ffhCode($withoutSession))->toBe('session_expired')
        ->and($remoteUpload['status'])->toBe(200, $remoteUpload['body'])
        ->and($remoteUpload['headers']['set-cookie'] ?? null)->toBeNull()
        ->and($remotePosted['status'])->toBe(200, $remotePosted['body']);
});

it('never lets Craft store a file posted alongside a comment instead of uploaded first (upload abuse)', function() {
    $entry = CommentsFixtureFactory::entry('Form field raw upload');
    $member = ffhUser('ffhRaw');
    $browser = ffhBrowser($member);
    $csrf = ffhCsrf($browser);
    $handle = $GLOBALS['ffhField']->handle;
    $before = (int)Asset::find()->volumeId($GLOBALS['ffhVolume'][1]->id)->status(null)->count();

    $response = $browser->request('POST', '/actions/comments/api/create', [
        'headers' => ['X-CSRF-Token' => $csrf],
        'multipart' => [
            ['name' => 'operationId', 'contents' => StringHelper::UUID()],
            ['name' => 'ownerId', 'contents' => (string)$entry->id],
            ['name' => 'content[format]', 'contents' => 'plain'],
            ['name' => 'content[text]', 'contents' => 'Sneaking a file'],
            ['name' => $handle, 'contents' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'filename' => 'drawing.svg'],
        ],
    ]);

    expect($response['status'])->toBe(422, $response['body'])
        ->and($response['data']['errors'][0]['message'] ?? null)->toBe('Only files you upload here can be added.')
        ->and((int)Asset::find()->volumeId($GLOBALS['ffhVolume'][1]->id)->status(null)->count())->toBe($before)
        ->and(Comment::find()->ownerId($entry->id)->status(null)->exists())->toBeFalse();
});

it('removes uploads that were never posted, and forgets the ones that were (garbage collection)', function() {
    $entry = CommentsFixtureFactory::entry('Form field clean-up');
    $member = ffhUser('ffhCleanup');
    $browser = ffhBrowser($member);
    $csrf = ffhCsrf($browser);
    $handle = $GLOBALS['ffhField']->handle;
    $params = ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId];
    $abandoned = (int)ffhUpload($browser, 'abandoned.png', ffhPng(), $params, [], $csrf)['data']['file']['id'];
    $posted = (int)ffhUpload($browser, 'posted.png', ffhPng(), $params, [], $csrf)['data']['file']['id'];
    ffhCreate($browser, $entry, 'Posted one', [$handle => [$posted]], [], $csrf);

    // A day and more since the abandoned one was uploaded.
    Db::update(FormFields::UPLOADS_TABLE, ['dateCreated' => Db::prepareDateForDb(new DateTime('-2 days'))], ['assetId' => $abandoned]);
    Craft::$app->getElements()->invalidateAllCaches();
    Comments::$plugin->getFormFields()->deleteExpiredUploads();

    expect(Asset::find()->id($abandoned)->status(null)->trashed(null)->exists())->toBeFalse()
        ->and(Asset::find()->id($posted)->status(null)->exists())->toBeTrue()
        ->and((new craft\db\Query())->from(FormFields::UPLOADS_TABLE)->where(['assetId' => [$abandoned, $posted]])->count())->toEqual(0);

    // Craft's own garbage collection runs it.
    $waiting = (int)ffhUpload($browser, 'waiting.png', ffhPng(), $params, [], $csrf)['data']['file']['id'];
    Db::update(FormFields::UPLOADS_TABLE, ['dateCreated' => Db::prepareDateForDb(new DateTime('-2 days'))], ['assetId' => $waiting]);
    Craft::$app->getGc()->run(true);

    expect(Asset::find()->id($waiting)->status(null)->trashed(null)->exists())->toBeFalse();
});

it('takes a file on the site’s own form only through the upload rules (H1, Twig form)', function() {
    $entry = CommentsFixtureFactory::entry('Form field site form upload');
    $member = ffhUser('ffhSiteForm');
    $browser = ffhBrowser($member);
    $csrf = ffhCsrf($browser);
    $handle = $GLOBALS['ffhField']->handle;
    $volumeId = (int)$GLOBALS['ffhVolume'][1]->id;
    $before = (int)Asset::find()->volumeId($volumeId)->status(null)->count();
    $form = static fn(string $name, string $contents) => $browser->request('POST', '/actions/comments/comments/save', [
        'headers' => ['X-CSRF-Token' => $csrf],
        'multipart' => [
            ['name' => 'elementId', 'contents' => (string)$entry->id],
            ['name' => 'siteId', 'contents' => (string)$entry->siteId],
            ['name' => 'fields[comment]', 'contents' => 'Through the form'],
            ['name' => "fields[$handle][]", 'contents' => $contents, 'filename' => $name],
        ],
    ]);

    $svg = $form('drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $png = $form('photo.png', ffhPng());

    expect($svg['data']['success'] ?? null)->toBeFalse()
        ->and($svg['body'])->toContain('This kind of file can’t be uploaded here.')
        ->and($png['status'])->toBe(200, $png['body'])
        ->and(Asset::find()->volumeId($volumeId)->filename('drawing*')->status(null)->exists())->toBeFalse()
        ->and((int)Asset::find()->volumeId($volumeId)->status(null)->count())->toBeGreaterThan($before)
        ->and(Comment::find()->ownerId($entry->id)->status(null)->count())->toEqual(1);
});

it('limits guests’ uploads by address across guest identities, and only with Guests Can Upload Files on (M3)', function() {
    $entry = CommentsFixtureFactory::entry('Form field guest limits');
    $handle = $GLOBALS['ffhField']->handle;
    $params = ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId];
    Craft::$app->getCache()->flush();
    $responses = [];

    // Every new browser is a new guest identity, from the same address.
    for ($i = 0; $i <= FormFields::MAX_GUEST_UPLOADS_PER_ADDRESS; $i++) {
        $guest = new HttpClient();
        $responses[] = ffhUpload($guest, "guest$i.png", ffhPng(), $params, [], ffhCsrf($guest));
    }

    $last = array_pop($responses);

    expect(array_unique(array_column($responses, 'status')))->toBe([200])
        ->and($last['status'])->toBe(429, $last['body'])
        ->and(ffhCode($last))->toBe('rate_limited');

    // A member isn't counted against the guests' address.
    $member = ffhBrowser(ffhUser('ffhNotLimited'));
    $memberUpload = ffhUpload($member, 'member.png', ffhPng(), $params, [], ffhCsrf($member));

    expect($memberUpload['status'])->toBe(200, $memberUpload['body']);

    // With the setting off, guests aren't offered the field at all.
    ffhApplySettings(['allowGuestFieldUploads' => false]);

    try {
        $guest = ffhBrowser();
        $off = ffhUpload($guest, 'off.png', ffhPng(), $params, [], ffhCsrf($guest));
        $viewer = $guest->get('/actions/comments/api/viewer', ['ownerId' => $entry->id]);

        expect(ffhCode($off))->toBe('validation_failed')
            ->and(array_column($viewer['data']['editor']['fields']['comment'], 'handle'))->toBe([]);
    } finally {
        ffhApplySettings(['allowGuestFieldUploads' => true]);
    }
});

it('checks a guest’s upload like their comment, and removes it after two hours with the identity it started (M3)', function() {
    $entry = CommentsFixtureFactory::entry('Form field guest protection');
    $handle = $GLOBALS['ffhField']->handle;
    $params = ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId];

    // Spam protection on, with the minimum form time, and no preparation sent.
    ffhApplySettings(['enableSpamChecks' => true, 'honeypotEnabled' => true, 'minimumSubmitTime' => 3]);

    try {
        $guest = ffhBrowser();
        $identities = (int)(new craft\db\Query())->from('{{%comments_identities}}')->count();
        $refused = ffhUpload($guest, 'unprotected.png', ffhPng(), $params, [], ffhCsrf($guest));

        expect($refused['status'])->toBeGreaterThanOrEqual(400, $refused['body'])
            ->and($refused['data']['errors'][0]['category'] ?? null)->toBe('protection')
            // A refused upload starts no guest identity.
            ->and((int)(new craft\db\Query())->from('{{%comments_identities}}')->count())->toBe($identities);
    } finally {
        ffhApplySettings(['enableSpamChecks' => false]);
    }

    $guest = ffhBrowser();
    $guestId = (int)ffhUpload($guest, 'guest.png', ffhPng(), $params, [], ffhCsrf($guest))['data']['file']['id'];
    $guestIdentity = (int)(new craft\db\Query())->select(['identityId'])->from(FormFields::UPLOADS_TABLE)->where(['assetId' => $guestId])->scalar();
    $member = ffhBrowser(ffhUser('ffhLifetime'));
    $memberId = (int)ffhUpload($member, 'member.png', ffhPng(), $params, [], ffhCsrf($member))['data']['file']['id'];
    Db::update(FormFields::UPLOADS_TABLE, ['dateCreated' => Db::prepareDateForDb(new DateTime('-3 hours'))], ['assetId' => [$guestId, $memberId]]);
    Craft::$app->getElements()->invalidateAllCaches();
    Comments::$plugin->getFormFields()->deleteExpiredUploads();

    expect(Asset::find()->id($guestId)->status(null)->trashed(null)->exists())->toBeFalse()
        ->and(Asset::find()->id($memberId)->status(null)->exists())->toBeTrue()
        ->and((new craft\db\Query())->from('{{%comments_identities}}')->where(['id' => $guestIdentity])->exists())->toBeFalse();
});

it('takes a guest’s upload with the form’s own preparation under the default spam protection, without counting it against the rate limit', function() {
    $entry = CommentsFixtureFactory::entry('Form field guest default protection');
    $handle = $GLOBALS['ffhField']->handle;
    $params = ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId];
    $settings = Comments::$plugin->getSettings();
    $before = ['enableSpamChecks' => $settings->enableSpamChecks, 'honeypotEnabled' => $settings->honeypotEnabled, 'minimumSubmitTime' => $settings->minimumSubmitTime, 'rateLimitEnabled' => $settings->rateLimitEnabled, 'rateLimitCount' => $settings->rateLimitCount, 'rateLimitWindow' => $settings->rateLimitWindow, 'spamClassifier' => $settings->spamClassifier];

    // Rate limiting is on by default, and it's part of the plan a preparation is issued for. Uploads
    // used to be checked against a plan without it, so every guest upload was refused as a mismatch.
    // One comment a minute here, so an upload that counted against it would leave none for the comment.
    ffhApplySettings(['enableSpamChecks' => true, 'honeypotEnabled' => true, 'minimumSubmitTime' => 0, 'rateLimitEnabled' => true, 'rateLimitCount' => 1, 'rateLimitWindow' => 60, 'spamClassifier' => '']);

    try {
        $guest = ffhBrowser();
        $csrf = ffhCsrf($guest);
        $evidence = function() use ($guest, $entry): array {
            $manifest = $guest->get('/actions/comments/api/protection', ['ownerId' => $entry->id])['data']['protection']['manifest'];

            return ['version' => 1, 'preparation' => $manifest['preparation'], 'honeypot' => [$manifest['honeypot'] => '']];
        };

        $first = ffhUpload($guest, 'first.png', ffhPng(), [...$params, 'protection' => json_encode($evidence())], [], $csrf);
        $second = ffhUpload($guest, 'second.png', ffhPng(), [...$params, 'protection' => json_encode($evidence())], [], $csrf);
        $ids = [(int)($first['data']['file']['id'] ?? 0), (int)($second['data']['file']['id'] ?? 0)];
        $posted = $guest->postJson('/actions/comments/api/create', [
            'operationId' => StringHelper::UUID(),
            'ownerId' => (int)$entry->id,
            'siteId' => (int)$entry->siteId,
            'content' => ['format' => 'plain', 'text' => 'With two photos'],
            'fields' => [$handle => $ids],
            'guest' => ['name' => 'Photo Guest', 'email' => 'photo-guest@example.test'],
            'protection' => $evidence(),
        ], [], $csrf);

        expect($first['status'])->toBe(200, $first['body'])
            ->and($second['status'])->toBe(200, $second['body'])
            ->and($posted['status'])->toBe(200, $posted['body']);

        // The preparation is still checked: one already used, or none at all, is refused.
        $used = $evidence();
        ffhUpload($guest, 'third.png', ffhPng(), [...$params, 'protection' => json_encode($used)], [], $csrf);
        $replayed = ffhUpload($guest, 'fourth.png', ffhPng(), [...$params, 'protection' => json_encode($used)], [], $csrf);
        $unprepared = ffhUpload($guest, 'fifth.png', ffhPng(), $params, [], $csrf);

        expect(ffhCode($replayed))->toBe('protection_expired')
            ->and($unprepared['data']['errors'][0]['category'] ?? null)->toBe('protection');
    } finally {
        ffhApplySettings($before);
    }
});

it('takes field uploads from a member on another site through a member token (tier 3)', function() {
    $entry = CommentsFixtureFactory::entry('Form field tier 3 upload');
    $member = ffhUser('ffhTierThree');
    $handle = $GLOBALS['ffhField']->handle;
    $raw = json_encode(['user' => ['uid' => $member->uid], 'origin' => FFH_MEMBERS], JSON_UNESCAPED_SLASHES);
    $mint = (new HttpClient())->request('POST', '/actions/comments/api/member-token', [
        'body' => $raw,
        'headers' => ['Content-Type' => 'application/json', verbb\comments\services\MemberTokens::SIGNATURE_HEADER => verbb\comments\services\MemberTokens::signatureHeader(FFH_KEY, 'POST', 'comments/api/member-token', $raw)],
    ]);
    $headers = ['Origin' => FFH_MEMBERS, 'X-Comments-Profile' => 'cross-origin-member', 'Authorization' => 'Bearer ' . ($mint['data']['token'] ?? '')];
    $browser = ffhBrowser();
    $upload = ffhUpload($browser, 'remote.png', ffhPng(), ['field' => $handle, 'ownerId' => $entry->id, 'siteId' => $entry->siteId], $headers);
    $posted = ffhCreate($browser, $entry, 'From another site', [$handle => [(int)($upload['data']['file']['id'] ?? 0)]], $headers);

    expect($mint['status'])->toBe(200, $mint['body'])
        ->and($upload['status'])->toBe(200, $upload['body'])
        ->and($upload['headers']['set-cookie'] ?? null)->toBeNull()
        ->and((int)Asset::find()->id($upload['data']['file']['id'])->status(null)->one()->uploaderId)->toBe((int)$member->id)
        ->and($posted['status'])->toBe(200, $posted['body']);
});
