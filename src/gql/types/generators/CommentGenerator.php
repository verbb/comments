<?php
namespace verbb\comments\gql\types\generators;

use verbb\comments\elements\Comment;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\types\CommentType;

use craft\gql\GqlEntityRegistry;
use craft\gql\TypeManager;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\SingleGeneratorInterface;
use Craft;

class CommentGenerator extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function generateTypes($context = null): array
    {
        // Comments have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType($context)
    {
        $context = $context ?: Craft::$app->getFields()->getLayoutByType(Comment::class);

        $typeName = Comment::gqlTypeNameByContext(null);
        $contentFieldGqlTypes = self::getContentFields($context);

        $commentFields = TypeManager::prepareFieldDefinitions(array_merge(
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
