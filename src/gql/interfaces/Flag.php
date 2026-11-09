<?php
namespace verbb\comments\gql\interfaces;

use verbb\comments\gql\types\Flag as FlagType;
use verbb\comments\helpers\Gql as GqlHelper;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\elements\User;
use craft\helpers\Gql as CraftGqlHelper;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class Flag extends InterfaceType
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
            'description' => 'This is the interface implemented by all comment flags.',
            'resolveType' => static function() {
                // register Flag type that implements this interface
                return GqlEntityRegistry::getEntity('Flag') ?: GqlEntityRegistry::createEntity('Flag', new FlagType([
                    'name' => 'Flag',
                    'fields' => self::getFieldDefinitions(),
                ]));
            },
        ]));
    }

    public static function getName(): string
    {
        return 'FlagInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(self::getConditionalFields(), [
            'id' => [
                'name' => 'id',
                'type' => Type::id(),
                'description' => 'The ID of the flag.',
            ],
            'sessionId' => [
                'name' => 'sessionId',
                'type' => Type::id(),
                'description' => 'The session ID from which the flag was submitted.',
            ],
            'lastIp' => [
                'name' => 'lastIp',
                'type' => Type::string(),
                'description' => 'The last known IP address of the person flagging.',
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
                    'description' => 'The ID of the submitter of this flag.',
                ],
                'user' => [
                    'name' => 'user',
                    'type' => User::getType(),
                    'description' => 'The flag’s submitter.',
                ],
            ]);
        }

        if (GqlHelper::canQueryComments()) {
            $conditionalFields = array_merge($conditionalFields, [
                'commentId' => [
                    'name' => 'commentId',
                    'type' => Type::id(),
                    'description' => 'The ID of the comment the flag is applied to.',
                ],
                'comment' => [
                    'name' => 'comment',
                    'type' => CommentInterface::getType(),
                    'description' => 'The comment the flag is applied to.',
                ],
            ]);
        }

        return $conditionalFields;
    }
}
