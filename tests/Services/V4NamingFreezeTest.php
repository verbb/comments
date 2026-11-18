<?php
use craft\models\UserGroup;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationState;
use verbb\comments\events\DefineBadgesEvent;
use verbb\comments\helpers\SettingsTabs;
use verbb\comments\models\CommentSubmission;
use verbb\comments\models\Settings;
use verbb\comments\services\Comments as CommentsService;
use yii\base\Event;

/**
 * The names the gate 7 naming freeze settled (SD-48), and the behaviour that came with them: approved
 * comments only from the hierarchy accessors and the viewer's own badges.
 */

beforeEach(function() {
    $this->admin = Craft::$app->getUser()->getIdentity();
    $settings = Comments::$plugin->getSettings();
    $this->originalSettings = $settings->toArray();
    $settings->enableSpamChecks = false;
    $settings->requireModeration = false;
    $settings->participantNotifications = [];
    $settings->staffNotifications = [];
    Craft::$app->getConfig()->getGeneral()->enableGraphqlCaching = false;
});

afterEach(function() {
    Event::off(CommentsService::class, CommentsService::EVENT_DEFINE_BADGES);
    Craft::$app->getUser()->setIdentity($this->admin);
    Comments::$plugin->getSettings()->setAttributes($this->originalSettings, false);
    Craft::$app->getGql()->flushCaches();
    Comments::$plugin->getConversations()->clearCaches();
    Comments::$plugin->getIdentities()->clearCaches();
});

function freezeComment($entry, string $content, ?int $parentId = null): Comment
{
    return Comments::$plugin->getComments()->create(new CommentSubmission(array_filter([
        'ownerId' => $entry->id,
        'siteId' => $entry->siteId,
        'content' => $content,
        'userId' => (int)Craft::$app->getUser()->getIdentity()->id,
        'parentId' => $parentId,
    ], static fn($value) => $value !== null)))->comment;
}

function freezeHold(Comment $comment): void
{
    Comments::$plugin->getModeration()->setState($comment, ModerationState::Pending, Craft::$app->getUser()->getIdentity());
}

// Hierarchy accessors
// =========================================================================

it('gives approved replies from getReplies(), children and getDescendants(), and every reply with status(null)', function() {
    $entry = CommentsFixtureFactory::entry('Freeze replies owner');
    $root = freezeComment($entry, 'Root');
    $approved = freezeComment($entry, 'Approved reply', (int)$root->id);
    $held = freezeComment($entry, 'Held reply', (int)$root->id);
    $underHeld = freezeComment($entry, 'Under the held reply', (int)$held->id);
    freezeHold($held);

    $root = Comment::find()->id($root->id)->one();

    expect($root->getReplies()->ids())->toBe([(int)$approved->id])
        ->and($root->getReplies()->status(null)->ids())->toEqualCanonicalizing([(int)$approved->id, (int)$held->id])
        ->and($root->getChildren()->ids())->toBe([(int)$approved->id])
        // A reply under a held one is left out with it, as the thread leaves it out.
        ->and(array_map(static fn(Comment $comment): int => (int)$comment->id, $root->getDescendants()->all()))->toBe([(int)$approved->id])
        ->and((int)$underHeld->parentId)->toBe((int)$held->id);
});

it('gives only an approved parent from getParent(), and any parent from getParentComment()', function() {
    $entry = CommentsFixtureFactory::entry('Freeze parent owner');
    $parent = freezeComment($entry, 'Parent');
    $reply = freezeComment($entry, 'Reply', (int)$parent->id);

    expect(Comment::find()->id($reply->id)->one()->getParent()?->id)->toBe($parent->id);

    freezeHold($parent);
    $reply = Comment::find()->id($reply->id)->one();

    expect($reply->getParent())->toBeNull()
        ->and($reply->parent)->toBeNull()
        ->and($reply->getParentComment()?->id)->toBe($parent->id)
        ->and(Comment::find()->id($reply->parentId)->status(null)->one()?->id)->toBe($parent->id);
});

it('keeps GraphQL’s parent field as it was: any state on a schema that can read every state, approved only on a public one', function() {
    $entry = CommentsFixtureFactory::entry('Freeze GraphQL parent owner');
    $parent = freezeComment($entry, 'Parent');
    $reply = freezeComment($entry, 'Reply', (int)$parent->id);
    freezeHold($parent);
    $query = "{ comment(id: {$reply->id}) { ... on Comment { parent { id } } } }";

    $private = gapsGql(['comments:read'], $query);
    $public = gapsGql(['comments:read'], $query, true);

    expect($private['errors'] ?? [])->toBe([])
        ->and((int)($private['data']['comment']['parent']['id'] ?? 0))->toBe((int)$parent->id)
        ->and($public['data']['comment']['parent'] ?? null)->toBeNull();
});

// GraphQL and the JSON format
// =========================================================================

it('says hidden in GraphQL when guests give no name or email, as the JSON format does', function() {
    $entry = CommentsFixtureFactory::entry('Freeze guest policy owner');
    $settings = Comments::$plugin->getSettings();
    $settings->guestRequireEmailName = false;
    $settings->guestShowEmailName = false;
    Comments::$plugin->getConversations()->clearCaches();

    $result = gapsGql(['comments:read'], "{ commentConversation(ownerId: {$entry->id}, siteId: {$entry->siteId}) { guestPolicy { nameAndEmail } } }", true);

    expect($result['errors'] ?? [])->toBe([])
        ->and($result['data']['commentConversation']['guestPolicy']['nameAndEmail'])->toBe('hidden')
        ->and(Comments::$plugin->getApi()->thread($entry)['conversation']['guestPolicy']['nameAndEmail'])->toBe('hidden');
});

it('stores and answers rich content as rich, in GraphQL and the JSON format', function() {
    expect(ContentFormat::Rich->value)->toBe('rich')
        ->and(ContentFormat::tryFrom('editor'))->toBeNull();

    $entry = CommentsFixtureFactory::entry('Freeze rich owner');
    $comment = freezeComment($entry, 'Plain');
    $comment->contentFormat = ContentFormat::Rich->value;
    $comment->bodyContent = json_encode([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Rich words']]]], JSON_THROW_ON_ERROR);
    Craft::$app->getElements()->saveElement($comment);

    $result = gapsGql(['comments:read'], "{ comment(id: {$comment->id}) { ... on Comment { contentFormat contentDocument } } }");
    $row = (new craft\db\Query())->from('{{%comments_comments}}')->where(['id' => $comment->id])->one();

    expect($result['errors'] ?? [])->toBe([])
        ->and($result['data']['comment']['contentFormat'])->toBe('rich')
        ->and($result['data']['comment']['contentDocument'])->toBeString()
        ->and($row['contentFormat'])->toBe('rich');
});

it('gives the viewer’s badges in the same shape as an author’s, with a site’s own', function() {
    $entry = CommentsFixtureFactory::entry('Freeze viewer badges owner');
    $staff = CommentsFixtureFactory::user('freezeStaff' . bin2hex(random_bytes(3)));
    $group = new UserGroup(['name' => 'Freeze ' . bin2hex(random_bytes(3)), 'handle' => 'freeze' . bin2hex(random_bytes(3))]);
    Craft::$app->getUserGroups()->saveGroup($group, false);
    Craft::$app->getUsers()->assignUserToGroups((int)$staff->id, [(int)$group->id]);
    $settings = Comments::$plugin->getSettings();
    $settings->staffBadgeLabel = 'Team';
    $settings->staffBadgeGroups = [$group->uid];
    $seen = [];

    Event::on(CommentsService::class, CommentsService::EVENT_DEFINE_BADGES, function(DefineBadgesEvent $event) use (&$seen) {
        $seen[] = [$event->comment?->userId, $event->comment?->ownerId, $event->isPublic];
        $event->badges[] = ['type' => 'patron', 'label' => 'Patron'];
    });

    Craft::$app->getUser()->setIdentity(Craft::$app->getUsers()->getUserById((int)$staff->id));
    Comments::$plugin->getIdentities()->clearCaches();
    $viewer = Comments::$plugin->getApi()->viewer($entry)['viewer'];

    expect($viewer)->not->toHaveKey('badge')
        ->and($viewer['badges'])->toBe([['type' => 'staff', 'label' => 'Team'], ['type' => 'patron', 'label' => 'Patron']])
        ->and($seen)->toContain([(int)$staff->id, (int)$entry->id, false]);

    Craft::$app->getUser()->setIdentity(null);
    Comments::$plugin->getIdentities()->clearCaches();

    expect(Comments::$plugin->getApi()->viewer($entry)['viewer']['badges'])->toBe([]);
});

// Settings
// =========================================================================

it('ignores a setting saved under a name Comments doesn’t have, and saves the settings without it', function() {
    $projectConfig = Craft::$app->getProjectConfig();
    $previous = $projectConfig->get('plugins.comments.settings');
    $plugin = Comments::$plugin;

    try {
        $projectConfig->set('plugins.comments.settings.editorTheme', 'dark');
        $settings = new Settings();
        $settings->setAttributes(['editorTheme' => 'dark', 'theme' => 'auto'], false);

        expect($settings->theme)->toBe('auto')
            ->and($settings->toArray())->not->toHaveKey('editorTheme')
            ->and(Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray()))->toBeTrue()
            ->and($projectConfig->get('plugins.comments.settings.editorTheme'))->toBeNull()
            ->and($projectConfig->get('plugins.comments.settings.theme'))->toBe('auto');
    } finally {
        $projectConfig->set('plugins.comments.settings', $previous);
        $plugin->getSettings()->setAttributes($this->originalSettings, false);
    }
});

it('reads a plain origin in allowedOrigins as that origin with everything else off', function() {
    $settings = new Settings(['allowedOrigins' => [
        'https://www.example.com',
        ['origin' => 'https://app.example.com', 'memberTokens' => true],
    ]]);
    $origins = $settings->getAllowedOrigins();

    expect(array_keys($origins))->toBe(['https://www.example.com', 'https://app.example.com'])
        ->and([$origins['https://www.example.com']->credentials, $origins['https://www.example.com']->memberTokens, $origins['https://www.example.com']->moderation])->toBe([false, false, false])
        ->and($origins['https://app.example.com']->memberTokens)->toBeTrue()
        ->and($settings->validate(['allowedOrigins']))->toBeTrue();
});

it('calls the settings pane CSS & JavaScript, and sends its old address there', function() {
    expect(SettingsTabs::navItems()['css-javascript']['title'])->toBe('CSS & JavaScript')
        ->and(SettingsTabs::has('templates'))->toBeFalse()
        ->and(SettingsTabs::canonicalHandle('templates'))->toBe('css-javascript');
});
