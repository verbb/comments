<?php
namespace verbb\comments\helpers;

use Craft;
use craft\errors\GqlException;
use craft\helpers\Gql as GqlHelper;

class Gql extends GqlHelper
{
    // Static Methods
    // =========================================================================

    public static function isPublicSchema(): bool
    {
        try {
            return Craft::$app->getGql()->getActiveSchema()->isPublic;
        } catch (GqlException) {
            return false;
        }
    }

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
