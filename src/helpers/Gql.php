<?php
namespace verbb\comments\helpers;

use Craft;
use craft\errors\GqlException;
use craft\helpers\Gql as GqlHelper;

class Gql extends GqlHelper
{
    // Constants
    // =========================================================================

    /** The schema permission to read commenters' email addresses, which only private schemas honour. */
    public const EMAILS_SCOPE = 'commentEmails:read';


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

    /**
     * Whether the active schema may read commenters' email addresses: a private schema given **View
     * commenters' email addresses** (SD-57). A public schema never may, whatever it's given.
     */
    public static function canReadCommentEmails(): bool
    {
        return !self::isPublicSchema() && self::canSchema('commentEmails', 'read');
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
