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
use verbb\comments\services\FormFields;

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
                        'id' => Type::id(),
                        'uid' => Type::string(),
                        'siteId' => Type::id(),
                        'upvote' => Type::boolean(),
                        'downvote' => Type::boolean(),
                        'vote' => [
                            'type' => Type::string(),
                            'description' => 'Set the vote to `upvote`, `downvote` or null for none, rather than toggling it.',
                        ],
                        'operationId' => [
                            'type' => Type::string(),
                            'description' => 'A unique ID for this change. A repeat with the same ID within ten minutes returns the first result rather than acting again.',
                        ],
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
                        'id' => Type::id(),
                        'uid' => Type::string(),
                        'siteId' => Type::id(),
                        'reason' => Type::string(),
                        'details' => Type::string(),
                        'withdraw' => [
                            'type' => Type::boolean(),
                            'description' => 'Set the report rather than toggling it: true withdraws the asker’s report, false makes one.',
                        ],
                        'operationId' => [
                            'type' => Type::string(),
                            'description' => 'A unique ID for this change. A repeat with the same ID within ten minutes returns the first result rather than acting again.',
                        ],
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
                        'following' => [
                            'type' => Type::boolean(),
                            'description' => 'Set whether the asker follows the conversation, rather than toggling it, and return `followed`, `confirming` or `unfollowed`. A guest gives `email`.',
                        ],
                        'email' => Type::string(),
                        'operationId' => [
                            'type' => Type::string(),
                            'description' => 'A unique ID for this change. A repeat with the same ID within ten minutes returns the first result rather than acting again.',
                        ],
                    ],
                    'resolve' => [$resolver, 'subscribeComment'],
                    'description' => 'Toggle comment thread subscription, or set it with `following`.',
                    'type' => Type::string(),
                ];
            }
        }

        if (GqlHelper::canSchema($scope, 'delete')) {
            $mutationList['deleteComment'] = [
                'name' => 'deleteComment',
                'args' => [
                    'id' => Type::id(),
                    'uid' => Type::string(),
                    'siteId' => Type::id(),
                    'operationId' => [
                        'type' => Type::string(),
                        'description' => 'A unique ID for this change. A repeat with the same ID within ten minutes returns the first result rather than acting again.',
                    ],
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
                    'id' => Type::id(),
                    'uid' => Type::string(),
                    'action' => [
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'One of `approve`, `remove`, `restore`, `spam`, `not-spam`, `keep` or `hold`.',
                    ],
                    'reason' => Type::string(),
                    'note' => [
                        'type' => Type::string(),
                        'description' => 'A note for other moderators, kept with the comment.',
                    ],
                    'operationId' => [
                        'type' => Type::string(),
                        'description' => 'A unique ID for this change. A repeat with the same ID within ten minutes returns the first result rather than acting again.',
                    ],
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

        // Only fields set to Collect or Collect and show can be sent, and each request is then held to the
        // fields its person is offered on that comment, as through the data API.
        $formFields = Comments::$plugin->getFormFields();
        $collectable = [];

        foreach ($context->getCustomFieldElements() as $element) {
            $field = $element->getField();

            if ($formFields->settingFor((string)$element->uid)['mode'] !== FormFields::MODE_OFF && $formFields->unsupportedReason($field) === null) {
                $collectable[] = $field;
            }
        }

        static::prepareResolver($resolver, $collectable);

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
