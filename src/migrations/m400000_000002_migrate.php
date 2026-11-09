<?php
namespace verbb\comments\migrations;

use verbb\comments\migrations\support\RunsOutsideTransaction;
use verbb\comments\migrations\support\V3Schema;
use verbb\comments\migrations\support\V3ToV4Migrator;

use craft\db\Migration;

/**
 * Populate Conversations, Identities, Comment v4 columns and hierarchy from v3 data.
 */
class m400000_000002_migrate extends Migration
{
    // Traits
    // =========================================================================

    use RunsOutsideTransaction;


    // Public Methods
    // =========================================================================

    public function safeDown(): bool
    {
        echo "m400000_000002_migrate cannot be reverted.\n";

        return false;
    }


    // Protected Methods
    // =========================================================================

    protected function runUpgrade(): void
    {
        if (!V3Schema::hasSourceData($this->db)) {
            return;
        }

        $this->_createWorkingTables();

        (new V3ToV4Migrator($this->db))->run();
    }


    // Private Methods
    // =========================================================================

    private function _createWorkingTables(): void
    {
        if (!$this->db->tableExists(V3Schema::MAP)) {
            $this->createTable(V3Schema::MAP, [
                'kind' => $this->string(16)->notNull(),
                'sourceKey' => $this->string(64)->notNull(),
                'identityId' => $this->integer()->notNull(),
            ]);

            $this->createIndex(null, V3Schema::MAP, ['kind', 'sourceKey'], true);
        }

        if (!$this->db->tableExists(V3Schema::LOG)) {
            $this->createTable(V3Schema::LOG, [
                'id' => $this->primaryKey(),
                'code' => $this->string(64)->notNull(),
                'severity' => $this->string(16)->notNull(),
                'count' => $this->integer()->notNull(),
                'samples' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, V3Schema::LOG, ['code'], true);
        }
    }
}
