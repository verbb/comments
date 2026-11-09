<?php
namespace Tests\Support\Fixtures;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTime;
use RuntimeException;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ContentFormat;
use verbb\comments\enums\ModerationState;
use verbb\comments\models\CommentSubmission;

/**
 * Builds larger comment corpora for performance profiles.
 *
 * Mixed corpora use batched Comments::create (canonical v4 writes).
 * Large corpora (perf-large group) bypass element saves and insert rows directly.
 */
final class PerformanceCorpusFixture
{
    private const CREATE_BATCH_SIZE = 25;
    private const DIRECT_BATCH_SIZE = 100;

    /**
     * Mixed corpus: mostly roots with a minority of replies and guest authors.
     */
    public static function seedMixed(Entry $owner, int $count): int
    {
        if ($count < 1) {
            throw new RuntimeException('Mixed corpus count must be at least one.');
        }

        self::silenceNotifications();

        $rootTarget = max(1, (int)round($count * 0.85));
        $replyTarget = max(0, $count - $rootTarget);
        $conversationId = null;

        for ($offset = 0; $offset < $rootTarget; $offset += self::CREATE_BATCH_SIZE) {
            $batchSize = min(self::CREATE_BATCH_SIZE, $rootTarget - $offset);
            $roots = CommentsFixtureFactory::conversationWithRoots($owner, $batchSize);

            if ($conversationId === null && $roots !== []) {
                $conversationId = (int)$roots[0]->conversationId;
            }
        }

        if ($replyTarget > 0) {
            $roots = CommentsFixtureFactory::conversationWithRoots($owner, max(1, (int)ceil($replyTarget / 3)));
            $conversationId ??= (int)$roots[0]->conversationId;

            $admin = Craft::$app->getUser()->getIdentity();
            if (!$admin) {
                throw new RuntimeException('No user available for mixed corpus replies.');
            }

            $parent = $roots[array_key_first($roots)];
            for ($i = 0; $i < $replyTarget; $i++) {
                Comments::$plugin->getComments()->create(new CommentSubmission([
                    'ownerId' => $owner->id,
                    'siteId' => $owner->siteId,
                    'parentId' => (int)$parent->id,
                    'content' => "Mixed reply {$i}",
                    'userId' => (int)$admin->id,
                ]));
            }
        }

        $guestRoots = max(1, (int)round($count * 0.02));
        for ($i = 0; $i < $guestRoots; $i++) {
            CommentsFixtureFactory::guestHeavyThread($owner, 1);
        }

        if ($conversationId === null) {
            $conversation = Comments::$plugin->getConversations()->findForOwner((int)$owner->id, (int)$owner->siteId);
            $conversationId = $conversation ? (int)$conversation->id : 0;
        }

        return $conversationId;
    }

    /**
     * Large approved-root corpus for perf-large profiles (direct DB inserts).
     */
    public static function seedLarge(Entry $owner, int $count): int
    {
        if ($count < 1) {
            throw new RuntimeException('Large corpus count must be at least one.');
        }

        self::silenceNotifications();

        // Seed one canonical row so conversation, identity and element shape are valid.
        $seed = CommentsFixtureFactory::conversationWithRoots($owner, 1)[0];
        $conversationId = (int)$seed->conversationId;
        $identityId = (int)$seed->identityId;
        $userId = (int)$seed->userId;
        $siteId = (int)$owner->siteId;
        $ownerId = (int)$owner->id;

        $elementTemplate = (new Query())
            ->from('{{%elements}}')
            ->where(['id' => $seed->id])
            ->one();

        $elementSiteTemplate = (new Query())
            ->from('{{%elements_sites}}')
            ->where(['elementId' => $seed->id, 'siteId' => $siteId])
            ->one();

        if (!$elementTemplate || !$elementSiteTemplate) {
            throw new RuntimeException('Failed reading element template for large corpus seed.');
        }

        $db = Craft::$app->getDb();
        $now = Db::prepareDateForDb(new DateTime());
        $remaining = $count - 1;
        $inserted = 1;

        while ($remaining > 0) {
            $batchSize = min(self::DIRECT_BATCH_SIZE, $remaining);
            $startId = (int)(new Query())->from('{{%elements}}')->max('id');
            $nextElementSiteId = (int)(new Query())->from('{{%elements_sites}}')->max('id');

            $elementRows = [];
            $elementSiteRows = [];
            $commentRows = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $elementId = $startId + 1 + $i;
                $uid = StringHelper::UUID();
                $elementSiteId = $nextElementSiteId + 1 + $i;
                $commentIndex = $inserted + $i;

                $elementRows[] = [
                    $elementId,
                    $elementTemplate['canonicalId'],
                    $elementTemplate['draftId'],
                    $elementTemplate['revisionId'],
                    $elementTemplate['fieldLayoutId'],
                    Comment::class,
                    1,
                    0,
                    $elementTemplate['dateLastMerged'],
                    null,
                    $elementTemplate['deletedWithOwner'],
                    $now,
                    $now,
                    $uid,
                ];

                $elementSiteRows[] = [
                    $elementSiteId,
                    $elementId,
                    $siteId,
                    $elementSiteTemplate['slug'] . "-{$commentIndex}",
                    $elementSiteTemplate['uri'],
                    1,
                    $now,
                    $now,
                    StringHelper::UUID(),
                ];

                $commentRows[] = [
                    $elementId,
                    $ownerId,
                    $siteId,
                    $userId,
                    Comment::STATUS_APPROVED,
                    "Large corpus root {$commentIndex}",
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $now,
                    $now,
                    $now,
                    $uid,
                    $conversationId,
                    $identityId,
                    null,
                    $elementId,
                    0,
                    ModerationState::Approved->value,
                    ContentFormat::Plain->value,
                    "Large corpus root {$commentIndex}",
                    null,
                ];
            }

            $db->createCommand()->batchInsert(
                '{{%elements}}',
                [
                    'id',
                    'canonicalId',
                    'draftId',
                    'revisionId',
                    'fieldLayoutId',
                    'type',
                    'enabled',
                    'archived',
                    'dateLastMerged',
                    'dateDeleted',
                    'deletedWithOwner',
                    'dateCreated',
                    'dateUpdated',
                    'uid',
                ],
                $elementRows,
            )->execute();

            $db->createCommand()->batchInsert(
                '{{%elements_sites}}',
                [
                    'id',
                    'elementId',
                    'siteId',
                    'slug',
                    'uri',
                    'enabled',
                    'dateCreated',
                    'dateUpdated',
                    'uid',
                ],
                $elementSiteRows,
            )->execute();

            $db->createCommand()->batchInsert(
                '{{%comments_comments}}',
                [
                    'id',
                    'ownerId',
                    'ownerSiteId',
                    'userId',
                    'status',
                    'comment',
                    'gifUrl',
                    'name',
                    'email',
                    'url',
                    'ipAddress',
                    'userAgent',
                    'commentDate',
                    'dateCreated',
                    'dateUpdated',
                    'uid',
                    'conversationId',
                    'identityId',
                    'parentId',
                    'rootId',
                    'depth',
                    'moderationState',
                    'contentFormat',
                    'content',
                    'authorRemovedAt',
                ],
                $commentRows,
            )->execute();

            $remaining -= $batchSize;
            $inserted += $batchSize;
        }

        $db->createCommand()
            ->update('{{%comments_conversations}}', [
                'commentCount' => $count,
                'lastActivityAt' => $now,
            ], ['id' => $conversationId])
            ->execute();

        $actual = (int)(new Query())
            ->from('{{%comments_comments}}')
            ->where(['ownerId' => $ownerId])
            ->count();

        if ($actual < $count) {
            throw new RuntimeException("Large corpus seed expected {$count} comments for owner {$ownerId}, found {$actual}.");
        }

        return $conversationId;
    }

    private static function silenceNotifications(): void
    {
        $settings = Comments::$plugin->getSettings();
        $settings->requireModeration = false;
        $settings->participantNotifications = [];
        $settings->staffNotifications = [];
        $settings->subscriptionTypes = [];
    }
}
