<?php
namespace verbb\comments\compatibility\forms;

use craft\web\Request;

/**
 * What Comments 3's form templates posted under other names. Read whatever `compatibilityMode` is, because
 * the form actions are a documented bridge for Comments 3 forms.
 */
class LegacyFormRequest
{
    // Static Methods
    // =========================================================================

    /**
     * The comment a reply form names as its parent. Comments 3's reply forms called it `newParentId`.
     */
    public static function parentId(Request $request): mixed
    {
        return $request->getParam('parentId') ?? $request->getParam('newParentId');
    }
}
