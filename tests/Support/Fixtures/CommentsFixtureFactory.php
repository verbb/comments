<?php
namespace Tests\Support\Fixtures;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\StringHelper;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use DateTime;
use RuntimeException;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

class CommentsFixtureFactory
{
    private static int $sequence = 0;

    public static function entry(?string $title = null): Entry
    {
        $section = self::section();
        $entryType = $section->getEntryTypes()[0] ?? null;

        if (!$entryType) {
            throw new RuntimeException('Comments test section has no entry types.');
        }

        $title ??= 'Test Entry ' . self::nextSequence();
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;

        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'siteId' => $siteId,
            'title' => $title,
            'slug' => StringHelper::toKebabCase($title),
            'enabled' => true,
        ]);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new RuntimeException('Failed creating entry fixture: ' . json_encode($entry->getErrors()));
        }

        return $entry;
    }

    public static function comment(
        Entry $owner,
        string $text,
        ?User $user = null,
        ?Comment $parent = null,
        string $status = Comment::STATUS_APPROVED,
    ): Comment {
        $user ??= Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new RuntimeException('No user available for comment fixture.');
        }

        $comment = new Comment([
            'comment' => $text,
            'ownerId' => $owner->id,
            'ownerSiteId' => $owner->siteId,
            'userId' => $user->id,
            'status' => $status,
            'commentDate' => new DateTime(),
        ]);
        $comment->setScenario(Comment::SCENARIO_CP);

        if ($parent) {
            $comment->parentId = $parent->id;
        }

        if (!Craft::$app->getElements()->saveElement($comment, true, false)) {
            throw new RuntimeException('Failed creating comment fixture: ' . json_encode($comment->getErrors()));
        }

        return $comment;
    }

    public static function guestComment(
        Entry $owner,
        string $text,
        string $name = 'Guest User',
        string $email = 'guest@example.test',
        ?Comment $parent = null,
        string $status = Comment::STATUS_APPROVED,
    ): Comment {
        $comment = new Comment([
            'comment' => $text,
            'ownerId' => $owner->id,
            'ownerSiteId' => $owner->siteId,
            'name' => $name,
            'email' => $email,
            'status' => $status,
            'commentDate' => new DateTime(),
        ]);
        $comment->setScenario(Comment::SCENARIO_CP);

        if ($parent) {
            $comment->parentId = $parent->id;
        }

        if (!Craft::$app->getElements()->saveElement($comment, true, false)) {
            throw new RuntimeException('Failed creating guest comment fixture: ' . json_encode($comment->getErrors()));
        }

        return $comment;
    }

    public static function thread(Entry $owner, int $depth = 2): Comment
    {
        return self::deepThread($owner, $depth);
    }

    public static function deepThread(Entry $owner, int $depth = 2): Comment
    {
        if ($depth < 1) {
            throw new RuntimeException('Thread depth must be at least one.');
        }

        $root = self::comment($owner, 'Root comment');
        $parent = $root;

        for ($i = 1; $i < $depth; $i++) {
            $parent = self::comment($owner, "Reply level {$i}", parent: $parent);
        }

        return $root;
    }

    /**
     * @return Comment[]
     */
    public static function manyRoots(Entry $owner, int $count, string $status = Comment::STATUS_APPROVED): array
    {
        if ($count < 0) {
            throw new RuntimeException('Comment count cannot be negative.');
        }

        $comments = [];

        for ($i = 0; $i < $count; $i++) {
            $comments[] = self::comment($owner, "Root comment {$i}", status: $status);
        }

        return $comments;
    }

    /**
     * Canonical v4 roots via Comments::create (Conversation + Identity + hierarchy).
     *
     * @return Comment[]
     */
    public static function conversationWithRoots(Entry $owner, int $count, string $status = Comment::STATUS_APPROVED): array
    {
        if ($count < 0) {
            throw new RuntimeException('Comment count cannot be negative.');
        }

        if ($count === 0) {
            return [];
        }

        $settings = Comments::$plugin->getSettings();
        $previousRequireModeration = $settings->requireModeration;
        $previousParticipantNotifications = $settings->participantNotifications;
        $previousStaffNotifications = $settings->staffNotifications;

        $settings->requireModeration = $status === Comment::STATUS_PENDING;
        $settings->participantNotifications = [];
        $settings->staffNotifications = [];

        $admin = Craft::$app->getUser()->getIdentity();
        if (!$admin) {
            throw new RuntimeException('No user available for conversation fixture.');
        }

        $comments = [];

        try {
            for ($i = 0; $i < $count; $i++) {
                $result = Comments::$plugin->getComments()->create(new CommentSubmission([
                    'ownerId' => $owner->id,
                    'siteId' => $owner->siteId,
                    'content' => "Conversation root {$i}",
                    'userId' => (int)$admin->id,
                ]));

                $comment = $result->comment;

                if ($status === Comment::STATUS_SPAM || $status === Comment::STATUS_REMOVED) {
                    $comment = self::applyLegacyStatus($comment, $status);
                }

                $comments[] = $comment;
            }
        } finally {
            $settings->requireModeration = $previousRequireModeration;
            $settings->participantNotifications = $previousParticipantNotifications;
            $settings->staffNotifications = $previousStaffNotifications;
        }

        return $comments;
    }

    /**
     * One root with many guest replies — useful for guest-heavy read/search profiles.
     */
    public static function guestHeavyThread(Entry $owner, int $count): Comment
    {
        if ($count < 0) {
            throw new RuntimeException('Guest reply count cannot be negative.');
        }

        $root = self::guestComment($owner, 'Guest-heavy thread root', name: 'Guest Root', email: 'guest-root@example.test');

        for ($i = 0; $i < $count; $i++) {
            self::guestComment(
                $owner,
                "Guest reply {$i}",
                name: "Guest {$i}",
                email: "guest-{$i}@example.test",
                parent: $root,
            );
        }

        return $root;
    }

    /**
     * Comments with varied Unicode/plain text for moderation search profiles.
     *
     * @return array<string, Comment>
     */
    public static function unicodeSearchCorpus(Entry $owner): array
    {
        $samples = [
            'latin' => 'Café résumé naïve Zürich Straße',
            'cjk' => '日本語コメント 你好世界',
            'arabic' => 'مرحبا بالعالم mixed English',
            'emoji' => 'Emoji 🎉🔥 test 评论',
            'combining' => "e\u{0301} vs é — fi\u{FB01} vs ﬁ",
            'symbols' => '© ® ™ — math ∑∫ ≤≥',
        ];

        $comments = [];

        foreach ($samples as $key => $text) {
            $comments[$key] = self::comment($owner, $text);
        }

        return $comments;
    }

    public static function wideThread(Entry $owner, int $replyCount = 10): Comment
    {
        if ($replyCount < 0) {
            throw new RuntimeException('Reply count cannot be negative.');
        }

        $root = self::comment($owner, 'Wide thread root');

        for ($i = 0; $i < $replyCount; $i++) {
            self::comment($owner, "Direct reply {$i}", parent: $root);
        }

        return $root;
    }

    /**
     * @return array<string, Comment>
     */
    public static function moderationMix(Entry $owner): array
    {
        return [
            Comment::STATUS_APPROVED => self::comment($owner, 'Approved fixture', status: Comment::STATUS_APPROVED),
            Comment::STATUS_PENDING => self::comment($owner, 'Pending fixture', status: Comment::STATUS_PENDING),
            Comment::STATUS_SPAM => self::guestComment(
                $owner,
                'Spam guest fixture',
                name: 'Spam Guest',
                email: 'spam-guest@example.test',
                status: Comment::STATUS_SPAM,
            ),
            Comment::STATUS_REMOVED => self::guestComment(
                $owner,
                'Trashed guest fixture',
                name: 'Removed Guest',
                email: 'removed-guest@example.test',
                status: Comment::STATUS_REMOVED,
            ),
        ];
    }

    public static function section(): Section
    {
        $handle = 'commentsTestSection';
        $sectionsService = Craft::$app->getEntries();
        $section = $sectionsService->getSectionByHandle($handle);

        if ($section) {
            return $section;
        }

        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $entryType = $sectionsService->getEntryTypeByHandle('commentsTest') ?? new EntryType([
            'name' => 'Comments Test',
            'handle' => 'commentsTest',
            'hasTitleField' => true,
        ]);

        // Craft 5 entry types are saved independently, then assigned to a section.
        if (!$entryType->id && !$sectionsService->saveEntryType($entryType)) {
            throw new RuntimeException('Failed creating comments test entry type: ' . json_encode($entryType->getErrors()));
        }

        $section = new Section([
            'name' => 'Comments Test',
            'handle' => $handle,
            'type' => Section::TYPE_CHANNEL,
            'enableVersioning' => false,
            'siteSettings' => [
                new Section_SiteSettings([
                    'siteId' => $siteId,
                    'enabledByDefault' => true,
                    'hasUrls' => true,
                    'uriFormat' => 'comments-test/{slug}',
                    'template' => '',
                ]),
            ],
        ]);
        $section->setEntryTypes([$entryType]);

        if (!$sectionsService->saveSection($section)) {
            throw new RuntimeException('Failed creating comments test section: ' . json_encode($section->getErrors()));
        }

        return $sectionsService->getSectionByHandle($handle) ?? $section;
    }

    /**
     * Create an additional Craft user for multi-actor integration tests.
     */
    public static function user(?string $prefix = 'commentsTestUser'): User
    {
        // Solo edition caps the install at one user; Pro removes the limit for mention/permission tests.
        Craft::$app->setEdition(\craft\enums\CmsEdition::Pro);

        $user = new User([
            'username' => $prefix . uniqid(),
            'email' => $prefix . uniqid() . '@example.test',
            'firstName' => 'Test',
            'active' => true,
            'newPassword' => 'comments-test-password',
        ]);
        $user->setScenario(User::SCENARIO_REGISTRATION);

        if (!Craft::$app->getElements()->saveElement($user)) {
            throw new RuntimeException('Failed creating user fixture: ' . json_encode($user->getErrors()));
        }

        return $user;
    }

    private static function nextSequence(): int
    {
        return ++self::$sequence;
    }

    private static function applyLegacyStatus(Comment $comment, string $status): Comment
    {
        $moderationState = match ($status) {
            Comment::STATUS_SPAM => ModerationState::Spam->value,
            Comment::STATUS_REMOVED => ModerationState::Removed->value,
            default => $comment->moderationState,
        };

        Craft::$app->getDb()->createCommand()
            ->update('{{%comments_comments}}', [
                'status' => $status,
                'moderationState' => $moderationState,
            ], ['id' => $comment->id])
            ->execute();

        $comment->status = $status;
        $comment->moderationState = $moderationState;

        return $comment;
    }
}
