<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\records\Conversation as ConversationRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\helpers\Db;

use yii\base\UserException;

use verbb\editor\Editor;
use verbb\editor\RichText;

/**
 * Comment mention eligibility, persistence and notification delta helpers.
 */
class Mentions extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Resolve eligible Craft user IDs for mention discovery on an owner element.
     */
    public function eligibleUserIdsForOwner(int $ownerId, int $siteId): array
    {
        $eligible = [];

        $conversation = ConversationRecord::findOne([
            'ownerId' => $ownerId,
            'siteId' => $siteId,
        ]);

        if ($conversation) {
            $participantUserIds = (new Query())
                ->select(['userId'])
                ->from('{{%comments_comments}}')
                ->where([
                    'conversationId' => (int)$conversation->id,
                ])
                ->andWhere(['not', ['userId' => null]])
                ->column();

            foreach ($participantUserIds as $userId) {
                $eligible[(int)$userId] = true;
            }
        }

        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if ($owner && method_exists($owner, 'getAuthor')) {
            $author = $owner->getAuthor();

            if ($author instanceof User) {
                $eligible[(int)$author->id] = true;
            }
        }

        foreach ($this->_moderatorUserIds() as $userId) {
            $eligible[$userId] = true;
        }

        return array_values(array_map('intval', array_keys($eligible)));
    }

    /**
     * Validate extracted mention references and return unique Identity IDs to persist.
     */
    public function resolveIdentityIdsFromRichText(
        RichText $richText,
        ?int $actorUserId,
        int $ownerId,
        int $siteId,
    ): array {
        $settings = CommentsPlugin::$plugin->getSettings();

        $mentions = Editor::getInstance()->getDocuments()->extractMentions($richText);

        if ($mentions === []) {
            return [];
        }

        if (!$settings->allowMentions) {
            throw new UserException(Craft::t('comments', 'Mentions are disabled for comments.'));
        }

        if (!$actorUserId) {
            throw new UserException(Craft::t('comments', 'You must be logged in to mention someone.'));
        }

        $eligibleUserIds = array_fill_keys($this->eligibleUserIdsForOwner($ownerId, $siteId), true);
        $identityIds = [];
        $identities = CommentsPlugin::$plugin->getIdentities();

        foreach ($mentions as $mention) {
            if ($mention->referenceType() !== User::class) {
                throw new UserException(Craft::t('comments', 'Invalid mention reference.'));
            }

            $userId = (int)$mention->referenceId();

            if ($userId <= 0 || !isset($eligibleUserIds[$userId])) {
                throw new UserException(Craft::t('comments', 'One or more mentions are not allowed.'));
            }

            $user = Craft::$app->getUsers()->getUserById($userId);

            if (!$user instanceof User || $user->suspended) {
                throw new UserException(Craft::t('comments', 'One or more mentions are not allowed.'));
            }

            $identity = $identities->getOrCreateForUser($user);
            $identityIds[(int)$identity->id] = true;
        }

        return array_values(array_map('intval', array_keys($identityIds)));
    }

    public function getIdentityIdsForComment(int $commentId): array
    {
        $rows = (new Query())
            ->select(['identityId'])
            ->from('{{%comments_mentions}}')
            ->where(['commentId' => $commentId])
            ->column();

        return array_values(array_map('intval', $rows));
    }

    /**
     * Replace mention rows for a comment.
     *
     * Returns the identity IDs now stored.
     */
    public function syncIdentityIds(int $commentId, array $identityIds): array
    {
        $identityIds = array_values(array_unique(array_map('intval', $identityIds)));

        $db = Craft::$app->getDb();

        $db->createCommand()
            ->delete('{{%comments_mentions}}', ['commentId' => $commentId])
            ->execute();

        foreach ($identityIds as $identityId) {
            $db->createCommand()
                ->insert('{{%comments_mentions}}', [
                    'commentId' => $commentId,
                    'identityId' => $identityId,
                ])
                ->execute();
        }

        return $identityIds;
    }

    public function newlyAddedIdentityIds(array $previousIdentityIds, array $currentIdentityIds, ?int $authorIdentityId = null): array
    {
        $previous = array_fill_keys($previousIdentityIds, true);
        $added = [];

        foreach ($currentIdentityIds as $identityId) {
            $identityId = (int)$identityId;

            if ($authorIdentityId !== null && $identityId === (int)$authorIdentityId) {
                continue;
            }

            if (!isset($previous[$identityId])) {
                $added[] = $identityId;
            }
        }

        return $added;
    }


    // Private Methods
    // =========================================================================

    private function _moderatorUserIds(): array
    {
        $settings = CommentsPlugin::$plugin->getSettings();
        $groupUid = $settings->moderatorUserGroup;

        if (!$groupUid) {
            return [];
        }

        $group = Craft::$app->getUserGroups()->getGroupByUid($groupUid);

        if (!$group) {
            return [];
        }

        $userIds = (new Query())
            ->select(['elements.id'])
            ->from(['elements' => Table::ELEMENTS])
            ->innerJoin(['users' => Table::USERS], '[[users.id]] = [[elements.id]]')
            ->innerJoin(['ug' => Table::USERGROUPS_USERS], '[[ug.userId]] = [[users.id]]')
            ->where([
                'ug.groupId' => (int)$group->id,
                'elements.dateDeleted' => null,
                'elements.draftId' => null,
                'elements.revisionId' => null,
            ])
            ->column();

        return array_values(array_map('intval', $userIds));
    }
}
