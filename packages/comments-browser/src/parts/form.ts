import type { EditFileData, FormFieldData, GifData, UploadedFileData, ViewerData } from '@verbb/comments-core';
import { html, nothing, type TemplateResult } from 'lit-html';
import { attributes } from '../directives.js';
import { format, type Labels } from '../labels.js';
import type { ThemeControlName } from '@verbb/comments-core';
import type { ControlSpec } from '../theme-config.js';
import type { AttributeMap } from '../directives.js';
import { avatar, badgeTemplate } from './comment.js';
import { frameIcon, icon } from './icons.js';
import type { TrayParts, UploadItem } from './tray.js';

export type FormMode = 'create' | 'reply' | 'edit';

/** Everything the comment form's templates draw from. */
export type FormView = {
    mode: FormMode;
    /** Prefix for the form's element IDs, such as `comments-109-1-reply-1446`. */
    idPrefix: string;
    labels: Labels;
    control(name: ThemeControlName, spec?: ControlSpec): AttributeMap;
    part(name: 'postingAs' | 'formFields', data: Record<string, unknown>, fallback: () => unknown): unknown;
    /** A custom field's input override by handle, or `fallback` without one. */
    field(kind: 'input', handle: string, data: Record<string, unknown>, fallback: () => unknown): unknown;
    /** Whether the site draws this field's input itself. */
    overridden(handle: string): boolean;
    viewer: ViewerData | null;
    /** Whether the guest's name and email are asked for. */
    guest: { show: boolean; required: boolean; name: string | null; email: string | null; offerReplyEmails: boolean };
    fields: FormFieldData[];
    /** Values set by field input overrides, by handle. */
    values: Record<string, unknown>;
    setValue(handle: string, value: unknown): void;
    /** Each Assets field's files: those the comment has, those uploaded, and those uploading or failed. */
    uploads: Record<string, UploadItem[]>;
    /** Draws an Assets field's file tray, once its chunk has arrived. */
    fileTray?: (view: FormView, field: FormFieldData, parts: TrayParts) => unknown;
    /** Whether the tray's chunk couldn't load, so its fields say so with Try again. */
    trayFailed?: boolean;
    /** An edited comment's files in an Assets field, for an input override. */
    fieldFiles?: (handle: string) => EditFileData[];
    /** Uploads a file to an Assets field, for an input override that draws its own picker. */
    upload?: (handle: string, file: File) => Promise<UploadedFileData>;
    /** A member's new comment starts as one line. */
    collapsible: boolean;
    collapsed: boolean;
    replyTo: string | null;
    placeholder: string;
    submitLabel: string;
    limit: number;
    length: number;
    draftState: 'saved' | 'faded' | null;
    notice: { message: string; action: string | null } | null;
    posting: boolean;
    /** Why Post can't go yet, or an empty string. */
    blocker: string;
    /** Error messages keyed by `content`, `guest.name`, `guest.email`, `fields.<handle>`, and `form` for the rest. */
    errors: Record<string, string>;
    editorMounted: boolean;
    shortcut: string;
    /** Whether the form offers GIFs, which the site turns on with GIPHY. */
    gifs: boolean;
    /** The GIF going with the comment, chosen here or already on an edited comment. */
    gif: GifData | null;
    /** Whether the GIF picker is open. */
    gifOpen: boolean;
    /** The picker's panel, once its chunk has arrived. */
    gifPanel?: unknown;
    /** Whether the picker's chunk couldn't load. */
    gifFailed?: boolean;
    /** The parts that move into Editor's frame when it takes over: live elements in the page, or templates for a string. */
    slots: { postingAs: unknown; guestFields: unknown; fieldsAbove: unknown; formFields: unknown; cancel: unknown; editor: unknown; gifButton: unknown; gifArea: unknown };
};

/** The comment form element's classes, the same in the browser and drawn to a string. */
export function formClass(mode: FormMode): string {
    return `comments__editor comments__editor--${mode}`;
}

const errorId = (view: FormView, key: string) => `${view.idPrefix}-${key.replace(/[^\w-]/g, '-')}-error`;

const warnedCustom = new Set<string>();

export function fieldError(view: FormView, key: string): unknown {
    const message = view.errors[key];
    return message ? html`<p class="comments__field-error" id=${errorId(view, key)}>${message}</p>` : nothing;
}

function invalid(view: FormView, key: string): Record<string, string | null> {
    return view.errors[key] ? { 'aria-invalid': 'true', 'aria-describedby': errorId(view, key) } : { 'aria-invalid': null, 'aria-describedby': null };
}

/** The ring that appears at 80% of the limit and counts down the last stretch, then how far over. */
function count(view: FormView): TemplateResult {
    const { limit, length, labels } = view;

    if (!(limit > 0) || length < limit * 0.8) {
        return html`<span class="comments__frame-count"></span>`;
    }

    const left = limit - length;
    const radius = 8;
    const circumference = 2 * Math.PI * radius;
    const fraction = Math.min(length / limit, 1);
    const state = left < 0 ? ' comments__frame-count--bad' : left <= limit * 0.1 ? ' comments__frame-count--warn' : '';

    return html`<span class=${`comments__frame-count${state}`} title=${format(labels.characterCount, { count: length.toLocaleString(), limit: limit.toLocaleString() })}><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle class="comments__ring-bg" cx="10" cy="10" r=${radius}></circle><circle class="comments__ring-fg" cx="10" cy="10" r=${radius} stroke-dasharray=${circumference} stroke-dashoffset=${circumference * (1 - fraction)}></circle></svg><span>${left}</span><span class="comments__visually-hidden">${left < 0 ? format(labels.tooLong, { count: -left }) : format(labels.charactersLeft, { count: left })}</span></span>`;
}

function status(view: FormView): TemplateResult {
    const over = view.limit > 0 ? view.length - view.limit : 0;

    if (over > 0) {
        return html`<span class="comments__frame-status comments__frame-status--bad">${frameIcon('alert')}<span class="comments__frame-status-text">${format(view.labels.tooLong, { count: over })}</span></span>`;
    }

    if (view.draftState) {
        return html`<span class=${`comments__frame-status${view.draftState === 'faded' ? ' comments__frame-status--faded' : ''}`}>${frameIcon('check')}<span class="comments__frame-status-text">${view.labels.draftSaved}</span></span>`;
    }

    return html`<span class="comments__frame-status"></span>`;
}

/**
 * The comment form: the writer's avatar beside the plugin's own editor frame, drawn to match
 * Editor's so that a rich form can swap Editor in without anything moving. The form element
 * itself, and the parts that move into Editor, belong to the script; this draws what's between.
 */
export function formTemplate(view: FormView): TemplateResult {
    const { labels, viewer } = view;
    const member = viewer && !viewer.isGuest ? viewer : null;
    const bodyId = `${view.idPrefix}-comment-body`;
    const labelId = `${view.idPrefix}-comment-label`;
    const head = Boolean(view.replyTo || member || view.guest.show);
    const above = view.fields.some((field) => fieldPosition(field) === 'above');
    const below = view.fields.some((field) => fieldPosition(field) === 'below');

    return html`${view.errors.form ? html`<p class="comments__form-error" tabindex="-1">${view.errors.form}</p>` : nothing}<div class="comments__editor-row">${member
        ? avatarFor(view, member)
        : html`<span ${attributes(view.control('avatar', { style: 'comments__avatar--guest', attributes: { 'aria-hidden': 'true' } }))}>${icon('user')}</span>`}<div class=${`comments__frame${view.fields.length ? ' comments__frame--fields' : ''}`} ?data-comments-collapsed=${view.collapsed}>${head
        ? html`<div class="comments__frame-head">${view.replyTo ? replyChip(view) : nothing}${member ? view.slots.postingAs : nothing}${view.guest.show ? view.slots.guestFields : nothing}</div>`
        : nothing}${above ? view.slots.fieldsAbove : nothing}<div class="comments__frame-box"><label class="comments__visually-hidden" id=${labelId} for=${bodyId}>${labels.yourComment}</label><textarea ${attributes(view.control('textarea', {
        identify: 'comments__textarea-fallback',
        attributes: {
            id: bodyId,
            name: 'content',
            rows: 1,
            placeholder: view.placeholder,
            required: true,
            'data-comments-body': true,
            ...invalid(view, 'content'),
        },
    }))}></textarea>${view.notice
        ? html`<div class="comments__frame-notice" role="status">${frameIcon('info')}<span class="comments__frame-notice-text">${view.notice.message}</span>${view.notice.action ? html`<button type="button" class="comments__frame-link" data-comments-action="discard-draft">${view.notice.action}</button>` : nothing}</div>`
        : html`<div class="comments__frame-notice" role="status" hidden></div>`}</div>${view.slots.editor}${view.gifs ? view.slots.gifArea : nothing}${below ? view.slots.formFields : nothing}<div class="comments__frame-foot"><span class="comments__frame-meta">${status(view)}${count(view)}</span>${view.gifs ? view.slots.gifButton : nothing}${view.slots.cancel}<button ${attributes(view.control('primaryButton', {
        identify: 'comments__submit',
        attributes: {
            type: 'submit',
            'aria-label': view.submitLabel,
            'aria-disabled': view.posting || view.blocker ? 'true' : 'false',
            'aria-description': !view.posting && view.blocker ? view.blocker : null,
            'data-comments-posting': view.posting ? true : null,
        },
    }))}><span class="comments__submit-label">${view.posting ? labels.posting : view.submitLabel}</span><kbd class="comments__submit-keys">${view.shortcut}</kbd>${frameIcon('up', 'comments__submit-icon')}</button></div></div>${fieldError(view, 'content')}</div>`;
}

function avatarFor(view: FormView, member: ViewerData): unknown {
    return avatar(view, member, 36);
}

function replyChip(view: FormView): TemplateResult {
    const [before, ...after] = view.labels.replyingTo.split('{name}');

    return html`<p class="comments__frame-reply">${frameIcon('reply')}<span class="comments__frame-reply-text">${before}<b>${view.replyTo}</b>${after.join('{name}')}</span><button type="button" class="comments__frame-reply-cancel" data-comments-action="cancel-editor" aria-label=${view.labels.cancelReply}>${frameIcon('close')}</button></p>`;
}

/** "Posting as Aroha", which moves into Editor's header once it's running. */
export function postingAsTemplate(view: FormView): unknown {
    const member = view.viewer;

    if (!member || member.isGuest) {
        return nothing;
    }

    return view.part('postingAs', { viewer: member }, () => {
        const [before, ...after] = view.labels.postingAs.split('{name}');
        return html`<span>${before}<b>${member.name}</b>${after.join('{name}')}</span>${member.badges.map(badgeTemplate)}`;
    });
}

/** A guest's name and address above the text, in Editor's header once it's running. */
export function guestFieldsTemplate(view: FormView): TemplateResult {
    const { labels, guest } = view;
    const id = (name: string) => `${view.idPrefix}-guest-${name}`;
    const star = guest.required ? html` <span class="comments__field-required" aria-hidden="true">*</span>` : nothing;

    return html`<legend class="comments__visually-hidden">${labels.yourDetails}</legend><label class="comments__field" for=${id('name')}><span class="comments__field-label">${labels.yourName}${star}</span><input ${attributes(view.control('input', { attributes: { id: id('name'), name: 'guest[name]', type: 'text', autocomplete: 'name', value: guest.name, required: guest.required, ...invalid(view, 'guest.name') } }))}>${fieldError(view, 'guest.name')}</label><label class="comments__field" for=${id('email')}><span class="comments__field-label">${labels.yourEmail}${star}<span class="comments__field-hint">${labels.notShownPublicly}</span></span><input ${attributes(view.control('input', { attributes: { id: id('email'), name: 'guest[email]', type: 'email', autocomplete: 'email', value: guest.email, required: guest.required, ...invalid(view, 'guest.email') } }))}>${fieldError(view, 'guest.email')}</label>${guest.offerReplyEmails
        ? html`<label class="comments__field--checkbox" for=${id('notify')}><input ${attributes(view.control('checkbox', { attributes: { id: id('notify'), name: 'guest[notifyReplies]', type: 'checkbox', value: '1' } }))}><span>${labels.replyEmails}</span></label>`
        : nothing}`;
}

/** Where a field goes in the form: above the text, or below it, from its place in the Comment field layout. */
export function fieldPosition(field: FormFieldData): 'above' | 'below' {
    return field.formPosition === 'above' ? 'above' : 'below';
}

/**
 * The custom fields this person may fill in, from the `viewer` read's field descriptions (or, for
 * an edit, the `edit` read's): every built-in input, an Assets field's file tray, and a site's
 * input override by handle. Drawn twice, for the fields above the text and those below it, so an
 * override of the `formFields` part is given each group with its `position`.
 */
export function formFieldsTemplate(view: FormView, position: 'above' | 'below' = 'below'): unknown {
    const fields = view.fields.filter((field) => fieldPosition(field) === position);

    // Each field takes the width set in the field layout, a quarter to all of the form's row.
    return view.part('formFields', { fields, position }, () => fields.map((field) => html`<div class="comments__field-cell" data-comments-width=${field.width ?? 100}>${customField(view, field)}</div>`));
}

function customField(view: FormView, field: FormFieldData): unknown {
    const inputId = `${view.idPrefix}-${field.handle}`;
    const name = `fields[${field.handle}]`;
    const key = `fields.${field.handle}`;
    const instructionsId = field.instructions ? `${inputId}-instructions` : null;
    const describedBy = [instructionsId, view.errors[key] ? errorId(view, key) : null].filter(Boolean).join(' ') || null;
    const star = field.required ? html` <span class="comments__field-required" aria-hidden="true">*</span>` : nothing;
    const instructions = field.instructions ? html`<p class="comments__field-instructions" id=${instructionsId!}>${field.instructions}</p>` : nothing;
    const labelId = `${inputId}-label`;
    const context = {
        field,
        value: view.values[field.handle] ?? null,
        setValue: (value: unknown) => view.setValue(field.handle, value),
        error: view.errors[key] ?? null,
        id: inputId,
        name,
        labelId,
        describedBy,
        mode: view.mode,
        // An Assets field's override gets the edited comment's files, and a way to upload more; its
        // value is the files' IDs, which is what the form posts.
        ...(field.type === 'file' ? { files: view.fieldFiles?.(field.handle) ?? [], upload: (file: File) => view.upload?.(field.handle, file) ?? Promise.reject(new Error('Comments: uploads aren’t available here.')) } : {}),
    };
    if (view.overridden(field.handle)) {
        // The site's own control, inside Comments' label, instructions and error. Its default is the
        // built-in field, for an override that only wraps it.
        return html`<div class="comments__field comments__field-custom" data-comments-field=${field.handle} data-comments-field-override><span class="comments__field-label" id=${labelId}>${field.label}${star}</span>${instructions}${view.field('input', field.handle, context, () => builtIn(view, field, inputId, describedBy, star, instructions))}${fieldError(view, key)}</div>`;
    }

    return builtIn(view, field, inputId, describedBy, star, instructions);
}

function builtIn(view: FormView, field: FormFieldData, inputId: string, describedBy: string | null, star: unknown, instructions: unknown): unknown {
    const name = `fields[${field.handle}]`;
    const key = `fields.${field.handle}`;
    const labelId = `${inputId}-label`;

    if (field.type === 'file') {
        const parts = { inputId, labelId, describedBy, star, instructions };
        // The tray's own chunk arrives just after the form's; until then, its label holds the place,
        // and isn't counted as drawn, so an edit's files aren't posted as none.
        return view.fileTray ? view.fileTray(view, field, parts) : html`<div class="comments__field comments__field-custom comments__field--files" data-comments-field-pending=${field.handle}><span class="comments__field-label" id=${labelId}>${field.label}${star}</span>${instructions}${view.trayFailed
            ? html`<p class="comments__field-error" role="alert">${view.labels.uploadsUnavailable ?? 'Files can’t be added right now.'}</p><button ${attributes(view.control('secondaryButton', { identify: 'comments__upload-add', attributes: { type: 'button', 'data-comments-upload-action': 'reload', 'data-comments-upload-field': field.handle } }))}>${view.labels.tryAgain}</button>`
            : nothing}</div>`;
    }

    const common = { 'aria-describedby': describedBy, 'aria-invalid': view.errors[key] ? 'true' : null, 'data-comments-field': field.handle };
    const extra = Object.fromEntries(Object.entries(field.attributes ?? {}).filter(([attribute]) => /^(min|max|step|maxlength|minlength|pattern|placeholder)$/.test(attribute)));
    // A form with an email field and a submit button looks like a sign-in form to password managers,
    // which then offer to sign in over it and take focus from the field. What a custom field asks for
    // belongs to the comment, not to anyone's account, so they leave these alone.
    const notCredentials = { 'data-1p-ignore': true, 'data-lpignore': 'true', 'data-bwignore': true, 'data-form-type': 'other' };

    if (field.type === 'checkboxes' || field.type === 'radio') {
        const control = field.type === 'radio' ? 'radio' : 'checkbox';

        // The group's error is on its first choice too, which is where the keyboard goes to fix it.
        return html`<fieldset class="comments__field comments__field-custom" aria-describedby=${describedBy ?? nothing}><legend class="comments__field-label">${field.label}${star}</legend>${instructions}${field.options.map((option, index) => html`<label class="comments__field--checkbox" for=${`${inputId}-${index + 1}`}><input ${attributes(view.control(control, { attributes: { id: `${inputId}-${index + 1}`, type: control, name: control === 'checkbox' ? `${name}[]` : name, value: option.value, required: control === 'radio' && field.required, 'aria-invalid': index === 0 && view.errors[key] ? 'true' : null, 'aria-describedby': index === 0 && view.errors[key] ? errorId(view, key) : null, 'data-comments-field': field.handle } }))}><span>${option.label}</span></label>`)}${fieldError(view, key)}</fieldset>`;
    }

    if (field.type === 'toggle') {
        // Drawn as a switch, under its label and instructions like any other field, as Craft draws it.
        return html`<div class="comments__field comments__field-custom"><label class="comments__field-label" for=${inputId}>${field.label}</label>${instructions}<input ${attributes(view.control('checkbox', { identify: 'comments__switch', attributes: { id: inputId, type: 'checkbox', role: 'switch', name, value: '1', ...common } }))}>${fieldError(view, key)}</div>`;
    }

    if (field.type === 'custom') {
        // A type only a field override can draw; nothing is drawn without one, and its errors go at
        // the top of the form.
        if (!warnedCustom.has(field.handle)) {
            warnedCustom.add(field.handle);
            console.warn(`Comments: the "${field.handle}" field needs an input override to be drawn: Comments.configure({ fields: { ${field.handle}: { input } } }).`);
        }

        return nothing;
    }

    let input: TemplateResult;

    if (field.type === 'select') {
        input = html`<select ${attributes(view.control('select', { attributes: { id: inputId, name: field.multiple ? `${name}[]` : name, multiple: field.multiple, required: field.required, ...common } }))}>${field.multiple ? nothing : html`<option value="">${view.labels.chooseOption}</option>`}${field.options.map((option) => html`<option value=${option.value}>${option.label}</option>`)}</select>`;
    } else if (field.type === 'textarea') {
        input = html`<textarea ${attributes(view.control('textarea', { attributes: { id: inputId, name, required: field.required, ...extra, ...common, ...notCredentials } }))}></textarea>`;
    } else {
        input = html`<input ${attributes(view.control('input', { attributes: { id: inputId, name, type: field.type, required: field.required, ...extra, ...common, ...notCredentials } }))}>`;
    }

    return html`<div class="comments__field comments__field-custom"><label class="comments__field-label" for=${inputId}>${field.label}${star}</label>${instructions}${input}${fieldError(view, key)}</div>`;
}
