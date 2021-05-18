<?php
namespace verbb\comments\gql\queries;

use verbb\comments\gql\arguments\CommentArguments;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\resolvers\CommentResolver;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\gql\base\Query;

use GraphQL\Type\Definition\Type;

class CommentQuery extends Query
{
    // Public Methods
    // =========================================================================

    public static function getQueries($checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryComments()) {
            return [];
        }

        return [
            'comments' => [
                'type' => Type::listOf(CommentInterface::getType()),
                'args' => CommentArguments::getArguments(),
                'resolve' => CommentResolver::class . '::resolve',
                'description' => 'This query is used to query for comments.',
            ],
            'comment' => [
                'type' => CommentInterface::getType(),
                'args' => CommentArguments::getArguments(),
                'resolve' => CommentResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a comment.',
            ],
        ];
    }
}
