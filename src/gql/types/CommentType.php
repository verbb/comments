<?php
namespace verbb\comments\gql\types;

use verbb\comments\gql\interfaces\CommentInterface;

use craft\gql\base\ObjectType;
use craft\gql\interfaces\Element as ElementInterface;

use GraphQL\Type\Definition\ResolveInfo;

class CommentType extends ObjectType
{
    // Public Methods
    // =========================================================================

    public function __construct(array $config)
    {
        $config['interfaces'] = [
            CommentInterface::getType(),
            ElementInterface::getType(),
        ];

        parent::__construct($config);
    }

    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
    {
        $fieldName = $resolveInfo->fieldName;

        return $source->$fieldName;
    }
}
