<?php
namespace verbb\comments\gql\types\generators;

use verbb\comments\elements\Comment;
use verbb\comments\gql\arguments\CommentArguments;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\types\CommentType;

use Craft;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;

class CommentGenerator implements GeneratorInterface
{
    // Public Methods
    // =========================================================================

    public static function generateTypes($context = null): array
    {
        $gqlTypes = [];
        $typeName = Comment::gqlTypeNameByContext(null);

        $contentFields = Craft::$app->getFields()->getLayoutByType(Comment::class)->getFields();
        $contentFieldGqlTypes = [];

        /** @var Field $contentField */
        foreach ($contentFields as $contentField) {
            $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
        }

        $commentFields = array_merge(CommentInterface::getFieldDefinitions(), $contentFieldGqlTypes);

        // Generate a type for each entry type
        $gqlTypes[$typeName] = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new CommentType([
            'name' => $typeName,
            'fields' => function() use ($commentFields) {
                return $commentFields;
            }
        ]));

        return $gqlTypes;
    }
}
