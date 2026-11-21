import { html, nothing } from 'lit-html';
import { attributes } from './directives.js';
import { resolveLabels, format } from './labels.js';
import { formFieldsTemplate, formClass, formTemplate, guestFieldsTemplate, postingAsTemplate } from './parts/form.js';
import { fileTray } from './parts/tray.js';
import { reportDialog } from './parts/report.js';
import { gifAreaTemplate, gifButtonAttributes, gifButtonContent } from './parts/gif.js';
import { controlAttributes, mergeThemeConfig } from './theme-config.js';
import { emptyUi } from './view.js';
/**
 * A view of a thread's state for drawing to a string, with the comment forms drawn from their
 * templates rather than the live elements the browser uses.
 */
export function createStringView(options) {
    const { state } = options;
    const labels = resolveLabels(state.labels, options.labels);
    const themeConfig = mergeThemeConfig([options.themeConfig]);
    const instanceId = options.instanceId ?? `comments-${state.conversation?.ownerId ?? 0}-${state.conversation?.siteId ?? 1}`;
    const ui = { ...emptyUi(), ...options.ui };
    const control = (name, spec) => controlAttributes(themeConfig, name, spec);
    const draw = (override, what, data, fallback, frame) => {
        if (!override)
            return fallback();
        const context = { labels, actions: {}, ...data, controlAttributes: control, html, defaultRender: fallback };
        if (typeof override === 'string') {
            return /^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/.test(override) ? { _$litType$: 1, strings: [`<${override}></${override}>`], values: [] } : nothing;
        }
        // A framework component's host, empty, as the browser draws it before the component renders.
        if (typeof override === 'object') {
            const host = { _$litType$: 1, strings: ['<verbb-comments-host></verbb-comments-host>'], values: [] };
            return frame ? frame.around(host) : host;
        }
        try {
            return override(context);
        }
        catch (error) {
            console.error(`Comments: the ${what} override failed, so the default was drawn.`, error);
            return fallback();
        }
    };
    const part = (name, data, fallback, frame) => draw(options.parts?.[name], `"${name}" part`, data, fallback, frame);
    const field = (kind, handle, data, fallback) => draw(options.fields?.[handle]?.[kind], `"${handle}" field's ${kind}`, data, fallback);
    const view = {
        mode: 'string',
        instanceId,
        phase: options.phase ?? (state.status === 'ready' ? 'ready' : 'waiting'),
        count: options.count ?? state.conversation?.commentCount ?? 0,
        state,
        ui,
        labels,
        control,
        part,
        field,
        fieldInputs: new Set(Object.keys(options.fields ?? {}).filter((handle) => options.fields?.[handle]?.input)),
        reportDialog,
        form: (key) => {
            const [mode, uid] = key.split(':');
            const form = createFormView(view, mode, uid ?? null);
            return html `<form class=${formClass(mode)} data-comments-form=${mode} ?data-comments-collapsible=${form.collapsible} novalidate>${formTemplate(form)}</form>`;
        },
        actions: () => ({}),
        formFields: (mode) => (mode === 'reply' ? state.viewerEditor?.fields.reply : state.viewerEditor?.fields.comment) ?? [],
    };
    return view;
}
/** A comment form's view, as drawn to a string, for one of a thread view's forms. */
export function createFormView(thread, mode, uid) {
    const { state, labels } = thread;
    const viewer = state.viewer;
    const guest = state.viewerEditor?.guest;
    const parentName = (uid ? state.comments[uid]?.author?.name : null) ?? labels.guest;
    const member = Boolean(viewer && !viewer.isGuest);
    const view = {
        mode,
        idPrefix: `${thread.instanceId}-${mode}-${uid ?? 'new'}`,
        labels,
        control: thread.control,
        part: (name, data, fallback) => thread.part(name, data, fallback),
        field: (kind, handle, data, fallback) => thread.field(kind, handle, data, fallback),
        overridden: (handle) => Boolean(thread.fieldInputs?.has(handle)),
        viewer,
        guest: {
            show: Boolean(viewer?.isGuest && guest?.show !== false && mode !== 'edit'),
            required: guest?.required ?? true,
            name: guest?.name ?? null,
            email: guest?.email ?? null,
            offerReplyEmails: guest?.offerReplyEmails ?? false,
        },
        fields: thread.formFields(mode),
        values: {},
        setValue: () => undefined,
        uploads: {},
        fileTray,
        avatars: state.conversation?.showAvatars !== false,
        collapsible: mode === 'create' && member,
        collapsed: mode === 'create' && member,
        replyTo: mode === 'reply' ? parentName : null,
        placeholder: mode === 'edit' ? labels.editPlaceholder : mode === 'reply' ? format(labels.replyTo, { name: parentName.split(/\s+/)[0] ?? parentName }) : labels.editorPlaceholder,
        submitLabel: mode === 'edit' ? labels.save : mode === 'reply' ? labels.reply : labels.post,
        limit: state.editor?.maxLength ?? 0,
        length: 0,
        draftState: null,
        notice: null,
        posting: false,
        blocker: labels.writeSomethingFirst,
        errors: {},
        editorMounted: false,
        shortcut: '⌘↵',
        gifs: Boolean(state.viewerEditor?.gifs),
        gif: null,
        gifOpen: false,
        slots: { postingAs: '', guestFields: '', fieldsAbove: '', formFields: '', cancel: '', editor: '', gifButton: '', gifArea: '' },
    };
    view.slots = {
        postingAs: html `<p class="comments__posting-as" data-comments-slot="header">${postingAsTemplate(view)}</p>`,
        guestFields: html `<fieldset class="comments__guest-fields" data-comments-slot="header">${guestFieldsTemplate(view)}</fieldset>`,
        fieldsAbove: html `<div class="comments__editor-fields comments__editor-fields--above" data-comments-slot="above">${formFieldsTemplate(view, 'above')}</div>`,
        formFields: html `<div class="comments__editor-fields" data-comments-slot="footer">${formFieldsTemplate(view, 'below')}</div>`,
        cancel: html `<button ${attributesFor(view)}>${labels.cancel}</button>`,
        editor: '',
        gifButton: html `<button ${attributes(gifButtonAttributes(view))}>${gifButtonContent(view)}</button>`,
        gifArea: html `<div class="comments__gif-area" data-comments-slot="footer">${gifAreaTemplate(view)}</div>`,
    };
    return view;
}
function attributesFor(view) {
    return attributes(view.control('secondaryButton', { identify: 'comments__cancel verbb-editor__button', attributes: { type: 'button', 'data-comments-action': 'cancel-editor', 'data-comments-slot': 'actions' } }));
}
