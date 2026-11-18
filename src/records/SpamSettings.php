<?php
namespace verbb\comments\records;

use verbb\comments\services\SpamSettings as SpamSettingsService;

use craft\db\ActiveRecord;

/**
 * The installation's one row of Spam settings.
 *
 * @property int $id
 * @property string $settings
 */
class SpamSettings extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    public static function tableName(): string
    {
        return SpamSettingsService::TABLE;
    }
}
