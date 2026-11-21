<?php
use craft\db\Query;
use verbb\comments\Comments;
use verbb\comments\models\Settings;
use verbb\comments\services\SpamSettings;

/**
 * A Spam setting `config/comments.php` sets to null, such as `App::env()` of a variable this environment
 * doesn't have, isn't an override: the page's value is saved and used. A value still wins.
 */

beforeEach(function() {
    $this->configFile = Craft::$app->getConfig()->getConfigFilePath('comments');
    $this->configBefore = is_file($this->configFile) ? file_get_contents($this->configFile) : null;
    $this->stored = (new Query())->select(['settings'])->from(SpamSettings::TABLE)->scalar();
});

afterEach(function() {
    if ($this->configBefore === null) {
        @unlink($this->configFile);
    } else {
        file_put_contents($this->configFile, $this->configBefore);
    }

    Craft::$app->getDb()->createCommand()->delete(SpamSettings::TABLE)->execute();

    if (is_string($this->stored)) {
        Craft::$app->getDb()->createCommand()->insert(SpamSettings::TABLE, ['settings' => $this->stored])->execute();
    }
});

function spamConfigFile(string $php): void
{
    file_put_contents(Craft::$app->getConfig()->getConfigFilePath('comments'), "<?php\nreturn " . $php . ";\n");
}

it('saves and uses a key the config file sets to null', function() {
    spamConfigFile("['akismetApiKey' => craft\\helpers\\App::env('COMMENTS_TEST_NOT_SET')]");
    (new SpamSettings())->saveValues(['akismetApiKey' => 'oldkey111']);
    $settings = new Settings(['akismetApiKey' => 'typedkey333']);

    expect((new SpamSettings())->save($settings))->toBeTrue()
        ->and((new SpamSettings())->getStoredValues()['akismetApiKey'] ?? null)->toBe('typedkey333');

    // Craft puts the file's null over what's saved; hydrating brings the saved key back.
    $fresh = new Settings(['akismetApiKey' => null]);
    (new SpamSettings())->hydrate($fresh);

    expect($fresh->akismetApiKey)->toBe('typedkey333');
});

it('still lets a value in the config file win', function() {
    spamConfigFile("['akismetApiKey' => 'configkey999']");
    (new SpamSettings())->saveValues(['akismetApiKey' => 'savedkey']);
    $settings = new Settings(['akismetApiKey' => 'typedkey444']);

    expect((new SpamSettings())->save($settings))->toBeTrue()
        ->and((new SpamSettings())->getStoredValues()['akismetApiKey'] ?? null)->toBe('savedkey');

    $fresh = new Settings(['akismetApiKey' => 'configkey999']);
    (new SpamSettings())->hydrate($fresh);

    expect($fresh->akismetApiKey)->toBe('configkey999');
});
