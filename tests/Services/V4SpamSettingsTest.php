<?php
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\Json;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\exceptions\CommentValidationException;
use verbb\comments\helpers\SettingsTabs;
use verbb\comments\migrations\m400000_000013_spam_settings;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\services\SpamSettings;

/**
 * The Spam settings kept in the installation's database, and the rules among them: the link limit,
 * blocked email domains, holding first comments and deleting old spam.
 */

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $this->original = $settings->getAttributes();
    $this->stored = (new Query())->select(['settings'])->from(SpamSettings::TABLE)->scalar();
    $settings->requireModeration = false;
    $settings->enableSpamChecks = false;
    $settings->allowGuest = true;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    $settings->subscriptionTypes = [];
    $settings->useQueueForNotifications = false;
    Comments::$plugin->getConversations()->clearCaches();
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
    Craft::$app->getDb()->createCommand()->delete(SpamSettings::TABLE)->execute();

    if (is_string($this->stored)) {
        Craft::$app->getDb()->createCommand()->insert(SpamSettings::TABLE, ['settings' => $this->stored])->execute();
    }

    spamResetStore();
});

function spamResetStore(): void
{
    (new ReflectionProperty(SpamSettings::class, '_stored'))->setValue(Comments::$plugin->getSpamSettings(), null);
}

function spamPost(Entry $entry, array $values): Comment
{
    $user = Craft::$app->getUser();
    $signedIn = $user->getIdentity();

    if (!isset($values['userId'])) {
        $user->setIdentity(null);
    }

    try {
        return Comments::$plugin->getComments()->create(new CommentSubmission($values + [
            'ownerId' => $entry->id,
            'siteId' => $entry->siteId,
        ]))->comment;
    } finally {
        $user->setIdentity($signedIn);
    }
}

function spamState(Comment $comment): array
{
    return (new Query())->select(['moderationState', 'heldReason'])->from('{{%comments_comments}}')->where(['id' => $comment->id])->one();
}

it('keeps the Spam settings out of project config', function() {
    $fields = Comments::$plugin->getSettings()->toArray();

    foreach (SpamSettings::KEYS as $key) {
        expect($fields)->not->toHaveKey($key);
    }

    expect($fields)->toHaveKey('requireModeration')->toHaveKey('holdFirstComments');
});

it('saves the Spam settings to the database and reads them back over what project config holds', function() {
    $service = Comments::$plugin->getSpamSettings();
    $settings = Comments::$plugin->getSettings();
    $settings->securitySpamlist = 'casino';
    $settings->maxLinks = 2;

    expect($service->save($settings))->toBeTrue();

    $row = Json::decode((string)(new Query())->select(['settings'])->from(SpamSettings::TABLE)->scalar());

    expect($row['securitySpamlist'])->toBe('casino')
        ->and($row['maxLinks'])->toBe(2);

    // A model built from stale project config values takes what's saved, and defaults for the rest.
    $fresh = new Settings(['securitySpamlist' => 'old', 'securityBanned' => 'stale', 'requireModeration' => true]);
    spamResetStore();
    $service->hydrate($fresh);

    expect($fresh->securitySpamlist)->toBe('casino')
        ->and((int)$fresh->maxLinks)->toBe(2)
        ->and($fresh->securityBanned)->toBe((new Settings())->securityBanned)
        ->and($fresh->requireModeration)->toBeTrue();
});

it('masks a saved secret and keeps it when the mask is posted back', function() {
    $service = Comments::$plugin->getSpamSettings();
    $settings = Comments::$plugin->getSettings();
    $settings->akismetApiKey = 'literal-key';
    $settings->turnstileSecret = '$TURNSTILE_SECRET';

    expect($service->displayValue($settings, 'akismetApiKey'))->toBe(SpamSettings::SECRET_MASK)
        ->and($service->displayValue($settings, 'turnstileSecret'))->toBe('$TURNSTILE_SECRET')
        ->and($service->displayValue($settings, 'turnstileKey'))->toBe($settings->turnstileKey);

    $service->applyPosted($settings, ['akismetApiKey' => SpamSettings::SECRET_MASK, 'securityBanned' => 'spammer', 'requireModeration' => true]);

    expect($settings->akismetApiKey)->toBe('literal-key')
        ->and($settings->securityBanned)->toBe('spammer')
        ->and($settings->requireModeration)->toBeFalse();

    $service->applyPosted($settings, ['akismetApiKey' => 'replacement']);

    expect($settings->akismetApiKey)->toBe('replacement');
});

it('reads Comments 3’s reCAPTCHA switch and development builds’ Turnstile switch as the challenge provider', function() {
    expect((new Settings(['recaptchaEnabled' => true]))->challengeProvider)->toBe(Settings::CHALLENGE_RECAPTCHA)
        ->and((new Settings(['turnstileEnabled' => true, 'recaptchaEnabled' => true]))->challengeProvider)->toBe(Settings::CHALLENGE_TURNSTILE)
        ->and((new Settings(['recaptchaEnabled' => false]))->challengeProvider)->toBe(Settings::CHALLENGE_NONE)
        ->and((new Settings(['recaptchaEnabled' => true, 'challengeProvider' => '']))->challengeProvider)->toBe(Settings::CHALLENGE_NONE);
});

it('seeds the store from project config when upgrading, and leaves an existing store alone', function() {
    $projectConfig = Craft::$app->getProjectConfig();
    $saved = $projectConfig->get('plugins.comments.settings') ?? [];
    $db = Craft::$app->getDb();

    try {
        $db->createCommand()->delete(SpamSettings::TABLE)->execute();
        $projectConfig->set('plugins.comments.settings', array_merge($saved, [
            'securityModeration' => 'review me',
            'recaptchaEnabled' => true,
            'recaptchaKey' => 'site-key',
        ]));

        (new m400000_000013_spam_settings())->safeUp();

        $row = Json::decode((string)(new Query())->select(['settings'])->from(SpamSettings::TABLE)->scalar());

        expect($row['securityModeration'])->toBe('review me')
            ->and($row['challengeProvider'])->toBe(Settings::CHALLENGE_RECAPTCHA)
            ->and($row['recaptchaKey'])->toBe('site-key')
            ->and($row)->not->toHaveKey('maxLinks');

        $projectConfig->set('plugins.comments.settings.securityModeration', 'changed');
        (new m400000_000013_spam_settings())->safeUp();

        expect((int)(new Query())->from(SpamSettings::TABLE)->count())->toBe(1)
            ->and(Json::decode((string)(new Query())->select(['settings'])->from(SpamSettings::TABLE)->scalar())['securityModeration'])->toBe('review me');
    } finally {
        $projectConfig->set('plugins.comments.settings', $saved);
    }
});

it('gives someone who can manage spam settings the Spam Protection page, but not Settings', function() {
    $user = CommentsFixtureFactory::user('spamManager');
    $signedIn = Craft::$app->getUser()->getIdentity();
    Craft::$app->getUser()->setIdentity($user);

    try {
        expect(Comments::$plugin->getSpamSettings()->canManage())->toBeFalse();

        Craft::$app->getUserPermissions()->saveUserPermissions($user->id, ['accessplugin-comments', 'comments-managespamsettings']);

        $nav = Comments::$plugin->getCpNavItem();

        expect(Comments::$plugin->getSpamSettings()->canManage())->toBeTrue()
            ->and($nav['subnav']['spamProtection']['url'] ?? $nav['url'])->toBe('comments/spam-protection')
            ->and($nav['subnav']['settings'] ?? null)->toBeNull()
            ->and(array_keys(SettingsTabs::navItems()))->not->toContain('spam');
    } finally {
        Craft::$app->getUser()->setIdentity($signedIn);
    }
});

it('counts the links in a comment', function() {
    $security = Comments::$plugin->getSecurity();

    expect($security->countLinks('No links here, just example.com'))->toBe(0)
        ->and($security->countLinks('See https://one.test and www.two.test'))->toBe(2)
        ->and($security->countLinks('<p><a href="https://one.test">https://one.test</a> and http://two.test</p>'))->toBe(2)
        ->and($security->countLinks('Mail me at someone@www.example.test'))->toBe(0);
});

it('holds a comment with more links than Maximum Links allows', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('Link limit');
    $member = CommentsFixtureFactory::user('linkLimit');
    $settings->maxLinks = 1;

    $one = spamPost($entry, ['userId' => $member->id, 'content' => 'Read https://one.test']);
    $two = spamPost($entry, ['userId' => $member->id, 'content' => 'Read https://one.test and https://two.test']);

    expect(spamState($one))->toBe(['moderationState' => ModerationState::Approved->value, 'heldReason' => null])
        ->and(spamState($two))->toBe(['moderationState' => ModerationState::Pending->value, 'heldReason' => HeldReason::Links->value]);

    $settings->maxLinks = null;

    expect(spamState(spamPost($entry, ['userId' => $member->id, 'content' => 'https://a.test https://b.test https://c.test']))['moderationState'])->toBe(ModerationState::Approved->value);
});

it('refuses guests at a blocked email domain or one of its subdomains', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('Blocked domains');
    $settings->blockedEmailDomains = "@Spam.test\nother.test";

    expect($settings->getBlockedEmailDomains())->toBe(['spam.test', 'other.test'])
        ->and(Comments::$plugin->getSecurity()->isBlockedEmail('someone@mail.spam.test'))->toBeTrue()
        ->and(Comments::$plugin->getSecurity()->isBlockedEmail('someone@notspam.test'))->toBeFalse();

    expect(fn() => spamPost($entry, ['content' => 'Guest at a blocked domain', 'name' => 'Guest', 'email' => 'guest@spam.test']))->toThrow(CommentValidationException::class);
    expect(spamPost($entry, ['content' => 'Guest elsewhere', 'name' => 'Guest', 'email' => 'guest@allowed.test'])->id)->not->toBeNull();

    $settings->blockedEmailDomains = 'not a domain!';

    expect($settings->validate(['blockedEmailDomains']))->toBeFalse();
});

it('holds each person’s comments until one has been approved', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('First comments');
    $member = CommentsFixtureFactory::user('firstComment');
    $settings->holdFirstComments = true;

    $first = spamPost($entry, ['userId' => $member->id, 'content' => 'My first comment']);
    $second = spamPost($entry, ['userId' => $member->id, 'content' => 'Still waiting']);

    expect(spamState($first))->toBe(['moderationState' => ModerationState::Pending->value, 'heldReason' => HeldReason::FirstComment->value])
        ->and(spamState($second)['heldReason'])->toBe(HeldReason::FirstComment->value);

    Comments::$plugin->getModeration()->approve(Comment::find()->id($first->id)->status(null)->one(), Craft::$app->getUsers()->getUserByUsernameOrEmail('admin'));

    expect(spamState(spamPost($entry, ['userId' => $member->id, 'content' => 'Now I’m known']))['moderationState'])->toBe(ModerationState::Approved->value);
});

it('deletes spam older than Delete Spam After, keeping recent spam and spam with replies', function() {
    $settings = Comments::$plugin->getSettings();
    $entry = CommentsFixtureFactory::entry('Spam retention');
    $old = CommentsFixtureFactory::guestComment($entry, 'Old spam', status: Comment::STATUS_SPAM);
    $recent = CommentsFixtureFactory::guestComment($entry, 'Recent spam', status: Comment::STATUS_SPAM);
    $parent = CommentsFixtureFactory::guestComment($entry, 'Old spam with a reply', status: Comment::STATUS_SPAM);
    CommentsFixtureFactory::guestComment($entry, 'A reply', parent: $parent);
    $db = Craft::$app->getDb();

    foreach ([$old, $recent, $parent] as $comment) {
        $db->createCommand()->update('{{%comments_comments}}', ['moderationState' => ModerationState::Spam->value], ['id' => $comment->id])->execute();
    }

    $db->createCommand()->update('{{%elements}}', ['dateUpdated' => Db::prepareDateForDb(new DateTime('-40 days'))], ['id' => [$old->id, $parent->id]])->execute();

    $settings->spamRetentionDays = null;

    expect(Comments::$plugin->getModeration()->deleteExpiredSpam())->toBe(0);

    $settings->spamRetentionDays = 30;

    expect(Comments::$plugin->getModeration()->deleteExpiredSpam())->toBe(1)
        ->and((new Query())->from('{{%elements}}')->where(['id' => $old->id])->exists())->toBeFalse()
        ->and((int)(new Query())->from('{{%elements}}')->where(['id' => [$recent->id, $parent->id]])->count())->toBe(2);
});

it('builds the chosen challenge provider and spam classifier into the plan', function() {
    $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
    $checks = static function(Settings $settings) use ($siteId): array {
        $found = ['challenge' => null, 'challengeOn' => false, 'classifier' => null];

        foreach (Comments::$plugin->getProtection()->plan($siteId, false, settings: $settings)->checks() as $check) {
            if ($check instanceof verbb\protect\checks\Challenge) {
                $found['challenge'] = $check->provider;
                $found['challengeOn'] = $check->enabled;
            }

            if ($check instanceof verbb\protect\checks\Classification) {
                $found['classifier'] = $check->provider;
            }
        }

        return $found;
    };

    $none = $checks(new Settings(['enableSpamChecks' => true]));

    expect($none['challengeOn'])->toBeFalse()
        ->and($none['classifier'])->toBeNull();

    $hcaptcha = $checks(new Settings(['enableSpamChecks' => true, 'challengeProvider' => 'hcaptcha', 'hcaptchaKey' => 'h-site', 'hcaptchaSecret' => 'h-secret', 'spamClassifier' => 'cleantalk', 'cleantalkApiKey' => 'ct-key']));

    expect($hcaptcha['challenge'])->toBeInstanceOf(verbb\protect\integrations\challenges\Hcaptcha::class)
        ->and($hcaptcha['challenge']->siteKey)->toBe('h-site')
        ->and($hcaptcha['challengeOn'])->toBeTrue()
        ->and($hcaptcha['classifier'])->toBeInstanceOf(verbb\protect\integrations\classifiers\CleanTalk::class)
        ->and($hcaptcha['classifier']->apiKey)->toBe('ct-key');

    $friendly = $checks(new Settings(['enableSpamChecks' => true, 'challengeProvider' => 'friendly-captcha', 'friendlyCaptchaKey' => 'f-site', 'friendlyCaptchaSecret' => 'f-key', 'friendlyCaptchaEndpoint' => 'eu', 'spamClassifier' => 'oopspam', 'oopspamApiKey' => 'oop', 'oopspamSpamThreshold' => 5]));

    expect($friendly['challenge'])->toBeInstanceOf(verbb\protect\integrations\challenges\FriendlyCaptcha::class)
        ->and($friendly['challenge']->endpoint)->toBe('eu')
        ->and($friendly['classifier'])->toBeInstanceOf(verbb\protect\integrations\classifiers\OopSpam::class)
        ->and($friendly['classifier']->spamThreshold)->toBe(5);
});

it('reads development builds’ Akismet switches as the spam classifier and corrections', function() {
    $settings = new Settings(['akismetEnabled' => true, 'akismetFeedbackEnabled' => true]);

    expect($settings->spamClassifier)->toBe(Settings::CLASSIFIER_AKISMET)
        ->and($settings->spamFeedbackEnabled)->toBeTrue()
        ->and((new Settings(['akismetEnabled' => false]))->spamClassifier)->toBe(Settings::CLASSIFIER_NONE);
});

it('requires the chosen providers’ keys', function() {
    $settings = new Settings(['enableSpamChecks' => true, 'challengeProvider' => 'hcaptcha', 'spamClassifier' => 'oopspam', 'oopspamSpamThreshold' => 9]);

    expect($settings->validate(SpamSettings::KEYS))->toBeFalse()
        ->and($settings->getErrors())->toHaveKeys(['hcaptchaKey', 'hcaptchaSecret', 'oopspamApiKey', 'oopspamSpamThreshold']);
});

it('renders the Spam and Moderation panes with the new fields', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->challengeProvider = Settings::CHALLENGE_FRIENDLY_CAPTCHA;
    $settings->spamClassifier = Settings::CLASSIFIER_OOPSPAM;
    $settings->oopspamApiKey = 'literal-key';
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode($view::TEMPLATE_MODE_CP);

    try {
        $spam = $view->renderTemplate('comments/spam-protection/_fields', [
            'settings' => $settings,
            'secrets' => ['oopspamApiKey' => Comments::$plugin->getSpamSettings()->displayValue($settings, 'oopspamApiKey')],
        ]);
        $moderation = $view->renderTemplate('comments/settings/_panes/moderation', ['settings' => $settings]);
    } finally {
        $view->setTemplateMode($mode);
    }

    expect($spam)->toContain('name="friendlyCaptchaEndpoint"')
        ->toContain('name="spamClassifier"')
        ->toContain('name="maxLinks"')
        ->toContain('name="blockedEmailDomains"')
        ->toContain('name="spamRetentionDays"')
        ->toContain(SpamSettings::SECRET_MASK)
        ->not->toContain('literal-key')
        ->and($moderation)->toContain('name="holdFirstComments"');
});
