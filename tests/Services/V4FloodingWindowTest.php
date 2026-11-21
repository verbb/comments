<?php
use verbb\comments\Comments;
use verbb\comments\models\Settings;
use verbb\protect\checks\RateLimit;

/**
 * Minimum Time Between Comments over a day, which Protect's rate windows can't hold, waits a day rather
 * than leaving every comment refused as misconfigured.
 */

function floodingRule(Settings $settings): ?verbb\protect\models\RateLimitRule
{
    $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;

    foreach (Comments::$plugin->getProtection()->plan($siteId, true, settings: $settings)->checks() as $check) {
        if ($check instanceof RateLimit) {
            foreach ($check->rules as $rule) {
                if ($rule->handle === 'flooding') {
                    return $rule;
                }
            }
        }
    }

    return null;
}

it('caps a wait over a day at a day, with protection on or off', function(bool $enabled) {
    $settings = new Settings(['enableSpamChecks' => $enabled, 'securityFlooding' => 100000]);

    expect(floodingRule($settings)?->window)->toBe(86400)
        ->and(Comments::$plugin->getProtection()->configurationError($settings))->toBeNull()
        ->and($settings->validate(['securityFlooding', 'enableSpamChecks']))->toBeTrue();

    Comments::$plugin->getProtection()->plan((int)Craft::$app->getSites()->getPrimarySite()->id, false, settings: $settings)->validate();
})->with([true, false]);

it('keeps shorter waits as they are', function() {
    expect(floodingRule(new Settings(['securityFlooding' => 60]))?->window)->toBe(60)
        ->and(floodingRule(new Settings(['securityFlooding' => 86400]))?->window)->toBe(86400)
        ->and(floodingRule(new Settings(['securityFlooding' => null])))->toBeNull();
});
