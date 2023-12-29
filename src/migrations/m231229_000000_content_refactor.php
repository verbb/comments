<?php
namespace verbb\comments\migrations;

use verbb\comments\elements\Comment;

use craft\db\Query;
use craft\migrations\BaseContentRefactorMigration;

class m231229_000000_content_refactor extends BaseContentRefactorMigration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->updateElements(
            (new Query())->from('{{%comments_comments}}'),
            $fieldsService->getLayoutByType(Comment::class),
        );

        return true;
    }

    public function safeDown(): bool
    {
        echo "m231229_000000_content_refactor cannot be reverted.\n";

        return false;
    }
}
