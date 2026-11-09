<?php
namespace verbb\comments\gql\types;

use verbb\comments\gql\interfaces\CommentInterface;
use verbb\comments\gql\resolvers\CommentResolver;
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
        // The hierarchy accessors load every state, so route them through the same public rules.
        if ($resolveInfo->fieldName === 'children') {
            return CommentResolver::resolve($source, $arguments, $context, $resolveInfo);
        }

        $value = parent::resolve($source, $arguments, $context, $resolveInfo);

        if ($resolveInfo->fieldName === 'parent' && GqlHelper::isPublicSchema() && !CommentResolver::isPubliclyReadable($value)) {
            return null;
        }

        return $value;
    }
}
