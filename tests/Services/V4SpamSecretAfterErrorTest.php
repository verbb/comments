<?php
use craft\db\Query;
use verbb\comments\Comments;
use verbb\comments\models\Settings;
use verbb\comments\services\SpamSettings;

/**
 * A secret typed into the Spam Protection page shows as typed when the save fails on another setting,
 * so posting the page again saves it rather than the mask keeping the old one.
 */

beforeEach(function() {
    $this->original = Comments::$plugin->getSettings()->getAttributes();
    $this->stored = (new Query())->select(['settings'])->from(SpamSettings::TABLE)->scalar();
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
    Craft::$app->getDb()->createCommand()->delete(SpamSettings::TABLE)->execute();

    if (is_string($this->stored)) {
        Craft::$app->getDb()->createCommand()->insert(SpamSettings::TABLE, ['settings' => $this->stored])->execute();
    }
});

it('keeps a newly typed secret through a failed save and saves it on the next', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->enableSpamChecks = true;
    $settings->spamClassifier = Settings::CLASSIFIER_AKISMET;

    // The old key, saved in an earlier request.
    $earlier = new SpamSettings();
    $earlier->applyPosted($settings, ['akismetApiKey' => 'oldkey111', 'akismetBlogUrl' => 'https://example.org']);
    expect($earlier->save($settings))->toBeTrue();

    // A request that types a new key, with a Site URL that fails.
    $failed = new SpamSettings();
    $settings->akismetApiKey = 'oldkey111';
    expect($failed->displayValue($settings, 'akismetApiKey'))->toBe(SpamSettings::SECRET_MASK);
    $failed->applyPosted($settings, ['akismetApiKey' => 'newkey222', 'akismetBlogUrl' => 'example.com']);

    expect($failed->save($settings))->toBeFalse()
        ->and($failed->displayValue($settings, 'akismetApiKey'))->toBe('newkey222')
        ->and($failed->getStoredValues()['akismetApiKey'])->toBe('oldkey111');

    // The re-rendered page posts what it shows.
    $next = new SpamSettings();
    $next->applyPosted($settings, ['akismetApiKey' => $failed->displayValue($settings, 'akismetApiKey'), 'akismetBlogUrl' => 'https://example.com']);

    expect($next->save($settings))->toBeTrue()
        ->and($next->getStoredValues()['akismetApiKey'])->toBe('newkey222')
        ->and((new SpamSettings())->displayValue($settings, 'akismetApiKey'))->toBe(SpamSettings::SECRET_MASK);
});

it('keeps an untyped saved secret masked when a save fails', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->akismetApiKey = 'saved-key';
    $settings->turnstileSecret = '$TURNSTILE_SECRET';
    $service = new SpamSettings();
    $service->applyPosted($settings, ['akismetApiKey' => SpamSettings::SECRET_MASK, 'akismetBlogUrl' => 'example.com']);

    expect($service->displayValue($settings, 'akismetApiKey'))->toBe(SpamSettings::SECRET_MASK)
        ->and($settings->akismetApiKey)->toBe('saved-key')
        ->and($service->displayValue($settings, 'turnstileSecret'))->toBe('$TURNSTILE_SECRET');
});
