<?php
namespace verbb\comments\migrations;

use craft\db\Migration;

/**
 * A GIF's width and height beside its URL, so the thread keeps its place while the GIF loads (SD-52), and
 * the site's own copies of commenters' Gravatar images (SD-53). An image is kept in the database rather
 * than on disk, so every server and ephemeral filesystem has it, and goes with its commenter's identity.
 * Fresh installs create these through `Install`.
 */
class m400000_000012_gifs_avatars extends Migration
{
    // Constants
    // =========================================================================

    public const AVATARS_TABLE = '{{%comments_avatars}}';


    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        foreach (['gifWidth', 'gifHeight'] as $column) {
            if (!$this->db->columnExists('{{%comments_comments}}', $column)) {
                $this->addColumn('{{%comments_comments}}', $column, $this->integer()->unsigned());
            }
        }

        if (!$this->db->tableExists(self::AVATARS_TABLE)) {
            $this->createTable(self::AVATARS_TABLE, [
                'id' => $this->primaryKey(),
                'identityId' => $this->integer()->notNull(),
                // A SHA-256 hash of the address the image was fetched for, so a changed address is fetched again.
                'emailHash' => $this->char(64)->notNull(),
                'found' => $this->boolean()->notNull()->defaultValue(false),
                'mimeType' => $this->string(64),
                // Base64, which MySQL and PostgreSQL store the same way.
                'image' => $this->mediumText(),
                'checkedAt' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, self::AVATARS_TABLE, ['identityId'], true);
            $this->createIndex(null, self::AVATARS_TABLE, ['uid'], true);
            $this->addForeignKey(null, self::AVATARS_TABLE, ['identityId'], '{{%comments_identities}}', ['id'], 'CASCADE', null);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(self::AVATARS_TABLE);

        foreach (['gifWidth', 'gifHeight'] as $column) {
            if ($this->db->columnExists('{{%comments_comments}}', $column)) {
                $this->dropColumn('{{%comments_comments}}', $column);
            }
        }

        return true;
    }
}
