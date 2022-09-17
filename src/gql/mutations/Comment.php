<?php
namespace verbb\comments\gql\mutations;

use verbb\comments\Comments;
use verbb\comments\gql\arguments\mutations\Comment as CommentMutationArguments;
use verbb\comments\gql\interfaces\Flag;
use verbb\comments\gql\interfaces\Vote;
use verbb\comments\gql\resolvers\mutations\Comment as CommentMutationResolver;
use verbb\comments\helpers\Gql as GqlHelper;
use verbb\comments\models\Settings;
use verbb\comments\gql\types\generators\CommentGenerator;
use verbb\comments\elements\Comment as CommentElement;

use Craft;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\Mutation;

use GraphQL\Type\Definition\Type;

use yii\base\InvalidConfigException;

class Comment extends Mutation
{
    // Static Methods
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
            $mutationList['createComment'] = self::createSaveMutation(
                'createComment',
                'saveComment',
                'Create a comment.'
            );
        }

        if (GqlHelper::canSchema($scope, 'save')) {
            $mutationList['saveComment'] = self::createSaveMutation(
                'saveComment',
                'saveComment',
                'Save a comment.'
            );

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
                        'ownerId' => Type::nonNull(Type::id()),
                        'siteId' => Type::id(),
                        'commentId' => Type::id(),
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

    /**
     * Create the save mutation.
     *
     * @param string $mutationName Mutation name.
     * @param string $resolveMethod Resolver method (also used for mutation name).
     * @param string $description Mutation description.
     * @return array
     * @throws InvalidConfigException
     */
    public static function createSaveMutation(string $mutationName, string $resolveMethod, string $description): array
    {
        // Only one context
        $context = Craft::$app->getFields()->getLayoutByType(CommentElement::class);
        $mutationArguments = CommentMutationArguments::getArguments();
        $generatedType = CommentGenerator::generateType(null);
        /** @var CommentMutationResolver $resolver */
        $resolver = Craft::createObject(CommentMutationResolver::class);

        // Prepare resolver with custom fields
        static::prepareResolver($resolver, $context->getCustomFields());

        $mutationArguments = array_merge(
            $mutationArguments,
            $resolver->getResolutionData(ElementMutationResolver::CONTENT_FIELD_KEY)
        );

        return [
            'name' => $resolveMethod,
            'description' => $description,
            'args' => $mutationArguments,
            'resolve' => [$resolver, $resolveMethod],
            'type' => $generatedType,
        ];
    }
}
