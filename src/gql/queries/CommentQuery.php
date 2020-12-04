<?php
namespace verbb\comments\gql\queries;

use verbb\comments\gql\arguments\CommentArguments;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\resolvers\CommentResolver;

use craft\gql\base\Query;
use craft\helpers\Gql as GqlHelper;

use GraphQL\Type\Definition\Type;

class CommentQuery extends Query
{
    // Public Methods
    // =========================================================================

    public static function getQueries($checkToken = true): array
    {
        $canQueryComments = (bool)isset(GqlHelper::extractAllowedEntitiesFromSchema()['comments']);

        if ($checkToken && !$canQueryComments) {
            return [];
        }

        return [
            'comments' => [
                'type' => Type::listOf(CommentInterface::getType()),
                'args' => CommentArguments::getArguments(),
                'resolve' => CommentResolver::class . '::resolve',
                'description' => 'This query is used to query for comments.',
            ],
        ];
    }
}
