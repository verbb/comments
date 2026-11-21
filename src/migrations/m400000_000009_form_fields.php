<?php
namespace verbb\comments\migrations;

use verbb\comments\migrations\support\V3UpgradeLog;
use verbb\comments\elements\Comment;
use verbb\comments\services\FormFields;
use verbb\comments\migrations\support\SavesProjectConfig;

use Craft;
use craft\db\Migration;
use craft\db\Table;

/**
 * Custom fields on the site's comment form (SD-40). Each comment records which fields it was posted with,
 * uploads for Assets fields wait in their own table until a comment is posted with them, and development
 * builds' **Public Fields** becomes **Comment Form Fields**, with each field it listed set to Collect
 * (never Collect and show), on comments and replies as before. The Upgrade Report says so, because a
 * field the site wants shown now has to be switched to Collect and show.
 *
 * Fresh installs create the column and table through `Install`.
 */
class m400000_000009_form_fields extends Migration
{
    // Traits
    // =========================================================================

    use SavesProjectConfig;


    // Constants
    // =========================================================================

    public const PUBLIC_FIELDS_NOTICE = 'settings.publicFields';


    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createSchema();
        $this->_translatePublicFields();

        return true;
    }

    /**
     * The column and table, which `Install` creates too.
     */
    public function createSchema(): void
    {
        $comments = '{{%comments_comments}}';

        if ($this->db->tableExists($comments) && !$this->db->columnExists($comments, 'postedFields')) {
            $this->addColumn($comments, 'postedFields', $this->text());
        }

        if (!$this->db->tableExists(FormFields::UPLOADS_TABLE)) {
            $this->createTable(FormFields::UPLOADS_TABLE, [
                'id' => $this->primaryKey(),
                'assetId' => $this->integer()->notNull(),
                'fieldUid' => $this->uid()->notNull(),
                'identityId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, FormFields::UPLOADS_TABLE, ['assetId'], true);
            $this->createIndex(null, FormFields::UPLOADS_TABLE, ['identityId', 'fieldUid'], false);
            $this->createIndex(null, FormFields::UPLOADS_TABLE, ['dateCreated'], false);
            $this->addForeignKey(null, FormFields::UPLOADS_TABLE, ['assetId'], Table::ASSETS, ['id'], 'CASCADE', null);

            if ($this->db->tableExists('{{%comments_identities}}')) {
                $this->addForeignKey(null, FormFields::UPLOADS_TABLE, ['identityId'], '{{%comments_identities}}', ['id'], 'CASCADE', null);
            }
        }
    }

    public function safeDown(): bool
    {
        echo "m400000_000009_form_fields cannot be reverted.\n";

        return false;
    }


    // Private Methods
    // =========================================================================

    private function _translatePublicFields(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Applying YAML from the environment that ran this already brings the new setting with it.
        if ($projectConfig->readOnly || version_compare($projectConfig->get('plugins.comments.schemaVersion', true) ?? '', '4.0.7', '>=')) {
            return;
        }

        $settings = $projectConfig->get('plugins.comments.settings') ?? [];

        if (!array_key_exists('publicFields', $settings)) {
            return;
        }

        $handles = is_array($settings['publicFields']) ? array_values(array_filter($settings['publicFields'], 'is_string')) : [];
        $formFields = is_array($settings['formFields'] ?? null) ? $settings['formFields'] : [];

        foreach ($this->_layoutElements() as $uid => $handle) {
            if (in_array($handle, $handles, true) && !isset($formFields[$uid])) {
                $formFields[$uid] = ['mode' => FormFields::MODE_COLLECT, 'placement' => FormFields::PLACEMENT_DETAILS, 'replies' => true];
            }
        }

        if ($formFields !== []) {
            $projectConfig->set('plugins.comments.settings.formFields', $formFields);
        }

        $projectConfig->remove('plugins.comments.settings.publicFields');

        if ($handles !== []) {
            $this->_recordNotice();
        }
    }

    /**
     * The custom field elements in the Comment field layout, as UID to field handle.
     */
    private function _layoutElements(): array
    {
        $elements = [];

        foreach (Craft::$app->getFields()->getLayoutByType(Comment::class)->getCustomFieldElements() as $element) {
            $elements[(string)$element->uid] = $element->getField()->handle;
        }

        return $elements;
    }

    private function _recordNotice(): void
    {
        // A development build that didn't come from Comments 3 has no Upgrade Report yet.
        V3UpgradeLog::createTable($this);

        $log = new V3UpgradeLog($this->db);
        $log->add(self::PUBLIC_FIELDS_NOTICE, V3UpgradeLog::WARNING);
        $log->flush();
    }
}
