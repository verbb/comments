<?php
namespace verbb\comments\migrations\support;

use craft\helpers\Db;

/**
 * Helpers for v4 schema migrations that may be re-run after interrupted bootstrap.
 *
 * Index creation uses Craft's Migration::createIndexIfMissing().
 */
trait ResumableSchemaMigration
{
    // Protected Methods
    // =========================================================================

    protected function addForeignKeyIfMissing(
        ?string $name,
        string $table,
        array|string $columns,
        string $refTable,
        array|string $refColumns,
        ?string $delete = null,
        ?string $update = null,
    ): void {
        if (Db::findForeignKey($table, $columns, $this->db) !== null) {
            return;
        }

        $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }
}
