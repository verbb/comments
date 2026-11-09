<?php
namespace verbb\comments\gql\mutations;

use verbb\comments\Comments;
use verbb\comments\elements\Comment as CommentElement;
use verbb\comments\gql\arguments\mutations\Comment as CommentMutationArguments;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\interfaces\Flag;
use verbb\comments\gql\interfaces\Vote;
use verbb\comments\gql\resolvers\mutations\Comment as CommentMutationResolver;
use verbb\comments\gql\types\generators\CommentGenerator;
use verbb\comments\helpers\Gql as GqlHelper;
use verbb\comments\models\Settings;

use Craft;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\Mutation;

use yii\base\InvalidConfigException;

use GraphQL\Type\Definition\Type;

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

            if ($settings->resolveInteractionMode()->isEnabled()) {
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
                        'reason' => Type::string(),
                        'details' => Type::string(),
                    ],
                    'resolve' => [$resolver, 'flagComment'],
                    'description' => 'Flag a comment.',
                    'type' => Flag::getType(),
                ];
            }

            if ($settings->subscriptionTypes !== []) {
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

        if (GqlHelper::canSchema($scope, 'moderate')) {
            $mutationList['moderateComment'] = [
                'name' => 'moderateComment',
                'args' => [
                    'id' => Type::nonNull(Type::id()),
                    'action' => [
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'One of `approve`, `remove`, `restore`, `spam` or `notSpam`.',
                    ],
                    'reason' => Type::string(),
                ],
                'resolve' => [$resolver, 'moderateComment'],
                'description' => 'Moderate a comment. Needs a signed-in user who can moderate, as well as this schema permission.',
                'type' => CommentInterface::getType(),
            ];
        }

        return $mutationList;
    }

    /**
     * Create the save mutation.
     *
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
        $publicFields = Comments::$plugin->getSettings()->publicFields;
        static::prepareResolver($resolver, array_filter(
            $context->getCustomFields(),
            static fn($field) => in_array($field->handle, $publicFields, true),
        ));

        $mutationArguments = array_merge(
            $mutationArguments,
            $resolver->getResolutionData(ElementMutationResolver::CONTENT_FIELD_KEY)
        );

        return [
            'name' => $mutationName,
            'description' => $description,
            'args' => $mutationArguments,
            'resolve' => [$resolver, $resolveMethod],
            'type' => $generatedType,
        ];
    }
}
