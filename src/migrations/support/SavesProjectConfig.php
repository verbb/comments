<?php
namespace verbb\comments\migrations\support;

use Craft;

/**
 * For upgrade migrations that change project config.
 *
 * Craft saves project config when the request ends. If `craft up` stops before then, from a timeout or a
 * failure in a later migration, the changes are lost while the migration stays recorded as applied, so
 * they'd never be made. Saving once the migration's work has committed, before Craft records it, means an
 * interrupted upgrade either keeps them or runs the migration again.
 */
trait SavesProjectConfig
{
    // Protected Methods
    // =========================================================================

    protected function afterUp(): void
    {
        self::saveProjectConfig();

        parent::afterUp();
    }

    protected static function saveProjectConfig(): void
    {
        Craft::$app->getProjectConfig()->flush();
    }
}
