/// <reference types="vite/client" />
/**
 * The Comment Fields settings' two previews: the site's comment form with the fields it asks for, and
 * a posted comment with their answers where they'd show. Both are drawn by Comments' own templates
 * from sample answers, with its default theme, in a shadow root so the control panel's styles and
 * the theme's stay apart. They follow the switches and selects beside them as they change.
 */
import type { CommentData, FieldValueData, FormFieldData, ThreadState } from '@verbb/comments-core';
import commentsCss from '@verbb/comments-browser/css/comments.css?inline';
import { html, render } from 'lit-html';
import { formClass } from '../../../../../packages/comments-browser/src/parts/form.js';
import { commentItem, createFormView, createStringView, formTemplate } from '../../../../../packages/comments-browser/src/render.js';
import './comment-fields.css';

type Row = { uid: string; label: string; description: FormFieldData | null };
type JQueryLike = (element: Element) => { on(event: string, handler: () => void): void };

const SAMPLE_TEXT = 'Has anyone styled the thread with Tailwind? The web starter does it with the stable class names.';

/** A believable answer for each kind of field, as the thread would show it. */
function sample(field: FormFieldData, photoUrl: string): Pick<FieldValueData, 'value' | 'text'> {
    const options = field.options.map((option) => option.label);

    switch (field.type) {
        case 'number': return { value: 4, text: '4' };
        case 'textarea': return { value: 'A longer answer, a sentence or two about how it went.', text: 'A longer answer, a sentence or two about how it went.' };
        case 'email': return { value: 'alex@example.com', text: 'alex@example.com' };
        case 'url': return { value: 'https://example.com/', text: 'example.com' };
        case 'date': return { value: '2026-11-11', text: new Date(2026, 10, 11).toLocaleDateString(undefined, { dateStyle: 'medium' }) };
        case 'select': return field.multiple ? { value: [], text: options.slice(0, 2).join(', ') } : { value: '', text: options[0] ?? 'An option' };
        case 'radio': return { value: '', text: options[0] ?? 'An option' };
        case 'checkboxes': return { value: [], text: options.slice(0, 2).join(', ') || 'Two options' };
        case 'toggle': return { value: true, text: 'Yes' };
        case 'file': return { value: [{ url: photoUrl, filename: 'photo.jpg', kind: 'image', width: 64, height: 48 }], text: 'photo.jpg' };
        default: return { value: 'Wellington', text: 'Wellington' };
    }
}

function state(fields: FormFieldData[], comment: CommentData | null, labels: Record<string, string>): ThreadState {
    const viewer = { isGuest: false, hasSession: true, name: 'Alex Morgan', initials: 'AM', avatarUrl: null, avatarTone: 5, badges: [], canComment: true, canVote: true, canReport: true, isModerator: false, blocked: false, loginUrl: null, follow: { available: false, following: false, needsEmail: false } };

    return {
        status: 'ready',
        error: null,
        sort: 'newest',
        threadVersion: null,
        conversation: null,
        editor: null,
        labels,
        locale: null,
        comments: comment ? { [comment.uid]: comment } : {},
        roots: comment ? [comment.uid] : [],
        next: null,
        loadingMore: false,
        replies: {},
        viewer,
        viewerEditor: { draftKey: 'preview', guest: { show: false, required: false, name: null, email: null, offerReplyEmails: false }, fields: { comment: fields, reply: fields }, protection: null, richEditor: null },
        permissions: comment ? { [comment.uid]: { canReply: true, canEdit: false, canDelete: false, canVote: true, canDownvote: true, canReport: true, vote: null, reported: false, isAuthor: false, moderation: null, custom: {} } } : {},
        moderation: null,
        busy: {},
        unavailable: [],
    } as unknown as ThreadState;
}

function mount(container: HTMLElement): ShadowRoot {
    const root = container.attachShadow({ mode: 'open' });
    const sheet = new CSSStyleSheet();

    sheet.replaceSync(`${commentsCss}\n:host { display: block; }\n.preview { pointer-events: none; }`);
    root.adoptedStyleSheets = [sheet];

    return root;
}

function init(settings: HTMLElement): void {
    const rows = JSON.parse(settings.querySelector('[data-comments-field-rows]')?.textContent ?? '[]') as Row[];
    // The wording the site's threads use, in the site's language.
    const labels = JSON.parse(settings.querySelector('[data-comments-field-labels]')?.textContent ?? '{}') as Record<string, string>;
    const photoUrl = settings.dataset.photoUrl ?? '';
    const formPreview = settings.querySelector<HTMLElement>('[data-comments-form-preview]');
    const cardPreview = settings.querySelector<HTMLElement>('[data-comments-card-preview]');

    if (!formPreview || !cardPreview) return;

    const formRoot = mount(formPreview);
    const cardRoot = mount(cardPreview);

    // The layout as it is in the designer right now, saved or not: each field's order, its width, and
    // whether it sits above the Comment element.
    const designer = settings.closest('form')?.querySelector<HTMLInputElement>('input[name="settings[fieldLayout]"]');
    const layout = (): { order: string[]; width: Record<string, number>; above: Set<string> } => {
        const order: string[] = [];
        const width: Record<string, number> = {};
        const above = new Set<string>();
        let seenComment = false;

        try {
            const config = JSON.parse(designer?.value ?? '{}') as { tabs?: { elements?: { type?: string; uid?: string; width?: number }[] }[] };

            for (const element of (config.tabs ?? []).flatMap((tab) => tab.elements ?? [])) {
                if (element.type === 'verbb\\comments\\fieldlayoutelements\\CommentsField') {
                    seenComment = true;
                } else if (element.uid) {
                    order.push(element.uid);
                    width[element.uid] = element.width ?? 100;
                    if (!seenComment) above.add(element.uid);
                }
            }
        } catch {
            // The designer's own value is always JSON; if it isn't, the saved layout stands.
        }

        // With no Comment element in the layout, nothing can come before it.
        if (!seenComment) above.clear();

        return { order, width, above };
    };

    const asked = (uid: string) => settings.querySelector(`[data-comments-field-ask="${uid}"]`)?.classList.contains('on') ?? false;
    const shownAs = (uid: string) => settings.querySelector<HTMLSelectElement>(`[data-comments-field-show="${uid}"]`)?.value ?? 'none';

    const draw = () => {
        const current = layout();
        const place = (uid: string) => (current.order.includes(uid) ? current.order.indexOf(uid) : Number.MAX_SAFE_INTEGER);
        const fields = rows
            .filter((row) => row.description && asked(row.uid) && (current.order.length === 0 || current.order.includes(row.uid)))
            .sort((a, b) => place(a.uid) - place(b.uid))
            .map((row) => ({
                ...row.description!,
                ...(current.order.length ? {
                    width: ([25, 50, 75].includes(current.width[row.uid] ?? 100) ? current.width[row.uid] : 100) as FormFieldData['width'],
                    formPosition: current.above.has(row.uid) ? 'above' as const : 'below' as const,
                } : {}),
            }));
        const uidOf = new Map(fields.map((field) => [field, rows.find((row) => row.description?.handle === field.handle)!.uid]));

        // The form, open, as a signed-in commenter sees it.
        const formView = createFormView(createStringView({ state: state(fields, null, labels), instanceId: 'preview' }), 'create', null);
        formView.collapsed = false;
        render(html`<section class="comments preview" data-comments-root data-comments-theme="light"><form class=${formClass('create')} novalidate>${formTemplate(formView)}</form></section>`, formRoot);

        // A posted comment with every asked field's sample answer where it shows.
        const shown: FieldValueData[] = [];

        for (const field of fields) {
            const placement = shownAs(uidOf.get(field)!);

            if (placement === 'inline' || placement === 'details') {
                shown.push({ handle: field.handle, label: field.label, type: field.type, placement, ...sample(field, photoUrl) });
            }
        }

        const comment = {
            uid: 'preview', parentUid: null, depth: 0, state: 'approved',
            content: { format: 'plain', html: null, text: SAMPLE_TEXT, gifUrl: null },
            author: { name: 'Alex Morgan', initials: 'AM', avatarUrl: null, avatarTone: 5, isGuest: false, badges: [] },
            createdAt: new Date(Date.now() - 3 * 86400000).toISOString(), editedAt: null,
            display: { timeAgo: '3 days ago', fullDate: '' },
            permalink: '#', fields: shown, interaction: { upvotes: 2, downvotes: 0 }, custom: {},
            replies: { count: 0, items: [], next: null },
        } as unknown as CommentData;
        const cardView = createStringView({ state: state(fields, comment, labels), instanceId: 'preview' });

        render(html`<section class="comments preview" data-comments-root data-comments-theme="light"><ol class="comments__list">${commentItem(cardView, comment)}</ol></section>`, cardRoot);

        // The second section only lists fields the form asks for, and says which answers stay hidden.
        const hidden: string[] = [];

        for (const row of rows) {
            const shownRow = settings.querySelector<HTMLElement>(`[data-comments-shown-row="${row.uid}"]`);
            const on = asked(row.uid);

            if (shownRow) shownRow.hidden = !on;
            if (on && row.description && shownAs(row.uid) === 'none') hidden.push(row.label);

            shownRow?.querySelector<HTMLElement>('[data-comments-email-warning]')?.toggleAttribute('hidden', shownAs(row.uid) === 'none');
        }

        const note = settings.querySelector<HTMLElement>('[data-comments-card-hidden]');
        if (note) note.textContent = hidden.length ? Craft.t('comments', 'Not shown: {fields}.', { fields: hidden.join(', ') }) : '';

        settings.querySelector<HTMLElement>('[data-comments-shown-empty]')?.toggleAttribute('hidden', rows.some((row) => row.description && asked(row.uid)));
    };

    // Craft's lightswitches announce changes as jQuery events, which native listeners don't hear.
    (window as unknown as { jQuery?: JQueryLike }).jQuery?.(settings).on('change', draw);
    settings.addEventListener('change', draw);

    // The designer sets its hidden value without an event, so the previews follow its changes to the
    // page instead: dragging a field, resizing it, or adding or removing one.
    const designerArea = settings.closest('form')?.querySelector('.layoutdesigner');
    let pending = 0;

    if (designerArea) {
        new MutationObserver(() => {
            cancelAnimationFrame(pending);
            pending = requestAnimationFrame(draw);
        }).observe(designerArea, { subtree: true, childList: true, attributes: true });
    }

    draw();
}

declare const Craft: { t(category: string, message: string, params?: Record<string, string>): string };

document.querySelectorAll<HTMLElement>('[data-comments-field-settings]').forEach(init);
