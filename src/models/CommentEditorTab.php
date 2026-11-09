<?php
namespace verbb\comments\models;

use craft\models\FieldLayoutTab;

/**
 * A tab added to the comment editor. Its label includes a count, so its ID can't come from its name:
 * Craft matches tabs by ID when it refreshes the form, and the selected tab would be lost as counts change.
 */
class CommentEditorTab extends FieldLayoutTab
{
    // Properties
    // =========================================================================

    public string $handle;


    // Public Methods
    // =========================================================================

    public function getHtmlId(): string
    {
        return 'comment-' . $this->handle;
    }
}
