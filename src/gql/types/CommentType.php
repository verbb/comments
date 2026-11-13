<?php
namespace verbb\comments\gql\types;

use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\helpers\Gql as GqlHelper;

use craft\gql\types\elements\Element;

use GraphQL\Type\Definition\ResolveInfo;

class CommentType extends Element
{
    // Public Methods
    // =========================================================================

    public function __construct(array $config)
    {
        $config['interfaces'] = [
            CommentInterface::getType(),
        ];

        parent::__construct($config);
    }


    // Protected Methods
    // =========================================================================

    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        // Anyone can query a public schema, so it never returns a commenter's email address. The field
        // stays in the schema so existing queries that ask for it keep working.
        if ($resolveInfo->fieldName === 'email' && GqlHelper::isPublicSchema()) {
            return null;
        }

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}
