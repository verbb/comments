<?php
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\SubscriptionType;
use verbb\comments\models\Settings;

it('uses canonical v4 settings for fresh installs', function() {
    $settings = new Settings();

    expect($settings->interactionMode)->toBe(InteractionMode::Like->value);
    expect($settings->resolveInteractionMode())->toBe(InteractionMode::Like);
    expect(property_exists($settings, 'allowVoting'))->toBeFalse();
    expect($settings->participantNotifications)->toBe([
        Settings::NOTIFICATION_OWNER,
        Settings::NOTIFICATION_REPLY,
        Settings::NOTIFICATION_MENTION,
    ]);
    expect($settings->staffNotifications)->toBe([]);
    expect($settings->subscriptionTypes)->toBe([]);
    expect($settings->subscriptionDefault)->toBe(Settings::SUBSCRIPTION_DEFAULT_NONE);
    expect($settings->useQueueForNotifications)->toBeTrue();
});

it('translates the v3 voting toggle to the canonical interaction mode', function() {
    expect((new Settings(['allowVoting' => true]))->interactionMode)->toBe(InteractionMode::UpDown->value);
    expect((new Settings(['allowVoting' => false]))->interactionMode)->toBe(InteractionMode::None->value);
    expect((new Settings([
        'allowVoting' => false,
        'interactionMode' => InteractionMode::UpDown->value,
    ]))->interactionMode)->toBe(InteractionMode::UpDown->value);
    expect((new Settings([
        'allowVoting' => false,
        'interactionMode' => null,
    ]))->interactionMode)->toBe(InteractionMode::None->value);
    expect((new Settings(['interactionMode' => null]))->interactionMode)->toBe(InteractionMode::Like->value);
});

it('translates the v3 notification matrix into canonical v4 settings', function() {
    $settings = new Settings([
        'notificationAuthorEnabled' => false,
        'notificationReplyEnabled' => true,
        'notificationMentionEnabled' => true,
        'notificationModeratorApprovedEnabled' => true,
        'notificationModeratorEnabled' => true,
        'notificationModeratorEditEnabled' => false,
        'notificationAdminEnabled' => true,
        'notificationFlaggedEnabled' => true,
        'notificationSubscribeEnabled' => true,
        'notificationSubscribeCommentEnabled' => true,
        'notificationSubscribeDefault' => true,
        'notificationAdmins' => [
            ['email' => 'staff@example.test', 'enabled' => true],
        ],
        'indexSidebarLimit' => 25,
        'indexSidebarGroup' => true,
        'indexSidebarIndividualElements' => false,
    ]);

    expect($settings->participantNotifications)->toBe([
        Settings::NOTIFICATION_REPLY,
        Settings::NOTIFICATION_MENTION,
        Settings::NOTIFICATION_APPROVED,
    ]);
    expect($settings->staffNotifications)->toBe([
        Settings::NOTIFICATION_MODERATOR_PENDING,
        Settings::NOTIFICATION_STAFF_NEW,
        Settings::NOTIFICATION_STAFF_REPORTED,
    ]);
    expect($settings->subscriptionTypes)->toBe([
        SubscriptionType::Conversation->value,
        SubscriptionType::Replies->value,
    ]);
    expect($settings->subscriptionDefault)->toBe(Settings::SUBSCRIPTION_DEFAULT_REPLIES);
    // Staff are told through an event now, so the address list goes.
    $attributes = array_keys($settings->getAttributes());

    foreach (['notificationAuthorEnabled', 'notificationAdmins', 'notificationRecipients', 'indexSidebarLimit'] as $retired) {
        expect($attributes)->not->toContain($retired);
    }
});

it('rejects a default subscription type that is not available', function() {
    $settings = new Settings([
        'subscriptionTypes' => [SubscriptionType::Conversation->value],
        'subscriptionDefault' => Settings::SUBSCRIPTION_DEFAULT_REPLIES,
    ]);

    expect($settings->validate())->toBeFalse();
    expect($settings->getErrors('subscriptionDefault'))->not->toBeEmpty();
});

it('validates settings that are conditionally required', function() {
    $settings = new Settings([
        'allowAttachments' => true,
        'recaptchaEnabled' => true,
    ]);

    expect($settings->validate())->toBeFalse();
    expect($settings->getErrors())->toHaveKeys([
        'attachmentVolumeId',
        'recaptchaKey',
        'recaptchaSecret',
    ]);
});

it('keeps moderation enabled when the configured moderator group no longer exists', function() {
    $settings = new Settings([
        'requireModeration' => true,
        'moderatorExcluded' => true,
        'moderatorUserGroup' => '00000000-0000-0000-0000-000000000000',
    ]);

    expect($settings->doesRequireModeration())->toBeTrue();
});
