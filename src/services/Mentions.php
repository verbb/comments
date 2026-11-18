<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\enums\ModerationState;
use verbb\comments\records\Conversation as ConversationRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;

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
     * The users who can be mentioned on an owner's thread, for suggestions and for checking a comment's
     * mentions: everyone with an approved comment there that the thread shows, and the element's author.
     * A moderator is only included once they've commented publicly there, and someone whose only comments
     * are waiting, marked as spam or removed isn't, so suggestions never reveal who that is (stage C's
     * L8, SD-46). With `$commentId`, the people that comment already mentions stay mentionable, so its
     * author can edit it and it still shows its mentions.
     */
    public function eligibleUserIdsForOwner(int $ownerId, int $siteId, ?int $commentId = null): array
    {
        $eligible = [];

        $conversation = ConversationRecord::findOne([
            'ownerId' => $ownerId,
            'siteId' => $siteId,
        ]);

        if ($conversation) {
            $participantUserIds = (new Query())
                ->select(['comments.userId'])
                ->distinct()
                ->from(['comments' => '{{%comments_comments}}'])
                ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[comments.id]]')
                ->where([
                    'comments.conversationId' => (int)$conversation->id,
                    'comments.moderationState' => ModerationState::Approved->value,
                    'comments.authorRemovedAt' => null,
                    'elements.dateDeleted' => null,
                ])
                ->andWhere(['not', ['comments.userId' => null]])
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

        if ($commentId) {
            foreach ($this->mentionedUserIds($commentId) as $userId) {
                $eligible[$userId] = true;
            }
        }

        return array_values(array_map('intval', array_keys($eligible)));
    }

    /**
     * The users a comment already mentions.
     */
    public function mentionedUserIds(int $commentId): array
    {
        return array_map('intval', (new Query())
            ->select(['identities.userId'])
            ->from(['mentions' => '{{%comments_mentions}}'])
            ->innerJoin(['identities' => '{{%comments_identities}}'], '[[identities.id]] = [[mentions.identityId]]')
            ->where(['mentions.commentId' => $commentId])
            ->andWhere(['not', ['identities.userId' => null]])
            ->column());
    }

    /**
     * Validate extracted mention references and return unique Identity IDs to persist.
     */
    public function resolveIdentityIdsFromRichText(
        RichText $richText,
        ?int $actorUserId,
        int $ownerId,
        int $siteId,
        ?int $commentId = null,
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

        $eligibleUserIds = array_fill_keys($this->eligibleUserIdsForOwner($ownerId, $siteId, $commentId), true);
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
}
