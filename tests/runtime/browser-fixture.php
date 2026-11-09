<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use craft\elements\User;
use craft\enums\CmsEdition;
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\InteractionMode;
use verbb\comments\enums\InteractionType;
use verbb\comments\models\CommentSubmission;

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
$app->setEdition(CmsEdition::Pro);

$admin = $app->getUsers()->getUserByUsernameOrEmail('admin');
if (!$admin) {
    throw new RuntimeException('The browser fixture requires the test administrator.');
}

$app->getUser()->setIdentity($admin);
$plugin = Comments::$plugin;
$settings = $plugin->getSettings();
$settings->participantNotifications = [];
$settings->staffNotifications = [];
$settings->allowGuest = true;
$settings->guestRequireEmailName = false;
$settings->interactionMode = InteractionMode::UpDown->value;
$settings->requireModeration = true;

$community = CommentsFixtureFactory::entry('Community gardens worth visiting');
$reading = CommentsFixtureFactory::entry('The winter reading list');
$market = CommentsFixtureFactory::entry('How our neighbourhood market works');

$maya = CommentsFixtureFactory::user('maya');
$maya->firstName = 'Maya';
$maya->lastName = 'Chen';
$maya->fullName = 'Maya Chen';
$app->getElements()->saveElement($maya);

$oliver = CommentsFixtureFactory::user('oliver');
$oliver->firstName = 'Oliver';
$oliver->lastName = 'Grant';
$oliver->fullName = 'Oliver Grant';
$app->getElements()->saveElement($oliver);

$priya = CommentsFixtureFactory::user('priya');
$priya->firstName = 'Priya';
$priya->lastName = 'Nair';
$priya->fullName = 'Priya Nair';
$app->getElements()->saveElement($priya);

foreach ([
    [$community, 'Community gardens worth visiting'],
    [$reading, 'The winter reading list'],
    [$market, 'How our neighbourhood market works'],
] as [$entry, $title]) {
    Craft::$app->getDb()->createCommand()
        ->update('{{%elements_sites}}', ['title' => $title], [
            'elementId' => $entry->id,
            'siteId' => $entry->siteId,
        ])
        ->execute();
}

/**
 * Create a browser-fixture comment through the same service used by the site UI.
 */
$post = static function(
    int $ownerId,
    string $content,
    ?User $author,
    ?int $parentId = null,
    ?string $name = null,
    ?string $email = null,
): Comment {
    $identityId = $author
        ? null
        : (int)Comments::$plugin->getIdentities()->createHistoricalGuest($name, $email)->id;

    return Comments::$plugin->getComments()->create(new CommentSubmission([
        'ownerId' => $ownerId,
        'siteId' => (int)\Craft::$app->getSites()->getPrimarySite()->id,
        'content' => $content,
        'identityId' => $identityId,
        'userId' => $author?->id,
        'parentId' => $parentId,
        'name' => $name,
        'email' => $email,
    ]))->comment;
};

$moderation = $plugin->getModeration();
$pendingGarden = $post(
    (int)$community->id,
    'The map of accessible paths is genuinely useful. Could you add the weekend opening hours near the top as well?',
    $maya,
);
$moderation->addNote($pendingGarden, 'Check the updated opening hours before approving.', $admin);

$reportedGarden = $post(
    (int)$community->id,
    'Good call — I have added the Saturday and Sunday hours to the visitor notes.',
    $oliver,
    (int)$pendingGarden->id,
);
$moderation->approve($reportedGarden, $admin, 'Helpful reply.');
$plugin->getReports()->create(
    (int)$reportedGarden->id,
    (int)$plugin->getIdentities()->getOrCreateForUser($maya)->id,
    'other',
    'Please confirm the opening hours are current.',
);

$approvedReading = $post(
    (int)$reading->id,
    'The essays were an unexpected favourite. I would love a follow-up list focused on local writers.',
    $priya,
);
$moderation->approve($approvedReading, $admin, 'Constructive feedback.');

$spam = $post(
    (int)$reading->id,
    'Visit my profile for guaranteed prizes and instant rewards.',
    null,
    null,
    'Theo Martin',
    'theo@example.test',
);
$moderation->markSpam($spam, $admin, 'Promotional spam.');
$plugin->getModeration()->blockIdentity((int)$spam->identityId, null, $admin, 'Repeated promotion.');

$removed = $post(
    (int)$market->id,
    'This is no longer relevant to the discussion and can be removed.',
    $oliver,
);
$moderation->remove($removed, $admin, 'Off-topic.');

foreach ([
    ['The volunteer team were brilliant and made the visit feel welcoming.', $priya, $community],
    ['Would it be possible to note which gardens welcome dogs?', $maya, $community],
    ['The vendor map makes planning a quick visit much easier.', $oliver, $market],
    ['The stallholder interviews make the market feel like a community.', $priya, $market],
    ['Could the reading list include estimated reading times?', $maya, $reading],
] as [$content, $author, $owner]) {
    $comment = $post((int)$owner->id, $content, $author);
    $moderation->approve($comment, $admin);
}

$namedGuest = $post(
    (int)$market->id,
    'I visited on Sunday and the produce stalls were still open after lunch.',
    null,
    null,
    'Alex Morgan',
);
$moderation->approve($namedGuest, $admin);

$anonymousGuest = $post(
    (int)$community->id,
    'Are there drinking-water fountains near the accessible entrance?',
    null,
);
$moderation->approve($anonymousGuest, $admin);

$adminIdentity = $plugin->getIdentities()->getOrCreateForUser($admin);
$plugin->getInteractions()->toggle((int)$approvedReading->id, (int)$adminIdentity->id, InteractionType::Upvote);
$plugin->getDiagnostics()->rebuildAllAggregates();

echo "Browser fixture ready.\n";
echo 'Login: https://' . getenv('DDEV_SITENAME') . ".ddev.site/admin/login\n";
echo 'Owner entry: ' . $community->getCpEditUrl() . "\n";
