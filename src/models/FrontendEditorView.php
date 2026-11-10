<?php
namespace verbb\comments\models;

use verbb\comments\helpers\EditorContexts;

use craft\base\Model;
use craft\helpers\Json;

/**
 * Transient editor shell for canonical frontend forms — one instance per render.
 */
class FrontendEditorView extends Model
{
    // Properties
    // =========================================================================

    public int $contractVersion = 1;

    /** create|reply|edit */
    public string $mode = 'create';

    public string $operationId;
    /** Primary form action, the frontend API's action URL that the script posts to. */
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
    /** Their first name where the account has one, for the "Reply to Alex…" placeholder. */
    public ?string $parentAuthorShortName = null;

    /** Comment element ID when editing an existing comment. */
    public ?int $editCommentId = null;

    public ?string $initialPlainContent = null;

    /** Canonical Editor JSON for rich edit prefill. */
    public ?string $initialEditorContent = null;

    /** Where the editor keeps an unsent draft in the browser, per person and per thing replied to. */
    public ?string $draftKey = null;

    /** The site's Maximum Comment Length, shown as the editor's character limit. */
    public ?int $maxLength = null;

    /**
     * Where the browser loads Editor from, for a rich editor only. Everyone starts in the plain box,
     * and the script swaps Editor in once someone starts writing.
     */
    public ?array $editorAsset = null;


    // Public Methods
    // =========================================================================

    /**
     * Options for Editor's `<verbb-editor>`, which a rich form swaps in once someone starts writing. Asked for
     * by the template, so a shared page that leaves the form out never issues a CSRF token.
     */
    public function getEditorOptions(): array
    {
        $options = EditorContexts::browserOptions($this->editorContext, $this->ownerId, $this->siteId, $this->editCommentId);
        $content = $this->initialEditorContent ? Json::decodeIfJson($this->initialEditorContent) : null;

        if (is_array($content)) {
            $options['content'] = $content;
        }

        return $options;
    }
}
