<?php
namespace verbb\comments\gql\types;

use verbb\comments\Comments;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\DateTime;
use craft\helpers\DateTimeHelper;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ConversationType
{
    // Static Methods
    // =========================================================================

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate('CommentConversation', static fn() => new ObjectType([
            'name' => 'CommentConversation',
            'fields' => [
                'ownerId' => ['type' => Type::nonNull(Type::int()), 'description' => 'The ID of the element the conversation is on.'],
                'siteId' => ['type' => Type::nonNull(Type::int()), 'description' => 'The ID of the site the conversation is on.'],
                'commentCount' => ['type' => Type::nonNull(Type::int()), 'description' => 'The number of published comments.'],
                'lastActivityAt' => ['type' => DateTime::getType(), 'description' => 'When the latest published comment was made.'],
                'open' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether new comments can be posted.'],
            ],
        ]));
    }

    /**
     * Elements with comments turned off return nothing, the same as they show nothing on the site.
     */
    public static function resolve(mixed $source, array $arguments): ?array
    {
        $ownerId = (int)$arguments['ownerId'];
        $siteId = (int)($arguments['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id);
        $conversations = Comments::$plugin->getConversations();

        $owner = Craft::$app->getElements()->getElementById($ownerId, null, $siteId);

        if (!$owner || !$conversations->isOwnerPublic($owner)) {
            return null;
        }

        $config = $conversations->getConfigForOwner($ownerId, $siteId);

        if (!$config->enabled) {
            return null;
        }

        $summary = $conversations->getSummaryForOwner($ownerId, $siteId);

        return [
            'ownerId' => $ownerId,
            'siteId' => $siteId,
            'commentCount' => $summary?->commentCount ?? 0,
            'lastActivityAt' => $summary?->lastActivityAt ? DateTimeHelper::toDateTime($summary->lastActivityAt) : null,
            'open' => $config->isCommentingEnabled(),
        ];
    }
}
