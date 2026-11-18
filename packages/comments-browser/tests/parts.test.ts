import { describe, expect, it, vi } from 'vitest';
import { createFormView, createStringView, formFieldsTemplate, renderToString, threadTemplate, formTemplate } from '../src/render';
import type { StringViewOptions } from '../src/render';
import { comment, GUEST, MEMBER, permissions, threadState } from './support/thread';

function draw(options: StringViewOptions): string {
    return renderToString(threadTemplate(createStringView(options)));
}

/** The markup with tags only, for asserting structure without attribute noise. */
function count(html: string, pattern: RegExp): number {
    return html.match(new RegExp(pattern.source, 'g'))?.length ?? 0;
}

describe('the thread', () => {
    it('draws the header, the editor, each comment and the dialog for a member', async () => {
        const html = draw({ state: await threadState() });

        expect(html).toContain('<h2 class="comments__heading" id="comments-109-1-heading" tabindex="-1">Comments <span class="comments__count"><span class="comments__visually-hidden">: </span>2</span></h2>');
        expect(html).toContain('aria-label="Sort comments"');
        expect(count(html, /class="comments__sort-option /)).toBe(3);
        expect(html).toMatch(/aria-pressed="true"[^>]*>Newest</);
        expect(count(html, /<li class="comments__comment"/)).toBe(2);
        expect(html).toContain('<article id="comment-');
        expect(html).toContain('aria-label="Comment by Alex Morgan"');
        expect(html).toContain('<dialog class="comments__report-dialog" data-comments-report-dialog');
        expect(html).toContain('data-comments-region="live" class="comments__live" role="status" aria-live="polite" aria-atomic="true"');
    });

    it('shows a loading line or the Show comments button before the thread arrives, with the count', async () => {
        const state = await threadState();
        const waiting = draw({ state, phase: 'waiting', count: 42 });
        const button = draw({ state, phase: 'button', count: 42 });

        expect(waiting).toContain('<p class="comments__loading" data-comments-placeholder aria-busy="true">Loading comments…</p>');
        expect(waiting).not.toContain('comments__comment');
        expect(button).toContain('data-comments-action="show-comments"');
        expect(button).toContain('>Show comments (42)</button>');
    });

    it('draws the empty state, and asks members to start it', async () => {
        const member = draw({ state: await threadState({ comments: [] }) });
        const guest = draw({ state: await threadState({ comments: [], viewer: GUEST }) });

        expect(member).toContain('<div class="comments__empty"><p>No comments yet.</p><p>Be the first to comment.</p></div>');
        expect(guest).toContain('<div class="comments__empty"><p>No comments yet.</p></div>');
    });

    it('gives a signed-out visitor the sign-in card, with the guest form when guests may post', async () => {
        const signIn = draw({ state: await threadState({ viewer: GUEST }) });
        const guests = draw({ state: await threadState({ viewer: { ...GUEST, canComment: true }, guest: { show: true, required: true, offerReplyEmails: true } }) });

        expect(signIn).toContain('<h3 class="comments__join-title"');
        expect(signIn).toContain('>Sign in to comment.</p>');
        expect(signIn).toMatch(/<a [^>]*href="\/login"[^>]*>Sign in<\/a>/);
        expect(signIn).not.toContain('data-comments-form="create"');
        expect(guests).toContain('<details class="comments__join-guest">');
        expect(guests).toContain('name="guest[name]"');
        expect(guests).toContain('name="guest[email]"');
        expect(guests).toContain('name="guest[notifyReplies]"');
        expect(guests).toContain('<span class="comments__field-hint">Not shown publicly</span>');
    });

    it('shows a closed conversation’s notice instead of the editor', async () => {
        const html = draw({ state: await threadState({ conversation: { status: 'closed', closedNotice: 'Closed after 30 days.' } }) });

        expect(html).toContain('<p class="comments__closed-notice">Closed after 30 days.</p>');
        expect(html).not.toContain('data-comments-form="create"');
    });
});

describe('a comment', () => {
    it('escapes everything a commenter wrote except the sanitised rich body', async () => {
        const evil = '<img src=x onerror=alert(1)>';
        const html = draw({
            state: await threadState({
                comments: [
                    comment({ uid: 'p1', content: { format: 'plain', html: null, text: `${evil}\nsecond line`, gifUrl: null, gifWidth: null, gifHeight: null }, author: { name: evil, initials: '<b>', avatarUrl: null, avatarTone: 1, isGuest: true, badges: [{ type: 'x" onclick="y', label: evil }], score: null } }),
                    comment({ uid: 'r1', content: { format: 'rich', html: '<p><strong>Bold</strong></p>', text: 'Bold', gifUrl: null, gifWidth: null, gifHeight: null } }),
                ],
            }),
        });

        expect(html).not.toContain('<img src=x');
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;<br>second line');
        expect(html).toContain('<span class="comments__author-name">&lt;img src=x onerror=alert(1)&gt;</span>');
        expect(html).toContain('class="comments__badge comments__badge--xonclicky"');
        expect(html).toContain('<div class="comments__content-rich"><p><strong>Bold</strong></p></div>');
    });

    it('likes: Like until there’s a count, then the count, pressed for the viewer’s own vote', async () => {
        const html = draw({
            state: await threadState({
                comments: [comment({ uid: 'a' }), comment({ uid: 'b', interaction: { upvotes: 4, downvotes: 0 } })],
                permissions: { a: permissions(), b: permissions({ vote: 'upvote' }) },
            }),
        });

        expect(html).toMatch(/data-comments-comment-uid="a" aria-pressed="false"[^>]*>.*?<span class="comments__vote-label">Like<\/span><\/button>/);
        expect(html).toMatch(/data-comments-comment-uid="b" aria-pressed="true"[^>]*>.*?<span class="comments__visually-hidden">Like<\/span><span class="comments__vote-count">4<\/span>/);
    });

    it('up and down votes show the score between the arrows', async () => {
        const html = draw({
            state: await threadState({
                conversation: { interactionMode: 'updown' },
                comments: [comment({ uid: 'a', interaction: { upvotes: 5, downvotes: 2 } })],
                permissions: { a: permissions({ canDownvote: true, vote: 'downvote' }) },
            }),
        });

        expect(html).toContain('data-comments-interaction-mode="updown"');
        expect(html).toContain('<span class="comments__score"><span class="comments__visually-hidden">Score: </span>3</span>');
        expect(html).toMatch(/comments__vote--down[^>]*aria-pressed="true"/);
    });

    it('shows a count without a button to someone who can’t vote, and nothing with voting off', async () => {
        const closed = draw({ state: await threadState({ comments: [comment({ uid: 'a', interaction: { upvotes: 3, downvotes: 0 } })], permissions: { a: permissions({ canVote: false }) } }) });
        const off = draw({ state: await threadState({ conversation: { interactionMode: 'none' }, comments: [comment({ uid: 'a', interaction: { upvotes: 3, downvotes: 0 } })] }) });

        expect(closed).toContain('<span class="comments__vote-total">');
        expect(closed).not.toContain('data-comments-action="vote"');
        expect(off).not.toContain('comments__interactions');
    });

    it('follows the viewer’s flags for a blocked commenter (SD-45): no vote, report, reply or follow', async () => {
        const blocked = { ...MEMBER, canComment: false, canVote: false, canReport: false, blocked: true, follow: { available: false, following: false, needsEmail: false } };
        const html = draw({ state: await threadState({ viewer: blocked, permissions: { c1: permissions({ canVote: false, canReport: false, canReply: false }), c2: permissions({ canVote: false, canReport: false, canReply: false }) } }) });

        expect(html).not.toContain('data-comments-action="vote"');
        expect(html).not.toContain('data-comments-action="report"');
        expect(html).not.toContain('data-comments-action="reply"');
        expect(html).not.toContain('data-comments-action="subscribe"');
        expect(html).not.toContain('data-comments-form="create"');
    });

    it('builds the ··· menu from what the viewer may do, with a moderator group', async () => {
        const html = draw({
            state: await threadState({
                comments: [comment({ uid: 'a' })],
                permissions: { a: permissions({ canEdit: true, canDelete: true, moderation: { reviewLabel: null, openReportCount: 0, actions: ['remove', 'spam'] } }) },
            }),
        });

        expect(html).toContain('<details class="comments__menu"><summary');
        expect(html).toMatch(/data-comments-action="copy-link"[^>]*>Copy link</);
        expect(html).toMatch(/data-comments-action="edit"[^>]*>Edit</);
        expect(html).toMatch(/data-comments-action="report"[^>]*>Report…</);
        expect(html).toMatch(/comments__menu-item comments__menu-item--danger" data-comments-action="delete"[^>]*>Delete</);
        expect(html).toContain('<span class="comments__menu-heading"');
        expect(html).toMatch(/data-comments-moderation-action="spam"[^>]*>Mark as spam</);
    });

    it('shows its author their own pending comment with a note (SD-44)', async () => {
        const pending = comment({ uid: 'mine', state: 'pending' });
        const html = draw({ state: await threadState({ comments: [comment({ uid: 'c1' })], extraComments: [pending], permissions: { c1: permissions(), mine: permissions({ isAuthor: true, canReply: false }) } }) });

        expect(html).toContain('class="comments__comment comments__comment--pending"');
        expect(html).toContain('data-comments-state="pending"');
        expect(html).toContain('Comment mine');
        expect(html).toContain('<p class="comments__review-label">');
        expect(html).toContain('Waiting for approval. Only you can see this until a moderator approves it.</p>');
        expect(count(html.slice(html.indexOf('data-comments-comment-uid="mine"')), /data-comments-action="vote"/)).toBe(0);
    });

    it('gives moderators held and reported comments with decisions, the first one primary', async () => {
        const held = comment({ uid: 'h1', state: 'held', moderation: { reviewLabel: 'Held · Pre-moderation', openReportCount: 0, actions: ['approve', 'spam', 'remove'] } });
        const reported = comment({ uid: 'r1', state: 'reported', moderation: { reviewLabel: 'Reported once', openReportCount: 1, actions: ['keep', 'remove', 'spam'] } });
        const html = draw({
            state: await threadState({ viewer: { ...MEMBER, isModerator: true }, comments: [comment({ uid: 'c1' })], extraComments: [held, reported], moderation: { heldCount: 1, reportedCount: 1, reviewUrl: '/admin/comments' } }),
        });

        expect(html).toContain('<div class="comments__moderator-bar" role="note"><p><strong>Moderator view.</strong> 1 held · 1 reported on this page</p>');
        expect(html).toContain('class="comments__comment comments__comment--held"');
        expect(html).toContain('class="comments__comment comments__comment--reported"');
        expect(html).toMatch(/class="comments__button comments__button--primary" [^>]*data-comments-moderation-action="approve"[^>]*>Approve</);
        expect(html).toMatch(/class="comments__button comments__button--secondary" [^>]*data-comments-moderation-action="spam"[^>]*>Spam</);
        expect(html).toContain('role="group" aria-label="Moderation"');
    });

    it('draws a placeholder for a hidden parent whose replies still show', async () => {
        const placeholder = comment({ uid: 'p', state: 'placeholder', content: null, author: null, display: null, createdAt: null, permalink: null, interaction: null, replies: { count: 1, items: [comment({ uid: 'kid', parentUid: 'p', depth: 1 })], next: null } });
        const html = draw({ state: await threadState({ comments: [placeholder] }) });

        expect(html).toContain('<p class="comments__placeholder">Comment unavailable.</p>');
        expect(html).not.toMatch(/data-comments-comment-uid="p"[^>]*><article[^>]*aria-label/);
        expect(html).toContain('data-comments-comment-uid="kid"');
    });

    it('indents replies to the fourth level, then shares its indent', async () => {
        let tip = comment({ uid: 'd6', depth: 6 });

        for (let depth = 5; depth >= 0; depth--) {
            tip = comment({ uid: `d${depth}`, depth, replies: { count: 1, items: [tip], next: null } });
        }

        const html = draw({ state: await threadState({ comments: [tip] }) });

        expect(html).toContain('data-comments-depth="6" data-comments-visual-depth="4"');
        expect(html).toContain('data-comments-depth="3" data-comments-visual-depth="3"');
        expect(count(html, /<ol class="comments__replies-list"/)).toBe(6);
    });

    it('offers the rest of a comment’s replies, with how many are left', async () => {
        const parent = comment({ uid: 'p', replies: { count: 5, items: [comment({ uid: 'k1', parentUid: 'p', depth: 1 })], next: 'cursor' } });
        const html = draw({ state: await threadState({ comments: [parent] }) });

        expect(html).toMatch(/data-comments-action="load-replies"[^>]*>Load more replies \(4\)<\/button>/);
        expect(html).toMatch(/data-comments-action="toggle-replies"[^>]*aria-expanded="true"[^>]*>Hide replies</);
    });

    it('collapses replies with Hide replies', async () => {
        const parent = comment({ uid: 'p', replies: { count: 1, items: [comment({ uid: 'k1', parentUid: 'p', depth: 1 })], next: null } });
        const html = draw({ state: await threadState({ comments: [parent] }), ui: { collapsed: new Set(['p']) } });

        expect(html).toMatch(/aria-expanded="false"[^>]*>Show replies</);
        expect(html).toMatch(/<ol class="comments__replies-list"[^>]*hidden>/);
    });

    it('shows the full date after a tap, and an edit', async () => {
        const html = draw({ state: await threadState({ comments: [comment({ uid: 'a', editedAt: '2026-11-10T10:00:00+00:00' })] }), ui: { fullDates: new Set(['a']) } });

        expect(html).toMatch(/<time class="comments__date" datetime="2026-11-09T21:04:00\+00:00" title="9 November 2026, 9:04 pm">9 November 2026, 9:04 pm<\/time>/);
        expect(html).toContain('<span class="comments__edited" title="2026-11-10T10:00:00+00:00">Edited</span>');
    });

    it('shows custom field values inline after the date and in a details list', async () => {
        const html = draw({
            state: await threadState({
                comments: [comment({ uid: 'a', fields: [
                    { handle: 'location', label: 'Where?', type: 'text', placement: 'inline', value: 'Rotorua', text: 'Rotorua, which is a long way from anywhere you might think of' },
                    { handle: 'rating', label: 'Rating', type: 'number', placement: 'details', value: 4, text: '4' },
                ] })],
            }),
        });

        expect(html).toContain('<span class="comments__meta-item comments__field-value--location">Rotorua, which is a long way from anywhe…</span>');
        expect(html).toContain('<dl class="comments__fields"><div class="comments__field-value comments__field-value--rating"><dt class="comments__field-name">Rating</dt><dd class="comments__field-text">4</dd></div></dl>');
    });

    it('links web addresses and files only when they’re http or https, with rel="ugc nofollow noopener"', async () => {
        const url = (handle: string, value: unknown, text = String(value)) => ({ handle, label: handle, type: 'url' as const, placement: 'details' as const, value, text });
        const html = draw({
            state: await threadState({
                comments: [comment({ uid: 'a', fields: [
                    url('site', 'https://example.com/run'),
                    url('script', 'javascript:alert(1)'),
                    url('nulled', null, 'javascript:alert(2)'),
                    url('spaced', ' JAVASCRIPT:alert(3)'),
                    url('data', 'data:text/html,<b>x</b>'),
                    { handle: 'files', label: 'Files', type: 'file', placement: 'details', text: 'run.jpg, x.svg, notes.pdf, private.pdf', value: [
                        { url: 'https://example.com/run.jpg', filename: 'run.jpg', kind: 'image', width: 1200, height: 800 },
                        { url: 'javascript:alert(4)', filename: 'x.svg', kind: 'image' },
                        { url: '/uploads/notes.pdf', filename: 'notes.pdf', kind: 'pdf' },
                        { url: null, filename: 'private.pdf', kind: 'pdf' },
                        { url: '/\t/evil.test/tab.pdf', filename: 'tab.pdf', kind: 'pdf' },
                        { url: '//evil.test/slashes.pdf', filename: 'slashes.pdf', kind: 'pdf' },
                    ] },
                    { handle: 'where', label: 'Where', type: 'url', placement: 'inline', value: 'https://example.com/a-very-long-address-that-goes-on-and-on', text: 'https://example.com/a-very-long-address-that-goes-on-and-on' },
                    { handle: 'bio', label: 'Bio', type: 'textarea', placement: 'details', value: '<img src=x onerror=alert(5)>', text: '<img src=x onerror=alert(5)>' },
                ] })],
            }),
        });

        expect(html).toContain('<dd class="comments__field-text"><a class="comments__field-link" href="https://example.com/run" rel="ugc nofollow noopener">https://example.com/run</a></dd>');
        expect(html).toContain('<dd class="comments__field-text">javascript:alert(1)</dd>');
        expect(html).toContain('<dd class="comments__field-text">javascript:alert(2)</dd>');
        expect(html).not.toMatch(/href="\s*(javascript|data):/i);
        expect(html).toContain('<a class="comments__field-file comments__field-file--image" href="https://example.com/run.jpg" rel="ugc nofollow noopener"><img class="comments__field-thumb" src="https://example.com/run.jpg" alt="run.jpg" loading="lazy" width="1200" height="800"></a>');
        expect(html).toContain('<span class="comments__field-file">x.svg</span>');
        expect(html).toContain('<a class="comments__field-file" href="/uploads/notes.pdf" rel="ugc nofollow noopener">notes.pdf</a>');
        expect(html).toContain('<span class="comments__field-file">private.pdf</span>');
        // A path with whitespace or a second slash would leave the site once a browser reads it.
        expect(html).toContain('<span class="comments__field-file">tab.pdf</span>');
        expect(html).toContain('<span class="comments__field-file">slashes.pdf</span>');
        expect(html).toContain('<span class="comments__meta-item comments__field-value--where"><a class="comments__field-link" href="https://example.com/a-very-long-address-that-goes-on-and-on" rel="ugc nofollow noopener">https://example.com/a-very-long-address-…</a></span>');
        expect(html).toContain('<dd class="comments__field-text">&lt;img src=x onerror=alert(5)&gt;</dd>');
    });

    it('shows a field through its display override by handle, inline and in the details list', async () => {
        const fields = [
            { handle: 'rating', label: 'Rating', type: 'number', placement: 'details', value: 4, text: '4' },
            { handle: 'mood', label: 'Mood', type: 'select', placement: 'inline', value: 'happy', text: 'Happy' },
        ];
        const html = draw({
            state: await threadState({ comments: [comment({ uid: 'a', fields: fields as never })] }),
            fields: {
                rating: { display: ({ html, value }) => html`<span class="stars" aria-label=${`${value} out of 5`}>${'★'.repeat(Number(value))}${'☆'.repeat(5 - Number(value))}</span>` },
                mood: { display: ({ html, defaultRender }) => html`<em>${defaultRender()}</em>` },
            },
        });

        expect(html).toContain('<dd class="comments__field-text"><span class="stars" aria-label="4 out of 5">★★★★☆</span></dd>');
        expect(html).toContain('<span class="comments__meta-item comments__field-value--mood"><em>Happy</em></span>');
    });
});

describe('the comment form', () => {
    it('is a collapsed frame for a member’s new comment, with Post waiting for text', async () => {
        const html = draw({ state: await threadState() });

        expect(html).toContain('<div class="comments__frame" data-comments-collapsed>');
        expect(html).toContain('<p class="comments__posting-as" data-comments-slot="header"><span>Posting as <b>Aroha Ngata</b></span></p>');
        expect(html).toMatch(/<textarea class="comments__textarea-fallback comments__textarea"[^>]*name="content"[^>]*placeholder="Add to the conversation…"/);
        expect(html).toMatch(/class="comments__submit comments__button comments__button--primary"[^>]*aria-label="Post" aria-disabled="true" aria-description="Write something first."/);
        expect(html).toContain('<kbd class="comments__submit-keys">⌘↵</kbd>');
    });

    it('draws a reply form with the reply chip and an edit form under its comment', async () => {
        const state = await threadState();
        const view = createStringView({ state, ui: { openForm: { mode: 'reply', uid: 'c2' } } });
        const html = renderToString(threadTemplate(view));

        expect(html).toContain('<p class="comments__frame-reply">');
        expect(html).toContain('<span class="comments__frame-reply-text">Replying to <b>Pete from Twizel</b></span>');
        expect(html).toContain('placeholder="Reply to Pete…"');
        expect(html).toContain('aria-label="Reply"');

        const edit = renderToString(threadTemplate(createStringView({ state, ui: { openForm: { mode: 'edit', uid: 'c1' } } })));
        expect(edit).toContain('placeholder="Edit your comment…"');
        expect(edit).toContain('aria-label="Save comment"');
    });

    it('shows its errors beside their fields, with the visible empty-post message (SD-44)', async () => {
        const state = await threadState();
        const form = createFormView(createStringView({ state }), 'create', null);
        const html = renderToString(formTemplate({ ...form, errors: { content: 'Write something first.', form: 'Please check the form.' } }));

        expect(html).toContain('<p class="comments__form-error" tabindex="-1">Please check the form.</p>');
        expect(html).toMatch(/aria-invalid="true" aria-describedby="comments-109-1-create-new-content-error"/);
        expect(html).toContain('<p class="comments__field-error" id="comments-109-1-create-new-content-error">Write something first.</p>');
    });

    it('gives each field the width set in the field layout, and puts fields placed before the Comment element above the text', async () => {
        const base = { uid: 'u', instructions: null, required: false, multiple: false, options: [], attributes: {}, public: false };
        const fields = [
            { ...base, handle: 'rating', label: 'Rating', type: 'number', formPosition: 'above', width: 50 },
            { ...base, handle: 'city', label: 'City', type: 'text', width: 25 },
            { ...base, handle: 'story', label: 'Story', type: 'textarea' },
        ] as never[];
        const html = draw({ state: await threadState({ fields: { comment: fields } }) });

        expect(html).toMatch(/<div class="comments__editor-fields comments__editor-fields--above"[^>]*><div class="comments__field-cell" data-comments-width="50">.*name="fields\[rating\]"/s);
        expect(html).toMatch(/<div class="comments__field-cell" data-comments-width="25">.*name="fields\[city\]"/s);
        expect(html).toMatch(/<div class="comments__field-cell" data-comments-width="100">.*name="fields\[story\]"/s);
        // The rating is drawn before the comment box, the others after it.
        expect(html.indexOf('fields[rating]')).toBeLessThan(html.indexOf('data-comments-body'));
        expect(html.indexOf('fields[city]')).toBeGreaterThan(html.indexOf('data-comments-body'));
    });

    it('draws an input for each of the 11 built-in field types', async () => {
        const base = { uid: 'u', instructions: null, required: false, multiple: false, options: [], attributes: {}, public: false };
        const options = [{ label: 'One', value: '1' }, { label: 'Two', value: '2' }];
        const fields = [
            { ...base, handle: 'plain', label: 'Plain', type: 'text' },
            { ...base, handle: 'long', label: 'Long', type: 'textarea', attributes: { maxlength: 200 } },
            { ...base, handle: 'number', label: 'Number', type: 'number', attributes: { min: 1, max: 5 } },
            { ...base, handle: 'email', label: 'Email', type: 'email', required: true },
            { ...base, handle: 'url', label: 'URL', type: 'url' },
            { ...base, handle: 'date', label: 'Date', type: 'date' },
            { ...base, handle: 'dropdown', label: 'Dropdown', type: 'select', options },
            { ...base, handle: 'multi', label: 'Multi', type: 'select', multiple: true, options },
            { ...base, handle: 'radio', label: 'Radio', type: 'radio', options, instructions: 'Pick one' },
            { ...base, handle: 'boxes', label: 'Boxes', type: 'checkboxes', options },
            { ...base, handle: 'toggle', label: 'Toggle', type: 'toggle' },
            { ...base, handle: 'photo', label: 'Photo', type: 'file', accept: ['.jpg', '.png'], maxFiles: 2, maxSize: 5242880, multiple: true },
        ] as never[];
        const html = draw({ state: await threadState({ fields: { comment: fields } }) });

        expect(html).toContain('<div class="comments__editor-fields" data-comments-slot="footer">');
        for (const [name, type] of [['plain', 'text'], ['number', 'number'], ['email', 'email'], ['url', 'url'], ['date', 'date']]) {
            expect(html).toMatch(new RegExp(`name="fields\\[${name}\\]" type="${type}"`));
        }
        expect(html).toMatch(/<textarea class="comments__textarea"[^>]*name="fields\[long\]"[^>]*maxlength="200"/);
        expect(html).toMatch(/name="fields\[number\]" type="number" min="1" max="5"/);
        expect(html).toMatch(/name="fields\[email\]" type="email" required/);
        // Password managers leave custom fields alone, so an email field doesn't make it a sign-in form.
        expect(html).toMatch(/name="fields\[email\]"[^>]*data-1p-ignore data-lpignore="true" data-bwignore data-form-type="other"/);
        expect(html).toMatch(/<textarea [^>]*name="fields\[long\]"[^>]*data-1p-ignore/);
        expect(html).toMatch(/<select class="comments__select"[^>]*name="fields\[dropdown\]"/);
        expect(html).toMatch(/<select class="comments__select"[^>]*name="fields\[multi\]\[\]" multiple/);
        expect(html).toMatch(/<fieldset class="comments__field comments__field-custom" aria-describedby="[^"]*-radio-instructions"><legend class="comments__field-label">Radio<\/legend>/);
        expect(count(html, /type="radio" name="fields\[radio\]"/)).toBe(2);
        expect(count(html, /type="checkbox" name="fields\[boxes\]\[\]"/)).toBe(2);
        expect(html).toMatch(/class="comments__switch comments__checkbox"[^>]*type="checkbox" role="switch" name="fields\[toggle\]" value="1"/);
        // An Assets field is a file tray: a picker kept out of the tab order, then Add files with its
        // limits beside it, which the button names as its description.
        expect(html).toMatch(/<input class="comments__visually-hidden" id="[^"]*-photo" type="file" tabindex="-1" aria-hidden="true" multiple accept="\.jpg,\.png" data-comments-upload-input="photo">/);
        expect(html).toMatch(/<div class="comments__upload-row"><button class="comments__upload-add comments__button comments__button--secondary" type="button" data-comments-upload-action="add" data-comments-upload-field="photo" aria-describedby="[^"]*-photo-limits"[^>]*>.*Add files<\/button><span class="comments__upload-limit" id="[^"]*-photo-limits">Up to 2 files, 5 MB each\.<\/span><\/div>/);
    });

    it('draws a field’s input override by handle inside Comments’ label, with its value and setValue()', async () => {
        const base = { uid: 'u', instructions: 'Out of five.', required: true, multiple: false, options: [], attributes: {}, public: true, placement: 'details' };
        const stars = vi.fn(({ html, value, id, labelId }: { html: typeof import('lit-html').html; value: unknown; id: string; labelId: string }) => html`<div id=${id} role="radiogroup" aria-labelledby=${labelId}>${[1, 2, 3, 4, 5].map((n) => html`<button type="button" aria-pressed=${String(Number(value) >= n)}>★</button>`)}</div>`);
        const state = await threadState({ fields: { comment: [{ ...base, handle: 'rating', label: 'Rating', type: 'number' }] as never[] } });
        const html = draw({ state, fields: { rating: { input: stars as never } } });

        expect(stars).toHaveBeenCalledWith(expect.objectContaining({ field: expect.objectContaining({ handle: 'rating' }), value: null, name: 'fields[rating]', mode: 'create', setValue: expect.any(Function), defaultRender: expect.any(Function) }));
        expect(html).toMatch(/<div class="comments__field comments__field-custom" data-comments-field="rating" data-comments-field-override><span class="comments__field-label" id="([^"]+)-rating-label">Rating <span class="comments__field-required" aria-hidden="true">\*<\/span><\/span><p class="comments__field-instructions"[^>]*>Out of five\.<\/p><div id="[^"]+-rating" role="radiogroup" aria-labelledby="[^"]+-rating-label">/);
        expect(count(html, /<button type="button" aria-pressed="false">★<\/button>/)).toBe(5);
        expect(html).not.toContain('name="fields[rating]"');
    });

    it('lets an input override wrap the built-in field with defaultRender()', async () => {
        const base = { uid: 'u', instructions: null, required: false, multiple: false, options: [], attributes: {}, public: false, placement: null };
        const state = await threadState({ fields: { comment: [{ ...base, handle: 'code', label: 'Code', type: 'text' }] as never[] } });
        const html = draw({ state, fields: { code: { input: ({ html, defaultRender }) => html`<div class="mine">${defaultRender()}</div>` } } });

        expect(html).toMatch(/<div class="mine"><div class="comments__field comments__field-custom"><label class="comments__field-label" for="[^"]+-code">Code<\/label><input class="comments__input"[^>]*name="fields\[code\]" type="text"/);
    });

    it('draws the file tray’s uploaded, uploading and failed files, with remove and retry', async () => {
        const html = renderToString(formFieldsTemplate({
            ...createFormView(createStringView({ state: await threadState() }), 'create', null),
            fields: [{ uid: 'u', handle: 'photos', label: 'Photos', instructions: null, required: false, type: 'file', multiple: true, options: [], attributes: {}, public: true, placement: 'details', accept: ['.jpg'], maxFiles: 2, maxSize: 1048576 }],
            uploads: { photos: [
                { key: 'u1', id: 1, filename: 'run.jpg', kind: 'image', size: 2048, preview: 'https://example.com/run.jpg', state: 'done', error: null },
                { key: 'u2', id: null, filename: 'big.jpg', kind: 'image', size: 4096, preview: null, state: 'failed', error: 'That file is too large.' },
            ] },
        }));

        expect(html).toContain('<li class="comments__upload comments__upload--image comments__upload--done"><img class="comments__upload-thumb" src="https://example.com/run.jpg" alt=""><span class="comments__upload-text"><span class="comments__upload-name">run.jpg</span><span class="comments__upload-meta">2 KB</span></span><button type="button" class="comments__upload-remove" data-comments-upload-action="remove" data-comments-upload-field="photos" data-comments-upload="u1" aria-label="Remove run.jpg">');
        expect(html).toContain('<li class="comments__upload comments__upload--file comments__upload--failed">');
        expect(html).toContain('<span class="comments__upload-meta">That file is too large.</span></span><button type="button" class="comments__upload-retry" data-comments-upload-action="retry" data-comments-upload-field="photos" data-comments-upload="u2" aria-label="Try big.jpg again">');
        // Two of two files: Add files hides, though a failed one doesn't count.
        expect(html).not.toMatch(/comments__upload-add[^>]*hidden/);
    });
});

describe('the report dialog', () => {
    it('quotes the comment and lists the reasons as radios', async () => {
        const state = await threadState();
        const html = draw({ state, ui: { reportUid: 'c1' } });

        expect(html).toContain('<blockquote class="comments__report-quote"><strong>Alex Morgan</strong>: Comment c1</blockquote>');
        expect(html).toMatch(/<input class="comments__radio"[^>]*type="radio" name="reason" value="spam" required/);
        expect(html).toContain('<span class="comments__report-reason-description">Ads, scams or links that don’t belong</span>');
        expect(html).toMatch(/<textarea class="comments__textarea"[^>]*name="details" rows="3" maxlength="2000"/);
    });
});

describe('part overrides', () => {
    it('replaces a part with a function’s template, which can wrap the default', async () => {
        const html = draw({
            state: await threadState({ comments: [comment({ uid: 'a', author: { name: 'Sam', initials: 'S', avatarUrl: null, avatarTone: 1, isGuest: false, badges: [{ type: 'verified', label: 'Verified buyer' }], score: null } })] }),
            parts: {
                badge: ({ badge, html, defaultRender }) => (badge as { type: string }).type === 'verified' ? html`<span class="badge badge-success">✓ ${(badge as { label: string }).label}</span>` : defaultRender(),
                authorName: ({ html, defaultRender }) => html`<em class="wrapped">${defaultRender()}</em>`,
            },
        });

        expect(html).toContain('<span class="badge badge-success">✓ Verified buyer</span>');
        expect(html).toContain('<em class="wrapped"><span class="comments__author-name">Sam</span></em>');
    });

    it('draws a custom element’s name as that element, and refuses names that aren’t one', async () => {
        const html = draw({ state: await threadState(), parts: { avatar: 'acme-avatar', meta: 'not a tag' } as never });

        expect(count(html, /<acme-avatar><\/acme-avatar>/)).toBe(2);
        expect(html).not.toContain('not a tag');
    });

    it('gives each override the labels, controls and actions', async () => {
        const seen = vi.fn(() => '');
        draw({ state: await threadState({ comments: [comment({ uid: 'a' })] }), parts: { actions: seen } });
        const context = (seen.mock.calls[0] as unknown[])[0] as Record<string, any>;

        expect(context.comment.uid).toBe('a');
        expect(context.labels.reply).toBe('Reply');
        expect(context.controlAttributes('quietButton').class).toBe('comments__button comments__button--quiet');
        expect(typeof context.defaultRender).toBe('function');
        expect(typeof context.html).toBe('function');
    });
});

describe('folded comments and author scores (SD-57)', () => {
    const scored = (name: string, score: number | null, isGuest = false) => ({ name, initials: name[0]!, avatarUrl: null, avatarTone: 1, isGuest, badges: [], score });

    it('folds a comment after reports or downvotes, keeping its author and actions, and drops its text, GIF and fields', async () => {
        const state = await threadState({
            conversation: { interactionMode: 'updown' },
            comments: [
                comment({ uid: 'r', folded: 'reports', content: { format: 'plain', html: null, text: 'Hidden text', gifUrl: 'https://media.giphy.com/a.gif', gifWidth: null, gifHeight: null }, fields: [{ handle: 'where', label: 'Where', type: 'text', placement: 'inline', value: 'Secret place', text: 'Secret place' }] }),
                comment({ uid: 'd', folded: 'downvotes' }),
            ],
        });
        const html = draw({ state });

        expect(html).toContain('<div class="comments__folded" data-comments-folded="reports"><p class="comments__folded-label">Comment marked as inappropriate</p>');
        expect(html).toContain('<div class="comments__folded" data-comments-folded="downvotes"><p class="comments__folded-label">Comment hidden due to low rating</p>');
        expect(html).toMatch(/data-comments-action="unfold"[^>]*data-comments-comment-uid="r"[^>]*>Show comment</);
        expect(html).not.toContain('Hidden text');
        expect(html).not.toContain('Secret place');
        expect(html).not.toContain('comments__gif');
        expect(count(html, /class="comments__author-name"/)).toBe(2);
        expect(count(html, /class="comments__actions"/)).toBe(2);
    });

    it('shows a folded comment once a reader opens it, through a content override', async () => {
        const state = await threadState({ comments: [comment({ uid: 'r', folded: 'reports', content: { format: 'plain', html: null, text: 'Now readable', gifUrl: null, gifWidth: null, gifHeight: null } })] });
        const html = draw({ state, ui: { unfolded: new Set(['r']) }, parts: { content: ({ html, defaultRender }) => html`<div class="mine">${defaultRender()}</div>` } });

        expect(html).not.toContain('comments__folded');
        expect(html).toContain('<div class="mine"><div class="comments__content" dir="auto"><p class="comments__content-plain">Now readable</p>');
    });

    it('shows an author’s score with its sign, and none for a guest or when scores are off', async () => {
        const state = await threadState({
            comments: [
                comment({ uid: 'a', author: scored('Ana', 12) }),
                comment({ uid: 'b', author: scored('Ben', -3) }),
                comment({ uid: 'c', author: scored('Cat', 0) }),
                comment({ uid: 'd', author: scored('Dan', null) }),
                comment({ uid: 'e', author: scored('Eve', 4, true) }),
            ],
        });
        const html = draw({ state });

        expect(html).toContain('<span class="comments__author-score comments__author-score--positive" title="Total votes across this user’s comments"><span class="comments__visually-hidden">Total votes across this user’s comments: </span>+12</span>');
        expect(html).toContain('<span class="comments__author-score comments__author-score--negative" title="Total votes across this user’s comments"><span class="comments__visually-hidden">Total votes across this user’s comments: </span>-3</span>');
        expect(html).toContain('<span class="comments__author-score" title="Total votes across this user’s comments"><span class="comments__visually-hidden">Total votes across this user’s comments: </span>0</span>');
        expect(count(html, /class="comments__author-score/)).toBe(3);
    });
});
