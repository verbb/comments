<?php

declare(strict_types=1);

use craft\db\Query;
use craft\elements\User;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use Tests\Support\HttpClient;
use Tests\Support\SpamSettingsStore;
use verbb\comments\services\SpamSettings;

/**
 * People who manage spam settings without being admins can't name an environment variable or alias in
 * the Spam Protection page's key fields, since a site key resolved from one is sent to every visitor.
 * They can still type literal keys and keep a reference an admin set.
 */

const COMMENTS_ENV_REF_PASSWORD = 'Comments-EnvRef-Test-1!';
const COMMENTS_ENV_REF_PATH = '/admin/comments/spam-protection';

function envRefBrowser(bool $admin): HttpClient
{
    $user = CommentsFixtureFactory::user($admin ? 'envRefAdmin' : 'envRefManager');
    $user->newPassword = COMMENTS_ENV_REF_PASSWORD;
    $user->admin = $admin;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);
    Craft::$app->getUserPermissions()->saveUserPermissions((int)$user->id, ['accessCp', 'accessPlugin-comments', 'comments-manageSpamSettings']);

    // Craft 5.12 rate-limits sign-ins per address, and every browser here signs in from the same one.
    Craft::$app->getCache()->flush();
    $browser = new HttpClient();
    $browser->login((string)$user->username, COMMENTS_ENV_REF_PASSWORD);

    return $browser;
}

function envRefSave(HttpClient $browser, array $settings): array
{
    return $browser->post(COMMENTS_ENV_REF_PATH, ['action' => 'comments/spam-protection/save', 'settings' => $settings]);
}

// The field errors on a re-rendered page, to say why a save failed without the whole page.
function envRefErrors(array $response): string
{
    preg_match_all('/<ul[^>]*class="errors"[^>]*>(.*?)<\/ul>/s', $response['body'], $matches);

    return $response['status'] . ' ' . trim(preg_replace('/\s+/', ' ', strip_tags(implode(' | ', $matches[1]))));
}

function envRefStored(): array
{
    return json_decode((string)(new Query())->select(['settings'])->from(SpamSettings::TABLE)->orderBy(['id' => SORT_ASC])->scalar(), true) ?: [];
}

beforeAll(function() {
    // In Solo every user is an admin, which would let the refusals below pass or fail for the wrong reason.
    Craft::$app->getProjectConfig()->set('system.edition', craft\enums\CmsEdition::Pro->handle());
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    HttpClient::startServer();
});

afterAll(function() {
    HttpClient::stopServer();
});

beforeEach(function() {
    $this->snapshot = SpamSettingsStore::snapshot();
    SpamSettingsStore::apply(['challengeProvider' => '', 'turnstileKey' => 'literal-site', 'turnstileSecret' => 'literal-secret', 'recaptchaKey' => null]);
});

afterEach(function() {
    SpamSettingsStore::restore($this->snapshot);
});

it('refuses a new environment variable or alias in a key field from someone who isn’t an admin', function() {
    $manager = envRefBrowser(false);

    foreach ([
        ['turnstileKey' => '$CRAFT_SECURITY_KEY'],
        ['turnstileKey' => '${CRAFT_SECURITY_KEY}'],
        ['turnstileKey' => ' $CRAFT_SECURITY_KEY'],
        ['turnstileKey' => '@webroot'],
        // Craft resolves a reference inside a value too, so these would publish the variable with a prefix.
        ['turnstileKey' => 'x${CRAFT_SECURITY_KEY}'],
        ['turnstileKey' => '0x4AAA/$CRAFT_SECURITY_KEY'],
        ['turnstileSecret' => '$CRAFT_SECURITY_KEY'],
        ['recaptchaKey' => '$CRAFT_SECURITY_KEY'],
        ['akismetBlogUrl' => 'https://x/${CRAFT_SECURITY_KEY}'],
        ['akismetBlogUrl' => 'https://example.test/${CRAFT_SECURITY_KEY}'],
    ] as $posted) {
        $before = envRefStored();
        $response = envRefSave($manager, $posted + ['maxLinks' => '4']);
        $stored = envRefStored();
        $key = array_key_first($posted);

        expect($response['status'])->toBe(200, json_encode($posted))
            ->and($response['body'])->toContain('Only admins can use an environment variable or alias here.')
            ->and($stored[$key] ?? null)->toBe($before[$key] ?? null)
            ->and($stored['maxLinks'] ?? null)->not->toBe(4);
    }

    // Literal keys still save.
    $saved = envRefSave($manager, ['turnstileKey' => 'new-literal-site', 'turnstileSecret' => 'new-literal-secret']);

    expect($saved['status'])->toBe(302, envRefErrors($saved))
        ->and(envRefStored()['turnstileKey'])->toBe('new-literal-site')
        ->and(envRefStored()['turnstileSecret'])->toBe('new-literal-secret');
});

it('keeps a reference an admin set when someone who isn’t an admin saves the page, and lets them replace it', function() {
    $admin = envRefBrowser(true);
    $saved = envRefSave($admin, ['turnstileKey' => '$COMMENTS_TEST_SITE_KEY', 'turnstileSecret' => '$COMMENTS_TEST_SECRET', 'recaptchaKey' => '@webroot']);

    expect($saved['status'])->toBe(302, envRefErrors($saved))
        ->and(envRefStored()['turnstileKey'])->toBe('$COMMENTS_TEST_SITE_KEY');

    $manager = envRefBrowser(false);
    $resaved = envRefSave($manager, ['turnstileKey' => '$COMMENTS_TEST_SITE_KEY', 'turnstileSecret' => '$COMMENTS_TEST_SECRET', 'recaptchaKey' => '@webroot', 'maxLinks' => '6']);
    $stored = envRefStored();

    expect($resaved['status'])->toBe(302, envRefErrors($resaved))
        ->and($stored['turnstileKey'])->toBe('$COMMENTS_TEST_SITE_KEY')
        ->and($stored['turnstileSecret'])->toBe('$COMMENTS_TEST_SECRET')
        ->and($stored['maxLinks'])->toBe(6);

    $replaced = envRefSave($manager, ['turnstileKey' => 'manager-literal', 'recaptchaKey' => '']);

    expect($replaced['status'])->toBe(302, envRefErrors($replaced))
        ->and(envRefStored()['turnstileKey'])->toBe('manager-literal')
        ->and(envRefStored()['recaptchaKey'] ?? null)->toBeIn([null, '']);
});
