<?php
namespace verbb\comments\gql\mutations;

use verbb\comments\gql\arguments\mutations\Comment as CommentMutationArguments;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\resolvers\mutations\Comment as CommentMutationResolver;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\gql\base\Mutation;
use Craft;

use GraphQL\Type\Definition\Type;

class Comment extends Mutation
{
    // Public Methods
    // =========================================================================

    public static function getMutations(): array
    {
        if (!GqlHelper::canMutateComments()) {
            return [];
        }

        $mutationList = [];
        $scope = 'comments';
        $resolver = Craft::createObject(CommentMutationResolver::class);

        if (GqlHelper::canSchema($scope, 'edit')) {
            $mutationList['createComment'] = [
                'name' => 'createComment',
                'args' => CommentMutationArguments::getArguments(),
                'resolve' => [$resolver, 'saveComment'],
                'description' => 'Create a comment.',
                'type' => CommentInterface::getType(),
            ];
        }

        if (GqlHelper::canSchema($scope, 'save')) {
            $mutationList['saveComment'] = [
                'name' => 'saveComment',
                'args' => CommentMutationArguments::getArguments(),
                'resolve' => [$resolver, 'saveComment'],
                'description' => 'Save a comment.',
                'type' => CommentInterface::getType(),
            ];

            $mutationList['voteComment'] = [
                'name' => 'voteComment',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'siteId' => Type::int(),
                    'upvote' => Type::boolean(),
                    'downvote' => Type::boolean(),
                ],
                'resolve' => [$resolver, 'voteComment'],
                'description' => 'Vote on a comment.',
                'type' => CommentInterface::getType(),
            ];

            $mutationList['flagComment'] = [
                'name' => 'flagComment',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'siteId' => Type::int(),
                ],
                'resolve' => [$resolver, 'flagComment'],
                'description' => 'Flag a comment.',
                'type' => CommentInterface::getType(),
            ];
        }

        if (GqlHelper::canSchema($scope, 'delete')) {
            $mutationList['deleteComment'] = [
                'name' => 'deleteComment',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'siteId' => Type::int(),
                ],
                'resolve' => [$resolver, 'deleteComment'],
                'description' => 'Delete a comment.',
                'type' => Type::boolean(),
            ];
        }

        return $mutationList;
    }
}