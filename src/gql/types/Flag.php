<?php
namespace verbb\comments\gql\types;

use GraphQL\Type\Definition\ResolveInfo;
use verbb\comments\gql\interfaces\Flag as FlagInterface;
use craft\gql\base\ObjectType;

class Flag extends ObjectType
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            FlagInterface::getType(),
        ];

        parent::__construct($config);
    }

    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var \verbb\comments\models\Flag $source */
        $fieldName = $resolveInfo->fieldName;

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}