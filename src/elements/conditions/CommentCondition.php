<?php
namespace verbb\comments\elements\conditions;

use craft\elements\conditions\ElementCondition;

class CommentCondition extends ElementCondition
{
    // Protected Methods
    // =========================================================================
    
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            CommentConditionRule::class,
            EmailConditionRule::class,
            NameConditionRule::class,
            OwnerConditionRule::class,
            StatusConditionRule::class,
            UrlConditionRule::class,
        ]);
    }
}