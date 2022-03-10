<?php
namespace verbb\comments\gql\types\generators;

use verbb\comments\elements\Comment;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\types\CommentType;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\SingleGeneratorInterface;

class CommentGenerator extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        // Comments have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): mixed
    {
        $context = $context ?: Craft::$app->getFields()->getLayoutByType(Comment::class);

        $typeName = Comment::gqlTypeNameByContext(null);
        $contentFieldGqlTypes = self::getContentFields($context);

        $commentFields = Craft::$app->getGql()->prepareFieldDefinitions(array_merge(
            CommentInterface::getFieldDefinitions(),
            $contentFieldGqlTypes
        ), $typeName);

        return GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new CommentType([
            'name' => $typeName,
            'fields' => function() use ($commentFields) {
                return $commentFields;
            },
        ]));
    }
}
