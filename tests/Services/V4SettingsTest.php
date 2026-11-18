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
    // Comments 3's Comment Administrators become the Notification Recipients.
    expect($settings->notificationRecipients)->toBe([['email' => 'staff@example.test', 'enabled' => true]])
        ->and($settings->getEnabledNotificationRecipientEmails())->toBe(['staff@example.test']);

    $attributes = array_keys($settings->getAttributes());

    foreach (['notificationAuthorEnabled', 'notificationAdmins', 'emailModeratorGroup', 'indexSidebarLimit'] as $retired) {
        expect($attributes)->not->toContain($retired);
    }
});

it('reads Notification Recipients rows, plain addresses and closures, once each', function() {
    $settings = new Settings();
    $settings->setAttributes(['notificationRecipients' => [
        ['email' => 'one@example.test', 'enabled' => '1'],
        ['email' => 'off@example.test', 'enabled' => ''],
        'plain@example.test',
        ['email' => 'ONE@example.test', 'enabled' => true],
        ['email' => '', 'enabled' => true],
    ]], false);

    expect($settings->getEnabledNotificationRecipientEmails())->toBe(['one@example.test', 'plain@example.test'])
        ->and($settings->validate(['notificationRecipients']))->toBeTrue();

    $settings->notificationRecipients = [['email' => 'not an address', 'enabled' => true]];

    expect($settings->validate(['notificationRecipients']))->toBeFalse()
        ->and($settings->getEnabledNotificationRecipientEmails())->toBe([]);

    $settings->notificationRecipients = static fn() => [['email' => 'closure@example.test', 'enabled' => true]];

    expect($settings->getEnabledNotificationRecipientEmails())->toBe(['closure@example.test']);

    // An empty editable table posts an empty string, and Comments 4's development switches are dropped.
    $settings->setAttributes(['notificationRecipients' => '', 'emailModeratorGroup' => true, 'javascriptCheckEnabled' => true], false);

    expect($settings->notificationRecipients)->toBe([])
        ->and(array_keys($settings->getAttributes()))->not->toContain('javascriptCheckEnabled');
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
        'challengeProvider' => Settings::CHALLENGE_RECAPTCHA,
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
