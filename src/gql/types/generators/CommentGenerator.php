<?php
namespace verbb\comments\gql\types\generators;

use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\types\CommentType;

use Craft;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class CommentGenerator extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    // Static Methods
    // =========================================================================

    public static function generateTypes(mixed $context = null): array
    {
        // Comments have no context
        $type = static::generateType($context);

        return [$type->name => $type];
    }

    public static function generateType(mixed $context): mixed
    {
        $context = $context ?: Craft::$app->getFields()->getLayoutByType(Comment::class);

        $typeName = Comment::gqlTypeNameByContext(null);
        $contentFieldGqlTypes = array_map(self::_privateWhereNotShown(...), self::getContentFields($context));

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

    /**
     * Whether the request may read custom field `$handle` of `$source`: every field on a schema that can
     * moderate, otherwise only one the comment shows publicly.
     */
    public static function canRead(mixed $source, string $handle): bool
    {
        $formFields = Comments::$plugin->getFormFields();

        return $formFields->canReadEveryField() || ($source instanceof Comment && $formFields->isShownOn($source, $handle));
    }

    /**
     * A custom field's definition, answering null on a schema that can't moderate unless the comment shows
     * the field publicly. Field types with their own resolver, such as relation fields, resolve without
     * going through `CommentType`, so the check is made here for them too. A value that's hidden is null,
     * so the type can't insist on one.
     */
    private static function _privateWhereNotShown(mixed $definition): mixed
    {
        if (!is_array($definition)) {
            return Type::getNullableType($definition);
        }

        if (isset($definition['type']) && $definition['type'] instanceof Type) {
            $definition['type'] = Type::getNullableType($definition['type']);
        }

        if (isset($definition['resolve'])) {
            $resolve = $definition['resolve'];

            $definition['resolve'] = static function(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo) use ($resolve): mixed {
                if (!self::canRead($source, $resolveInfo->fieldName)) {
                    return null;
                }

                return call_user_func($resolve, $source, $arguments, $context, $resolveInfo);
            };
        }

        return $definition;
    }
}
