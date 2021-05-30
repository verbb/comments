<?php
namespace verbb\comments\gql\types;

use GraphQL\Type\Definition\ResolveInfo;
use verbb\comments\gql\interfaces\Vote as VoteInterface;
use craft\gql\base\ObjectType;

class Vote extends ObjectType
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            VoteInterface::getType(),
        ];

        parent::__construct($config);
    }

    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
    {
        /** @var \verbb\comments\models\Vote $source */
        $fieldName = $resolveInfo->fieldName;

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}