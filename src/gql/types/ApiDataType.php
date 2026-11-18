<?php
namespace verbb\comments\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\Type;

/**
 * A data API response through GraphQL: its API version, and in `data` the same JSON `comments/api/*`
 * returns, without the `apiVersion` and `status` that this type and GraphQL itself give.
 */
class ApiDataType extends ObjectType
{
    // Static Methods
    // =========================================================================

    public static function getName(): string
    {
        return 'CommentsApiData';
    }

    public static function getType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new self([
            'name' => self::getName(),
            'fields' => [
                'apiVersion' => [
                    'name' => 'apiVersion',
                    'type' => Type::nonNull(Type::int()),
                ],
                'data' => [
                    'name' => 'data',
                    'type' => ArrayType::getType(),
                ],
            ],
        ]));
    }
}
