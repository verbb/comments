<?php

declare(strict_types=1);

use craft\db\Query;
use craft\db\Table;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\HttpClient;
use verbb\comments\elements\Comment;

/**
 * The Comment Fields pane posts the Comment field layout without `pluginHandle`. A field whose handle
 * Comments reserves, or a handle used twice, is refused there with an error, and nothing changes.
 */

const LAYOUT_HANDLES_PASSWORD = 'Comments-Layout-Handles-1!';

function layoutHandlesBrowser(): HttpClient
{
    $user = CommentsFixtureFactory::user('layoutHandlesAdmin');
    $user->admin = true;
    $user->newPassword = LAYOUT_HANDLES_PASSWORD;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);

    // Craft 5.12 rate-limits sign-ins per address.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();
    $browser->login((string)$user->username, LAYOUT_HANDLES_PASSWORD);

    return $browser;
}

/** Each request saves project config in another process, so this one loads it again before writing. */
function layoutHandlesRefresh(): void
{
    Craft::$app->getIsInstalled(true);
    Craft::$app->getProjectConfig()->reset();
}

function layoutHandlesField(string $handle): PlainText
{
    layoutHandlesRefresh();
    $field = Craft::$app->getFields()->getFieldByHandle($handle);

    if (!$field) {
        $field = new PlainText(['name' => ucfirst($handle), 'handle' => $handle]);
        expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
        $GLOBALS['layoutHandlesCreated'][] = $field;
    }

    return $field;
}

/** The Comment layout's custom field handles as saved, read from the database rather than any cache. */
function layoutHandlesSaved(): array
{
    $config = Json::decode((string)(new Query())->select(['config'])->from(Table::FIELDLAYOUTS)
        ->where(['type' => Comment::class, 'dateDeleted' => null])->scalar()) ?? [];
    $handles = [];

    foreach ($config['tabs'] ?? [] as $tab) {
        foreach ($tab['elements'] ?? [] as $element) {
            if (($element['type'] ?? null) === CustomField::class) {
                $field = Craft::$app->getFields()->getFieldByUid($element['fieldUid']);
                $handles[] = $element['handle'] ?? $field?->handle;
            }
        }
    }

    return $handles;
}

/** The layout as the pane would post it: today's, with these fields added (UID => handle override). */
function layoutHandlesPost(HttpClient $browser, array $fields): array
{
    $config = Craft::$app->getFields()->getLayoutByType(Comment::class)->getConfig() ?? ['tabs' => [['name' => 'Content', 'elements' => []]]];

    foreach ($fields as [$field, $handle]) {
        $config['tabs'][0]['elements'][] = [
            'type' => CustomField::class,
            'fieldUid' => $field->uid,
            'handle' => $handle,
            'required' => false,
            'width' => 100,
            'uid' => StringHelper::UUID(),
        ];
    }

    // To the pane's own address with `action`, as the browser posts the form.
    return $browser->post('/admin/comments/settings/fields', [
        'action' => 'comments/settings/save-settings',
        'settings' => ['fieldLayout' => Json::encode($config)],
    ]);
}

/**
 * Post the layout with these fields added, read what's saved, and put the layout back through the same
 * pane if the post saved it.
 */
function layoutHandlesTry(HttpClient $browser, array $fields): array
{
    $original = Craft::$app->getFields()->getLayoutByType(Comment::class)->getConfig();
    $response = layoutHandlesPost($browser, $fields);
    $saved = layoutHandlesSaved();

    if ($response['status'] === 302) {
        $browser->post('/admin/comments/settings/fields', [
            'action' => 'comments/settings/save-settings',
            'settings' => ['fieldLayout' => Json::encode($original)],
        ]);
    }

    return [$response, $saved];
}

beforeAll(function() {
    Craft::$app->getProjectConfig()->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();

    // Fields are global, so the ones made here go again.
    layoutHandlesRefresh();
    foreach ($GLOBALS['layoutHandlesCreated'] ?? [] as $field) {
        Craft::$app->getFields()->deleteField($field);
    }
});

it('refuses a field Comments reserves the handle of, from the Comment Fields pane', function() {
    $browser = layoutHandlesBrowser();
    $field = layoutHandlesField('email');
    $before = layoutHandlesSaved();
    [$response, $saved] = layoutHandlesTry($browser, [[$field, null]]);

    expect($response['status'])->not->toBe(302)
        ->and($response['body'])->toContain('is a reserved word')
        ->and($saved)->toBe($before);
});

it('refuses a handle used twice, from the Comment Fields pane', function() {
    $browser = layoutHandlesBrowser();
    $field = layoutHandlesField('layoutHandlesNotes');
    $before = layoutHandlesSaved();
    [$response, $saved] = layoutHandlesTry($browser, [[$field, null], [$field, 'layoutHandlesNotes']]);

    expect($response['status'])->not->toBe(302)
        ->and($response['body'])->toContain('has already been taken')
        ->and($saved)->toBe($before);
});

it('still saves a layout with an ordinary field', function() {
    $browser = layoutHandlesBrowser();
    [$response, $saved] = layoutHandlesTry($browser, [[layoutHandlesField('layoutHandlesNotes'), null]]);

    expect($response['status'])->toBe(302, $response['body'])
        ->and($saved)->toContain('layoutHandlesNotes');
});
