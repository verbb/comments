<?php
namespace verbb\comments\gql\mutations;

use verbb\comments\Comments;
use verbb\comments\gql\arguments\mutations\Comment as CommentMutationArguments;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\interfaces\Flag;
use verbb\comments\gql\interfaces\Vote;
use verbb\comments\gql\resolvers\mutations\Comment as CommentMutationResolver;
use verbb\comments\helpers\Gql as GqlHelper;
use verbb\comments\models\Settings;

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

        /** @var Settings $settings */
        $settings = Comments::$plugin->getSettings();
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

            if ($settings->allowVoting) {
                $mutationList['voteComment'] = [
                    'name' => 'voteComment',
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                        'siteId' => Type::id(),
                        'upvote' => Type::boolean(),
                        'downvote' => Type::boolean(),
                    ],
                    'resolve' => [$resolver, 'voteComment'],
                    'description' => 'Vote on a comment.',
                    'type' => Vote::getType(),
                ];
            }

            if ($settings->allowFlagging) {
                $mutationList['flagComment'] = [
                    'name' => 'flagComment',
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                        'siteId' => Type::id(),
                    ],
                    'resolve' => [$resolver, 'flagComment'],
                    'description' => 'Flag a comment.',
                    'type' => Flag::getType(),
                ];
            }

            if ($settings->notificationSubscribeEnabled) {
                $mutationList['subscribeComment'] = [
                    'name' => 'subscribeComment',
                    'args' => [
                        'id' => Type::id(),
                        'siteId' => Type::id(),
                        'ownerId' => Type::nonNull(Type::id()),
                    ],
                    'resolve' => [$resolver, 'subscribeComment'],
                    'description' => 'Toggle comment thread subscription.',
                    'type' => Type::string(),
                ];
            }
        }

        if (GqlHelper::canSchema($scope, 'delete')) {
            $mutationList['deleteComment'] = [
                'name' => 'deleteComment',
                'args' => [
                    'id' => Type::nonNull(Type::id()),
                    'siteId' => Type::id(),
                ],
                'resolve' => [$resolver, 'deleteComment'],
                'description' => 'Delete a comment.',
                'type' => Type::boolean(),
            ];
        }

        return $mutationList;
    }
}
