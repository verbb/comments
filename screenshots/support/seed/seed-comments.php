// craft-screenshots: sample-frontend
/** Seed a small editorial site and realistic conversations for Comments screenshots. */

use craft\elements\Entry;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\fieldlayoutelements\CommentsField as CommentsFieldElement;

$entries = Craft::$app->getEntries();
$elements = Craft::$app->getElements();
$site = Craft::$app->getSites()->getPrimarySite();
$sectionHandle = 'commentsScreenshotStories';
$commentSettings = Comments::$plugin->getSettings();

// Comments creates its structure lazily and persists the UID through the same
// project-config settings path, so establish it before saving the full fixture.
$commentSettings->getStructureId();

// The isolated screenshot install already has the plugin in project config by
// the time its install migration runs, so ensure the default comment field is
// present before rendering the bundled front-end form.
$commentLayout = Craft::$app->getFields()->getLayoutByType(Comment::class) ?? new FieldLayout(['type' => Comment::class]);
$commentTab = new FieldLayoutTab(['name' => 'Comment Form', 'layout' => $commentLayout]);
$commentTab->setElements([new CommentsFieldElement()]);
$commentLayout->setTabs([$commentTab]);

if (!Craft::$app->getFields()->saveLayout($commentLayout)) {
    throw new RuntimeException('Unable to save the default Comments field layout.');
}

$section = $entries->getSectionByHandle($sectionHandle);

if (!$section) {
    $entryType = new EntryType(['name' => 'Stories', 'handle' => $sectionHandle . 'Type']);
    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => Craft::t('app', 'Content'), 'layout' => $layout]);
    $tab->setElements([new EntryTitleField()]);
    $layout->setTabs([$tab]);
    $entryType->setFieldLayout($layout);

    if (!$entries->saveEntryType($entryType)) {
        throw new RuntimeException('Unable to save Comments screenshot entry type: ' . Json::encode($entryType->getErrors()));
    }

    $section = new Section(['name' => 'Stories', 'handle' => $sectionHandle, 'type' => Section::TYPE_CHANNEL]);
    $section->setEntryTypes([$entryType]);
    $section->setSiteSettings([new Section_SiteSettings([
        'siteId' => $site->id,
        'enabledByDefault' => true,
        'hasUrls' => true,
        'uriFormat' => 'stories/{slug}',
        'template' => 'comments-screenshot-entry',
    ])]);

    if (!$entries->saveSection($section)) {
        throw new RuntimeException('Unable to save Comments screenshot section: ' . Json::encode($section->getErrors()));
    }
}

$entryType = $entries->getEntryTypesBySectionId($section->id)[0] ?? null;
$storyData = [
    ['title' => 'Community gardens worth visiting', 'slug' => 'community-gardens-worth-visiting'],
    ['title' => 'How our neighbourhood market works', 'slug' => 'how-our-neighbourhood-market-works'],
    ['title' => 'The winter reading list', 'slug' => 'the-winter-reading-list'],
];
$stories = [];

foreach ($storyData as $data) {
    $entry = Entry::find()->sectionId($section->id)->slug($data['slug'])->siteId($site->id)->status(null)->one();

    if (!$entry) {
        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'siteId' => $site->id,
            'slug' => $data['slug'],
            'enabled' => true,
        ]);
    }

    $entry->title = $data['title'];

    if (!$elements->saveElement($entry)) {
        throw new RuntimeException('Unable to save Comments screenshot entry: ' . Json::encode($entry->getErrors()));
    }

    $stories[$data['slug']] = $entry;
}

$commentData = [
    [
        'story' => 'community-gardens-worth-visiting',
        'name' => 'Maya Chen',
        'email' => 'maya.chen@example.com',
        'comment' => 'The map of accessible paths is genuinely useful. Could you add the weekend opening hours near the top as well?',
        'status' => Comment::STATUS_APPROVED,
        'date' => '2026-09-18 09:24:00',
        'key' => 'maya',
    ],
    [
        'story' => 'community-gardens-worth-visiting',
        'name' => 'Oliver Grant',
        'email' => 'oliver.grant@example.com',
        'comment' => 'Good call — I have added the Saturday and Sunday hours to the visitor notes.',
        'status' => Comment::STATUS_APPROVED,
        'date' => '2026-09-18 09:41:00',
        'parent' => 'maya',
        'key' => 'oliver',
    ],
    [
        'story' => 'community-gardens-worth-visiting',
        'name' => 'Priya Nair',
        'email' => 'priya.nair@example.com',
        'comment' => 'We visited with our kids last month and the native garden was the highlight. The volunteer team were brilliant.',
        'status' => Comment::STATUS_APPROVED,
        'date' => '2026-09-17 16:08:00',
        'key' => 'priya',
    ],
    [
        'story' => 'how-our-neighbourhood-market-works',
        'name' => 'Daniel Brooks',
        'email' => 'daniel.brooks@example.com',
        'comment' => 'Is there a quieter time to visit for anyone who finds the Saturday morning crowd a bit much?',
        'status' => Comment::STATUS_PENDING,
        'date' => '2026-09-17 13:52:00',
        'key' => 'daniel',
    ],
    [
        'story' => 'how-our-neighbourhood-market-works',
        'name' => 'Lena Ortiz',
        'email' => 'lena.ortiz@example.com',
        'comment' => 'The vendor map makes planning a quick visit much easier. Thank you for including public transport details too.',
        'status' => Comment::STATUS_APPROVED,
        'date' => '2026-09-16 11:15:00',
        'key' => 'lena',
    ],
    [
        'story' => 'the-winter-reading-list',
        'name' => 'Noah Patel',
        'email' => 'noah.patel@example.com',
        'comment' => 'The essays were an unexpected favourite. I would love a follow-up list focused on Australian writers.',
        'status' => Comment::STATUS_APPROVED,
        'date' => '2026-09-15 18:37:00',
        'key' => 'noah',
    ],
    [
        'story' => 'the-winter-reading-list',
        'name' => 'Sofia Bennett',
        'email' => 'sofia.bennett@example.com',
        'comment' => 'A thoughtful mix of familiar names and books I had never come across. The short descriptions are spot on.',
        'status' => Comment::STATUS_APPROVED,
        'date' => '2026-09-15 08:06:00',
        'key' => 'sofia',
    ],
    [
        'story' => 'community-gardens-worth-visiting',
        'name' => 'Marcus Lee',
        'email' => 'marcus.lee@example.com',
        'comment' => 'Would it be possible to note which gardens welcome dogs? That would make this guide even more useful.',
        'status' => Comment::STATUS_PENDING,
        'date' => '2026-09-14 14:33:00',
        'key' => 'marcus',
    ],
    [
        'story' => 'how-our-neighbourhood-market-works',
        'name' => 'Emma Wilson',
        'email' => 'emma.wilson@example.com',
        'comment' => 'The stallholder interviews are a lovely touch. They make the market feel like a community rather than a directory.',
        'status' => Comment::STATUS_APPROVED,
        'date' => '2026-09-13 12:19:00',
        'key' => 'emma',
    ],
    [
        'story' => 'the-winter-reading-list',
        'name' => 'Theo Martin',
        'email' => 'theo.martin@example.com',
        'comment' => 'Visit my profile for guaranteed prizes and instant rewards.',
        'status' => Comment::STATUS_SPAM,
        'date' => '2026-09-12 22:44:00',
        'key' => 'theo',
    ],
];

$savedComments = [];

foreach ($commentData as $data) {
    $owner = $stories[$data['story']];
    $comment = Comment::find()->ownerId($owner->id)->email($data['email'])->status(null)->one();

    if (!$comment) {
        $comment = new Comment([
            'siteId' => $site->id,
            'ownerId' => $owner->id,
            'ownerSiteId' => $site->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'comment' => $data['comment'],
            'status' => $data['status'],
            'commentDate' => new DateTime($data['date'], new DateTimeZone('Australia/Melbourne')),
            'enabled' => true,
            'scenario' => Comment::SCENARIO_CP,
        ]);

        if (!empty($data['parent'])) {
            $comment->newParentId = $savedComments[$data['parent']]->id;
        }

        if (!$elements->saveElement($comment)) {
            throw new RuntimeException('Unable to save Comments screenshot comment: ' . Json::encode($comment->getErrors()));
        }
    }

    $savedComments[$data['key']] = $comment;
}

$templateDir = Craft::getAlias('@templates');
FileHelper::createDirectory($templateDir);
$template = <<<'TWIG'
{# craft-screenshots: sample-frontend #}
{% set story = craft.entries.section('commentsScreenshotStories').slug('community-gardens-worth-visiting').one() %}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join the conversation</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 64px 32px; background: #f5f7fa; color: #243447; font: 16px/1.5 system-ui, sans-serif; }
        main { width: min(840px, 100%); margin: 0 auto; padding: 44px 48px 48px; background: #fff; border: 1px solid #dfe5eb; border-radius: 12px; box-shadow: 0 18px 45px rgba(38, 50, 56, 0.09); }
        main > header { margin-bottom: 30px; }
        main > header span { color: #64748b; font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        h1 { margin: 6px 0 8px; font-size: 32px; line-height: 1.2; }
        main > header p { margin: 0; color: #64748b; }
    </style>
</head>
<body>
    <main>
        <header>
            <span>Community gardens worth visiting</span>
            <h1>Join the conversation</h1>
            <p>Share a recommendation or ask a question about the guide.</p>
        </header>

        {{ craft.comments.render(story.id) }}
    </main>
</body>
</html>
TWIG;
file_put_contents($templateDir . '/comments-screenshot-entry.twig', $template);

echo Json::encode([
    'commentsRoute' => '/admin/comments',
    'settingsRoute' => '/admin/comments/settings',
    'frontendRoute' => '/stories/community-gardens-worth-visiting',
    'commentCount' => count($savedComments),
], JSON_THROW_ON_ERROR);
