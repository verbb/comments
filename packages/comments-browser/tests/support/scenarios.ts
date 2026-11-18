import { createFormView, createStringView, formFieldsTemplate, formTemplate, gifAreaTemplate, gifPickerTemplate, renderToString, threadTemplate } from '../../src/render';
import type { StringViewOptions } from '../../src/render';
import { comment, GUEST, MEMBER, permissions, threadState } from './thread';

/**
 * The renderer drawn in every state the markup reference covers, as HTML strings, for the class
 * list and the control tests.
 */
export async function scenarios(): Promise<Record<string, string>> {
    const draw = (options: StringViewOptions) => renderToString(threadTemplate(createStringView(options)));
    const fieldBase = { uid: 'u', instructions: 'Help', required: true, multiple: false, options: [{ label: 'One', value: '1' }], attributes: {}, public: false, placement: null, formPosition: 'below' as const };
    // A number first, as a rating placed before the Comment element would be, and the rest after the text.
    const fields = (['text', 'textarea', 'number', 'email', 'url', 'date', 'select', 'radio', 'checkboxes', 'toggle', 'file'] as const).map((type) => ({ ...fieldBase, handle: type, label: type, type, formPosition: type === 'number' ? 'above' as const : 'below' as const }));
    const deep = comment({
        uid: 'r', interaction: { upvotes: 2, downvotes: 0 }, author: { name: 'Sam', initials: 'S', avatarUrl: 'https://example.com/sam.jpg', avatarTone: 1, isGuest: false, badges: [{ type: 'owner', label: 'Author' }], score: null },
        content: { format: 'rich', html: '<p>Rich</p>', text: 'Rich', gifUrl: 'https://example.com/a.gif', gifWidth: 480, gifHeight: 270 }, editedAt: '2026-11-10T10:00:00+00:00',
        fields: [
            { handle: 'where', label: 'Where', type: 'text', placement: 'inline', value: 'Here', text: 'Here' },
            { handle: 'rating', label: 'Rating', type: 'number', placement: 'details', value: 4, text: '4' },
            { handle: 'site', label: 'Site', type: 'url', placement: 'details', value: 'https://example.com/', text: 'https://example.com/' },
            { handle: 'photos', label: 'Photos', type: 'file', placement: 'details', text: 'run.jpg, route.pdf', value: [
                { url: 'https://example.com/run.jpg', filename: 'run.jpg', kind: 'image', width: 1200, height: 800 },
                { url: 'https://example.com/route.pdf', filename: 'route.pdf', kind: 'pdf' },
            ] },
        ],
        replies: { count: 4, items: [comment({ uid: 'r-1', parentUid: 'r', depth: 1 })], next: 'cursor' },
    });
    const member = await threadState({
        comments: [deep, comment({ uid: 'p', state: 'placeholder', content: null, author: null, display: null, createdAt: null, permalink: null, interaction: null, replies: { count: 1, items: [comment({ uid: 'p-1', parentUid: 'p', depth: 1 })], next: null } })],
        // A portrait GIF, which the theme sizes from its height.
        extraComments: [comment({ uid: 'mine', state: 'pending', content: { format: 'plain', html: null, text: 'Mine', gifUrl: 'https://example.com/b.gif', gifWidth: 220, gifHeight: 293 } })],
        permissions: { r: permissions({ canEdit: true, canDelete: true, vote: 'upvote' }), 'r-1': permissions(), 'p-1': permissions(), mine: permissions({ isAuthor: true }) },
        fields: { comment: fields, reply: fields },
        next: 'more',
        editor: { maxLength: 100 },
    });
    const moderator = await threadState({
        viewer: { ...MEMBER, isModerator: true },
        conversation: { interactionMode: 'updown' },
        comments: [comment({ uid: 'a' })],
        permissions: { a: permissions({ canDownvote: true, moderation: { reviewLabel: null, openReportCount: 0, actions: ['remove', 'spam'] } }) },
        extraComments: [
            comment({ uid: 'h', state: 'held', moderation: { reviewLabel: 'Held', openReportCount: 0, actions: ['approve', 'spam'] } }),
            comment({ uid: 's', state: 'spam', moderation: { reviewLabel: 'Spam', openReportCount: 0, actions: ['not-spam'] } }),
            comment({ uid: 'x', state: 'reported', moderation: { reviewLabel: 'Reported once', openReportCount: 1, actions: ['keep', 'remove'] } }),
        ],
        moderation: { heldCount: 1, reportedCount: 1, reviewUrl: '/admin' },
    });
    const guest = await threadState({ viewer: { ...GUEST, canComment: true, follow: { available: true, following: false, needsEmail: true } }, guest: { show: true, required: true, offerReplyEmails: true } });
    const closed = await threadState({ conversation: { status: 'closed', closedNotice: 'Closed.' }, viewer: { ...GUEST, follow: { available: true, following: false, needsEmail: true } }, comments: [comment({ uid: 'v', interaction: { upvotes: 3, downvotes: 0 } })], permissions: { v: permissions({ canVote: false }) } });
    // Comments 3's thresholds and author scores (SD-57): folded for each reason, one opened, and scores either side of 0.
    const scored = (name: string, score: number) => ({ name, initials: name[0]!, avatarUrl: null, avatarTone: 2, isGuest: false, badges: [], score });
    const foldedState = await threadState({
        conversation: { interactionMode: 'updown' },
        comments: [
            comment({ uid: 'f1', folded: 'reports', author: scored('Ana', 12) }),
            comment({ uid: 'f2', folded: 'downvotes', author: scored('Ben', -3) }),
            comment({ uid: 'f3', folded: 'reports', author: scored('Cat', 0) }),
        ],
    });
    const empty = await threadState({ comments: [] });
    const form = createFormView(createStringView({ state: member }), 'reply', 'r');
    const gifState = await threadState({ gifs: { apiKey: 'key', rating: 'g', limit: 24, language: 'en' } });
    const gifForm = { ...createFormView(createStringView({ state: gifState }), 'create', null), gif: { url: 'https://media.giphy.com/media/a/giphy.webp', width: 480, height: 360 }, gifOpen: true };
    const gifResult = { title: 'Wave', images: { fixed_width: { webp: 'https://media.giphy.com/media/a/200w.webp', width: '200', height: '150' } } };

    return {
        mountPoint: '<section id="comments-109-1" class="comments comments--reserve-4" data-comments-root><h2 class="comments__heading">42 comments</h2><noscript><p class="comments__noscript">Turn on JavaScript to read and post comments.</p></noscript></section>',
        member: draw({ state: member, ui: { openForm: { mode: 'reply', uid: 'r' }, highlight: 'r', toast: { message: 'Saved.', kind: 'success', retry: true }, reportUid: 'r', reportError: 'Nope.' } }),
        memberEdit: draw({ state: member, ui: { openForm: { mode: 'edit', uid: 'r' }, collapsed: new Set(['r']) } }),
        moderator: draw({ state: moderator }),
        folded: draw({ state: foldedState, ui: { unfolded: new Set(['f3']) } }),
        guest: draw({ state: guest }),
        closed: draw({ state: closed }),
        empty: draw({ state: empty }),
        waiting: draw({ state: empty, phase: 'waiting', count: 3 }),
        button: draw({ state: empty, phase: 'button', count: 3 }),
        error: draw({ state: empty, phase: 'error', count: 3 }),
        formStates: [
            { length: 85, draftState: 'saved' as const, notice: { message: 'Restored your draft.', action: 'Discard' } as { message: string; action: string } | null, errors: <Record<string, string>>{ content: 'Too long.', form: 'Check the form.', 'guest.name': 'Name?', 'fields.text': 'Needed.' } },
            { length: 95, draftState: 'faded' as const, notice: null as { message: string; action: string } | null, errors: {} as Record<string, string> },
            { length: 120, draftState: null as 'saved' | 'faded' | null, notice: null as { message: string; action: string } | null, errors: {} as Record<string, string> },
        ].map((state) => renderToString(formTemplate({ ...form, ...state, guest: { ...form.guest, show: true } }))).join(''),
        gifs: renderToString(formTemplate(gifForm)) + renderToString(gifAreaTemplate({ ...gifForm, gifPanel: gifPickerTemplate(gifForm, { query: 'wave', status: 'ready', results: [gifResult, {}] }) })) + renderToString(gifAreaTemplate({ ...gifForm, gif: null })),
        fileTray: renderToString(formFieldsTemplate({
            ...form,
            fields: [{ ...fieldBase, handle: 'photos', label: 'Photos', type: 'file', multiple: true, accept: ['.jpg', '.pdf'], maxFiles: 3, maxSize: 5242880 }],
            uploads: { photos: [
                { key: 'u1', id: 1, filename: 'run.jpg', kind: 'image', size: 2048, preview: 'https://example.com/run.jpg', state: 'done', error: null },
                { key: 'u2', id: null, filename: 'route.pdf', kind: 'file', size: 4096, preview: null, state: 'uploading', error: null },
                { key: 'u3', id: null, filename: 'big.pdf', kind: 'file', size: 4096, preview: null, state: 'failed', error: 'Too large.' },
            ] },
        })),
    };
}

/** Every class in some HTML. */
export function classesIn(html: string): Set<string> {
    const found = new Set<string>();

    for (const match of html.matchAll(/\sclass="([^"]*)"/g)) {
        match[1]!.split(/\s+/).filter(Boolean).forEach((name) => found.add(name));
    }

    return found;
}
