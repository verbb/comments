<?php
namespace verbb\comments\services;

use verbb\comments\enums\InteractionType;
use verbb\comments\records\Interaction as InteractionRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\StringHelper;

use yii\db\IntegrityException;

use InvalidArgumentException;

/**
 * One current Interaction per Identity/Comment. Upsert on unique (commentId, identityId).
 */
class Interactions extends Component
{
    // Public Methods
    // =========================================================================

    public function set(int $commentId, int $identityId, InteractionType $type): InteractionRecord
    {
        $existing = InteractionRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
        ]);

        if ($existing) {
            if ($existing->type === $type->value) {
                return $existing;
            }

            $existing->type = $type->value;
            $existing->save(false);

            return $existing;
        }

        $record = new InteractionRecord([
            'commentId' => $commentId,
            'identityId' => $identityId,
            'type' => $type->value,
            'uid' => StringHelper::UUID(),
        ]);

        try {
            $record->save(false);
        } catch (IntegrityException) {
            $existing = InteractionRecord::findOne([
                'commentId' => $commentId,
                'identityId' => $identityId,
            ]);

            if (!$existing) {
                throw new InvalidArgumentException('Failed to save Interaction after unique-index race.');
            }

            $existing->type = $type->value;
            $existing->save(false);

            return $existing;
        }

        return $record;
    }

    public function remove(int $commentId, int $identityId): bool
    {
        $existing = InteractionRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
        ]);

        if (!$existing) {
            return false;
        }

        return (bool)$existing->delete();
    }

    public function getForIdentity(int $commentId, int $identityId): ?InteractionRecord
    {
        return InteractionRecord::findOne([
            'commentId' => $commentId,
            'identityId' => $identityId,
        ]);
    }

    /** The viewer's interaction for each comment, keyed by comment ID. */
    public function getViewerState(array $commentIds, int $identityId): array
    {
        $commentIds = array_values(array_unique(array_filter(array_map('intval', $commentIds))));
        $result = array_fill_keys($commentIds, null);

        if ($commentIds === []) {
            return $result;
        }

        /** @var InteractionRecord[] $rows */
        $rows = InteractionRecord::find()
            ->where([
                'commentId' => $commentIds,
                'identityId' => $identityId,
            ])
            ->all();

        foreach ($rows as $row) {
            $result[(int)$row->commentId] = $row;
        }

        return $result;
    }

    public function getTotals(array $commentIds): array
    {
        $commentIds = array_values(array_unique(array_filter(array_map('intval', $commentIds))));
        $result = [];

        foreach ($commentIds as $id) {
            $result[$id] = ['upvote' => 0, 'downvote' => 0];
        }

        if ($commentIds === []) {
            return $result;
        }

        $rows = (new Query())
            ->select(['commentId', 'type', 'total' => 'COUNT(*)'])
            ->from('{{%comments_interactions}}')
            ->where(['commentId' => $commentIds])
            ->groupBy(['commentId', 'type'])
            ->all();

        foreach ($rows as $row) {
            $commentId = (int)$row['commentId'];
            $type = (string)$row['type'];

            if (isset($result[$commentId][$type])) {
                $result[$commentId][$type] = (int)$row['total'];
            }
        }

        return $result;
    }

    public function getForUser(int $userId, ?InteractionType $type = null): array
    {
        $identityId = (new Query())
            ->select(['id'])
            ->from('{{%comments_identities}}')
            ->where(['userId' => $userId])
            ->scalar();

        if (!$identityId) {
            return [];
        }

        $query = InteractionRecord::find()->where(['identityId' => (int)$identityId]);

        if ($type) {
            $query->andWhere(['type' => $type->value]);
        }

        return $query->all();
    }

    /**
     * Toggle an interaction: re-applying the active type clears it, applying the
     * opposite type switches to it (relevant only in up/down mode).
     *
     * Returns the current interaction type, or null when cleared.
     */
    public function toggle(int $commentId, int $identityId, InteractionType $requested): ?InteractionType
    {
        $existing = $this->getForIdentity($commentId, $identityId);
        $current = $existing ? InteractionType::tryFrom((string)$existing->type) : null;

        if ($current === $requested) {
            $this->remove($commentId, $identityId);

            return null;
        }

        $this->set($commentId, $identityId, $requested);

        return $requested;
    }

    /**
     * Toggle with v3 front-end semantics, where the caller only knew about a boolean upvote flag.
     */
    public function toggleVote(int $commentId, int $identityId, bool $upvote): ?InteractionType
    {
        return $this->toggle(
            $commentId,
            $identityId,
            $upvote ? InteractionType::Upvote : InteractionType::Downvote,
        );
    }

    /**
     * Batched presentation state for a set of Comments: totals plus the viewer's own interaction.
     *
     * Identity may be null for anonymous viewers, in which case only totals are populated.
     */
    public function getPresentationState(array $commentIds, ?int $identityId): array
    {
        $totals = $this->getTotals($commentIds);
        $viewerState = $identityId !== null
            ? $this->getViewerState($commentIds, $identityId)
            : [];

        $result = [];

        foreach ($totals as $commentId => $counts) {
            $viewer = $viewerState[$commentId] ?? null;

            $result[$commentId] = [
                'upvotes' => $counts['upvote'],
                'downvotes' => $counts['downvote'],
                'score' => $counts['upvote'] - $counts['downvote'],
                'viewer' => $viewer ? (string)$viewer->type : null,
            ];
        }

        return $result;
    }
}
