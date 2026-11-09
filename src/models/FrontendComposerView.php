<?php
namespace verbb\comments\models;

use verbb\comments\helpers\EditorContexts;

use craft\base\Model;

/**
 * Transient composer shell for canonical frontend forms — one instance per render.
 */
class FrontendComposerView extends Model
{
    // Properties
    // =========================================================================

    public int $contractVersion = 1;

    /** create|reply|edit */
    public string $mode = 'create';

    public string $operationId;
    /** Primary form action — v1 API when enhanced JS is active. */
    public string $saveAction;
    /** Legacy Craft action URL for non-JS POST fallback. */
    public string $legacySaveAction = '';
    public int $ownerId;
    public int $siteId;

    public string $contentFormat = 'plain';
    public bool $richAuthoring = false;
    public string $editorContext = 'comments.reply';

    public bool $showGuestFields = false;
    public bool $guestRequireEmailName = true;
    public bool $offerReplyEmails = false;
    public ?string $guestName = null;
    public ?string $guestEmail = null;

    /**
     * Custom fields visitors can fill in, already prepared for plain inputs.
     */
    public array $publicFields = [];
    public bool $hasFileInputs = false;

    /** Where a form posted without JavaScript goes next. Rendered with the saved comment. */
    public string $redirectTemplate = '';
    public string $cancelHref = '';

    /** Parent comment element ID when composing a reply. */
    public ?int $parentCommentId = null;
    /** Who a reply answers, for "Replying to". */
    public ?string $parentAuthorName = null;

    /** Comment element ID when editing an existing comment. */
    public ?int $editCommentId = null;

    public ?string $initialPlainContent = null;

    /** Canonical Editor JSON for rich edit prefill. */
    public ?string $initialEditorContent = null;

    /** Where the composer keeps an unsent draft in the browser, per person and per thing replied to. */
    public ?string $draftKey = null;

    /** The site's Maximum Comment Length, shown as the composer's character limit. */
    public ?int $maxLength = null;


    // Public Methods
    // =========================================================================

    /**
     * Asked for by the template, so a shared page that leaves the form out never issues a CSRF token.
     */
    public function getEditorOptions(): array
    {
        return EditorContexts::browserOptions($this->editorContext, $this->ownerId, $this->siteId, $this->editCommentId);
    }
}
