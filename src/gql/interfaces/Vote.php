<?php
namespace verbb\comments\gql\interfaces;

use verbb\comments\helpers\Gql as GqlHelper;
use verbb\comments\gql\types\Vote as VoteType;

use Craft;
use craft\gql\interfaces\elements\User;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Gql as CraftGqlHelper;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class Vote extends InterfaceType
{
    // Static Methods
    // =========================================================================

    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all comment votes.',
            'resolveType' => static function() {
                // register Vote type that implements this interface
                return GqlEntityRegistry::getEntity('Vote') ?: GqlEntityRegistry::createEntity('Vote', new VoteType([
                    'name' => 'Vote',
                    'fields' => self::getFieldDefinitions(),
                ]));
            },
        ]));
    }

    public static function getName(): string
    {
        return 'VoteInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(self::getConditionalFields(), [
            'id' => [
                'name' => 'id',
                'type' => Type::id(),
                'description' => 'The ID of the vote.',
            ],
            'sessionId' => [
                'name' => 'sessionId',
                'type' => Type::id(),
                'description' => 'The session ID from which the vote was submitted.',
            ],
            'lastIp' => [
                'name' => 'lastIp',
                'type' => Type::string(),
                'description' => 'The last known IP address of the voter.',
            ],
            'upvote' => [
                'name' => 'upvote',
                'type' => Type::boolean(),
                'description' => 'Whether the vote is positive.',
            ],
            'downvote' => [
                'name' => 'downvote',
                'type' => Type::boolean(),
                'description' => 'Whether the vote is negative.',
            ],
        ]), self::getName());
    }

    protected static function getConditionalFields(): array
    {
        $conditionalFields = [];

        if (CraftGqlHelper::canQueryUsers()) {
            $conditionalFields = array_merge($conditionalFields, [
                'userId' => [
                    'name' => 'userId',
                    'type' => Type::int(),
                    'description' => 'The ID of the submitter of this vote.',
                ],
                'user' => [
                    'name' => 'user',
                    'type' => User::getType(),
                    'description' => 'The vote‘s submitter.',
                ],
            ]);
        }

        if (GqlHelper::canQueryComments()) {
            $conditionalFields = array_merge($conditionalFields, [
                'commentId' => [
                    'name' => 'commentId',
                    'type' => Type::id(),
                    'description' => 'The ID of the comment the vote is applied to.',
                ],
                'comment' => [
                    'name' => 'comment',
                    'type' => CommentInterface::getType(),
                    'description' => 'The comment the vote is applied to.',
                ],
            ]);
        }

        return $conditionalFields;
    }
}
