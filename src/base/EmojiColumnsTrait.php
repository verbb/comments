<?php
namespace verbb\comments\base;

use verbb\comments\helpers\StringHelper;

/**
 * For records with free-text columns people can type emoji into, such as names and notes. A table
 * never converted to utf8mb4 refuses 4-byte characters, so emoji are stored there as shortcodes
 * (see `StringHelper::emojiForDb()`) and turned back as the record loads. Code using the record
 * always sees emoji, before and after it's saved.
 */
trait EmojiColumnsTrait
{
    // Static Methods
    // =========================================================================

    public static function populateRecord($record, $row)
    {
        foreach (static::emojiColumns() as $column) {
            if (isset($row[$column]) && is_string($row[$column])) {
                $row[$column] = StringHelper::shortcodesToEmoji($row[$column]);
            }
        }

        parent::populateRecord($record, $row);
    }

    /**
     * The columns stored with emoji as shortcodes where the table can't hold them.
     */
    abstract protected static function emojiColumns(): array;


    // Properties
    // =========================================================================

    private array $_emojiColumnValues = [];


    // Public Methods
    // =========================================================================

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->_emojiColumnValues = [];

        foreach (static::emojiColumns() as $column) {
            $value = $this->getAttribute($column);
            $stored = is_string($value) ? StringHelper::emojiForDb($value, static::tableName()) : $value;

            // Kept to put back once it's saved.
            if ($stored !== $value) {
                $this->_emojiColumnValues[$column] = $value;
                $this->setAttribute($column, $stored);
            }
        }

        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        // The record goes on holding emoji, as it would if it were loaded again.
        foreach ($this->_emojiColumnValues as $column => $value) {
            $this->setAttribute($column, $value);
            $this->setOldAttribute($column, $value);
        }

        $this->_emojiColumnValues = [];

        parent::afterSave($insert, $changedAttributes);
    }
}
