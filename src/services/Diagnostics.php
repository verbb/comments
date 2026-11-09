<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ModerationState;
use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\records\Conversation as ConversationRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;

/**
 * Internal integrity scans, aggregate rebuilds, and health snapshots.
 */
class Diagnostics extends Component
{
    // Public Methods
    // =========================================================================

    public function runIntegrityScan(): array
    {
        $v4 = $this->_scanV4Integrity();

        return [
            'upgrade' => V3UpgradeLog::all(Craft::$app->getDb()),
            'v4' => $v4,
            'hasErrors' => $this->_hasSeverity($v4['issues'], 'error'),
        ];
    }

    public function rebuildAllAggregates(): array
    {
        $ids = ConversationRecord::find()->select(['id'])->column();
        $conversations = CommentsPlugin::$plugin->getConversations();
        $rebuilt = 0;

        foreach ($ids as $id) {
            $conversations->rebuildAggregates((int)$id);
            $rebuilt++;
        }

        return ['rebuilt' => $rebuilt];
    }

    public function rebuildAggregatesForOwner(int $ownerId, int $siteId): array
    {
        $conversation = CommentsPlugin::$plugin->getConversations()->findForOwner($ownerId, $siteId);

        if (!$conversation) {
            return ['rebuilt' => false, 'conversationId' => 0];
        }

        CommentsPlugin::$plugin->getConversations()->rebuildAggregates((int)$conversation->id);

        return ['rebuilt' => true, 'conversationId' => (int)$conversation->id];
    }

    public function getHealthSnapshot(): array
    {
        $db = Craft::$app->getDb();

        $conversationCount = (int)ConversationRecord::find()->count();
        $commentCount = (int)Comment::find()->status(null)->count();
        $pendingCount = (int)Comment::find()
            ->status(null)
            ->moderationState(ModerationState::Pending->value)
            ->count();

        // Drift is measured against the same definition rebuildAggregates() writes, so a
        // "0 drift" reading can be trusted rather than reflecting two different queries agreeing by luck.
        $conversations = CommentsPlugin::$plugin->getConversations();
        $aggregateDrift = 0;

        foreach (ConversationRecord::find()->all() as $conversation) {
            $actual = (int)$conversations->publicCommentQuery((int)$conversation->id)->count();

            if ((int)$conversation->commentCount !== $actual) {
                $aggregateDrift++;
            }
        }

        $queueFailures = 0;

        if ($db->tableExists('{{%queue}}')) {
            $queueFailures = (int)(new Query())
                ->from('{{%queue}}')
                ->where(['fail' => true])
                ->andWhere(['like', 'description', 'comments%', false])
                ->count();
        }

        return [
            'conversations' => $conversationCount,
            'comments' => $commentCount,
            'pending' => $pendingCount,
            'aggregateDrift' => $aggregateDrift,
            'queueFailures' => $queueFailures,
            'schemaVersion' => CommentsPlugin::$plugin->schemaVersion,
        ];
    }


    // Private Methods
    // =========================================================================

    private function _scanV4Integrity(): array
    {
        $issues = [];

        $orphanParents = (new Query())
            ->select(['c.id'])
            ->from(['c' => '{{%comments_comments}}'])
            ->leftJoin(['p' => '{{%comments_comments}}'], '[[p.id]] = [[c.parentId]]')
            ->where(['not', ['c.parentId' => null]])
            ->andWhere(['p.id' => null])
            ->column();

        foreach ($orphanParents as $commentId) {
            $issues[] = [
                'severity' => 'error',
                'code' => 'orphan_parent',
                'message' => "Comment {$commentId} references a missing parent.",
                'commentId' => (int)$commentId,
            ];
        }

        $missingOwners = (new Query())
            ->select(['c.id', 'c.ownerId'])
            ->from(['c' => '{{%comments_comments}}'])
            ->leftJoin(['e' => '{{%elements}}'], '[[e.id]] = [[c.ownerId]]')
            ->where(['not', ['c.ownerId' => null]])
            ->andWhere(['e.id' => null])
            ->limit(25)
            ->all();

        foreach ($missingOwners as $row) {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'missing_owner',
                'message' => "Comment {$row['id']} references missing owner {$row['ownerId']}.",
                'commentId' => (int)$row['id'],
            ];
        }

        $conversationsMissingOwner = (new Query())
            ->select(['cc.id'])
            ->from(['cc' => '{{%comments_conversations}}'])
            ->leftJoin(['e' => '{{%elements}}'], '[[e.id]] = [[cc.ownerId]]')
            ->where(['not', ['cc.ownerId' => null]])
            ->andWhere(['e.id' => null])
            ->column();

        foreach ($conversationsMissingOwner as $conversationId) {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'conversation_missing_owner',
                'message' => "Conversation {$conversationId} references a missing owner element.",
                'conversationId' => (int)$conversationId,
            ];
        }

        return [
            'summary' => [
                'issues' => count($issues),
                'errors' => count(array_filter($issues, fn(array $i) => $i['severity'] === 'error')),
                'warnings' => count(array_filter($issues, fn(array $i) => $i['severity'] === 'warning')),
            ],
            'issues' => $issues,
        ];
    }

    private function _hasSeverity(array $issues, string $severity): bool
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === $severity) {
                return true;
            }
        }

        return false;
    }
}
