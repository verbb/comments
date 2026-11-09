<?php
namespace Tests\Support;

use Craft;
use craft\db\Query;
use verbb\comments\elements\Comment;

/**
 * Test DB reset helpers.
 */
class ResetTestDatabase
{
    /**
     * Rejected speculative tables from the pre-handoff tranche.
     * Canonical Conversations/Identities/satellites must NOT appear here.
     *
     * @var list<string>
     */
    private const REJECTED_SPECULATIVE_TABLES = [
        '{{%comments_outbox_consumptions}}',
        '{{%comments_outbox}}',
        '{{%comments_mutation_receipts}}',
        '{{%comments_notification_attempts}}',
        '{{%comments_notification_delivery_reasons}}',
        '{{%comments_notification_deliveries}}',
        '{{%comments_notification_events}}',
        '{{%comments_notification_management_tokens}}',
        '{{%comments_notification_preferences}}',
        '{{%comments_provider_attempts}}',
        '{{%comments_trust_signals}}',
        '{{%comments_trust_assessments}}',
        '{{%comments_identity_verification_challenges}}',
        '{{%comments_guest_credentials}}',
        '{{%comments_comment_search}}',
        '{{%comments_attachments}}',
        '{{%comments_conversation_summaries}}',
        '{{%comments_migration_diagnostics}}',
        '{{%comments_migration_checkpoints}}',
    ];

    /**
     * Rejected additive columns (never part of the canonical handoff schema).
     *
     * @var list<string>
     */
    private const REJECTED_COMMENT_COLUMNS = [
        'authorIdentityId',
        'originSiteId',
        'authorDisplayNameSnapshot',
        'removalKind',
        'workflowVersion',
        'editedAt',
        'firstPublishedAt',
        'threadOrdinal',
        'nextThreadOrdinal',
        'directPublicReplyCount',
        'rootPublicDescendantCount',
        'interactionPositiveCount',
        'interactionNegativeCount',
        'interactionScore',
        'openReportCount',
        'oldestOpenReportAt',
        'attachmentCount',
    ];

    public static function resetCommentsData(): void
    {
        $db = Craft::$app->getDb();

        // Clear the hierarchy first, or deleting a comment is refused while it still has replies.
        if ($db->tableExists('{{%comments_comments}}')) {
            $nulls = [];

            foreach (['parentId', 'rootId', 'conversationId', 'identityId'] as $column) {
                if ($db->columnExists('{{%comments_comments}}', $column)) {
                    $nulls[$column] = null;
                }
            }

            if ($nulls !== []) {
                $db->createCommand()
                    ->update('{{%comments_comments}}', $nulls)
                    ->execute();
            }
        }

        foreach (Comment::find()->status(null)->site('*')->unique()->all() as $comment) {
            Craft::$app->getElements()->deleteElement($comment, true);
        }

        $db = Craft::$app->getDb();

        if ($db->tableExists('{{%comments_votes}}')) {
            $db->createCommand()->delete('{{%comments_votes}}')->execute();
        }

        if ($db->tableExists('{{%comments_flags}}')) {
            $db->createCommand()->delete('{{%comments_flags}}')->execute();
        }

        if ($db->tableExists('{{%comments_subscribe}}')) {
            $db->createCommand()->delete('{{%comments_subscribe}}')->execute();
        }

        $remainingIds = array_map('intval', (new Query())
            ->select(['id'])
            ->from('{{%comments_comments}}')
            ->column());

        if ($remainingIds !== []) {
            $db->createCommand()
                ->delete('{{%structureelements}}', ['elementId' => $remainingIds])
                ->execute();
            $db->createCommand()
                ->delete('{{%comments_comments}}', ['id' => $remainingIds])
                ->execute();
            $db->createCommand()
                ->delete('{{%elements}}', ['id' => $remainingIds])
                ->execute();
        }

        // Clear canonical satellite rows between tests.
        foreach ([
            '{{%comments_interactions}}',
            '{{%comments_reports}}',
            '{{%comments_subscriptions}}',
            '{{%comments_mentions}}',
            '{{%comments_assets}}',
            '{{%comments_moderationactions}}',
            '{{%comments_moderatornotes}}',
            '{{%comments_restrictions}}',
            '{{%comments_conversations}}',
            '{{%comments_identities}}',
        ] as $table) {
            if ($db->tableExists($table)) {
                $db->createCommand()->delete($table)->execute();
            }
        }

        // Component instances survive across Pest tests — drop stale Identity/Conversation lookups.
        if (class_exists(\verbb\comments\Comments::class) && \verbb\comments\Comments::$plugin) {
            \verbb\comments\Comments::$plugin->getConversations()->clearCaches();
            \verbb\comments\Comments::$plugin->getIdentities()->clearCaches();
        }
    }

    /**
     * Drop leftover rejected speculative schema only. Safe no-op on clean installs.
     */
    public static function stripRejectedSpeculativeSchema(): void
    {
        $db = Craft::$app->getDb();
        $commentsTable = '{{%comments_comments}}';

        if ($db->tableExists($commentsTable)) {
            self::dropForeignKeysTouchingColumns($commentsTable, self::REJECTED_COMMENT_COLUMNS);
        }

        foreach (self::REJECTED_SPECULATIVE_TABLES as $table) {
            if ($db->tableExists($table)) {
                $db->createCommand()->dropTable($table)->execute();
            }
        }

        if ($db->tableExists($commentsTable)) {
            foreach (self::REJECTED_COMMENT_COLUMNS as $column) {
                if ($db->columnExists($commentsTable, $column)) {
                    $db->createCommand()
                        ->dropColumn($commentsTable, $column)
                        ->execute();
                }
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function dropForeignKeysTouchingColumns(string $table, array $columns): void
    {
        $db = Craft::$app->getDb();
        $schema = $db->getTableSchema($table, true);

        if ($schema === null) {
            return;
        }

        $columnSet = array_fill_keys($columns, true);

        foreach ($schema->foreignKeys as $fkName => $fk) {
            $localColumns = array_keys($fk);
            array_shift($localColumns);

            foreach ($localColumns as $localColumn) {
                if (isset($columnSet[$localColumn])) {
                    $db->createCommand()
                        ->dropForeignKey((string)$fkName, $table)
                        ->execute();

                    break;
                }
            }
        }
    }
}
