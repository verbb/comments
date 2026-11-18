import { isCommentsRequestError } from '@verbb/comments-core';
import { mountProtection } from '@verbb/protect';
import { render } from 'lit-html';
import { hasEditorDraft, importFresh, isMac, isShown, prefersReducedMotion, readDraft, relativeTime, writeDraft } from './dom.js';
import { editorDefined, loadEditor, loadEditorFeatures } from './editor-loader.js';
import { format } from './labels.js';
import { fieldPosition, formFieldsTemplate, formClass, formTemplate, guestFieldsTemplate, postingAsTemplate } from './parts/form.js';
import { gifAreaTemplate, gifButtonAttributes, gifButtonContent } from './parts/gif.js';
/** How long the frame waits after focus leaves before shrinking, so a press on Cancel or Post lands first. */
const COLLAPSE_DELAY = 140;
/** Whether a form control's value differs from the one it was drawn with. */
function changedControl(control) {
    if (control instanceof HTMLSelectElement) {
        const options = [...control.options];
        // A single select with nothing marked selected starts on its first option.
        if (!control.multiple)
            return control.selectedIndex !== Math.max(0, options.findIndex((option) => option.defaultSelected));
        return options.some((option) => option.selected !== option.defaultSelected);
    }
    if (control instanceof HTMLTextAreaElement)
        return control.value !== control.defaultValue;
    if (['hidden', 'button', 'submit', 'reset', 'image'].includes(control.type))
        return false;
    if (control.type === 'checkbox' || control.type === 'radio')
        return control.checked !== control.defaultChecked;
    if (control.type === 'file')
        return (control.files?.length ?? 0) > 0;
    return control.value !== control.defaultValue;
}
/**
 * One comment form: a new comment, a reply or an edit. It owns its `<form>` element, which the
 * thread places in the page, and draws the plugin's own editor frame into it: the plain box with
 * Post waiting for some text, ⌘↵, a character ring, a draft kept in this browser, and a member's
 * new comment collapsed to one line. A rich form swaps Editor in when someone starts writing,
 * moving the parts marked `data-comments-slot` into Editor's frame along with what was typed.
 * Protect is mounted once someone starts writing, and its evidence goes with the post.
 */
export class FormController {
    constructor(host, mode, uid, edit = null) {
        this.host = host;
        this.mode = mode;
        this.uid = uid;
        this.editorElement = null;
        this.errors = {};
        this.posting = false;
        this.awaitingEditor = false;
        this.cancelAttributes = null;
        this.draftState = null;
        this.notice = null;
        this.focusInside = false;
        this.protection = null;
        this.mountedManifest = null;
        this.stopProtection = null;
        this.flow = null;
        this.editing = null;
        this.initialText = '';
        this.destroyed = false;
        // Whether `comments:editor-opened` has fired for this form, which it does once.
        this.announced = false;
        // Values set by field input overrides, and each Assets field's files, by handle.
        this.values = {};
        // The fields with an input override at the last draw, to notice one given or taken away later.
        this.overridden = null;
        this.tray = null;
        this.trayFailed = false;
        this.guestUploads = Promise.resolve();
        // The GIF going with the comment, whether this form changed it (which is all an edit sends), and
        // the ping GIPHY wants once it's posted.
        this.gif = null;
        this.gifChanged = false;
        this.gifSent = null;
        this.gifOpen = false;
        this.gifFailed = false;
        this.picker = null;
        this.gifButtonAttributes = null;
        this.abort = new AbortController();
        this.editing = edit;
        this.element = document.createElement('form');
        this.element.className = formClass(mode);
        this.element.dataset.commentsForm = mode;
        this.element.noValidate = true;
        this.element.toggleAttribute('data-comments-collapsible', this.collapsible);
        this.marker = document.createComment('');
        this.element.append(this.marker);
        const postingAs = document.createElement('p');
        postingAs.className = 'comments__posting-as';
        postingAs.dataset.commentsSlot = 'header';
        const guestFields = document.createElement('fieldset');
        guestFields.className = 'comments__guest-fields';
        guestFields.dataset.commentsSlot = 'header';
        // Fields placed before the Comment element in the field layout go above the text, the rest below it.
        const fieldsAbove = document.createElement('div');
        fieldsAbove.className = 'comments__editor-fields comments__editor-fields--above';
        fieldsAbove.dataset.commentsSlot = 'above';
        const formFields = document.createElement('div');
        formFields.className = 'comments__editor-fields';
        formFields.dataset.commentsSlot = 'footer';
        const cancel = document.createElement('button');
        cancel.dataset.commentsSlot = 'actions';
        cancel.textContent = host.labels().cancel;
        // The GIF button sits beside Cancel and the chosen GIF below the text. They're the plain
        // form's only: Editor has a GIF control of its own.
        const gifButton = document.createElement('button');
        gifButton.dataset.commentsSlot = 'actions';
        const gifArea = document.createElement('div');
        gifArea.className = 'comments__gif-area';
        gifArea.dataset.commentsSlot = 'footer';
        this.slots = { postingAs, guestFields, fieldsAbove, formFields, cancel, gifButton, gifArea };
        this.gif = edit?.comment.content.gif ?? null;
        const signal = this.abort.signal;
        this.element.addEventListener('input', (event) => this.onInput(event), { signal });
        this.element.addEventListener('keydown', (event) => this.onKeydown(event), { signal });
        this.element.addEventListener('focusin', () => this.onFocusIn(), { signal });
        this.element.addEventListener('focusout', (event) => this.onFocusOut(event), { signal });
        // A press outside the form closes it, when nothing in the form keeps focus.
        document.addEventListener('pointerdown', (event) => {
            if (this.focusInside && !event.composedPath().includes(this.element))
                this.checkFocusSoon();
        }, { signal });
        // Listened for on the thread, after the event has bubbled, so a site's own listener on the
        // form runs first and can stop the post, as it could on the Twig thread.
        host.root.addEventListener('submit', (event) => {
            if (event.target === this.element)
                void this.onSubmit(event);
        }, { signal });
        // The plain box keeps Editor's text, so a site reading the form, such as in
        // comments:before-submit, sees what will be posted.
        this.element.addEventListener('editor-update', () => this.syncContent(), { signal });
        this.element.addEventListener('click', (event) => this.onClick(event), { signal });
        this.element.addEventListener('change', (event) => this.onChange(event), { signal });
        this.element.addEventListener('editor-reply-cancel', (event) => {
            event.preventDefault();
            this.host.close(this, 'user-cancel');
        }, { signal });
        this.seedFields(this.editing?.fields ?? {});
        this.loadTray();
        this.render();
        this.restoreDraft();
        this.prefill(this.editing?.fields ?? {});
        if (this.editing?.comment.content.format === 'plain' || (this.editing && !this.rich)) {
            this.setText(this.editing.comment.content.text);
        }
        this.initialText = this.text();
        // Only Editor can bring back a draft it kept, and an edit of a rich comment keeps its formatting.
        if (this.rich && (hasEditorDraft(this.draftKey) || this.editing?.comment.content.format === 'rich')) {
            void this.upgrade();
        }
    }
    get key() {
        return this.mode === 'create' ? 'create' : `${this.mode}:${this.uid}`;
    }
    /** Whether Editor is in this form now. */
    get hasEditor() {
        return Boolean(this.editorElement);
    }
    /**
     * Fires `comments:editor-opened` for this form, once: when it's drawn and ready, and on a rich thread
     * once Editor is in it, or Editor couldn't load. Editor arriving later, such as for a post, doesn't
     * open the form again.
     */
    announceOpen() {
        if (this.announced || this.destroyed)
            return;
        this.announced = true;
        this.host.dispatch('comments:editor-opened', { mode: this.mode, commentUid: this.uid });
    }
    /** Whether this form writes in Editor once someone starts. */
    get rich() {
        return this.host.store.getState().editor?.authoring === 'rich';
    }
    get collapsible() {
        const viewer = this.host.store.getState().viewer;
        return this.mode === 'create' && Boolean(viewer && !viewer.isGuest);
    }
    get draftKey() {
        const prefix = this.host.store.getState().viewerEditor?.draftKey ?? `comments-${this.host.instanceId}`;
        return this.mode === 'create' ? `${prefix}-create` : `${prefix}-${this.mode}-${this.uid}`;
    }
    /** The plain box. */
    get box() {
        return this.element.querySelector('textarea[data-comments-body]');
    }
    text() {
        return this.editorElement?.editor?.getText() ?? this.box?.value ?? '';
    }
    setText(text) {
        if (this.box)
            this.box.value = text;
        this.render();
        this.grow();
    }
    /** What was typed into the stand-in before this form arrived, if nothing's here yet. */
    carry(text) {
        if (this.text().trim())
            return;
        this.setText(text);
        this.box?.setSelectionRange(text.length, text.length);
    }
    /** Whether a file has been chosen or an override has set a value. */
    hasFieldContent() {
        return Boolean(this.gif) || this.gifOpen
            || Object.values(this.tray?.items ?? {}).some((items) => items.length > 0)
            || Object.values(this.values).some((value) => value !== null && value !== undefined && value !== '')
            // What someone has typed or chosen in a field, which collapsing would hide.
            || [this.slots.fieldsAbove, this.slots.formFields].some((slot) => [...slot.querySelectorAll('input, select, textarea')].some(changedControl));
    }
    /** Whether there's anything here its author would lose. */
    isDirty() {
        const text = this.text();
        return (Boolean(text.trim()) && text !== this.initialText) || (this.mode === 'edit' ? this.gifChanged : Boolean(this.gif));
    }
    /** GIF search for this form, or null when the site hasn't turned it on or this person can't use it. */
    gifConfig() {
        if (this.mode === 'edit' && this.editing)
            return this.editing.editor.gifs ?? null;
        return this.host.store.getState().viewerEditor?.gifs ?? null;
    }
    /**
     * The custom fields this form asks for: a reply's or a new comment's, and for an edit exactly
     * those the comment was posted with, from the `edit` read (or its kind's, from an older server).
     */
    fields() {
        if (this.mode === 'edit' && this.editing?.editor.fields)
            return this.editing.editor.fields;
        const state = this.host.store.getState();
        const fields = state.viewerEditor?.fields;
        const reply = this.mode === 'reply' || (this.mode === 'edit' && Boolean(this.uid && state.comments[this.uid]?.parentUid));
        return (reply ? fields?.reply : fields?.comment) ?? [];
    }
    /**
     * Fills the custom fields with the values an edited comment was posted with: text, a choice,
     * several choices or a switch, whichever each field is.
     */
    prefill(values) {
        for (const field of this.fields()) {
            if (!(field.handle in values))
                continue;
            const value = values[field.handle];
            const list = (Array.isArray(value) ? value : value === null || value === undefined ? [] : [value])
                .map((item) => (item && typeof item === 'object' && 'value' in item ? String(item.value) : String(item)));
            for (const input of this.element.querySelectorAll(`[data-comments-field="${CSS.escape(field.handle)}"]`)) {
                if (input instanceof HTMLInputElement && (input.type === 'checkbox' || input.type === 'radio')) {
                    input.checked = field.type === 'toggle' ? Boolean(value) && value !== '0' && value !== 'false' : list.includes(input.value);
                }
                else if (input instanceof HTMLSelectElement) {
                    Array.from(input.options).forEach((option) => { option.selected = list.includes(option.value); });
                }
                else if (input.type !== 'file') {
                    input.value = list[0] ?? '';
                }
            }
        }
    }
    /**
     * An edited comment's values for the fields the site draws itself. An Assets field's are the
     * IDs of its files, which is what a post sends; the files themselves go to the override too.
     */
    seedFields(values) {
        for (const field of this.fields()) {
            if (!this.host.fieldOverride('input', field.handle))
                continue;
            const value = values[field.handle] ?? null;
            this.values[field.handle] = field.type === 'file' ? editFiles(value).map((file) => file.id) : value;
        }
    }
    /**
     * The file tray's chunk, for a form with an Assets field. Edits start with the comment's files.
     * If it can't load, the field says so with Try again, and an edit keeps the comment's files.
     * `again` is Try again, which keeps what's written (see `importFresh()`).
     */
    loadTray(again = false) {
        if (!this.fields().some((field) => field.type === 'file' && !this.host.fieldOverride('input', field.handle)))
            return;
        this.trayFailed = false;
        (again ? importFresh(() => import('./uploads.js')) : import('./uploads.js')).then(({ Uploads }) => {
            if (this.destroyed)
                return;
            this.tray = new Uploads(this.trayForm(), this.fields(), this.editing?.fields ?? {});
            this.render();
        }, () => {
            this.trayFailed = true;
            this.render();
        });
    }
    /**
     * Uploads a file to an Assets field: for a new comment with its thread (and the comment it
     * replies to), for an edit with the comment. A guest's uploads go one at a time, each with a fresh
     * protection preparation it uses up, so one never takes Protect from under another.
     */
    sendUpload(handle, file, signal) {
        const state = this.host.store.getState();
        const target = this.mode === 'edit'
            ? { commentUid: this.uid ?? undefined }
            : { ownerId: state.conversation?.ownerId, siteId: state.conversation?.siteId ?? null, parentUid: this.mode === 'reply' ? this.uid : null };
        const send = (protection) => this.host.client.upload({ file, filename: file.name, field: handle, ...target, protection }, { signal });
        if (!state.viewer?.isGuest)
            return send().then((response) => response.file);
        const turn = this.guestUploads.then(() => this.host.store.protection().run(send, (protection) => this.evidence(protection), { action: 'upload' }));
        this.guestUploads = turn.catch(() => undefined);
        return turn.then((response) => response.file);
    }
    /** What the tray needs from its form. */
    trayForm() {
        return {
            host: this.host,
            mode: this.mode,
            uid: this.uid,
            element: this.element,
            view: () => this.view(),
            render: () => this.render(),
            setError: (handle, message) => this.setFieldError(handle, message),
            send: (handle, file, signal) => this.sendUpload(handle, file, signal),
        };
    }
    /** Puts Editor's text in the plain box, which is what the form holds as `content`. */
    syncContent() {
        const editor = this.editorElement?.editor;
        if (editor && this.box)
            this.box.value = editor.getText();
    }
    /** Why Post can't go yet, or nothing if it can. */
    blocker() {
        if (this.editorElement)
            return '';
        const labels = this.host.labels();
        const text = this.box?.value ?? '';
        const limit = this.limit();
        if (!text.trim() && !this.gif)
            return labels.writeSomethingFirst;
        if (limit > 0 && text.length > limit)
            return format(labels.tooLong, { count: text.length - limit });
        return '';
    }
    limit() {
        return this.host.store.getState().editor?.maxLength ?? 0;
    }
    view() {
        const state = this.host.store.getState();
        const labels = this.host.labels();
        const parent = this.uid ? state.comments[this.uid] : undefined;
        const parentName = parent?.author?.name ?? labels.guest;
        const guest = state.viewerEditor?.guest;
        const viewer = state.viewer;
        const view = {
            mode: this.mode,
            idPrefix: `${this.host.instanceId}-${this.mode}-${this.uid ?? 'new'}`,
            labels,
            control: (name, spec) => this.host.control(name, spec),
            part: (name, data, fallback) => this.host.part(name, data, fallback, undefined, `form:${this.key}`),
            viewer,
            guest: {
                show: Boolean(viewer?.isGuest && guest?.show !== false && this.mode !== 'edit'),
                required: guest?.required ?? true,
                name: guest?.name ?? null,
                email: guest?.email ?? null,
                offerReplyEmails: guest?.offerReplyEmails ?? false,
            },
            fields: this.fields(),
            field: (kind, handle, data, fallback) => this.host.field(kind, handle, data, fallback, `form:${this.key}`),
            overridden: (handle) => Boolean(this.host.fieldOverride('input', handle)),
            values: this.values,
            setValue: (handle, value) => {
                this.values = { ...this.values, [handle]: value };
                this.clearFieldError(handle);
                this.render();
            },
            uploads: this.tray?.items ?? {},
            fileTray: this.tray?.template,
            trayFailed: this.trayFailed,
            fieldFiles: (handle) => editFiles(this.editing?.fields[handle]),
            upload: (handle, file) => this.sendUpload(handle, file),
            collapsible: this.collapsible,
            // A file chosen, or a field set, keeps it open too, since the file picker takes focus away.
            collapsed: this.collapsible && !this.editorElement && !this.focusInside && !(this.box?.value ?? '') && !this.hasFieldContent(),
            replyTo: this.mode === 'reply' ? parentName : null,
            placeholder: this.mode === 'edit' ? labels.editPlaceholder : this.mode === 'reply' ? format(labels.replyTo, { name: parentName.split(/\s+/)[0] ?? parentName }) : labels.editorPlaceholder,
            submitLabel: this.mode === 'edit' ? labels.save : this.mode === 'reply' ? labels.reply : labels.post,
            limit: this.limit(),
            length: (this.box?.value ?? '').length,
            draftState: this.draftState,
            notice: this.notice,
            posting: this.posting,
            blocker: this.blocker(),
            errors: this.errors,
            editorMounted: Boolean(this.editorElement),
            shortcut: isMac() ? '⌘↵' : 'Ctrl+Enter',
            // Once Editor is running, its own GIF control takes over.
            gifs: Boolean(this.gifConfig()) && !this.editorElement,
            gif: this.gif,
            gifOpen: this.gifOpen,
            gifFailed: this.gifFailed,
            slots: { ...this.slots, editor: this.editorElement ?? '' },
        };
        if (this.gifOpen && this.picker)
            view.gifPanel = this.picker.panel(view);
        return view;
    }
    /** Draws the form and its movable parts again from the current state. */
    render() {
        if (this.destroyed)
            return;
        const refill = this.followOverrides();
        const view = this.view();
        const cancel = this.host.control('secondaryButton', { identify: 'comments__cancel verbb-editor__button', attributes: { type: 'button', 'data-comments-action': 'cancel-editor', 'data-comments-slot': 'actions' } });
        render(postingAsTemplate(view), this.slots.postingAs);
        render(guestFieldsTemplate(view), this.slots.guestFields);
        render(formFieldsTemplate(view, 'above'), this.slots.fieldsAbove);
        render(formFieldsTemplate(view, 'below'), this.slots.formFields);
        // Cancel moves into Editor's frame, so it's a lasting element whose attributes are set here.
        applyAttributes(this.slots.cancel, cancel, this.cancelAttributes);
        this.cancelAttributes = cancel;
        this.slots.cancel.textContent = view.labels.cancel;
        if (view.gifs) {
            const gifButton = gifButtonAttributes(view);
            applyAttributes(this.slots.gifButton, gifButton, this.gifButtonAttributes);
            this.gifButtonAttributes = gifButton;
            render(gifButtonContent(view), this.slots.gifButton);
            render(gifAreaTemplate(view), this.slots.gifArea);
        }
        render(formTemplate(view), this.element, { renderBefore: this.marker });
        this.element.toggleAttribute('data-comments-editor-mounted', Boolean(this.editorElement));
        this.element.toggleAttribute('data-comments-editor-frame', true);
        // A built-in input that took over from an override starts with the value it had.
        if (refill)
            this.prefill(refill);
        this.host.hostsDrawn();
    }
    /**
     * Follows input overrides given or taken away while the form is open, such as through
     * `setOptions()`. A field that gains one starts with the edited comment's value; one that loses
     * one goes back to its built-in input, filled with the override's last value (or the edited
     * comment's), so an edit never posts nothing over a saved value. Returns what to fill in.
     */
    followOverrides() {
        const fields = this.fields();
        const now = new Set(fields.filter((field) => this.host.fieldOverride('input', field.handle)).map((field) => field.handle));
        const before = this.overridden;
        const saved = this.editing?.fields ?? {};
        let refill = null;
        this.overridden = now;
        if (!before)
            return null;
        for (const handle of now) {
            if (!before.has(handle) && !(handle in this.values) && handle in saved) {
                const field = fields.find((item) => item.handle === handle);
                this.values = { ...this.values, [handle]: field?.type === 'file' ? editFiles(saved[handle]).map((file) => file.id) : saved[handle] };
            }
        }
        for (const handle of before) {
            if (now.has(handle))
                continue;
            const field = fields.find((item) => item.handle === handle);
            const { [handle]: last, ...rest } = this.values;
            const had = handle in this.values;
            this.values = rest;
            // An Assets field's files are the tray's, which loads now if it hasn't.
            if (field?.type === 'file') {
                if (!this.tray)
                    this.loadTray();
            }
            else if (field) {
                refill = { ...(refill ?? {}), [handle]: had ? last : saved[handle] ?? null };
            }
        }
        return refill;
    }
    /** The box grows with what's written, as Editor's does, up to the same height. */
    grow() {
        const box = this.box;
        if (!box)
            return;
        box.style.height = '';
        if (box.value && box.scrollHeight > box.clientHeight) {
            box.style.height = `${Math.min(box.scrollHeight + box.offsetHeight - box.clientHeight, 436)}px`;
        }
    }
    focus() {
        const fields = Array.from(this.element.querySelectorAll('[contenteditable="true"], input:not([type="hidden"]), textarea, select'))
            .filter((field) => isShown(field) && !field.closest('.protect-honeypot'));
        const emptyRequired = fields.find((field) => field instanceof HTMLInputElement && field.required && !field.value);
        if (emptyRequired) {
            emptyRequired.focus();
        }
        else if (this.editorElement) {
            this.editorElement.focus();
        }
        else {
            (this.box ?? fields[0])?.focus();
        }
    }
    // Writing
    // -------------------------------------------------------------------------
    onInput(event) {
        const target = event.target;
        if (target === this.box) {
            this.notice = null;
            delete this.errors.content;
            this.scheduleDraft();
            this.render();
            this.grow();
            return;
        }
        if (target instanceof HTMLElement && target.closest('[name]')) {
            const name = target.name ?? '';
            const key = errorKey(name);
            if (key && this.errors[key]) {
                delete this.errors[key];
                this.render();
            }
        }
    }
    onKeydown(event) {
        if (event.target === this.box && event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
            event.preventDefault();
            this.element.requestSubmit();
        }
    }
    onFocusIn() {
        clearTimeout(this.collapseTimer);
        const wasCollapsed = !this.focusInside;
        this.focusInside = true;
        this.startProtection();
        if (wasCollapsed)
            this.render();
    }
    /**
     * The form closes when focus moves to something else on the page. Focus that goes nowhere, as when
     * someone presses a field's instructions, switches window, or a password manager opens its own
     * menu over a field, leaves it open: a press outside the form closes it then.
     */
    onFocusOut(event) {
        const next = event.relatedTarget;
        if (!(next instanceof Element) || next instanceof HTMLIFrameElement)
            return;
        this.checkFocusSoon();
    }
    checkFocusSoon() {
        clearTimeout(this.collapseTimer);
        this.collapseTimer = setTimeout(() => {
            this.focusInside = this.element.contains(document.activeElement);
            this.render();
        }, COLLAPSE_DELAY);
    }
    onClick(event) {
        const target = event.target instanceof Element ? event.target : null;
        const gifControl = target?.closest('[data-comments-gif-action]');
        // A result is chosen by the picker's own chunk.
        if (gifControl && this.element.contains(gifControl)) {
            if (gifControl.dataset.commentsGifAction === 'toggle') {
                if (this.gifOpen)
                    this.closeGifs(true);
                else
                    this.openGifs();
            }
            else if (gifControl.dataset.commentsGifAction === 'remove') {
                this.setGif(null, null);
                this.slots.gifButton.focus();
            }
            return;
        }
        const upload = target?.closest('[data-comments-upload-action]');
        if (upload) {
            event.preventDefault();
            if (upload.dataset.commentsUploadAction === 'reload')
                this.loadTray(true);
            else
                this.tray?.act(upload);
            return;
        }
        if (target?.closest('[data-comments-action="discard-draft"]')) {
            event.preventDefault();
            this.clearDraft();
            this.setText(this.editing?.comment.content.text ?? '');
            this.box?.focus();
        }
    }
    onChange(event) {
        if (event.target instanceof HTMLInputElement && event.target.dataset.commentsUploadInput !== undefined)
            this.tray?.choose(event.target);
    }
    setFieldError(handle, message) {
        const key = `fields.${handle}`;
        const { [key]: _old, ...rest } = this.errors;
        this.errors = message ? { ...rest, [key]: message } : rest;
    }
    clearFieldError(handle) {
        if (this.errors[`fields.${handle}`])
            this.setFieldError(handle, null);
    }
    /** Someone heading for this form: Editor starts loading, and swaps in once they're in the box. */
    intent(focusedBox) {
        if (!this.rich || this.editorElement)
            return;
        if (focusedBox) {
            void this.upgrade();
        }
        else {
            this.prefetch();
        }
    }
    prefetch() {
        const load = editorDefined() ? Promise.resolve() : loadEditor(this.host.store.getState().editor?.richEditor ?? null, { loader: this.host.loader(), nonce: this.host.nonce() });
        load.then(() => loadEditorFeatures(this.editorOptions())).catch(() => undefined);
    }
    /** Editor's options for this form: the comment's own when editing, else the viewer's. */
    editorOptions() {
        return this.editing?.editor.richEditor?.options ?? this.host.store.getState().viewerEditor?.richEditor?.options ?? {};
    }
    // GIFs
    // -------------------------------------------------------------------------
    setGif(gif, sent) {
        this.gif = gif;
        this.gifSent = sent;
        this.gifChanged = true;
        delete this.errors.content;
        this.render();
    }
    /** Opens the picker, loading its chunk the first time, and shows what's trending. */
    openGifs() {
        const config = this.gifConfig();
        if (!config)
            return;
        this.gifOpen = true;
        this.gifFailed = false;
        this.render();
        if (this.picker) {
            this.showPicker(this.picker);
            return;
        }
        // Only now, and only here, does anything of GIPHY's load.
        import('./gif-picker.js').then(({ attachGifPicker }) => {
            if (this.destroyed || !this.gifOpen)
                return;
            this.picker = attachGifPicker(config, {
                element: this.element,
                signal: this.abort.signal,
                isOpen: () => this.gifOpen,
                render: () => this.render(),
                chosen: (choice) => {
                    this.closeGifs(true);
                    this.setGif(choice.gif, choice.sent);
                },
                close: () => this.closeGifs(true),
            });
            this.showPicker(this.picker);
        }, () => {
            this.gifFailed = true;
            this.render();
        });
    }
    /** Shows what's trending, or the last search, and moves to the search box. */
    showPicker(picker) {
        if (picker.status !== 'ready')
            void picker.start();
        else
            this.render();
        this.element.querySelector('[data-comments-gif-search]')?.focus();
    }
    closeGifs(returnFocus) {
        if (!this.gifOpen)
            return;
        this.gifOpen = false;
        this.picker?.stop();
        this.render();
        if (returnFocus)
            this.slots.gifButton.focus();
    }
    /**
     * The GIF as the data API takes it: a new comment sends one if it has one, and an edit only when
     * it changed, with `null` to take it off.
     */
    gifInput() {
        if (this.mode === 'edit')
            return this.gifChanged ? { gif: this.gif ? { ...this.gif } : null } : {};
        return this.gif ? { gif: { ...this.gif } } : {};
    }
    // Drafts
    // -------------------------------------------------------------------------
    /** A rich form keeps no draft here: Editor keeps its own once it's running, and the two would disagree. */
    keepsDraft() {
        return !this.rich;
    }
    scheduleDraft() {
        if (!this.keepsDraft())
            return;
        clearTimeout(this.draftTimer);
        this.draftTimer = setTimeout(() => {
            const text = this.box?.value ?? '';
            if (text === this.initialText || !text.trim()) {
                writeDraft(this.draftKey, null);
                if (this.draftState) {
                    this.draftState = null;
                    this.render();
                }
                return;
            }
            if (readDraft(this.draftKey)?.text === text)
                return;
            writeDraft(this.draftKey, text);
            this.draftState = 'saved';
            this.render();
            clearTimeout(this.fadeTimer);
            this.fadeTimer = setTimeout(() => {
                this.draftState = 'faded';
                this.render();
            }, 2600);
        }, 800);
    }
    restoreDraft() {
        if (!this.keepsDraft())
            return;
        const draft = readDraft(this.draftKey);
        if (!draft || draft.text === (this.box?.value ?? ''))
            return;
        const labels = this.host.labels();
        this.setText(draft.text);
        this.notice = { message: format(labels.draftRestored, { time: relativeTime(draft.savedAt) }), action: labels.discard };
        this.render();
    }
    clearDraft() {
        clearTimeout(this.draftTimer);
        clearTimeout(this.fadeTimer);
        this.draftState = null;
        this.notice = null;
        writeDraft(this.draftKey, null);
        this.editorElement?.clearDraft();
        this.render();
    }
    // Editor
    // -------------------------------------------------------------------------
    /**
     * Swaps the plain box for Editor, loading Editor first if it isn't here. Whatever was typed
     * comes along, and the caret too if the box had it. Without Editor, the plain box carries on.
     */
    async upgrade() {
        if (this.editorElement || !this.rich)
            return;
        const state = this.host.store.getState();
        const options = this.editorOptions();
        try {
            await loadEditor(state.editor?.richEditor ?? null, { loader: this.host.loader(), nonce: this.host.nonce() });
            // So the editor mounts the moment it's added and what was typed moves straight across.
            await loadEditorFeatures(options);
        }
        catch {
            // The form stays a plain box, which is ready as it is.
            this.announceOpen();
            return;
        }
        // Not while a post is under way, unless that post is what's waiting for Editor.
        if (this.destroyed || this.editorElement || (this.posting && !this.awaitingEditor) || !this.element.isConnected)
            return;
        const view = this.view();
        const box = this.box;
        const hadFocus = Boolean(box && document.activeElement === box);
        const typed = box?.value ?? '';
        const element = document.createElement('verbb-editor');
        // Editor's GIF control replaces the plain form's, and starts with the GIF chosen here.
        this.closeGifs(false);
        const auth = this.host.client.auth;
        const client = this.host.client;
        element.setAttribute('data-editor-root', '');
        element.setAttribute('data-options', JSON.stringify(options));
        element.setAttribute('aria-labelledby', `${view.idPrefix}-comment-label`);
        element.setAttribute('submit-label', view.submitLabel);
        element.setAttribute('placeholder', view.placeholder);
        element.setAttribute('draft-key', this.draftKey);
        element.setAttribute('theme', this.host.editorTheme());
        element.toggleAttribute('required', true);
        element.toggleAttribute('collapsible', this.collapsible);
        if (view.replyTo)
            element.setAttribute('reply-to', view.replyTo);
        if (view.limit)
            element.setAttribute('limit', String(view.limit));
        // Editor's mentions and uploads go under the same sign-in as the thread's own writes.
        element.options = {
            request: {
                credentials: auth.credentials,
                headers: () => auth.headers({ write: true, session: () => client.session() }),
            },
            ...(this.editing?.comment.content.format === 'rich' && this.editing.comment.content.document ? { content: this.editing.comment.content.document } : {}),
            ...(this.gif ? { gif: this.gif } : {}),
        };
        // The GIF is still the comment's, so the form follows Editor's control to post it.
        element.addEventListener('editor-update', () => {
            const gif = element.editor?.getGif?.() ?? null;
            if (gif?.url === this.gif?.url)
                return;
            this.gif = gif;
            this.gifChanged = true;
            delete this.errors.content;
            this.render();
        });
        this.editorElement = element;
        this.render();
        // Editor moves slotted children into its own frame as they arrive.
        const has = (position) => view.fields.some((field) => fieldPosition(field) === position);
        for (const [name, slot] of [['header', this.slots.postingAs], ['header', this.slots.guestFields], ['above', this.slots.fieldsAbove], ['footer', this.slots.formFields], ['actions', this.slots.cancel]]) {
            if ((slot === this.slots.postingAs && !(state.viewer && !state.viewer.isGuest)) || (slot === this.slots.guestFields && !view.guest.show) || (slot === this.slots.fieldsAbove && !has('above')) || (slot === this.slots.formFields && !has('below')))
                continue;
            slot.setAttribute('slot', name);
            element.append(slot);
        }
        if (typed && typed !== (this.editing?.comment.content.text ?? '') && element.editor) {
            element.editor.setContent(typed);
        }
        if (box)
            box.required = false;
        if (hadFocus)
            element.focus();
        this.initialText = this.editing ? this.text() : this.initialText;
        this.render();
        this.announceOpen();
    }
    // Protection
    // -------------------------------------------------------------------------
    /** Once someone starts writing: a preparation for this form, kept fresh while it's open. */
    startProtection() {
        if (this.flow || this.destroyed)
            return;
        try {
            this.flow = this.host.store.protection(this.mode === 'edit' ? this.uid ?? undefined : undefined);
        }
        catch {
            return;
        }
        this.flow.activate();
        this.stopProtection = this.flow.subscribe((protection) => this.mountProtection(protection));
        const current = this.flow.current();
        if (current) {
            this.mountProtection(current);
        }
        else {
            this.flow.ensure().then((protection) => this.mountProtection(protection)).catch(() => undefined);
        }
    }
    mountProtection(protection) {
        if (this.destroyed || !protection || protection.manifest === this.mountedManifest)
            return;
        this.protection?.destroy();
        this.protection = mountProtection(this.element, protection.manifest, { nonce: this.host.nonce() });
        this.mountedManifest = protection.manifest;
    }
    /** Protect's evidence for the preparation the store hands this post. */
    async evidence(protection) {
        this.mountProtection(protection);
        if (!this.protection) {
            throw new Error('Comment verification is unavailable.');
        }
        return await this.protection.prepare();
    }
    /** A hidden tab that becomes visible again may hold a stale preparation. */
    refreshProtection() {
        this.flow?.refreshIfStale().catch(() => undefined);
    }
    // Posting
    // -------------------------------------------------------------------------
    refuse(reason) {
        this.errors = { ...this.errors, content: reason };
        this.render();
        this.host.announce(reason);
        if (!prefersReducedMotion())
            this.element.querySelector('.comments__submit')?.animate?.([{ transform: 'translateX(0)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(3px)' }, { transform: 'translateX(0)' }], { duration: 220 });
        this.box?.focus();
    }
    content() {
        const editor = this.editorElement?.editor;
        const gif = this.gifInput();
        // A comment can be only its GIF, which is posted as plain text with none.
        const gifOnly = this.gif ? { format: 'plain', text: '', ...gif } : null;
        if (editor) {
            return editor.getText().trim() ? { format: 'rich', document: editor.getJSON(), ...gif } : gifOnly;
        }
        const text = this.box?.value ?? '';
        return text.trim() ? { format: 'plain', text, ...gif } : gifOnly;
    }
    async onSubmit(event) {
        // A listener that ran first, on the form itself or while capturing, has already stopped this.
        if (event.defaultPrevented)
            return;
        event.preventDefault();
        if (this.posting)
            return;
        const labels = this.host.labels();
        const blocker = this.blocker();
        // Nothing to post yet, or too much of it: said beside the box, where it's seen, and read out.
        if (blocker) {
            this.refuse(blocker);
            return;
        }
        if (this.editorElement?.editor && !this.editorElement.editor.getText().trim() && !this.gif) {
            this.errors = { ...this.errors, content: labels.writeSomethingFirst };
            this.render();
            this.host.announce(labels.writeSomethingFirst);
            this.editorElement.focus();
            return;
        }
        // A rich form posted before Editor has arrived, such as with ⌘↵ straight after typing, waits
        // for it, so the comment is posted as Editor's document rather than plain text. If Editor
        // can't load, the plain box's text is posted as it is.
        if (this.rich && !this.editorElement) {
            this.posting = true;
            this.awaitingEditor = true;
            this.render();
            await this.upgrade().catch(() => undefined);
            this.awaitingEditor = false;
            this.posting = false;
            this.render();
        }
        // What's still uploading isn't in the document or a field yet, so posting now would lose it.
        if (this.editorElement?.editor?.isUploading() || this.tray?.busy()) {
            this.showErrors({ message: labels.uploadInProgress });
            return;
        }
        // A file that didn't upload would be left out without a word, so it's removed or retried first.
        const failed = this.tray?.failed();
        if (failed) {
            this.showErrors({ fieldErrors: { [`fields.${failed}`]: [labels.uploadsFailed ?? 'Remove or try again the files that didn’t upload, so nothing is left out.'] } });
            return;
        }
        this.syncContent();
        // The site's last word before anything is sent, such as its own validation. PHP still checks
        // everything, so this can only refuse a post, never allow one.
        const allowed = this.host.dispatch('comments:before-submit', {
            form: this.element,
            mode: this.mode,
            commentUid: this.uid,
            formData: new FormData(this.element),
        }, { cancelable: true, source: 'user' });
        if (!allowed)
            return;
        // Read after the event, so a site that changed the form's fields sends what it changed.
        const content = this.content();
        if (!content) {
            this.refuse(labels.writeSomethingFirst);
            return;
        }
        const data = new FormData(this.element);
        // Without the tray (its chunk failed), an edit keeps the comment's files as they were.
        const files = this.tray?.items ?? Object.fromEntries(this.fields().filter((field) => field.type === 'file').map((field) => [field.handle, editFiles(this.editing?.fields[field.handle]).map((file) => file.id)]));
        const fields = collectFields(data, this.fields(), this.element, this.values, files, this.mode === 'edit');
        const viewer = this.host.store.getState().viewer;
        const guest = viewer?.isGuest && this.mode !== 'edit' && this.view().guest.show
            ? { name: String(data.get('guest[name]') ?? ''), email: String(data.get('guest[email]') ?? ''), notifyReplies: data.get('guest[notifyReplies]') === '1' }
            : undefined;
        this.posting = true;
        this.errors = {};
        this.editorElement?.toggleAttribute('busy', true);
        this.editorElement?.clearPostError();
        this.render();
        const evidence = (protection) => this.evidence(protection);
        try {
            let response;
            if (this.mode === 'edit' && this.uid) {
                response = await this.host.store.update(this.uid, { content, fields }, { evidence });
                this.host.dispatch('comments:comment-updated', { commentUid: response.comment.uid });
            }
            else {
                response = await this.host.store.create({ parentUid: this.mode === 'reply' ? this.uid : null, content, guest, fields }, { evidence });
                this.host.dispatch('comments:comment-created', { commentUid: response.comment.uid, moderationState: response.moderationState });
            }
            if (response.announcement)
                this.host.toast(response.announcement, 'success');
            // GIPHY counts the GIF as sent once the comment it's in is posted.
            if (this.editorElement)
                this.editorElement.editor?.reportGifSent?.();
            else if (this.gifSent)
                this.picker?.giphy.ping(this.gifSent);
            this.posting = false;
            this.editorElement?.toggleAttribute('busy', false);
            this.clearDraft();
            this.reset();
            this.host.posted(this, response.comment.uid);
        }
        catch (error) {
            this.posting = false;
            this.editorElement?.toggleAttribute('busy', false);
            if (isCommentsRequestError(error)) {
                this.showErrors({ fieldErrors: error.fieldErrors, message: error.message });
            }
            else {
                this.showErrors({ message: labels.error });
            }
            // Editor keeps the text and offers to try again.
            this.editorElement?.showPostError(isCommentsRequestError(error) ? error.message : labels.error);
            this.host.dispatch('comments:request-error', {
                reason: this.mode,
                message: isCommentsRequestError(error) ? error.message : labels.error,
                category: isCommentsRequestError(error) ? error.category : undefined,
                code: isCommentsRequestError(error) ? error.code : undefined,
                fieldErrors: isCommentsRequestError(error) ? error.fieldErrors : undefined,
                recoverable: isCommentsRequestError(error) ? error.recoverable : undefined,
            });
        }
    }
    /** Back to empty after a post, ready for the next. */
    reset() {
        Array.from(this.element.elements).forEach((element) => {
            if (element instanceof HTMLInputElement && (element.type === 'checkbox' || element.type === 'radio'))
                element.checked = false;
            else if ((element instanceof HTMLInputElement && element.type !== 'hidden') || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement) {
                if (!element.closest('.protect-honeypot'))
                    element.value = '';
            }
        });
        this.editorElement?.editor?.setContent('');
        this.editorElement?.editor?.setGif?.(null);
        this.tray?.clear();
        this.gif = null;
        this.gifChanged = false;
        this.gifSent = null;
        this.closeGifs(false);
        this.values = {};
        this.initialText = '';
        this.errors = {};
        this.notice = null;
        this.render();
        this.grow();
    }
    // Errors
    // -------------------------------------------------------------------------
    /**
     * Shows errors beside their fields and takes the writer to the first. Keys are the data API's
     * (`content`, `guest.name`, `fields.<handle>`) or an input's `name`; anything without a field
     * here goes at the top of the form.
     */
    showErrors(errors) {
        const next = {};
        // A field the form didn't draw (a type only an override can draw, with none given) has
        // nowhere to show its error, so that goes at the top with the form's.
        const drawn = this.fields().filter((field) => field.type !== 'custom' || this.host.fieldOverride('input', field.handle));
        const known = new Set(['content', ...(this.view().guest.show ? ['guest.name', 'guest.email'] : []), ...drawn.map((field) => `fields.${field.handle}`)]);
        const unplaced = [];
        for (const [name, messages] of Object.entries(errors.fieldErrors ?? {})) {
            const key = errorKey(name);
            const message = messages[0] ?? errors.message ?? '';
            if (key && known.has(key))
                next[key] = message;
            else if (message)
                unplaced.push(message);
        }
        if (!Object.keys(next).length && (errors.message || unplaced.length)) {
            next.form = errors.message || unplaced[0];
        }
        this.errors = next;
        this.render();
        this.markEditorInvalid(Boolean(next.content));
        const first = Object.keys(next)[0];
        this.host.announce(first ? next[first] : '');
        if (first === 'form') {
            this.element.querySelector('.comments__form-error')?.focus();
        }
        else if (first === 'content') {
            if (this.editorElement)
                this.editorElement.focus();
            else
                this.box?.focus();
        }
        else if (first) {
            // A field's override may be a component that draws its error state a moment later, so
            // its control is looked for again briefly: one marked invalid, or the one with its ID.
            const handle = first.startsWith('fields.') ? first.slice(7) : null;
            const id = handle ? `${this.host.instanceId}-${this.mode}-${this.uid ?? 'new'}-${handle}` : null;
            // The first errored field's own container is searched first, so a built-in field further
            // down that's already marked invalid doesn't take the keyboard from it.
            this.host.focusSoon(() => {
                const field = handle ? this.element.querySelector(`[data-comments-field-override][data-comments-field="${CSS.escape(handle)}"]`) : null;
                if (field)
                    return field.querySelector('[aria-invalid="true"]') ?? (id ? field.querySelector(`#${CSS.escape(id)}`) : null);
                return this.element.querySelector('[aria-invalid="true"]');
            });
        }
    }
    clearErrors() {
        this.errors = {};
        this.markEditorInvalid(false);
        this.render();
    }
    markEditorInvalid(invalid) {
        const editable = this.editorElement?.querySelector('[contenteditable]');
        if (!editable)
            return;
        editable.toggleAttribute('aria-invalid', invalid);
        if (invalid)
            editable.setAttribute('aria-invalid', 'true');
    }
    destroy() {
        this.destroyed = true;
        this.abort.abort();
        clearTimeout(this.collapseTimer);
        clearTimeout(this.draftTimer);
        clearTimeout(this.fadeTimer);
        this.stopProtection?.();
        this.flow?.deactivate();
        this.protection?.destroy();
        this.tray?.clear();
        this.picker?.stop();
        this.element.remove();
    }
}
/** The data API's key for an input's name or an error key: `content`, `guest.name`, `fields.<handle>`. */
export function errorKey(name) {
    const normal = name.replace(/\[\]$/, '').replace(/\[([^\]]+)\]/g, '.$1');
    if (normal === 'content' || normal === 'fields.comment' || normal === 'fields.editorContent')
        return 'content';
    if (normal === 'fields.name' || normal === 'guest.name')
        return 'guest.name';
    if (normal === 'fields.email' || normal === 'guest.email')
        return 'guest.email';
    if (/^fields\.[\w-]+$/.test(normal))
        return normal;
    return null;
}
/**
 * Custom field values keyed by handle, as the data API takes them: what each drawn input holds,
 * what a field override set, and an Assets field's uploaded files as their IDs.
 */
function collectFields(data, fields, form, set, files, editing) {
    // Only the fields the form drew, so one without an input here (a type only a field override can
    // draw, with none given) keeps its value rather than being emptied.
    const drawn = new Set(Array.from(form.querySelectorAll('[data-comments-field]'), (element) => element.dataset.commentsField));
    const values = {};
    for (const field of fields) {
        const name = `fields[${field.handle}]`;
        if (field.handle in set && drawn.has(field.handle)) {
            values[field.handle] = set[field.handle];
        }
        else if (field.type === 'file') {
            // The tray's uploaded files, or, while it isn't here, an edit's files as they were.
            const list = files[field.handle];
            if (list && (drawn.has(field.handle) || editing)) {
                values[field.handle] = list.map((item) => (typeof item === 'number' ? item : item.state === 'done' ? item.id : null)).filter((id) => id !== null);
            }
        }
        else if (!drawn.has(field.handle)) {
            continue;
        }
        else if (field.type === 'checkboxes' || (field.type === 'select' && field.multiple)) {
            values[field.handle] = data.getAll(`${name}[]`).map(String);
        }
        else if (field.type === 'toggle') {
            values[field.handle] = data.get(name) === '1';
        }
        else {
            values[field.handle] = data.get(name) === null ? null : String(data.get(name));
        }
    }
    return Object.keys(values).length ? values : undefined;
}
/** An Assets field's files in an `edit` read's values. */
function editFiles(value) {
    return Array.isArray(value) ? value.filter((file) => Boolean(file) && typeof file === 'object' && typeof file.id === 'number') : [];
}
/** Sets an element's attributes to a map, removing the ones a previous map set that it no longer has. */
function applyAttributes(element, map, previous) {
    for (const name of Object.keys(previous ?? {})) {
        if (!(name in map))
            element.removeAttribute(name);
    }
    for (const [name, value] of Object.entries(map)) {
        element.setAttribute(name, value === true ? '' : value);
    }
}
/** Starts fetching Editor ahead of need, for a rich thread's visitor heading for a form. */
export function prefetchEditor(host) {
    const state = host.store.getState();
    if (state.editor?.authoring === 'rich' && !editorDefined()) {
        loadEditor(state.editor.richEditor ?? null, { loader: host.loader(), nonce: host.nonce() })
            .then(() => loadEditorFeatures(state.viewerEditor?.richEditor?.options ?? {}))
            .catch(() => undefined);
    }
}
