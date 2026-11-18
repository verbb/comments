<?php
namespace verbb\comments\gql\queries;

use verbb\comments\gql\resolvers\ApiResolver;
use verbb\comments\gql\types\ApiDataType;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\gql\base\Query;

use GraphQL\Type\Definition\Type;

/**
 * The data API's reads through GraphQL, built by the same code as `comments/api/*`, so a client gets the
 * same data whichever it uses.
 */
class ApiQuery extends Query
{
    // Static Methods
    // =========================================================================

    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryComments()) {
            return [];
        }

        $owner = [
            'ownerId' => Type::id(),
            'ownerUri' => Type::string(),
            'siteId' => Type::id(),
        ];
        $window = [
            'sort' => Type::string(),
            'limit' => Type::int(),
            'cursor' => Type::string(),
        ];

        return [
            'commentsThread' => [
                'type' => ApiDataType::getType(),
                'args' => $owner + $window,
                'resolve' => ApiResolver::class . '::resolveThread',
                'description' => 'A page of a thread, as the data API’s `thread` returns it.',
            ],
            'commentsReplies' => [
                'type' => ApiDataType::getType(),
                'args' => [
                    'parentUid' => Type::nonNull(Type::string()),
                    'limit' => Type::int(),
                    'cursor' => Type::string(),
                ],
                'resolve' => ApiResolver::class . '::resolveReplies',
                'description' => 'A page of a comment’s replies, as the data API’s `replies` returns it.',
            ],
            'commentsComment' => [
                'type' => ApiDataType::getType(),
                'args' => [
                    'uid' => Type::string(),
                    'id' => Type::int(),
                ],
                'resolve' => ApiResolver::class . '::resolveComment',
                'description' => 'One comment in its thread, by `uid` or by `id`, the number in its permalink, as the data API’s `comment` returns it.',
            ],
            'commentsViewer' => [
                'type' => ApiDataType::getType(),
                'args' => $owner + $window + [
                    'uids' => Type::listOf(Type::string()),
                    'ids' => Type::listOf(Type::int()),
                ],
                'resolve' => ApiResolver::class . '::resolveViewer',
                'description' => 'Everything personal for a thread’s window, as the data API’s `viewer` returns it. Never cached.',
            ],
        ];
    }
}
