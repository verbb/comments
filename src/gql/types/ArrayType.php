<?php
namespace verbb\comments\gql\types;

use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

/**
 * The data API's JSON as a GraphQL value, so a GraphQL client reads exactly what `comments/api/*` returns.
 * It's named `CommentsArrayType` in the schema, so it can't clash with another plugin's scalar.
 */
class ArrayType extends ScalarType
{
    // Static Methods
    // =========================================================================

    public static function getName(): string
    {
        return 'CommentsArrayType';
    }

    public static function getType(): self
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new self(['name' => self::getName()]));
    }


    // Public Methods
    // =========================================================================

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value)
    {
        return $value;
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        return AST::valueFromASTUntyped($valueNode, $variables);
    }
}
