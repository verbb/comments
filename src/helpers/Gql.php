<?php
namespace verbb\comments\helpers;

use craft\helpers\Gql as GqlHelper;
use craft\models\GqlSchema;

class Gql extends GqlHelper
{
    // Public Methods
    // =========================================================================

    public static function canQueryComments($schema = null): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('read', $schema);

        return isset($allowedEntities['comments']);
    }

    public static function canMutateComments($schema = null): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('edit', $schema);

        return isset($allowedEntities['comments']);
    }
}
