<?php
namespace verbb\comments\gql\types;

use GraphQL\Type\Definition\ResolveInfo;
use verbb\comments\gql\interfaces\Vote as VoteInterface;
use craft\gql\base\ObjectType;

class Vote extends ObjectType
{
    // Public Methods
    // =========================================================================

    public function __construct(array $config)
    {
        $config['interfaces'] = [
            VoteInterface::getType(),
        ];

        parent::__construct($config);
    }


    // Protected Methods
    // =========================================================================

    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var \verbb\comments\models\Vote $source */
        $fieldName = $resolveInfo->fieldName;

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}