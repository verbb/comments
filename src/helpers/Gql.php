<?php
namespace verbb\comments\helpers;

use Craft;
use craft\errors\GqlException;
use craft\helpers\Gql as GqlHelper;

class Gql extends GqlHelper
{
    // Static Methods
    // =========================================================================

    public static function canQueryComments($schema = null): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('read', $schema);

        return isset($allowedEntities['comments']);
    }

    public static function isPublicSchema(): bool
    {
        try {
            return Craft::$app->getGql()->getActiveSchema()->isPublic;
        } catch (GqlException) {
            return false;
        }
    }

    public static function canMutateComments($schema = null): bool
    {
        foreach (['edit', 'save', 'delete', 'moderate'] as $action) {
            if (isset(self::extractAllowedEntitiesFromSchema($action, $schema)['comments'])) {
                return true;
            }
        }

        return false;
    }
}
