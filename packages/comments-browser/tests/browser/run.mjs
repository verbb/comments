// The renderer's browser checks: the plugin's built comments script against a stubbed data API, in
// headless Chromium by default. Run `npm run build:assets`, then `node packages/comments-browser/tests/browser/run.mjs`.
//
// It needs `playwright-core` and a browser. COMMENTS_ENGINES picks engines (`chromium,firefox,webkit`),
// PLAYWRIGHT_CORE points at another copy of playwright-core, and PW_FIREFOX and PW_WEBKIT at browser
// builds to launch with `executablePath`, and COMMENTS_SECTIONS runs only the named sections. The
// performance checks (gate 2) need Chromium's DevTools protocol.
import { readdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const here = path.dirname(fileURLToPath(import.meta.url));
const plugin = path.resolve(here, '../../../..');
const dist = path.resolve(plugin, 'src/web/assets/frontend/dist');
const script = readFileSync(path.join(dist, 'js/comments.js'), 'utf8');
// The renderer and the forms, which the startup loader imports from beside itself (SD-46).
const chunks = new Map(readdirSync(path.join(dist, 'js/chunks')).map((file) => [file, readFileSync(path.join(dist, 'js/chunks', file), 'utf8')]));
const allScript = [script, ...chunks.values()].join('\n');
const css = readFileSync(path.join(dist, 'css/comments.css'), 'utf8');
const axe = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

let playwright;

try {
    playwright = require(process.env.PLAYWRIGHT_CORE ?? 'playwright-core');
} catch {
    try {
        playwright = require(path.join(process.env.HOME ?? '', 'node_modules/playwright-core'));
    } catch {
        console.error('These checks need playwright-core and its Chromium: npm install --no-save playwright-core && npx playwright-core install chromium');
        process.exit(1);
    }
}

const ORIGIN = 'https://comments.test';
const engines = (process.env.COMMENTS_ENGINES ?? 'chromium').split(',');
const results = [];
let engine = '';

function check(name, ok, detail = '') {
    results.push({ engine, name, ok: Boolean(ok) });
    console.log(`${ok ? 'PASS' : 'FAIL'}  [${engine}] ${name}${detail ? `  (${detail})` : ''}`);
}

// Data
// -----------------------------------------------------------------------------

const LABELS = JSON.parse(readFileSync(path.join(here, 'labels.json'), 'utf8'));
let id = 1400;

// The thread's own comments keep their numbers, so a link to one is the same in every check.
const NUMBERS = { c1: 1001, c2: 1002, c3: 1003, c4: 1004, c5: 1005 };

function comment(uid, overrides = {}) {
    const n = NUMBERS[uid] ?? ++id;
    return {
        uid, parentUid: null, depth: 0, state: 'approved',
        content: { format: 'plain', html: null, text: `Comment ${uid}`, gifUrl: null, gifWidth: null, gifHeight: null },
        author: { name: 'Alex Morgan', initials: 'AM', avatarUrl: null, avatarTone: 3, isGuest: false, badges: [], score: null },
        createdAt: '2026-11-09T21:04:00+00:00', editedAt: null, display: { timeAgo: '2 hours ago', fullDate: '9 November 2026, 9:04 pm' },
        permalink: `${ORIGIN}/post#comment-${n}`, fields: [], interaction: { upvotes: 0, downvotes: 0 }, folded: null, custom: {},
        replies: { count: 0, items: [], next: null }, ...overrides,
    };
}

function permissions(overrides = {}) {
    return { canReply: true, canEdit: false, canDelete: false, canVote: true, canDownvote: false, canReport: true, vote: null, reported: false, isAuthor: false, moderation: null, custom: {}, ...overrides };
}

const MEMBER_NAME = 'Aroha Ngata';
const MEMBER = { isGuest: false, hasSession: true, name: 'Aroha Ngata', initials: 'AN', avatarUrl: null, avatarTone: 6, badges: [], canComment: true, canVote: true, canReport: true, isModerator: false, blocked: false, loginUrl: null, follow: { available: true, following: false, needsEmail: false } };

/** A fake data API with one thread, answering every action the renderer uses and recording requests. */
function server(options = {}) {
    const roots = options.roots ?? [comment('c1', { interaction: { upvotes: 2, downvotes: 0 } }), comment('c2', { replies: { count: 3, items: [comment('c2-1', { parentUid: 'c2', depth: 1 })], next: 'rc' } }), comment('c3')];
    const page2 = options.page2 ?? [comment('c4'), comment('c5')];
    const perms = Object.fromEntries([...roots, ...page2, ...roots.flatMap((root) => root.replies.items), comment('c2-2'), comment('c2-3')].map((item) => [item.uid, permissions(options.permissions?.(item.uid) ?? {})]));
    const extra = options.extraComments ?? [];
    const requests = [];
    const conversation = {
        uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: 8, rootCount: 5, sort: 'newest', sorts: ['newest', 'oldest', 'top'],
        interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: true,
        reportReasons: [{ value: 'spam', label: 'Spam', description: 'Ads, scams or links that don’t belong' }, { value: 'other', label: 'Other', description: null }],
        ...options.conversation,
    };
    const editor = { authoring: 'plain', maxLength: null, richEditor: null, ...options.editor };
    const ok = (body) => ({ apiVersion: 1, status: 'ok', ...body });

    const answers = {
        thread: (query) => ok({
            threadVersion: 'v1', conversation: { ...conversation, sort: query.get('sort') ?? 'newest' }, editor,
            comments: query.get('cursor') ? page2 : roots, page: { next: query.get('cursor') ? null : 'cursor-2', limit: 20 }, labels: { ...LABELS, ...options.labels }, locale: 'en',
        }),
        viewer: () => ok({
            threadVersion: 'v1', viewer: options.viewer ?? MEMBER,
            editor: { draftKey: 'comments-109-1-7', guest: { show: false, required: true, name: null, email: null, offerReplyEmails: false, ...options.guest }, fields: { comment: options.fields?.comment ?? [], reply: options.fields?.reply ?? [] }, protection: null, richEditor: options.richOptions ?? null, gifs: options.gifs ?? null },
            comments: perms, extraComments: extra, moderation: options.moderation ?? null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' }, custom: {},
        }),
        replies: () => ok({ parentUid: 'c2', replies: { count: 3, items: [comment('c2-2', { parentUid: 'c2', depth: 1 }), comment('c2-3', { parentUid: 'c2', depth: 1 })], next: null } }),
        // With `protect`, a fresh single-use preparation each time, numbered, as Protect's would be.
        protection: () => ok({ protection: options.protect ? preparation(requests.filter((request) => request.action === 'protection').length) : null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } }),
        session: () => ok({ viewer: options.viewer ?? MEMBER, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } }),
        edit: (query) => ok({ comment: { uid: query.get('uid'), content: { format: 'plain', text: `Comment ${query.get('uid')}`, document: null } }, fields: options.editFields ?? {}, editor: { richEditor: null, ...(options.editEditorFields ? { fields: options.editEditorFields } : {}) } }),
        // A custom field's file, numbered from 501.
        upload: (_, body) => ok({ field: body.field, file: { id: 500 + requests.filter((request) => request.action === 'upload').length, filename: body.file?.filename ?? 'upload', kind: 'image', size: 2048 } }),
        create: (_, body) => ok({ replayed: false, comment: { ...comment(`new-${requests.length}`, { parentUid: body.parentUid, depth: body.parentUid ? 1 : 0, content: { format: 'plain', html: null, text: body.content.text ?? 'Rich', gifUrl: null, gifWidth: null, gifHeight: null } }), viewer: permissions({ isAuthor: true, canEdit: true }) }, moderationState: 'approved', conversation: { commentCount: 9 }, announcement: 'Comment posted.' }),
        update: (_, body) => ok({ replayed: false, comment: { ...comment(body.uid, { content: { format: 'plain', html: null, text: body.content.text, gifUrl: null, gifWidth: null, gifHeight: null }, editedAt: '2026-11-10T00:00:00+00:00' }), viewer: permissions({ canEdit: true, canDelete: true, isAuthor: true }) }, moderationState: 'approved', announcement: 'Comment saved.' }),
        vote: (_, body) => ok({ replayed: false, comment: { uid: body.uid, interaction: { upvotes: body.vote === 'upvote' ? 3 : 2, downvotes: 0 }, viewer: permissions({ vote: body.vote }) }, announcement: null }),
        report: (_, body) => ok({ replayed: false, comment: { uid: body.uid, viewer: permissions({ reported: true }) }, announcement: 'Thanks for reporting this. A moderator will take a look.' }),
        delete: (_, body) => ok({ replayed: false, uid: body.uid, conversation: { commentCount: 7 }, announcement: 'Comment deleted.' }),
        moderate: (_, body) => ok({ replayed: false, comment: { ...comment(body.uid), moderation: { reviewLabel: null, openReportCount: 0, actions: ['remove', 'spam'] } }, announcement: 'Comment approved.' }),
        follow: (_, body) => ok({ replayed: false, viewer: { follow: { available: true, following: body.following, needsEmail: false } }, outcome: body.following ? 'followed' : 'unfollowed', announcement: body.following ? 'You’re following this conversation.' : null }),
    };

    return {
        requests,
        conversation,
        async handle(route) {
            const request = route.request();
            const url = new URL(request.url());
            const action = url.pathname.split('/').pop();
            const body = (request.headers()['content-type'] ?? '').includes('multipart/form-data') ? multipart(request) : request.postData() ? JSON.parse(request.postData()) : null;
            requests.push({ action, url, body, headers: request.headers() });

            if (options.override?.[action]) {
                const answer = await options.override[action](url.searchParams, body, requests);
                if (answer) return route.fulfill({ status: answer.status ?? 200, contentType: 'application/json', headers: { 'X-Comments-Api-Version': '1' }, body: JSON.stringify(answer.body) });
            }

            if (options.delay?.[action]) await new Promise((resolve) => setTimeout(resolve, options.delay[action]));

            const answer = answers[action];

            if (!answer) return route.fulfill({ status: 404, body: '' });

            return route.fulfill({ contentType: 'application/json', headers: { 'X-Comments-Api-Version': '1' }, body: JSON.stringify(answer(url.searchParams, body)) });
        },
    };
}

/** The mount point `craft.comments.render()` writes, below a long article. */
/** A Protect preparation with a honeypot and the JavaScript check, which need nothing from outside. */
function preparation(n) {
    const now = Math.floor(Date.now() / 1000);
    return { version: 1, manifest: { version: 1, instanceId: `pi-${n}`, preparation: `prep-${n}`, issuedAt: now, expiresAt: now + 600, honeypot: 'a1b2c3', javascript: true, challenges: [] }, expiresAt: now + 600 };
}

/** A multipart upload's fields, with a file as its name. */
function multipart(request) {
    const boundary = /boundary=(.+)$/.exec(request.headers()['content-type'] ?? '')?.[1];
    const raw = request.postDataBuffer()?.toString('latin1') ?? '';
    const fields = {};

    for (const part of boundary ? raw.split(`--${boundary}`) : []) {
        const match = /name="([^"]+)"(?:; filename="([^"]*)")?/.exec(part);
        if (match) fields[match[1]] = match[2] !== undefined ? { filename: match[2] } : part.slice(part.indexOf('\r\n\r\n') + 4).replace(/\r\n$/, '');
    }

    return fields;
}

function pageHtml({ load = 'eager', theme = 'light', config = { sort: 'newest', limit: 20 }, autoMount = true, extraScript = '', before = '', after = '', dir = 'ltr', nonce = '', htmlClass = '' } = {}) {
    const n = nonce ? ` nonce="${nonce}"` : '';
    return `<!doctype html><html lang="en" dir="${dir}" class="${htmlClass}"><head><meta charset="utf-8"><title>Thread</title><link rel="stylesheet" href="/comments.css"></head><body${autoMount ? '' : ' data-comments-auto-mount="false"'}>
        <main><h1>Post</h1><nav><a id="toc" href="#chapter-2">Chapter 2</a></nav><h2 id="chapter-2">Chapter 2</h2>
        ${before}
        <div id="spacer" class="${load === 'visible' ? 'tall' : 'short'}"></div>
        <section id="comments-109-1" class="comments comments--reserve-4" aria-labelledby="comments-109-1-heading" data-comments-root data-comments-api-version="1" data-comments-endpoint="${ORIGIN}/actions/comments/api" data-comments-owner-id="109" data-comments-site-id="1" data-comments-load-mode="${load}" data-comments-theme="${theme}" data-comments-config='${JSON.stringify(config)}' data-comments-count="8" data-comments-labels='{"comments":"Comments","loading":"Loading comments…","showComments":"Show comments","showCommentsCount":"Show comments ({count})"}'><h2 id="comments-109-1-heading" class="comments__heading">8 comments</h2><noscript><p class="comments__noscript">Turn on JavaScript to read and post comments.</p></noscript></section>
        ${after}
        <p id="after">After the thread.</p></main>
        <script type="module" src="/comments.js"${n}></script>${extraScript}
    </body></html>`;
}

/** A strict policy: scripts only with the nonce, no inline styles, and Trusted Types for every sink. */
const STRICT_CSP = "default-src 'self'; script-src 'nonce-n0nce' 'strict-dynamic'; style-src 'self'; img-src 'self' data:; connect-src 'self'; require-trusted-types-for 'script'; trusted-types lit-html verbb-comments verbb-protect";

// A stand-in for Editor's element, enough for the swap: it renders a contenteditable and gives the
// `editor` methods Comments calls, so the checks don't need Editor's bundle.
const FAKE_EDITOR = `
customElements.define('verbb-editor', class extends HTMLElement {
    connectedCallback() {
        if (this.querySelector('[contenteditable]')) return;
        const box = document.createElement('div');
        box.contentEditable = 'true';
        box.setAttribute('role', 'textbox');
        box.setAttribute('aria-labelledby', this.getAttribute('aria-labelledby') ?? '');
        this.prepend(box);
        // As Editor does: its text changes are announced, and ⌘↵ or Ctrl+Enter posts the form.
        box.addEventListener('input', () => this.dispatchEvent(new CustomEvent('editor-update', { bubbles: true })));
        box.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                this.closest('form')?.requestSubmit();
            }
        });
        this.editor = {
            getText: () => box.textContent ?? '',
            getJSON: () => ({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: box.textContent }] }] }),
            setContent: (text) => { box.textContent = typeof text === 'string' ? text : ''; },
            isUploading: () => false,
            // As Editor's GIF control: the GIF goes with the document, and GIPHY hears it was sent.
            getGif: () => this.gif ?? null,
            setGif: (gif) => { this.gif = gif; },
            reportGifSent: () => { window.__gifsSent = (window.__gifsSent ?? 0) + 1; },
        };
    }
    focus() { this.querySelector('[contenteditable]')?.focus(); }
    showPostError() {}
    clearPostError() {}
    clearDraft() {}
});`;

/** The stand-in with Editor's `loadFeatures()`, and a note of any editor added before it finished. */
function withFeatures(method) {
    return FAKE_EDITOR
        .replace("customElements.define('verbb-editor', class extends HTMLElement {", `customElements.define('verbb-editor', class extends HTMLElement {\n    ${method}`)
        .replace('    connectedCallback() {\n', '    connectedCallback() {\n        if (!window.__featuresLoaded) window.__addedEarly = true;\n');
}

let browser;

async function open(options = {}) {
    const context = await browser.newContext({ viewport: { width: 1000, height: 800 }, ...(options.context ?? {}) });
    const page = await context.newPage();
    const api = server(options.server);
    const errors = [];
    const warnings = [];
    const fetched = [];

    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') errors.push(message.text());
        if (message.type() === 'warning') warnings.push(message.text());
    });
    page.on('dialog', (dialog) => dialog.accept());

    // Another origin, from which Editor must never be loaded.
    await page.route('https://elsewhere.test/**', (route) => {
        fetched.push(`elsewhere:${new URL(route.request().url()).pathname}`);
        return route.fulfill({ contentType: 'text/javascript', body: FAKE_EDITOR });
    });

    await page.route(`${ORIGIN}/**`, async (route) => {
        const url = new URL(route.request().url());
        fetched.push(url.pathname);

        if (url.pathname.startsWith('/actions/comments/api/')) return api.handle(route);
        if (url.pathname === '/comments.js' || url.pathname === '/comments-copy.js') return route.fulfill({ contentType: 'text/javascript', body: script });
        if (url.pathname.startsWith('/chunks/') && chunks.has(url.pathname.slice(8))) {
            // A chunk that can't be fetched, as after a plugin update strands an open tab.
            if (options.failChunk?.(url.pathname)) return route.abort('failed');
            if (options.chunkDelay?.(url.pathname)) await new Promise((resolve) => setTimeout(resolve, options.chunkDelay(url.pathname)));
            // The comment forms arriving late, after the thread has drawn.
            if (options.formsDelay && url.pathname.startsWith('/chunks/form-controller')) await new Promise((resolve) => setTimeout(resolve, options.formsDelay));
            return route.fulfill({ contentType: 'text/javascript', body: chunks.get(url.pathname.slice(8)) });
        }
        if (url.pathname === '/comments.css') return route.fulfill({ contentType: 'text/css', body: css });
        // Files a check serves itself, such as the web components bundle.
        if (options.files?.has(url.pathname)) return route.fulfill({ contentType: 'text/javascript', body: options.files.get(url.pathname) });
        if (url.pathname === '/editor.js') {
            if (options.editorDelay) await new Promise((resolve) => setTimeout(resolve, options.editorDelay));
            return route.fulfill({ contentType: 'text/javascript', body: FAKE_EDITOR });
        }
        // A module that fails as it runs, and one that never defines the element.
        if (url.pathname === '/editor-throws.js') return route.fulfill({ contentType: 'text/javascript', body: 'throw new Error("broken build");' });
        if (url.pathname === '/editor-never.js') return route.fulfill({ contentType: 'text/javascript', body: 'window.__editorLoaded = true;' });
        // Editor whose features load on demand: slowly, or not at all.
        if (url.pathname === '/editor-features-slow.js') return route.fulfill({ contentType: 'text/javascript', body: withFeatures('static loadFeatures() { return new Promise((resolve) => setTimeout(() => { window.__featuresLoaded = true; resolve(); }, 600)); }') });
        if (url.pathname === '/editor-features-fail.js') return route.fulfill({ contentType: 'text/javascript', body: withFeatures('static loadFeatures() { return Promise.reject(new Error("no chunk")); }') });
        if (url.pathname === '/editor.css') return route.fulfill({ contentType: 'text/css', body: '' });
        if (url.pathname === '/page.css') return route.fulfill({ contentType: 'text/css', body: '#spacer.tall { height: 3000px } #spacer.short { height: 10px }' });

        return route.fulfill({ contentType: 'text/html', headers: options.csp ? { 'Content-Security-Policy': options.csp } : {}, body: (options.html ?? pageHtml(options.page)).replace('<link rel="stylesheet" href="/comments.css">', '<link rel="stylesheet" href="/comments.css"><link rel="stylesheet" href="/page.css">') });
    });

    await page.goto(`${ORIGIN}/post${options.hash ?? ''}`, { waitUntil: options.waitUntil ?? 'load' });

    if (options.wait !== false) {
        await page.waitForSelector('[data-comments-root][data-comments-phase="ready"]', { timeout: 10000 });
    }

    return { page, context, api, errors, warnings, fetched, close: () => context.close() };
}

const root = '[data-comments-root]';
const actions = (page, action, uid) => page.locator(`${root} [data-comments-action="${action}"]${uid ? `[data-comments-comment-uid="${uid}"]` : ''}`);
const focused = (page) => page.evaluate(() => {
    const element = document.activeElement;
    return element ? `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}${element.className && typeof element.className === 'string' ? `.${element.className.split(' ')[0]}` : ''}` : '';
});
const live = (page) => page.locator(`${root} [data-comments-region="live"]`).textContent();

// Checks
// -----------------------------------------------------------------------------

async function loadModes() {
    {
        const t = await open({ page: { load: 'visible' }, wait: false });
        await t.page.waitForTimeout(400);
        check('visible: nothing is fetched before the thread is near', !t.api.requests.length);
        check('visible: the loading line holds the reserved space, busy', await t.page.locator(`${root} .comments__loading[aria-busy="true"]`).count() === 1);
        const height = await t.page.locator(root).evaluate((element) => element.getBoundingClientRect().height);
        check('visible: the reserved space is held before loading', height >= 23 * 16, `${height}px`);
        await t.page.locator('#after').scrollIntoViewIfNeeded();
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        check('visible: scrolling near loads the thread and the viewer together', ['thread', 'viewer'].every((action) => t.api.requests.some((request) => request.action === action)));
        check('visible: the heading shows the count from the data', (await t.page.locator(`${root} .comments__heading`).textContent()).replace(/\s+/g, ' ').trim() === 'Comments : 8');
        await t.close();
    }
    {
        const t = await open({ page: { load: 'button' }, wait: false });
        await t.page.waitForTimeout(300);
        const button = t.page.getByRole('button', { name: 'Show comments (8)' });
        check('button: Show comments, with the count, and nothing fetched', await button.count() === 1 && !t.api.requests.length);
        await button.click();
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        await t.page.waitForTimeout(100);
        check('button: pressing it loads the thread and moves focus to the first comment', (await focused(t.page)).startsWith('article#comment-'));
        await t.close();
    }
    {
        const t = await open({ page: { load: 'button' }, wait: false, server: { override: { thread: (_q, _b, requests) => requests.filter((r) => r.action === 'thread').length === 1 ? { status: 500, body: { apiVersion: 1, status: 'error', errors: [{ category: 'server', code: 'server_error', message: 'Something went wrong. Please try again.', fieldErrors: {}, recoverable: true, retryAfter: null }] } } : null } } });
        await t.page.getByRole('button', { name: 'Show comments (8)' }).click();
        await t.page.waitForSelector(`${root} .comments__toast[data-comments-state="error"]`);
        check('button: a failed load says so and keeps the button to try again', await t.page.getByRole('button', { name: 'Show comments (8)' }).count() === 1);
        await t.page.getByRole('button', { name: 'Show comments (8)' }).click();
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        check('button: trying again loads it', true);
        await t.close();
    }
    {
        const t = await open({ page: { load: 'visible' }, hash: '#comment-1003', wait: false });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        await t.page.waitForTimeout(400);
        check('deep link: #comment-N loads a waiting thread straight away', t.api.requests.some((request) => request.action === 'thread'));
        check('deep link: the comment is outlined and focused', await t.page.locator(`${root} .comments__comment--highlight`).count() === 1 && (await focused(t.page)) === 'article#comment-1003');
        await t.close();
    }
    {
        const t = await open({ page: { load: 'visible' }, wait: false });
        await t.page.locator('#toc').click();
        await t.page.waitForTimeout(400);
        check('an in-page link that isn’t to a comment leaves the thread waiting', !t.api.requests.length);
        await t.page.evaluate(() => { window.location.hash = '#comment-1003'; });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        check('a later link to a comment loads it', true);
        await t.close();
    }
}

async function startup() {
    {
        const t = await open({ page: { autoMount: false }, wait: false });
        await t.page.waitForTimeout(300);
        check('data-comments-auto-mount="false" leaves the thread for the site to start', !t.api.requests.length && await t.page.locator(`${root}[data-comments-js]`).count() === 0);
        await t.page.evaluate(() => window.Comments.mount());
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        check('Comments.mount() starts it', true);
        await t.close();
    }
    {
        const t = await open({ page: { extraScript: '<script type="module" src="/comments-copy.js"></script>' } });
        await t.page.waitForTimeout(300);
        check('a second copy of the script hands over: one thread, one load, and a warning', t.api.requests.filter((request) => request.action === 'thread').length === 1 && t.warnings.some((warning) => warning.includes('loaded twice')));
        await t.close();
    }
    {
        const t = await open();
        const events = await t.page.evaluate(async () => {
            const seen = [];
            document.addEventListener('comments:destroyed', (event) => seen.push(`destroyed:${event.detail.apiVersion}`));
            document.addEventListener('comments:mounted', () => seen.push('mounted'));
            const section = document.querySelector('[data-comments-root]');
            section.remove();
            await new Promise((resolve) => setTimeout(resolve, 50));
            const restored = section.querySelector('.comments__heading')?.textContent;
            document.querySelector('main').append(section);
            await new Promise((resolve) => setTimeout(resolve, 200));
            return { seen, restored };
        });
        check('removing a thread destroys it, with the event reaching document and the mount point restored', events.seen.includes('destroyed:1') && events.restored === '8 comments');
        check('adding it back mounts it again', events.seen.includes('mounted') && await t.page.locator(`${root}[data-comments-phase="ready"]`).count() === 1);
        check('no errors in the page', !t.errors.length, t.errors.join(' | '));
        await t.close();
    }

    // comments:viewer-loaded says who's reading, so a site can show it without reading the store.
    {
        const t = await open({ page: { extraScript: '<script>window.__viewers = []; document.addEventListener("comments:viewer-loaded", (event) => window.__viewers.push(event.detail.viewer?.name ?? null));</script>' } });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        await t.page.waitForFunction(() => window.__viewers.length > 0);
        check('comments:viewer-loaded carries the viewer', (await t.page.evaluate(() => window.__viewers))[0] === MEMBER_NAME);
        await t.close();
    }
}

async function interactions() {
    const t = await open({ server: { delay: { vote: 400 } } });
    const { page, api } = t;

    // Voting answers at once and settles with the server.
    const like = actions(page, 'vote', 'c3');
    await like.click();
    check('like: pressed at once, before the server answers', await like.getAttribute('aria-pressed') === 'true' && !(await page.evaluate(() => 0)));
    await page.waitForTimeout(600);
    const vote = api.requests.find((request) => request.action === 'vote');
    check('like: sends a set vote with an operation ID and the CSRF token', vote?.body.vote === 'upvote' && /^[0-9a-f-]{36}$/.test(vote?.body.operationId) && vote?.headers['x-csrf-token'] === 'token');
    await like.click();
    await page.waitForTimeout(600);
    check('unlike: sends null', api.requests.filter((request) => request.action === 'vote').at(-1)?.body.vote === null);

    // Replying: the form opens under the comment with the keyboard in it, ⌘↵ posts.
    await actions(page, 'reply', 'c1').click();
    await page.waitForSelector(`${root} form[data-comments-form="reply"]`);
    check('reply: the form opens under its comment with focus in the box', (await focused(page)).startsWith('textarea#comments-109-1-reply-c1-comment-body'));
    check('reply: the chip names who’s being replied to', (await page.locator(`${root} .comments__frame-reply-text`).textContent()) === 'Replying to Alex Morgan');
    await page.keyboard.type('A thoughtful reply.');
    await page.keyboard.press(process.platform === 'darwin' && engine !== 'chromium' ? 'Meta+Enter' : 'Control+Enter');
    await page.waitForTimeout(400);
    const create = api.requests.find((request) => request.action === 'create');
    check('reply: ⌘↵ or Ctrl+Enter posts the reply under its parent', create?.body.parentUid === 'c1' && create?.body.content.text === 'A thoughtful reply.');
    check('reply: the new reply is drawn, focused and announced', (await focused(page)).startsWith('article#comment-') && (await live(page)) === 'Comment posted.');

    // An empty post is refused where it can be seen (SD-44).
    await page.locator(`${root} form[data-comments-form="create"] textarea`).click();
    // Post looks unavailable until there's text, but still answers a press, to say why.
    await page.locator(`${root} form[data-comments-form="create"] .comments__submit`).click({ force: true });
    await page.waitForTimeout(100);
    const error = page.locator(`${root} form[data-comments-form="create"] .comments__field-error`);
    check('empty post: a visible "Write something first." beside the box', await error.isVisible() && (await error.textContent()) === 'Write something first.');
    check('empty post: the box is marked invalid and described by it, and it’s read out', await page.locator(`${root} form[data-comments-form="create"] textarea`).getAttribute('aria-invalid') === 'true' && (await live(page)) === 'Write something first.');
    check('empty post: nothing is sent', api.requests.filter((request) => request.action === 'create').length === 1);
    await page.keyboard.type('x');
    check('typing clears the message', await error.count() === 0);

    // comments:before-submit can stop a post, and showFormErrors() shows the site's own errors.
    await page.evaluate(() => {
        document.addEventListener('comments:before-submit', (event) => {
            if (event.detail.formData.get('content') === 'blocked') {
                event.preventDefault();
                event.detail.controller.showFormErrors(event.detail.form, { fieldErrors: { content: ['Not that word.'] } });
            }
        });
    });
    const box = page.locator(`${root} form[data-comments-form="create"] textarea`);
    await box.fill('blocked');
    await page.locator(`${root} form[data-comments-form="create"] .comments__submit`).click({ force: true });
    await page.waitForTimeout(150);
    check('before-submit: a site’s listener stops the post and shows its own error', api.requests.filter((request) => request.action === 'create').length === 1 && (await page.locator(`${root} form[data-comments-form="create"] .comments__field-error`).textContent()) === 'Not that word.');

    // Cancel takes the keyboard back to where it came from.
    await actions(page, 'reply', 'c3').click();
    await page.waitForSelector(`${root} form[data-comments-form="reply"]`);
    await page.locator(`${root} form[data-comments-form="reply"] .comments__cancel`).click();
    await page.waitForTimeout(100);
    check('cancel: the reply form closes and focus returns to Reply', await page.locator(`${root} form[data-comments-form="reply"]`).count() === 0 && (await page.evaluate(() => document.activeElement?.dataset.commentsAction)) === 'reply');

    // The ··· menu works from the keyboard.
    const toggle = page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c2"]`);
    await toggle.focus();
    await page.keyboard.press('Enter');
    check('menu: Enter opens it on its first item', (await page.evaluate(() => document.activeElement?.textContent)) === 'Copy link');
    await page.keyboard.press('ArrowDown');
    check('menu: arrows move through it', (await page.evaluate(() => document.activeElement?.textContent)) === 'Report…');
    await page.keyboard.press('Escape');
    check('menu: Escape closes it and returns to the button', await page.locator(`${root} .comments__menu[open]`).count() === 0 && (await page.evaluate(() => document.activeElement?.classList.contains('comments__menu-toggle'))));

    // Reporting through the dialog.
    await toggle.click();
    await page.locator(`${root} [data-comments-action="report"][data-comments-comment-uid="c2"]`).click();
    await page.waitForSelector(`${root} dialog[open]`);
    check('report: the dialog opens with the comment quoted', (await page.locator(`${root} .comments__report-quote`).textContent()) === 'Alex Morgan: Comment c2');
    await page.getByLabel('Spam').check();
    await page.getByLabel('Anything else?').fill('Selling things.');
    await page.getByRole('button', { name: 'Send report' }).click();
    await page.waitForTimeout(300);
    const report = api.requests.find((request) => request.action === 'report');
    check('report: sends the reason and details, closes and thanks the reporter', report?.body.reason === 'spam' && report?.body.details === 'Selling things.' && await page.locator(`${root} dialog[open]`).count() === 0 && (await live(page)).startsWith('Thanks for reporting'));
    check('report: focus goes back to the comment’s menu button', await page.evaluate(() => document.activeElement?.classList.contains('comments__menu-toggle') && document.activeElement?.dataset.commentsCommentUid === 'c2'));

    // Sorting, paging and replies.
    await page.getByRole('button', { name: 'Oldest' }).click();
    await page.waitForTimeout(300);
    check('sort: asks for the thread in the new order and marks the choice', api.requests.some((request) => request.action === 'thread' && request.url.searchParams.get('sort') === 'oldest') && await page.getByRole('button', { name: 'Oldest' }).getAttribute('aria-pressed') === 'true');
    await page.getByRole('button', { name: 'Load more comments' }).click();
    await page.waitForTimeout(300);
    check('load more: reads the next page by cursor and focuses its first comment', api.requests.some((request) => request.action === 'thread' && request.url.searchParams.get('cursor') === 'cursor-2') && (await page.evaluate(() => document.activeElement?.closest('[data-comments-comment]')?.dataset.commentsCommentUid)) === 'c4');
    await page.getByRole('button', { name: /Load more replies/ }).click();
    await page.waitForTimeout(300);
    check('replies: loads the rest and focuses the first new one', (await page.evaluate(() => document.activeElement?.closest('[data-comments-comment]')?.dataset.commentsCommentUid)) === 'c2-2');
    const hide = page.getByRole('button', { name: 'Hide replies' });
    await hide.click();
    check('replies: Hide replies collapses them, with aria-expanded', await page.getByRole('button', { name: 'Show replies' }).getAttribute('aria-expanded') === 'false' && !(await page.locator(`${root} .comments__replies-list`).first().isVisible()));

    // The date toggles to the full date.
    const date = page.locator(`${root} .comments__permalink`).first();
    await date.click();
    check('a tap on the time shows the full date', (await date.textContent()) === '9 November 2026, 9:04 pm');

    check('no errors in the page', !t.errors.length, t.errors.join(' | '));
    await t.close();
}

async function ownComments() {
    const t = await open({
        server: {
            permissions: (uid) => (uid === 'c1' ? { canEdit: true, canDelete: true, isAuthor: true } : {}),
            extraComments: [comment('mine', { state: 'pending', author: { name: 'Aroha Ngata', initials: 'AN', avatarUrl: null, avatarTone: 6, isGuest: false, badges: [], score: null } })],
        },
    });
    const { page, api } = t;

    check('SD-44: the author sees their own pending comment with its waiting note', (await page.locator(`${root} [data-comments-comment-uid="mine"] .comments__review-label`).textContent()).includes('Waiting for approval'));

    await page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c1"]`).click();
    await page.locator(`${root} [data-comments-action="edit"][data-comments-comment-uid="c1"]`).click();
    await page.waitForSelector(`${root} form[data-comments-form="edit"]`);
    const box = page.locator(`${root} form[data-comments-form="edit"] textarea`);
    check('edit: reads the comment’s own content into the form', (await box.inputValue()) === 'Comment c1' && api.requests.some((request) => request.action === 'edit'));
    await box.fill('Changed my mind.');
    await page.getByRole('button', { name: 'Save comment' }).click();
    await page.waitForTimeout(300);
    check('edit: Save sends the update and the comment shows it', api.requests.find((request) => request.action === 'update')?.body.content.text === 'Changed my mind.' && (await page.locator(`${root} [data-comments-comment-uid="c1"] .comments__content`).first().textContent()) === 'Changed my mind.');

    await page.evaluate(() => document.addEventListener('comments:comment-deleted', (event) => { window.__deleted = event.detail.commentUid; }));
    await page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c1"]`).click();
    await page.locator(`${root} [data-comments-action="delete"][data-comments-comment-uid="c1"]`).click();
    await page.waitForTimeout(300);
    check('delete: comments:comment-deleted names the comment', (await page.evaluate(() => window.__deleted)) === 'c1');
    check('delete: confirms, removes the comment and moves focus to its neighbour', api.requests.some((request) => request.action === 'delete') && await page.locator(`${root} [data-comments-comment-uid="c1"]`).count() === 0 && (await focused(page)).startsWith('article#comment-'));

    await page.getByRole('button', { name: 'Follow' }).click();
    await page.waitForTimeout(300);
    check('follow: sends the change and shows Following, pressed', api.requests.find((request) => request.action === 'follow')?.body.following === true && await page.getByRole('button', { name: 'Following' }).getAttribute('aria-pressed') === 'true');
    await t.close();
}

async function moderators() {
    const t = await open({
        server: {
            viewer: { ...MEMBER, isModerator: true },
            extraComments: [comment('held', { state: 'held', moderation: { reviewLabel: 'Held · Pre-moderation', openReportCount: 0, actions: ['approve', 'spam', 'remove'] } })],
            moderation: { heldCount: 1, reportedCount: 0, reviewUrl: `${ORIGIN}/admin` },
        },
    });
    const { page, api } = t;

    check('moderator: the bar and the held comment show', await page.locator(`${root} .comments__moderator-bar`).count() === 1 && await page.locator(`${root} .comments__comment--held`).count() === 1);
    await page.getByRole('button', { name: 'Hide held' }).click();
    check('moderator: Hide held hides it', await page.locator(`${root} .comments__comment--held`).count() === 0);
    await page.getByRole('button', { name: 'Show held' }).click();
    await page.getByRole('group', { name: 'Moderation' }).getByRole('button', { name: 'Approve' }).click();
    await page.waitForTimeout(300);
    check('moderator: Approve sends the decision and announces it', api.requests.find((request) => request.action === 'moderate')?.body.action === 'approve' && (await live(page)) === 'Comment approved.');
    await t.close();
}

async function themes() {
    const t = await open({ page: { config: { sort: 'newest', limit: 20, themeConfig: { primaryButton: { resetClass: true, attributes: { class: 'btn btn-primary', 'data-track': 'post' } }, quietButton: { attributes: { class: 'btn-ghost' } } } } } });
    const { page } = t;
    const submit = page.locator(`${root} form[data-comments-form="create"] .comments__submit`);

    check('themeConfig from render(): resetClass swaps the styling classes and keeps the identifying one', (await submit.getAttribute('class')) === 'comments__submit btn btn-primary' && await submit.getAttribute('data-track') === 'post');
    check('themeConfig from render(): classes are added to every quiet button', await page.locator(`${root} [data-comments-control="quietButton"]:not(.btn-ghost)`).count() === 0);

    await page.evaluate(() => window.Comments.setTheme('dark'));
    check('setTheme() switches the thread', await page.locator(root).getAttribute('data-comments-theme') === 'dark');
    await page.evaluate(() => window.Comments.configure({ darkSelector: 'html.site-dark' }));
    await page.waitForTimeout(50);
    check('darkSelector: light while it doesn’t match', await page.locator(root).getAttribute('data-comments-theme') === 'light');
    await page.evaluate(() => document.documentElement.classList.add('site-dark'));
    await page.waitForTimeout(50);
    check('darkSelector: dark once the site’s toggle adds the class', await page.locator(root).getAttribute('data-comments-theme') === 'dark');
    await t.close();
}

async function richAuthoring() {
    const t = await open({ server: { editor: { authoring: 'rich', richEditor: { context: 'comments.reply', module: `${ORIGIN}/editor.js`, css: `${ORIGIN}/editor.css` } } } });
    const { page, api, fetched } = t;

    check('Editor: not requested when the thread loads', !fetched.includes('/editor.js'));
    const box = page.locator(`${root} form[data-comments-form="create"] textarea`);
    await box.click();
    await box.type('Started in the plain box');
    await page.waitForSelector(`${root} form[data-comments-form="create"] verbb-editor [contenteditable]`, { timeout: 5000 }).catch(() => undefined);
    check('Editor: loaded when someone starts writing, and swapped in', fetched.includes('/editor.js') && await page.locator(`${root} form[data-comments-editor-mounted] verbb-editor`).count() === 1);
    check('Editor: what was typed came along', (await page.locator(`${root} verbb-editor [contenteditable]`).textContent()).startsWith('Started'));
    check('Editor: who’s posting and Cancel moved into its frame', await page.locator(`${root} verbb-editor > .comments__posting-as[slot="header"]`).count() === 1 && await page.locator(`${root} verbb-editor > .comments__cancel[slot="actions"]`).count() === 1);
    await page.locator(`${root} verbb-editor [contenteditable]`).click();
    await page.keyboard.type(' and more');
    await page.keyboard.press('Control+Enter');
    await page.waitForTimeout(300);
    check('Editor: ⌘↵ in Editor posts Editor’s document', api.requests.find((request) => request.action === 'create')?.body.content.format === 'rich');
    await t.close();

    // ⌘↵ straight after typing, while Editor is still downloading, waits for it and posts rich.
    const slow = await open({ editorDelay: 800, server: { editor: { authoring: 'rich', richEditor: { context: 'comments.reply', module: `${ORIGIN}/editor.js`, css: `${ORIGIN}/editor.css` } } } });
    const plain = slow.page.locator(`${root} form[data-comments-form="create"] textarea`);
    await plain.click();
    await slow.page.keyboard.type('Quick one');
    await slow.page.keyboard.press('Control+Enter');
    await slow.page.waitForTimeout(1500);
    const quick = slow.api.requests.find((request) => request.action === 'create');
    check('Editor: ⌘↵ before Editor has arrived waits for it and posts the text as Editor’s document', quick?.body.content.format === 'rich' && JSON.stringify(quick.body.content.document).includes('Quick one'));
    await slow.close();

    // With rich authoring, Editor's GIF control takes over from the plain form's, and the comment
    // still posts the GIF chosen there.
    const gifs = await open({ server: { gifs: { apiKey: 'giphy-key', rating: 'g', limit: 12, language: 'en' }, editor: { authoring: 'rich', richEditor: { context: 'comments.reply', module: `${ORIGIN}/editor.js`, css: `${ORIGIN}/editor.css` } } } });
    const gifForm = `${root} form[data-comments-form="create"]`;
    check('Editor GIFs: the plain form offers GIFs before Editor arrives', await gifs.page.locator(`${gifForm} .comments__gif-button`).count() === 1);
    await gifs.page.locator(`${gifForm} textarea`).click();
    await gifs.page.keyboard.type('Look');
    await gifs.page.waitForSelector(`${gifForm} verbb-editor [contenteditable]`, { timeout: 5000 }).catch(() => undefined);
    check('Editor GIFs: once Editor runs, the plain GIF button and area are gone', await gifs.page.locator(`${gifForm} .comments__gif-button, ${gifForm} .comments__gif-area`).count() === 0);
    const gif = { url: 'https://media2.giphy.com/media/a/giphy.gif', width: 480, height: 360 };
    await gifs.page.evaluate(([selector, chosen]) => {
        const element = document.querySelector(`${selector} verbb-editor`);
        element.editor.setGif(chosen);
        element.dispatchEvent(new CustomEvent('editor-update', { bubbles: true }));
    }, [gifForm, gif]);
    await gifs.page.locator(`${gifForm} verbb-editor [contenteditable]`).click();
    await gifs.page.keyboard.press('Control+Enter');
    await gifs.page.waitForTimeout(300);
    const posted = gifs.api.requests.find((request) => request.action === 'create');
    check('Editor GIFs: the comment posts the GIF chosen in Editor, and GIPHY hears it was sent', JSON.stringify(posted?.body.content.gif) === JSON.stringify(gif) && await gifs.page.evaluate(() => window.__gifsSent) === 1, JSON.stringify(posted?.body.content));
    check('Editor GIFs: posting clears Editor’s GIF', await gifs.page.evaluate((selector) => document.querySelector(`${selector} verbb-editor`)?.editor.getGif(), gifForm) === null);
    await gifs.close();

    // Editor loads links and mentions on demand. Comments loads them before adding the editor, so what
    // was typed still comes across, and features that can't load leave the plain box to post.
    for (const [name, module] of [['loads its features slowly', 'editor-features-slow.js'], ['can’t load its features', 'editor-features-fail.js']]) {
        const t = await open({ server: { editor: { authoring: 'rich', richEditor: { context: 'comments.reply', module: `${ORIGIN}/${module}`, css: `${ORIGIN}/editor.css` } } } });
        const textarea = t.page.locator(`${root} form[data-comments-form="create"] textarea`);
        await textarea.click();
        await t.page.keyboard.type('Waiting on features');
        await t.page.waitForTimeout(1200);

        if (module === 'editor-features-slow.js') {
            check(`Editor that ${name}: added only once they've loaded, with what was typed`, !(await t.page.evaluate(() => window.__addedEarly)) && (await t.page.locator(`${root} verbb-editor [contenteditable]`).textContent()) === 'Waiting on features');
        } else {
            await t.page.keyboard.press('Control+Enter');
            await t.page.waitForTimeout(300);
            const create = t.api.requests.find((request) => request.action === 'create');
            check(`Editor that ${name}: the plain box stays and posts`, await t.page.locator(`${root} verbb-editor`).count() === 0 && create?.body.content.format === 'plain' && create.body.content.text === 'Waiting on features');
        }

        await t.close();
    }

    // Opening a reply says so once: on a rich thread once Editor is in the form, which the box getting
    // focus also asks for, and on a plain thread straight away.
    for (const authoring of ['rich', 'plain']) {
        const opened = await open(authoring === 'rich' ? { server: { editor: RICH } } : {});
        await opened.page.evaluate(() => {
            window.__opened = [];
            document.addEventListener('comments:editor-opened', (event) => window.__opened.push({ mode: event.detail.mode, editor: Boolean(event.detail.root.querySelector('form[data-comments-editor-mounted] verbb-editor')) }));
        });
        await opened.page.locator(`${root} [data-comments-action="reply"]`).first().click();
        await opened.page.waitForTimeout(authoring === 'rich' ? 1200 : 300);
        const events = await opened.page.evaluate(() => window.__opened);
        check(`Editor: opening a reply fires comments:editor-opened once (${authoring})`, events.length === 1 && events[0].mode === 'reply' && events[0].editor === (authoring === 'rich'), JSON.stringify(events));
        await opened.close();
    }
}

const RICH = { authoring: 'rich', richEditor: { context: 'comments.reply', module: `${ORIGIN}/editor.js`, css: `${ORIGIN}/editor.css` } };
const FIELD = { uid: 'u', instructions: null, required: false, multiple: false, options: [], attributes: {}, public: false, placement: null };
const OPTIONS = [{ label: 'One', value: '1' }, { label: 'Two', value: '2' }, { label: 'Three', value: '3' }];

async function editingAndPosting() {
    // B1: an edit draws the values the comment was posted with, and sends only the fields it drew.
    {
        const fields = [
            { ...FIELD, handle: 'where', label: 'Where', type: 'text', required: true },
            { ...FIELD, handle: 'boxes', label: 'Boxes', type: 'checkboxes', options: OPTIONS },
            { ...FIELD, handle: 'many', label: 'Many', type: 'select', multiple: true, options: OPTIONS },
            { ...FIELD, handle: 'pick', label: 'Pick', type: 'radio', options: OPTIONS },
            { ...FIELD, handle: 'switch', label: 'Switch', type: 'toggle' },
            { ...FIELD, handle: 'notes', label: 'Notes', type: 'textarea' },
            { ...FIELD, handle: 'special', label: 'Special', type: 'custom' },
        ];
        const t = await open({ server: {
            permissions: (uid) => (uid === 'c1' ? { canEdit: true, isAuthor: true } : uid === 'c2-1' ? { canEdit: true, isAuthor: true } : {}),
            fields: { comment: fields, reply: [{ ...FIELD, handle: 'replyOnly', label: 'Reply only', type: 'text' }] },
            editFields: { where: 'Rotorua', boxes: ['1', '3'], many: ['2', '3'], pick: '2', switch: true, notes: 'Long text', special: 'kept', replyOnly: 'Short' },
        } });
        const { page, api } = t;
        await page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c1"]`).click();
        await page.locator(`${root} [data-comments-action="edit"][data-comments-comment-uid="c1"]`).click();
        await page.waitForSelector(`${root} form[data-comments-form="edit"]`);
        const form = page.locator(`${root} form[data-comments-form="edit"]`);
        const drawn = await form.evaluate((element) => ({
            where: element.querySelector('[name="fields[where]"]').value,
            boxes: Array.from(element.querySelectorAll('[name="fields[boxes][]"]:checked'), (input) => input.value),
            many: Array.from(element.querySelector('[name="fields[many][]"]').selectedOptions, (option) => option.value),
            pick: element.querySelector('[name="fields[pick]"]:checked')?.value,
            toggle: element.querySelector('[name="fields[switch]"]').checked,
            notes: element.querySelector('[name="fields[notes]"]').value,
        }));
        check('edit: custom fields show the values the comment was posted with', JSON.stringify(drawn) === JSON.stringify({ where: 'Rotorua', boxes: ['1', '3'], many: ['2', '3'], pick: '2', toggle: true, notes: 'Long text' }), JSON.stringify(drawn));
        await page.getByRole('button', { name: 'Save comment' }).click();
        await page.waitForTimeout(300);
        const sent = api.requests.find((request) => request.action === 'update')?.body.fields;
        check('edit: saving sends those values unchanged, and leaves out a field it didn’t draw', JSON.stringify(sent) === JSON.stringify({ where: 'Rotorua', boxes: ['1', '3'], many: ['2', '3'], pick: '2', switch: true, notes: 'Long text' }), JSON.stringify(sent));

        await page.getByRole('button', { name: 'Hide replies' }).first().evaluate(() => undefined);
        await page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c2-1"]`).click();
        await page.locator(`${root} [data-comments-action="edit"][data-comments-comment-uid="c2-1"]`).click();
        await page.waitForSelector(`${root} form[data-comments-form="edit"] [name="fields[replyOnly]"]`);
        check('edit: a reply’s edit asks for the reply’s fields', await page.locator(`${root} form[data-comments-form="edit"] [name="fields[where]"]`).count() === 0 && (await page.locator(`${root} [name="fields[replyOnly]"]`).inputValue()) === 'Short');
        await t.close();
    }

    // I4: a returning guest's name and address are filled in.
    {
        const t = await open({ server: {
            viewer: { ...MEMBER, isGuest: true, name: null, canComment: true, canVote: false, loginUrl: '/login', follow: { available: false, following: false, needsEmail: true } },
            guest: { show: true, name: 'Pat Guest', email: 'pat@example.com' },
        } });
        await t.page.getByText('Comment as a guest').click();
        check('guest: a returning guest’s name and email are filled in', (await t.page.locator(`${root} [name="guest[name]"]`).inputValue()) === 'Pat Guest' && (await t.page.locator(`${root} [name="guest[email]"]`).inputValue()) === 'pat@example.com');
        await t.close();
    }

    // A site with its own classes on Comments' controls, which set `display`, and Comments' stylesheet
    // in a lower CSS layer, as Theming recommends for Tailwind. What the thread hides by state stays
    // hidden: the join buttons once a guest chooses to comment, and the plain box once Editor is running.
    {
        const themeConfig = {
            primaryButton: { resetClass: true, attributes: { class: 'btn' } },
            secondaryButton: { resetClass: true, attributes: { class: 'btn' } },
            textarea: { resetClass: true, attributes: { class: 'field' } },
        };
        const layered = (page) => pageHtml(page).replace('<link rel="stylesheet" href="/comments.css">', '<style>@layer comments, site; @import url("/comments.css") layer(comments); @layer site { .btn { display: inline-flex; } .field { display: block; } }</style>');
        const guestPage = { config: { sort: 'newest', limit: 20, themeConfig } };
        const guest = await open({ html: layered(guestPage), server: {
            viewer: { ...MEMBER, isGuest: true, name: null, canComment: true, canVote: false, loginUrl: '/login', follow: { available: false, following: false, needsEmail: true } },
            guest: { show: true },
        } });
        await guest.page.getByText('Comment as a guest').click();
        await guest.page.waitForSelector(`${root} [name="guest[name]"]`);
        check('layered site classes: choosing to comment as a guest still hides Sign in and the guest button', !(await guest.page.locator(`${root} .comments__join-actions > a`).isVisible()) && !(await guest.page.locator(`${root} .comments__join-guest > summary`).isVisible()));
        await guest.close();

        const member = await open({ html: layered(guestPage), server: { editor: RICH } });
        const box = member.page.locator(`${root} form[data-comments-form="create"] textarea`);
        await box.click();
        await box.type('Started in the plain box');
        await member.page.waitForSelector(`${root} form[data-comments-editor-mounted] verbb-editor [contenteditable]`, { timeout: 5000 }).catch(() => undefined);
        check('layered site classes: once Editor is running, the plain box stays hidden', await member.page.locator(`${root} form[data-comments-editor-mounted]`).count() === 1 && !(await box.isVisible()));
        await member.close();
    }

    // I3: a site reading the form sees what will be posted, and a listener that ran first can stop it.
    {
        const t = await open({ server: { editor: RICH } });
        const { page, api } = t;
        await page.evaluate(() => {
            document.addEventListener('comments:before-submit', (event) => { window.__content = event.detail.formData.get('content'); });
        });
        await page.locator(`${root} form[data-comments-form="create"] textarea`).click();
        await page.waitForSelector(`${root} verbb-editor [contenteditable]`);
        await page.locator(`${root} verbb-editor [contenteditable]`).click();
        await page.keyboard.type('Rich words');
        await page.keyboard.press('Control+Enter');
        await page.waitForTimeout(300);
        check('before-submit: formData has the rich editor’s text as content', (await page.evaluate(() => window.__content)) === 'Rich words');

        await page.evaluate(() => {
            const form = document.querySelector('[data-comments-root] form[data-comments-form="create"]');
            form.addEventListener('submit', (event) => event.preventDefault());
        });
        const before = api.requests.filter((request) => request.action === 'create').length;
        await page.locator(`${root} verbb-editor [contenteditable]`).click();
        await page.keyboard.type('Another');
        await page.keyboard.press('Control+Enter');
        await page.waitForTimeout(300);
        check('a site’s own submit listener on the form stops the post', api.requests.filter((request) => request.action === 'create').length === before);
        await t.close();
    }

    // I1 and S1: Editor that breaks, never arrives, or is on another origin; the post goes as plain text.
    for (const [name, module, wait] of [['throws as it runs', `${ORIGIN}/editor-throws.js`, 9500], ['never defines its element', `${ORIGIN}/editor-never.js`, 9500], ['is on another origin', 'https://elsewhere.test/editor.js', 1000]]) {
        const t = await open({ server: { editor: { authoring: 'rich', richEditor: { context: 'comments.reply', module, css: `${ORIGIN}/editor.css` } } } });
        const box = t.page.locator(`${root} form[data-comments-form="create"] textarea`);
        await box.click();
        await t.page.keyboard.type('Plain after all');
        await t.page.keyboard.press('Control+Enter');
        await t.page.waitForTimeout(wait);
        const create = t.api.requests.find((request) => request.action === 'create');
        check(`Editor ${name}: the post goes as plain text rather than hanging`, create?.body.content.format === 'plain' && create.body.content.text === 'Plain after all');
        if (module.startsWith('https://elsewhere')) check('Editor from another origin is never requested', !t.fetched.some((path) => path.startsWith('elsewhere:')));
        await t.close();
    }
}

async function pagesAndMounting() {
    // I2: a link on a page with two threads is looked up once and opens only in its own thread.
    {
        const other = comment('o1', { permalink: `${ORIGIN}/other-post#comment-2001` });
        const linked = comment('o9', { permalink: `${ORIGIN}/other-post#comment-2009` });
        const t = await open({
            hash: '#comment-2009',
            page: { after: `<section id="comments-555-1" data-comments-root data-comments-api-version="1" data-comments-endpoint="${ORIGIN}/actions/comments/api" data-comments-owner-id="555" data-comments-site-id="1" data-comments-load-mode="eager"></section>` },
            server: { override: {
                thread: (query) => (query.get('ownerId') === '555' ? { body: { apiVersion: 1, status: 'ok', threadVersion: 'v1', conversation: { uid: 'o', ownerId: 555, siteId: 1, status: 'open', closedNotice: null, commentCount: 2, rootCount: 2, sort: 'newest', sorts: ['newest'], interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: false, reportReasons: [] }, editor: { authoring: 'plain', maxLength: null, richEditor: null }, comments: [other], page: { next: null, limit: 20 }, labels: LABELS, locale: 'en' } } : null),
                comment: (query) => ({ body: { apiVersion: 1, status: 'ok', targetUid: 'o9', comment: query.get('id') === '2009' ? linked : null } }),
            } },
            wait: false,
        });
        await t.page.waitForFunction(() => document.querySelectorAll('[data-comments-phase="ready"]').length === 2);
        await t.page.waitForTimeout(600);
        const lookups = t.api.requests.filter((request) => request.action === 'comment');
        check('two threads: the link is looked up once, by its number', lookups.length === 1 && lookups[0].url.searchParams.get('id') === '2009');
        check('two threads: the comment opens only in its own thread', await t.page.locator('#comments-555-1 [data-comments-comment][data-comments-comment-uid="o9"]').count() === 1 && await t.page.locator('#comments-109-1 [data-comments-comment][data-comments-comment-uid="o9"]').count() === 0);
        await t.close();
    }

    // I5, I7 and the merge order: a thread mounted later on a plain element follows darkSelector and every settings source.
    {
        const t = await open({ page: { htmlClass: 'site-dark', config: { sort: 'newest', limit: 20, themeConfig: { primaryButton: { attributes: { class: 'from-render', title: 'render' } } } } } });
        await t.page.evaluate(() => window.Comments.configure({ darkSelector: 'html.site-dark', themeConfig: { primaryButton: { attributes: { class: 'from-configure', 'data-x': 'configure' } } } }));
        check('darkSelector: a thread already drawn follows it', await t.page.locator('#comments-109-1').getAttribute('data-comments-theme') === 'dark');
        const submitClass = await t.page.locator('#comments-109-1 form[data-comments-form="create"] .comments__submit').getAttribute('class');
        check('themeConfig: configure() wins over render() per attribute', submitClass.includes('from-configure') && !submitClass.includes('from-render') && await t.page.locator('#comments-109-1 form[data-comments-form="create"] .comments__submit').getAttribute('title') === 'render');
        await t.page.evaluate(() => {
            const element = document.createElement('div');
            element.id = 'later';
            document.querySelector('main').append(element);
            window.Comments.mountThread(element, { ownerId: 109, siteId: 1, endpoint: 'https://comments.test/actions/comments/api', loadMode: 'eager', themeConfig: { primaryButton: { attributes: { class: 'from-mount' } } } });
        });
        await t.page.waitForSelector('#later[data-comments-phase="ready"]');
        const later = t.page.locator('#later');
        check('mountThread(): a plain element becomes a thread, with the root’s attributes and class', await later.getAttribute('data-comments-root') === '' && (await later.getAttribute('class')).includes('comments') && await later.locator('[data-comments-comment]').count() > 0);
        check('darkSelector: a thread mounted later starts dark', await later.getAttribute('data-comments-theme') === 'dark');
        check('themeConfig: mountThread() wins over configure()', (await later.locator('form[data-comments-form="create"] .comments__submit').getAttribute('class')).includes('from-mount'));
        const height = await later.evaluate((element) => element.getBoundingClientRect().height);
        check('mountThread(): the thread is styled (its comments sit in a grid)', height > 200 && (await later.locator('.comments__list').evaluate((list) => getComputedStyle(list).display)) === 'grid');
        await t.close();
    }

    // Turbo Drive's cache and restore, and a morph that strips the drawn thread.
    {
        const t = await open();
        await t.page.evaluate(() => document.dispatchEvent(new Event('turbo:before-cache')));
        check('Turbo: before-cache stops the thread and leaves the mount point', await t.page.locator(`${root}[data-comments-js]`).count() === 0 && (await t.page.locator(`${root} .comments__heading`).textContent()) === '8 comments');
        await t.page.evaluate(() => document.dispatchEvent(new Event('turbo:load')));
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        check('Turbo: load starts it again', await t.page.locator(`${root} [data-comments-comment]`).count() > 0);
        await t.page.evaluate(() => {
            // What idiomorph does with the server's page: the mount point's own children back in place.
            const section = document.querySelector('[data-comments-root]');
            section.replaceChildren(Object.assign(document.createElement('h2'), { id: 'comments-109-1-heading', className: 'comments__heading', textContent: '8 comments' }));
        });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"] [data-comments-comment]`, { timeout: 5000 }).catch(() => undefined);
        check('a morph that takes the drawn thread away starts it again', await t.page.locator(`${root} [data-comments-comment]`).count() > 0 && await t.page.locator(`${root} [data-comments-region="live"]`).count() === 1);

        // A morph that keeps the live region at the top but takes the rest, with the keyboard in the comment box.
        await t.page.locator(`${root} form[data-comments-form="create"] textarea`).fill('Half a thought');
        await t.page.locator(`${root} form[data-comments-form="create"] textarea`).focus();
        const kept = await t.page.evaluate(async () => {
            const section = document.querySelector('[data-comments-root]');
            const controller = window.Comments.get(section);
            section.replaceChildren(section.querySelector(':scope > [data-comments-region="live"]'), Object.assign(document.createElement('h2'), { id: 'comments-109-1-heading', className: 'comments__heading', textContent: '8 comments' }));
            await new Promise((resolve) => setTimeout(resolve, 400));
            const box = section.querySelector('form[data-comments-form="create"] textarea');
            return { same: window.Comments.get(section) === controller, comments: section.querySelectorAll('[data-comments-comment]').length, stray: section.querySelectorAll(':scope > h2').length, text: box?.value, focused: document.activeElement === box };
        });
        check('a morph that keeps the live region but takes the rest is noticed, drawn again by the same controller, with the text and the keyboard back in the box', kept.same && kept.comments > 0 && kept.stray === 0 && kept.text === 'Half a thought' && kept.focused, JSON.stringify(kept));

        // A swap that makes the root another owner's mount point starts that owner's thread.
        const swapped = await t.page.evaluate(async () => {
            const section = document.querySelector('[data-comments-root]');
            const controller = window.Comments.get(section);
            section.dataset.commentsOwnerId = '110';
            section.innerHTML = '<h2>2 comments</h2>';
            await new Promise((resolve) => setTimeout(resolve, 600));
            return { other: window.Comments.get(section) !== controller && Boolean(window.Comments.get(section)), phase: section.dataset.commentsPhase };
        });
        check('a swap that makes the root another owner’s mount point starts that owner’s thread', swapped.other && swapped.phase === 'ready' && t.api.requests.some((request) => request.action === 'thread' && request.url.searchParams.get('ownerId') === '110'), JSON.stringify(swapped));
        const restored = await t.page.evaluate(() => { const section = document.querySelector('[data-comments-root]'); window.Comments.destroy(); return section.innerHTML; });
        check('and stopping it puts back the new owner’s markup', restored === '<h2>2 comments</h2>', restored);
        check('no errors in the page', !t.errors.length, t.errors.join(' | '));
        await t.close();
    }

    // An element a site adds beside the thread, once (S1) or kept there by a script of its own (S2), is left alone.
    for (const keep of [false, true]) {
        const t = await open();
        const result = await t.page.evaluate(async (keepIt) => {
            const section = document.querySelector('[data-comments-root]');
            const list = section.querySelector('.comments__list');
            let draws = 0;
            new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => { if (node.nodeType === 1 && node.matches('[data-comments-region="live"]')) draws++; }))).observe(section, { childList: true });
            const add = () => section.append(Object.assign(document.createElement('p'), { className: 'site-note', textContent: 'Comments are moderated.' }));
            if (keepIt) new MutationObserver(() => { if (!section.querySelector(':scope > .site-note')) add(); }).observe(section, { childList: true });
            add();
            await new Promise((resolve) => setTimeout(resolve, 1000));
            return { draws, note: Boolean(section.querySelector(':scope > .site-note')), sameList: section.querySelector('.comments__list') === list };
        }, keep);
        check(`a site’s own element beside the thread (${keep ? 'kept there by its script' : 'added once'}) stays, and the thread isn’t drawn again`, result.draws === 0 && result.note && result.sameList, JSON.stringify(result));
        await t.close();
    }

    // Focus inside a component whose comment a re-sort holds for a later batch comes back with it.
    {
        const many = Array.from({ length: 60 }, (_, index) => comment(`f${index}`));
        const t = await open({ server: { roots: many }, page: { autoMount: false }, wait: false });
        await t.page.waitForFunction(() => window.Comments);
        await t.page.evaluate(() => window.Comments.mountThread(document.querySelector('[data-comments-root]'), { loadMode: 'eager', parts: { content: { component: 'C' } }, onHosts: (hosts) => hosts.forEach((host) => {
            if (host.element.firstChild) return;
            const input = Object.assign(document.createElement('input'), { className: 'draft' });
            input.dataset.uid = host.context.comment.uid;
            host.element.append(input);
        }) }));
        await t.page.waitForFunction(() => document.querySelectorAll('[data-comments-root] input.draft').length === 60);
        await t.page.locator('input.draft[data-uid="f0"]').fill('typed');
        await t.page.locator('input.draft[data-uid="f0"]').focus();
        many.reverse();
        const result = await t.page.evaluate(async () => {
            const section = document.querySelector('[data-comments-root]');
            const input = section.querySelector('input.draft[data-uid="f0"]');
            window.Comments.get(section).refresh({ sort: 'oldest' });
            await new Promise((resolve) => { const wait = () => (section.querySelectorAll('[data-comments-comment]').length === 60 && section.querySelector('[data-comments-comment]').dataset.commentsCommentUid === 'f59' ? resolve() : setTimeout(wait, 20)); wait(); });
            await new Promise((resolve) => setTimeout(resolve, 300));
            return { focused: document.activeElement === input, value: input.value, same: section.querySelector('input.draft[data-uid="f0"]') === input };
        });
        check('focus in a component whose comment a re-sort held for a later batch comes back with it, with what was typed', result.focused && result.value === 'typed' && result.same, JSON.stringify(result));
        await t.close();
    }

    // A swap that keeps the live region and a site's note but makes the root another owner's: the note
    // carries over after the new thread, with one live region, and stopping puts back only the new markup.
    {
        const t = await open({ server: { roots: [comment('c1')] } });
        const result = await t.page.evaluate(async () => {
            const section = document.querySelector('[data-comments-root]');
            const note = Object.assign(document.createElement('p'), { className: 'site-note', textContent: 'Be kind.' });
            section.append(note);
            await new Promise((resolve) => setTimeout(resolve, 50));
            const live = section.querySelector(':scope > [data-comments-region="live"]');
            section.dataset.commentsOwnerId = '110';
            [...section.children].forEach((child) => { if (child !== live && child !== note) child.remove(); });
            section.prepend(Object.assign(document.createElement('h2'), { id: 'comments-110-1-heading', textContent: '2 comments' }));
            section.append(live.cloneNode());
            await new Promise((resolve) => setTimeout(resolve, 500));
            const out = { note: section.lastElementChild === note, lives: section.querySelectorAll(':scope > [data-comments-region="live"]').length, comments: section.querySelectorAll('[data-comments-comment]').length };
            window.Comments.destroy();
            out.after = [...section.children].map((child) => child.tagName).join(',');
            return out;
        });
        check('a swap to another owner carries a site’s note over after the new thread, with one live region, and stopping puts back only the new markup', result.note && result.lives === 1 && result.comments > 0 && result.after === 'H2', JSON.stringify(result));
        await t.close();
    }

    // A morph while the loader is waiting puts the server's mount point back: the waiting state is drawn again.
    for (const load of ['button', 'visible']) {
        const t = await open({ page: { load }, wait: false });
        await t.page.waitForSelector(`${root}[data-comments-phase="${load === 'button' ? 'button' : 'loading'}"]`, { state: 'attached' });
        for (const how of ['observer', 'turbo:load']) {
            await t.page.evaluate((viaLoad) => {
                const section = document.querySelector('[data-comments-root]');
                section.replaceChildren(Object.assign(document.createElement('h2'), { id: 'comments-109-1-heading', className: 'comments__heading', textContent: '8 comments' }));
                delete section.dataset.commentsPhase;
                if (viaLoad) document.dispatchEvent(new Event('turbo:load'));
            }, how === 'turbo:load');
            await t.page.waitForTimeout(300);
            const state = await t.page.evaluate(() => ({ phase: document.querySelector('[data-comments-root]').dataset.commentsPhase ?? null, button: document.querySelectorAll('[data-comments-root] [data-comments-action="show-comments"]').length, loading: document.querySelectorAll('[data-comments-root] .comments__loading').length }));
            check(`loader: a morph while waiting (${load}, noticed by ${how === 'observer' ? 'its observer' : 'turbo:load'}) draws the waiting state again`, load === 'button' ? state.phase === 'button' && state.button === 1 : state.phase === 'loading' && state.loading === 1, JSON.stringify(state));
        }

        if (load === 'button') {
            await t.page.getByRole('button', { name: 'Show comments (8)' }).click();
            await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
            check('loader: and Show comments still loads the thread', await t.page.locator(`${root} [data-comments-comment]`).count() > 0);
        } else {
            await t.page.locator('#after').scrollIntoViewIfNeeded();
            await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
            check('loader: and the thread still loads as it nears view', await t.page.locator(`${root} [data-comments-comment]`).count() > 0);
        }

        await t.close();
    }

    // I8: a second copy's nonce reaches the threads the first copy runs.
    {
        const t = await open({ server: { editor: RICH }, page: { extraScript: '<script type="module" src="/comments-copy.js" nonce="second"></script>' } });
        await t.page.waitForTimeout(200);
        await t.page.locator(`${root} form[data-comments-form="create"] textarea`).click();
        await t.page.waitForSelector(`${root} verbb-editor`, { timeout: 5000 }).catch(() => undefined);
        const nonce = await t.page.evaluate(() => document.querySelector('script[src$="/editor.js"]')?.nonce);
        check('a second copy hands its settings, such as its nonce, to the copy running', nonce === 'second', String(nonce));
        await t.close();
    }
}

async function overridesAndSafety() {
    // A custom element override, given the part's context as properties, and I6's helpers.
    {
        const t = await open({ page: { autoMount: false }, wait: false });
        await t.page.evaluate(() => {
            customElements.define('acme-avatar', class extends HTMLElement {
                set comment(value) { this.textContent = `★${value.author.initials}`; }
            });
            window.Comments.configure({
                parts: {
                    avatar: 'acme-avatar',
                    comment: ({ comment, html, defaultRender, form, replies }) => comment.uid === 'c3'
                        ? html`<li data-comments-comment data-comments-comment-uid="c3" class="acme-comment"><article id="comment-1003" tabindex="-1">Mine<button type="button" data-comments-action="reply" data-comments-comment-uid="c3">Reply</button>${form()}${replies()}</article></li>`
                        : defaultRender(),
                },
            });
            window.Comments.mount();
        });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        check('a custom element override is drawn with the comment as a property', (await t.page.locator(`${root} acme-avatar`).first().textContent()) === '★AM');
        await t.page.locator(`${root} .acme-comment [data-comments-action="reply"]`).click();
        await t.page.waitForTimeout(200);
        check('a comment override that draws form() keeps Reply working', await t.page.locator(`${root} .acme-comment form[data-comments-form="reply"]`).count() === 1);
        await t.close();
    }

    // S3: themeConfig refuses handlers and styles; S4: controls inside comment text do nothing.
    {
        const evil = comment('c9', { content: { format: 'rich', html: '<p>Click <button data-comments-action="delete" data-comments-comment-uid="c1">here</button></p>', text: 'Click here', gifUrl: null, gifWidth: null, gifHeight: null } });
        const t = await open({ server: { roots: [comment('c1'), evil], permissions: (uid) => (uid === 'c1' ? { canDelete: true } : {}) }, page: { config: { sort: 'newest', limit: 20, themeConfig: { quietButton: { attributes: { onclick: 'window.__pwned = 1', style: 'color: red', formaction: '/x', class: 'ok' } } } } } });
        const quiet = t.page.locator(`${root} [data-comments-control="quietButton"]`).first();
        check('themeConfig: event handlers, styles and form attributes are refused', await quiet.getAttribute('onclick') === null && await quiet.getAttribute('style') === null && await quiet.getAttribute('formaction') === null && (await quiet.getAttribute('class')).includes('ok') && t.warnings.some((warning) => warning.includes('"onclick"')));
        let asked = false;
        t.page.on('dialog', () => { asked = true; });
        await t.page.locator(`${root} .comments__content button`).click();
        await t.page.waitForTimeout(200);
        check('a control written inside a comment’s text does nothing', !asked && !t.api.requests.some((request) => request.action === 'delete'));
        await t.close();
    }

    // Hide held takes held replies out too, and a failed load offers Try again in place.
    {
        const held = comment('h-reply', { parentUid: 'c1', depth: 1, state: 'held', moderation: { reviewLabel: 'Held', openReportCount: 0, actions: ['approve'] } });
        const t = await open({ server: { viewer: { ...MEMBER, isModerator: true }, extraComments: [held], moderation: { heldCount: 1, reportedCount: 0, reviewUrl: '/admin' } } });
        check('a moderator sees a held reply', await t.page.locator(`${root} [data-comments-comment][data-comments-comment-uid="h-reply"]`).count() === 1);
        await t.page.getByRole('button', { name: 'Hide held' }).click();
        check('Hide held hides held replies too', await t.page.locator(`${root} [data-comments-comment][data-comments-comment-uid="h-reply"]`).count() === 0);
        await t.close();
    }
    {
        const failing = { thread: (_q, _b, requests) => (requests.filter((r) => r.action === 'thread').length === 1 ? { status: 500, body: { apiVersion: 1, status: 'error', errors: [{ category: 'server', code: 'server_error', message: 'Something went wrong. Please try again.', fieldErrors: {}, recoverable: true, retryAfter: null }] } } : null) };
        const t = await open({ wait: false, server: { override: failing } });
        await t.page.waitForSelector(`${root}[data-comments-phase="error"]`);
        check('a failed load replaces the loading line with the error and Try again', await t.page.locator(`${root} .comments__loading[aria-busy]`).count() === 0 && await t.page.locator(`${root} [data-comments-placeholder] [data-comments-action="retry"]`).count() === 1);
        await t.page.locator(`${root} [data-comments-placeholder] [data-comments-action="retry"]`).click();
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        check('Try again loads the thread', true);
        await t.close();
    }

    // A double press on Edit opens one form.
    {
        const t = await open({ server: { permissions: (uid) => (uid === 'c1' ? { canEdit: true } : {}), delay: { edit: 300 } } });
        await t.page.evaluate(() => {
            const controller = window.Comments.get(document.querySelector('[data-comments-root]'));
            controller.openEditor({ mode: 'edit', commentUid: 'c1' });
            controller.openEditor({ mode: 'edit', commentUid: 'c1' });
        });
        await t.page.waitForTimeout(700);
        check('a double press on Edit reads the comment once and opens one form', t.api.requests.filter((request) => request.action === 'edit').length === 1 && await t.page.locator(`${root} form[data-comments-form="edit"]`).count() === 1);
        await t.close();
    }

    // Trusted Types and a strict Content Security Policy, enforced by the browser.
    {
        const rich = comment('r1', { content: { format: 'rich', html: '<p><strong>Rich</strong> text</p>', text: 'Rich text', gifUrl: null, gifWidth: null, gifHeight: null } });
        const t = await open({ csp: STRICT_CSP, page: { nonce: 'n0nce' }, server: { roots: [rich, comment('c2')], editor: RICH }, wait: false });
        await t.page.evaluate(() => {
            window.__violations = [];
            document.addEventListener('securitypolicyviolation', (event) => window.__violations.push(`${event.violatedDirective} ${event.blockedURI}`));
        });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`, { timeout: 10000 });
        check('strict CSP and Trusted Types: the thread draws, rich text included', (await t.page.locator(`${root} .comments__content-rich strong`).textContent()) === 'Rich');
        await t.page.locator(`${root} form[data-comments-form="create"] textarea`).click();
        await t.page.waitForSelector(`${root} verbb-editor [contenteditable]`, { timeout: 5000 }).catch(() => undefined);
        check('strict CSP and Trusted Types: Editor loads with the nonce through the verbb-comments policy', await t.page.locator(`${root} verbb-editor [contenteditable]`).count() === 1);
        const violations = await t.page.evaluate(() => window.__violations);
        check('strict CSP and Trusted Types: no violations', !violations.length && !t.errors.some((error) => /Trusted|Content Security/i.test(error)), [...violations, ...t.errors].join(' | '));
        await t.close();
    }
}

async function presentation() {
    // Right to left: the reply arrows point the other way.
    {
        const t = await open({ page: { dir: 'rtl' } });
        const transform = await t.page.locator(`${root} .comments__icon--reply`).first().evaluate((icon) => getComputedStyle(icon).transform);
        check('RTL: the reply arrow is mirrored', transform.startsWith('matrix(-1'), transform);
        await t.close();
    }
    // 200% zoom: a 1000px window at 200% is a 500px layout, which mustn't scroll sideways.
    {
        const t = await open({ context: { viewport: { width: 500, height: 800 } } });
        await actions(t.page, 'reply', 'c1').click();
        const overflow = await t.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        check('200% zoom: nothing scrolls sideways, with a reply form open', overflow <= 0, `${overflow}px`);
        await t.close();
    }
    // Reduced motion: the empty-post shake and the toast's slide don't run.
    {
        const t = await open({ context: { reducedMotion: 'reduce' } });
        await t.page.locator(`${root} form[data-comments-form="create"] textarea`).click();
        await t.page.locator(`${root} form[data-comments-form="create"] .comments__submit`).click({ force: true });
        const running = await t.page.evaluate(() => document.getAnimations().filter((animation) => animation.playState === 'running').length);
        check('reduced motion: refusing an empty post doesn’t shake', running === 0, `${running} running`);
        await t.close();
    }
    // Forced colours: axe still passes and the focus ring is drawn.
    if (engine === 'chromium') {
        const t = await open({ context: { forcedColors: 'active' } });
        await t.page.addScriptTag({ content: axe });
        const violations = await t.page.evaluate(async () => (await window.axe.run(document.querySelector('[data-comments-root]'), { resultTypes: ['violations'] })).violations.map((violation) => violation.id));
        await actions(t.page, 'reply', 'c1').focus();
        await t.page.keyboard.press('Shift+Tab');
        await t.page.keyboard.press('Tab');
        const outline = await t.page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle);
        check('forced colours: no axe violations, and focus is outlined', !violations.length && outline !== 'none', `${violations.join(', ')} ${outline}`);
        await t.close();
    }
}

async function accessibility() {
    const states = [
        ['member', {}],
        ['moderator', { server: { viewer: { ...MEMBER, isModerator: true }, extraComments: [comment('held', { state: 'held', moderation: { reviewLabel: 'Held', openReportCount: 0, actions: ['approve', 'spam'] } })], moderation: { heldCount: 1, reportedCount: 0, reviewUrl: '/admin' } } }],
        ['guest', { server: { viewer: { ...MEMBER, isGuest: true, name: null, canComment: true, canVote: false, loginUrl: '/login', follow: { available: true, following: false, needsEmail: true } }, guest: { show: true, offerReplyEmails: true } } }],
        ['dark', { page: { theme: 'dark' } }],
    ];

    for (const [name, options] of states) {
        for (const prepare of [null, 'reply', 'report', 'menu']) {
            if (name !== 'member' && prepare) continue;

            const t = await open(options);
            const { page } = t;

            if (prepare === 'reply') {
                await actions(page, 'reply', 'c1').click();
                await page.waitForSelector(`${root} form[data-comments-form="reply"]`);
            } else if (prepare === 'report') {
                await page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c2"]`).click();
                await page.locator(`${root} [data-comments-action="report"][data-comments-comment-uid="c2"]`).click();
                await page.waitForSelector(`${root} dialog[open]`);
            } else if (prepare === 'menu') {
                await page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c2"]`).click();
            } else if (name === 'guest') {
                await page.getByText('Comment as a guest').click();
            }

            await page.addScriptTag({ content: axe });
            const violations = await page.evaluate(async () => {
                const result = await window.axe.run(document.querySelector('[data-comments-root]'), { resultTypes: ['violations'] });
                return result.violations.map((violation) => `${violation.id} (${violation.nodes.length}): ${violation.nodes.slice(0, 2).map((node) => node.target.join(' ')).join(', ')}`);
            });
            check(`axe: no violations, ${name}${prepare ? ` with the ${prepare} open` : ''}`, !violations.length, violations.join(' | '));
            await t.close();
        }
    }

    // Gate 4: borders that are a control's only edge reach 3:1 against what's inside and around them.
    for (const theme of ['light', 'dark']) {
        const t = await open({ page: { theme } });
        const ratios = await t.page.evaluate(() => {
            const rgb = (value) => value.match(/[\d.]+/g).slice(0, 3).map(Number);
            const luminance = ([r, g, b]) => [r, g, b].map((channel) => {
                const c = channel / 255;
                return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
            }).reduce((sum, c, index) => sum + c * [0.2126, 0.7152, 0.0722][index], 0);
            const contrast = (a, b) => {
                const [x, y] = [luminance(rgb(a)), luminance(rgb(b))].sort((m, n) => n - m);
                return (x + 0.05) / (y + 0.05);
            };
            const background = (element) => {
                for (let node = element; node; node = node.parentElement) {
                    const color = getComputedStyle(node).backgroundColor;
                    if (!/rgba\(.*, 0\)$/.test(color) && color !== 'transparent') return color;
                }
                return 'rgb(255, 255, 255)';
            };
            const frame = document.querySelector('[data-comments-root] .comments__frame');
            const input = document.querySelector('[data-comments-root] form[data-comments-form="create"] textarea');
            input.focus();
            input.blur();
            const border = getComputedStyle(frame).borderTopColor;
            return { inside: contrast(border, background(frame)), outside: contrast(border, background(frame.parentElement)) };
        });
        check(`contrast (${theme}): the editor frame's border, its only edge, reaches 3:1`, ratios.inside >= 3 && ratios.outside >= 3, `${ratios.inside.toFixed(2)} inside, ${ratios.outside.toFixed(2)} outside`);
        await t.close();
    }
}

/** Stage M's renderer half: every input, the file tray, field overrides by handle and the displays. */
async function customFields() {
    const F = { uid: 'u', instructions: null, required: false, multiple: false, options: [], attributes: {}, public: true, placement: 'details' };
    const COLOURS = [{ label: 'Red', value: 'red' }, { label: 'Blue', value: 'blue' }];
    const PHOTOS = { ...F, handle: 'photos', label: 'Photos', type: 'file', multiple: true, accept: ['.jpg', '.png'], maxFiles: 2, maxSize: 1024 * 1024 };
    const ALL = [
        { ...F, handle: 'plain', label: 'Plain', type: 'text', required: true },
        { ...F, handle: 'notes', label: 'Notes', type: 'textarea' },
        { ...F, handle: 'count', label: 'Count', type: 'number', attributes: { min: 1, max: 5 } },
        { ...F, handle: 'email', label: 'Email', type: 'email' },
        { ...F, handle: 'site', label: 'Site', type: 'url' },
        { ...F, handle: 'day', label: 'Day', type: 'date' },
        { ...F, handle: 'colour', label: 'Colour', type: 'select', options: COLOURS, required: true },
        { ...F, handle: 'colours', label: 'Colours', type: 'select', multiple: true, options: COLOURS },
        { ...F, handle: 'size', label: 'Size', type: 'radio', options: COLOURS, required: true },
        { ...F, handle: 'tags', label: 'Tags', type: 'checkboxes', options: COLOURS },
        { ...F, handle: 'agree', label: 'Agree', type: 'toggle' },
        PHOTOS,
    ];
    const file = (name, mimeType = 'image/jpeg', size = 2048) => ({ name, mimeType, buffer: Buffer.alloc(size, 1) });
    const create = `${root} form[data-comments-form="create"]`;
    const uploads = (api) => api.requests.filter((request) => request.action === 'upload');
    const error = (code, message, category = 'validation') => ({ status: 422, body: { apiVersion: 1, status: 'error', errors: [{ category, code, message, fieldErrors: {}, recoverable: false, retryAfter: null }] } });
    const post = async (page, form = create) => {
        await page.locator(`${form} .comments__submit`).click({ force: true });
        await page.waitForTimeout(400);
    };

    // Every built-in input, required only where the field is, and what each posts.
    {
        const t = await open({ server: { fields: { comment: ALL, reply: [] } } });
        const { page, api } = t;
        const form = page.locator(create);
        await page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        const drawn = await form.evaluate((element) => ({
            handles: Array.from(new Set(Array.from(element.querySelectorAll('[data-comments-field]'), (input) => input.dataset.commentsField))),
            types: ['plain', 'count', 'email', 'site', 'day'].map((handle) => element.querySelector(`[name="fields[${handle}]"]`)?.type),
            required: ['plain', 'notes', 'count', 'email', 'site', 'day', 'colour', 'colours'].filter((handle) => element.querySelector(`[name="fields[${handle}]"], [name="fields[${handle}][]"]`)?.required),
            radioRequired: Array.from(element.querySelectorAll('[name="fields[size]"]'), (input) => input.required),
        }));
        check('custom fields: an input for each of the 11 built-in types', drawn.handles.length === 12 && JSON.stringify(drawn.types) === JSON.stringify(['text', 'number', 'email', 'url', 'date']) && await form.locator('textarea[name="fields[notes]"]').count() === 1 && await form.locator('select[name="fields[colours][]"][multiple]').count() === 1 && await form.locator('[name="fields[tags][]"]').count() === 2 && await form.locator('[name="fields[agree]"][type="checkbox"]').count() === 1 && await form.locator('.comments__field--files').count() === 1, JSON.stringify(drawn));
        check('custom fields: required only where the field is', JSON.stringify(drawn.required) === JSON.stringify(['plain', 'colour']) && drawn.radioRequired.every(Boolean), JSON.stringify(drawn.required));

        await page.locator(`${create} textarea[data-comments-body]`).fill('With fields.');
        await form.locator('[name="fields[plain]"]').fill('Hello');
        await form.locator('[name="fields[notes]"]').fill('Longer notes');
        await form.locator('[name="fields[count]"]').fill('4');
        await form.locator('[name="fields[email]"]').fill('pat@example.com');
        await form.locator('[name="fields[site]"]').fill('https://example.com/');
        await form.locator('[name="fields[day]"]').fill('2026-11-11');
        await form.locator('[name="fields[colour]"]').selectOption('blue');
        await form.locator('[name="fields[colours][]"]').selectOption(['red', 'blue']);
        await form.locator('[name="fields[size]"][value="red"]').check();
        await form.locator('[name="fields[tags][]"][value="blue"]').check();
        await form.locator('[name="fields[agree]"]').check();
        await form.locator('input[data-comments-upload-input="photos"]').setInputFiles([file('run.jpg')]);
        await page.waitForSelector(`${create} .comments__upload--done`, { state: 'attached' });
        // Upload announcements are gathered for a moment, so several finishing together are all read.
        await page.waitForTimeout(300);
        const upload = uploads(api)[0]?.body ?? {};
        check('file tray: a chosen file uploads at once to its field, with the thread', upload.field === 'photos' && upload.ownerId === '109' && upload.siteId === '1' && upload.parentUid === undefined && upload.file?.filename === 'run.jpg' && upload.protection === undefined, JSON.stringify(upload));
        check('file tray: the uploaded file shows its thumbnail and size, and is announced', await form.locator('.comments__upload--done img.comments__upload-thumb').count() === 1 && (await form.locator('.comments__upload-meta').textContent()) === '2 KB' && (await live(page)) === 'run.jpg uploaded.');
        await post(page);
        const sent = api.requests.find((request) => request.action === 'create')?.body.fields;
        const expected = { plain: 'Hello', notes: 'Longer notes', count: '4', email: 'pat@example.com', site: 'https://example.com/', day: '2026-11-11', colour: 'blue', colours: ['red', 'blue'], size: 'red', tags: ['blue'], agree: true, photos: [501] };
        check('custom fields: posting sends every value by handle, and the file by its upload ID', JSON.stringify(sent) === JSON.stringify(expected), JSON.stringify(sent));
        check('custom fields: the form empties after posting, tray included', await form.locator('.comments__upload').count() === 0 && (await form.locator('[name="fields[plain]"]').inputValue()) === '');
        await t.close();
    }

    // Refused before uploading: a type the field doesn't take, a file too large, and one too many.
    {
        const t = await open({ server: { fields: { comment: [PHOTOS], reply: [] } } });
        const { page, api } = t;
        const input = page.locator(`${create} input[data-comments-upload-input="photos"]`);
        await page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        await input.setInputFiles([file('setup.exe', 'application/octet-stream')]);
        await page.waitForTimeout(100);
        check('file tray: a type the field doesn’t take is refused before uploading, beside the field', !uploads(api).length && (await page.locator(`${create} .comments__field--files .comments__field-error`).textContent()) === 'setup.exe can’t be added here. Choose .jpg, .png.');
        await input.setInputFiles([file('huge.jpg', 'image/jpeg', 2 * 1024 * 1024)]);
        await page.waitForTimeout(100);
        check('file tray: a file over the field’s size is refused before uploading', !uploads(api).length && (await page.locator(`${create} .comments__field--files .comments__field-error`).textContent()) === 'huge.jpg is larger than 1 MB.');
        await input.setInputFiles([file('a.jpg'), file('b.jpg'), file('c.jpg')]);
        await page.waitForFunction(() => document.querySelectorAll('.comments__upload--done').length === 2);
        check('file tray: too many files uploads only what the field takes, says why, and hides Add files', uploads(api).length === 2 && (await page.locator(`${create} .comments__field--files .comments__field-error`).textContent()) === 'You can add up to 2 files here.' && await page.locator(`${create} .comments__upload-add`).isHidden());

        // Remove keeps the keyboard in the tray, and what's removed isn't posted.
        await page.waitForTimeout(300);
        await page.locator(`${create} .comments__upload-remove`).first().focus();
        await page.keyboard.press('Enter');
        await page.waitForTimeout(150);
        check('file tray: Remove takes the file out, announces it, and moves focus to the next Remove', await page.locator(`${create} .comments__upload`).count() === 1 && (await page.evaluate(() => document.activeElement?.classList.contains('comments__upload-remove'))) && (await live(page)) === 'a.jpg removed.');
        await page.locator(`${create} textarea[data-comments-body]`).fill('Two photos, one removed.');
        await post(page);
        check('file tray: only the files left are posted', JSON.stringify(api.requests.find((request) => request.action === 'create')?.body.fields) === JSON.stringify({ photos: [502] }));
        await t.close();
    }

    // The server refusing an upload, Try again, and posting while one is still uploading.
    {
        let attempts = 0;
        const t = await open({ server: {
            fields: { comment: [PHOTOS], reply: [] },
            delay: { upload: 300 },
            override: { upload: () => (++attempts === 1 ? error('too_many_files', 'You have too many files waiting. Post or remove some first.') : null) },
        } });
        const { page, api } = t;
        await page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        await page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('run.jpg')]);
        await page.waitForSelector(`${create} .comments__upload--failed`, { state: 'attached' });
        await page.waitForTimeout(300);
        check('file tray: an upload the server refuses shows its message, with Try again', (await page.locator(`${create} .comments__upload-meta`).textContent()) === 'You have too many files waiting. Post or remove some first.' && await page.locator(`${create} .comments__upload-retry`).count() === 1 && (await live(page)).startsWith('run.jpg didn’t upload.'));
        await page.locator(`${create} .comments__upload-retry`).click();
        await page.locator(`${create} textarea[data-comments-body]`).fill('Too soon.');
        await post(page);
        check('file tray: posting while a file uploads waits, and says why', !api.requests.some((request) => request.action === 'create') && (await page.locator(`${create} .comments__form-error`).textContent()) === 'Please wait for your upload to finish.');
        await page.waitForSelector(`${create} .comments__upload--done`, { state: 'attached' });
        check('file tray: Try again uploads the same file again', uploads(api).length === 2 && uploads(api)[1].body.file?.filename === 'run.jpg');
        await t.close();
    }

    // An edit: exactly the fields the comment was posted with, its values, and its files.
    {
        const t = await open({ server: {
            permissions: (uid) => (uid === 'c1' ? { canEdit: true, isAuthor: true } : {}),
            fields: { comment: [{ ...F, handle: 'newer', label: 'Newer', type: 'text' }, PHOTOS], reply: [] },
            editEditorFields: [{ ...F, handle: 'where', label: 'Where', type: 'text' }, PHOTOS],
            editFields: { where: 'Rotorua', photos: [{ id: 41, url: `${ORIGIN}/uploads/old.jpg`, filename: 'old.jpg', kind: 'image', width: 10, height: 10 }, { id: 42, url: null, filename: 'route.pdf', kind: 'pdf' }] },
        } });
        const { page, api } = t;
        await page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c1"]`).click();
        await page.locator(`${root} [data-comments-action="edit"][data-comments-comment-uid="c1"]`).click();
        const edit = `${root} form[data-comments-form="edit"]`;
        await page.waitForSelector(`${edit} .comments__upload`);
        check('edit: draws exactly the fields the comment was posted with, from the edit read', (await page.locator(`${edit} [name="fields[where]"]`).inputValue()) === 'Rotorua' && await page.locator(`${edit} [name="fields[newer]"]`).count() === 0);
        check('edit: the tray shows the files the comment has', (await page.locator(`${edit} .comments__upload-name`).allTextContents()).join() === 'old.jpg,route.pdf' && (await page.locator(`${edit} .comments__upload-thumb`).getAttribute('src')) === `${ORIGIN}/uploads/old.jpg`);
        await page.locator(`${edit} .comments__upload-remove[aria-label="Remove route.pdf"]`).click();
        await page.locator(`${edit} input[data-comments-upload-input="photos"]`).setInputFiles([file('new.png', 'image/png')]);
        await page.waitForFunction((form) => document.querySelectorAll(`${form} .comments__upload--done`).length === 2, edit);
        check('edit: a new file uploads against the comment', uploads(api)[0]?.body.commentUid === 'c1' && uploads(api)[0]?.body.ownerId === undefined);
        await page.getByRole('button', { name: 'Save comment' }).click();
        await page.waitForTimeout(300);
        check('edit: saving keeps the files left and adds the new one', JSON.stringify(api.requests.find((request) => request.action === 'update')?.body.fields) === JSON.stringify({ where: 'Rotorua', photos: [41, 501] }), JSON.stringify(api.requests.find((request) => request.action === 'update')?.body.fields));
        await t.close();
    }

    // A reply's own fields, and its uploads sent with the comment it replies to.
    {
        const t = await open({ server: { fields: { comment: [{ ...F, handle: 'rating', label: 'Rating', type: 'number' }], reply: [{ ...PHOTOS, handle: 'replyPhoto', label: 'Reply photo' }] } } });
        const { page, api } = t;
        await actions(page, 'reply', 'c1').click();
        const reply = `${root} form[data-comments-form="reply"]`;
        await page.waitForSelector(`${reply} .comments__upload-add`, { state: 'attached' });
        check('reply: the form asks for the reply’s fields, not a new comment’s', await page.locator(`${reply} [name="fields[rating]"]`).count() === 0 && await page.locator(`${reply} [data-comments-field="replyPhoto"]`).count() === 1);
        await page.locator(`${reply} input[data-comments-upload-input="replyPhoto"]`).setInputFiles([file('reply.jpg')]);
        await page.waitForSelector(`${reply} .comments__upload--done`, { state: 'attached' });
        check('reply: its upload says which comment it replies to', uploads(api)[0]?.body.parentUid === 'c1' && uploads(api)[0]?.body.ownerId === '109');
        await t.close();
    }

    // Guests: no Assets field unless Guests Can Upload Files is on, and then a preparation per upload.
    {
        const guest = { ...MEMBER, isGuest: true, name: null, canComment: true, canVote: false, loginUrl: '/login', follow: { available: false, following: false, needsEmail: true } };
        const off = await open({ server: { viewer: guest, guest: { show: true }, fields: { comment: [{ ...F, handle: 'where', label: 'Where', type: 'text' }], reply: [] } } });
        await off.page.getByText('Comment as a guest').click();
        await off.page.waitForSelector(`${create} [name="fields[where]"]`, { state: 'attached' });
        check('guest: with Guests Can Upload Files off, no file tray is drawn', await off.page.locator(`${create} .comments__field--files`).count() === 0);
        await off.close();

        const t = await open({ server: { viewer: guest, guest: { show: true }, protect: true, fields: { comment: [PHOTOS], reply: [] } } });
        const { page, api } = t;
        await page.getByText('Comment as a guest').click();
        await page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        await page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('a.jpg')]);
        await page.waitForSelector(`${create} .comments__upload--done`, { state: 'attached' });
        await page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('b.jpg')]);
        await page.waitForFunction(() => document.querySelectorAll('.comments__upload--done').length === 2);
        await page.locator(`${create} [name="guest[name]"]`).fill('Pat');
        await page.locator(`${create} [name="guest[email]"]`).fill('pat@example.com');
        await page.locator(`${create} textarea[data-comments-body]`).fill('From a guest.');
        await post(page);
        const used = [...uploads(api).map((request) => JSON.parse(request.body.protection ?? '{}').preparation), api.requests.find((request) => request.action === 'create')?.body.protection?.preparation];
        check('guest: with it on, each upload uses a fresh preparation of its own, and the comment another', used.length === 3 && used.every(Boolean) && new Set(used).size === 3, used.join(', '));
        check('guest: the guest’s comment posts its files', JSON.stringify(api.requests.find((request) => request.action === 'create')?.body.fields) === JSON.stringify({ photos: [501, 502] }));
        await t.close();
    }

    // Shown values: links only for http and https, files with thumbnails, text never as HTML.
    {
        const shown = comment('c1', { fields: [
            { handle: 'site', label: 'Site', type: 'url', placement: 'details', value: 'https://example.com/run', text: 'https://example.com/run' },
            { handle: 'bad', label: 'Bad', type: 'url', placement: 'details', value: 'javascript:window.__pwned=1', text: 'javascript:window.__pwned=1' },
            { handle: 'nulled', label: 'Nulled', type: 'url', placement: 'inline', value: null, text: 'javascript:window.__pwned=2' },
            { handle: 'photos', label: 'Photos', type: 'file', placement: 'details', text: 'run.jpg, evil.svg', value: [{ url: `${ORIGIN}/uploads/run.jpg`, filename: 'run.jpg', kind: 'image', width: 12, height: 8 }, { url: 'javascript:window.__pwned=3', filename: 'evil.svg', kind: 'image' }] },
            { handle: 'bio', label: 'Bio', type: 'textarea', placement: 'details', value: '<img src=x onerror="window.__pwned=4">', text: '<img src=x onerror="window.__pwned=4">' },
        ] });
        const t = await open({ server: { roots: [shown, comment('c2')] } });
        const { page } = t;
        const links = await page.locator(`${root} [data-comments-comment-uid="c1"] article a[href]`).evaluateAll((anchors) => anchors.map((anchor) => [anchor.getAttribute('href'), anchor.rel, anchor.className]));
        const fieldLinks = links.filter(([, , className]) => /comments__field-(link|file)/.test(className));
        check('displays: an unsafe address is never linked or loaded, and links carry rel="ugc nofollow noopener"', !links.some(([href]) => /^\s*javascript:/i.test(href)) && fieldLinks.length === 2 && fieldLinks.every(([, rel]) => rel === 'ugc nofollow noopener') && await page.locator(`${root} img[src^="javascript"]`).count() === 0, JSON.stringify(links));
        check('displays: the address that is safe links, files show a thumbnail, and text stays text', await page.locator(`${root} a.comments__field-link[href="https://example.com/run"]`).count() === 1 && await page.locator(`${root} .comments__field-thumb[src="${ORIGIN}/uploads/run.jpg"]`).count() === 1 && (await page.locator(`${root} .comments__field-value--bio .comments__field-text`).textContent()) === '<img src=x onerror="window.__pwned=4">' && !(await page.evaluate(() => window.__pwned)));
        await t.close();
    }

    // A star rating through field overrides by handle: stars on the form, stars on the comment.
    {
        const t = await open({
            page: { autoMount: false },
            wait: false,
            server: {
                fields: { comment: [{ ...F, handle: 'rating', label: 'Rating', type: 'number', required: true }], reply: [] },
                roots: [comment('c1', { fields: [{ handle: 'rating', label: 'Rating', type: 'number', placement: 'details', value: 4, text: '4' }] })],
            },
        });
        const { page, api } = t;
        await page.waitForFunction(() => window.Comments);
        await page.evaluate(async () => {
            // Given in two calls, which merge per handle and per kind.
            window.Comments.configure({ fields: { rating: {
                input: ({ html, value, setValue, labelId }) => html`<div class="stars" role="radiogroup" aria-labelledby=${labelId}>${[1, 2, 3, 4, 5].map((n) => html`<button type="button" class="star" aria-label=${`${n} stars`} aria-pressed=${String(Number(value) >= n)} @click=${() => setValue(n)}>★</button>`)}</div>`,
            } } });
            window.Comments.configure({ fields: { rating: {
                display: ({ html, value }) => html`<span class="stars-shown" aria-label=${`${value} out of 5`}>${'★'.repeat(value)}${'☆'.repeat(5 - value)}</span>`,
            } } });
            await window.Comments.mount();
        });
        await page.waitForSelector(`${create} .stars`, { state: 'attached' });
        check('override: the rating shows as stars on the comment', (await page.locator(`${root} .comments__field-value--rating .stars-shown`).textContent()) === '★★★★☆');
        await page.locator(`${create} textarea[data-comments-body]`).fill('Three stars.');
        await page.getByRole('button', { name: '3 stars' }).click();
        check('override: the stars set the value, inside Comments’ label', (await page.locator(`${create} .star[aria-pressed="true"]`).count()) === 3 && (await page.locator(`${create} [data-comments-field="rating"] .comments__field-label`).textContent()).startsWith('Rating'));
        await post(page);
        check('override: posting sends what the override set', JSON.stringify(api.requests.find((request) => request.action === 'create')?.body.fields) === JSON.stringify({ rating: 3 }));
        await t.close();
    }
}

/**
 * Component hosts (stage F), which the React and Vue adapters render their components into. The page
 * stands in for an adapter: each override's `component` is a function that draws into its host.
 */
async function hosts() {
    const F = { uid: 'u', instructions: null, required: false, multiple: false, options: [], attributes: {}, public: true, placement: 'details' };
    const create = `${root} form[data-comments-form="create"]`;
    // Mounts the thread with component overrides and draws every report the way an adapter would.
    const mount = (page, options) => page.evaluate(async (names) => {
        const components = {
            Badge: (element, context) => { element.textContent = `★ ${context.badge.label}`; },
            Content: (element, context) => { element.dataset.drawn = String(Number(element.dataset.drawn ?? 0) + 1); element.textContent = context.comment.content.text; },
            Stars: (element, context) => {
                let group = element.querySelector('.stars');

                if (!group) {
                    group = document.createElement('div');
                    group.className = 'stars';
                    group.setAttribute('role', 'radiogroup');
                    group.tabIndex = -1;
                    for (let n = 1; n <= 5; n++) {
                        const star = document.createElement('button');
                        star.type = 'button';
                        star.textContent = '★';
                        star.setAttribute('aria-label', `${n} stars`);
                        star.addEventListener('click', () => group.context.setValue(n));
                        group.append(star);
                    }
                    element.append(group);
                }

                group.context = context;
                group.id = context.id;
                group.setAttribute('aria-labelledby', context.labelId);
                if (context.error) group.setAttribute('aria-invalid', 'true');
                else group.removeAttribute('aria-invalid');
                group.querySelectorAll('button').forEach((star, index) => star.setAttribute('aria-pressed', String(Number(context.value) > index)));
            },
        };
        const wrap = (name) => ({ component: components[name] });
        window.__reports = [];
        window.__controller = await window.Comments.mountThread(document.querySelector('[data-comments-root]'), {
            loadMode: 'eager',
            parts: Object.fromEntries(Object.entries(names.parts ?? {}).map(([part, name]) => [part, wrap(name)])),
            fields: Object.fromEntries(Object.entries(names.fields ?? {}).map(([handle, kinds]) => [handle, Object.fromEntries(Object.entries(kinds).map(([kind, name]) => [kind, wrap(name)]))])),
            onHosts: (list) => {
                window.__reports.push(list.map((host) => host.key));
                window.__hosts = Object.fromEntries(list.map((host) => [host.key, host]));
                list.forEach((host) => host.component(host.element, host.context));
            },
        });
    }, options);
    const keys = (page) => page.evaluate(() => Object.keys(window.__hosts ?? {}).sort());

    // A part's host survives a redraw, and a removed comment's host is dropped.
    {
        const badges = [{ type: 'verified', label: 'Verified' }];
        const t = await open({
            page: { autoMount: false },
            wait: false,
            server: { roots: [comment('c1', { author: { name: 'Alex Morgan', initials: 'AM', avatarUrl: null, avatarTone: 3, isGuest: false, badges, score: null } }), comment('c2'), comment('c3')], permissions: (uid) => (uid === 'c2' ? { canDelete: true, isAuthor: true } : {}) },
        });
        const { page, api } = t;
        await page.waitForFunction(() => window.Comments);
        await mount(page, { parts: { badge: 'Badge', content: 'Content' } });
        await page.waitForFunction(() => window.__hosts?.['comment:c3:content']);
        check('hosts: each override is an empty host the page renders into, keyed by comment', JSON.stringify(await keys(page)) === JSON.stringify(['comment:c1:badge:0', 'comment:c1:content', 'comment:c2:content', 'comment:c3:content']) && (await page.locator(`${root} [data-comments-comment-uid="c1"] verbb-comments-host[data-comments-host="comment:c1:badge:0"]`).textContent()) === '★ Verified', JSON.stringify(await keys(page)));
        check('hosts: a host adds no box of its own', (await page.locator(`${root} verbb-comments-host`).first().evaluate((element) => getComputedStyle(element).display)) === 'contents');

        const reports = await page.evaluate(() => {
            window.__badge = window.__hosts['comment:c1:badge:0'].element;
            window.__content = window.__hosts['comment:c1:content'].element;
            window.__content.dataset.mark = 'kept';
            return window.__reports.length;
        });
        await page.getByRole('button', { name: /^Like/ }).first().click();
        await page.waitForFunction((count) => window.__reports.length > count, reports);
        await page.waitForTimeout(200);
        const after = await page.evaluate(() => ({
            badge: window.__hosts['comment:c1:badge:0'].element === window.__badge,
            content: window.__hosts['comment:c1:content'].element === window.__content,
            mark: window.__content.dataset.mark,
            connected: window.__content.isConnected,
            likes: window.__hosts['comment:c1:content'].context.comment.interaction.upvotes,
        }));
        check('hosts: a vote redraws the comment and its hosts stay the same elements, with what was rendered in them', api.requests.some((request) => request.action === 'vote') && after.badge && after.content && after.mark === 'kept' && after.connected && after.likes === 3, JSON.stringify(after));

        await page.evaluate(() => {
            window.__removed = window.__hosts['comment:c2:content'].element;
            return window.__hosts['comment:c2:content'].context.actions.delete();
        });
        await page.waitForFunction(() => !window.__hosts['comment:c2:content']);
        check('hosts: a removed comment’s host is dropped from the next report and leaves the page', api.requests.some((request) => request.action === 'delete') && JSON.stringify(await keys(page)) === JSON.stringify(['comment:c1:badge:0', 'comment:c1:content', 'comment:c3:content']) && !(await page.evaluate(() => window.__removed.isConnected)));
        check('hosts: nothing went wrong', !t.errors.length, t.errors.join(' | '));
        await t.close();
    }

    // A field's input override as a component: it posts what it sets and shows the error it's sent.
    {
        let refused = 0;
        const t = await open({
            page: { autoMount: false },
            wait: false,
            server: {
                fields: { comment: [{ ...F, handle: 'rating', label: 'Rating', type: 'number', required: true }], reply: [] },
                override: { create: () => (refused++ === 0 ? { status: 422, body: { apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Check the form.', fieldErrors: { 'fields.rating': ['Pick between one and five stars.'] }, recoverable: true, retryAfter: null }] } } : null) },
            },
        });
        const { page, api } = t;
        await page.waitForFunction(() => window.Comments);
        await mount(page, { fields: { rating: { input: 'Stars' } } });
        await page.waitForSelector(`${create} verbb-comments-host[data-comments-host="form:create:input:rating"] .stars`, { state: 'attached' });
        check('hosts: an input host sits inside Comments’ own label and error', (await page.locator(`${create} [data-comments-field-override][data-comments-field="rating"] .comments__field-label`).textContent()).startsWith('Rating') && await page.locator(`${create} [data-comments-field="rating"] verbb-comments-host .stars[aria-labelledby$="-rating-label"]`).count() === 1);

        await page.locator(`${create} textarea[data-comments-body]`).fill('Four stars.');
        await page.getByRole('button', { name: '4 stars' }).click();
        await page.waitForFunction(() => window.__hosts['form:create:input:rating'].context.value === 4);
        check('hosts: the input’s setValue() reaches the next report as its value', await page.locator(`${create} .stars [aria-pressed="true"]`).count() === 4);

        await page.locator(`${create} .comments__submit`).click({ force: true });
        await page.waitForFunction(() => window.__hosts['form:create:input:rating'].context.error);
        await page.waitForTimeout(300);
        const refusedPost = api.requests.find((request) => request.action === 'create');
        check('hosts: posting sends what the input set', JSON.stringify(refusedPost?.body.fields) === JSON.stringify({ rating: 4 }), JSON.stringify(refusedPost?.body.fields));
        check('hosts: a refused field’s error reaches the input, which marks itself invalid beside Comments’ message, and gets the keyboard', await page.locator(`${create} .stars[aria-invalid]`).count() === 1 && (await page.locator(`${create} [data-comments-field="rating"] .comments__field-error`).textContent()) === 'Pick between one and five stars.' && await page.evaluate(() => document.activeElement?.classList.contains('stars')), await page.evaluate(() => document.activeElement?.outerHTML.slice(0, 80)));

        await page.getByRole('button', { name: '5 stars' }).click();
        await page.waitForFunction(() => window.__hosts['form:create:input:rating'].context.error === null);
        check('hosts: setting a value clears the error', await page.locator(`${create} .stars[aria-invalid]`).count() === 0 && await page.locator(`${create} [data-comments-field="rating"] .comments__field-error`).count() === 0);
        await page.locator(`${create} .comments__submit`).click({ force: true });
        await page.waitForTimeout(500);
        check('hosts: the next post goes through with the new value', JSON.stringify(api.requests.filter((request) => request.action === 'create')[1]?.body.fields) === JSON.stringify({ rating: 5 }) && api.requests.filter((request) => request.action === 'create').length === 2);

        await page.addScriptTag({ content: axe });
        const violations = await page.evaluate(async () => (await window.axe.run(document.querySelector('[data-comments-root]'), { resultTypes: ['violations'] })).violations.map((violation) => `${violation.id}: ${violation.nodes.length}`));
        check('hosts: axe finds nothing with a component input drawn in its host', !violations.length, violations.join(' | '));

        await page.evaluate(() => window.__controller.destroy());
        check('hosts: stopping the thread reports no hosts', JSON.stringify(await page.evaluate(() => window.__reports.at(-1))) === '[]');
        await t.close();
    }

    // An input override taken away while the form is open, then a group that can't take focus itself.
    {
        const t = await open({
            page: { autoMount: false },
            wait: false,
            server: {
                // Mood comes first on the form, so it's the first invalid control in it, but rating is the first error.
                fields: { comment: [{ ...F, handle: 'mood', label: 'Mood', type: 'text' }, { ...F, handle: 'rating', label: 'Rating', type: 'number', required: true }], reply: [] },
                override: { create: (_q, _b, requests) => (requests.filter((r) => r.action === 'create').length === 2 ? { status: 422, body: { apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Check the form.', fieldErrors: { 'fields.rating': ['Pick a rating.'], 'fields.mood': ['Say how you feel.'] }, recoverable: true, retryAfter: null }] } } : null) },
            },
        });
        const { page, api } = t;
        await page.waitForFunction(() => window.Comments);
        await mount(page, { fields: { rating: { input: 'Stars' } } });
        await page.waitForSelector(`${create} .stars`, { state: 'attached' });
        await page.locator(`${create} textarea[data-comments-body]`).fill('Four.');
        await page.getByRole('button', { name: '4 stars' }).click();
        await page.evaluate(() => window.__controller.setOptions({ fields: {} }));
        await page.waitForSelector(`${create} input[name="fields[rating]"]`);
        check('hosts: an input override taken away leaves the built-in input with its value', (await page.locator(`${create} input[name="fields[rating]"]`).inputValue()) === '4');
        await page.locator(`${create} input[name="fields[rating]"]`).fill('2');
        await page.locator(`${create} textarea[data-comments-body]`).fill('Two.');
        await page.locator(`${create} .comments__submit`).click({ force: true });
        await page.waitForTimeout(500);
        check('hosts: and posts what the built-in input holds, not the override’s last value', api.requests.find((request) => request.action === 'create')?.body.fields?.rating === '2', JSON.stringify(api.requests.find((request) => request.action === 'create')?.body.fields));

        // A radiogroup with no tabindex of its own, marked invalid, ahead of a built-in field that's invalid too.
        await page.evaluate(() => window.__controller.setOptions({ fields: { rating: { input: { component: (element, context) => {
            let group = element.querySelector('[role="radiogroup"]');
            if (!group) {
                group = document.createElement('div');
                group.setAttribute('role', 'radiogroup');
                group.innerHTML = '<button type="button" class="first-star">★</button><button type="button">★</button>';
                group.addEventListener('click', () => context.setValue(1));
                element.append(group);
            }
            if (context.error) group.setAttribute('aria-invalid', 'true');
            else group.removeAttribute('aria-invalid');
        } } } } }));
        await page.waitForSelector(`${create} .first-star`, { state: 'attached' });
        await page.locator(`${create} textarea[data-comments-body]`).fill('Again.');
        await page.locator(`${create} .first-star`).click();
        await page.locator(`${create} .comments__submit`).click({ force: true });
        await page.waitForFunction(() => document.activeElement?.classList.contains('first-star'), null, { timeout: 3000 }).catch(() => undefined);
        check('hosts: after a refused post, the first errored field’s group gives the keyboard to its first control', await page.evaluate(() => document.activeElement?.classList.contains('first-star')), await page.evaluate(() => document.activeElement?.outerHTML.slice(0, 80)));
        // The refused post's 422 is the browser's own note, expected here.
        const unexpected = t.errors.filter((error) => !error.includes('422'));
        check('hosts: nothing went wrong with overrides changing', !unexpected.length, unexpected.join(' | '));
        await t.close();
    }

    // Hosts let go when their comment goes: one whose component draws its default into its own element
    // (as error boundaries do), and one inside a removed component's default.
    {
        const badged = (uid) => comment(uid, { author: { name: 'Ana', initials: 'A', avatarUrl: null, avatarTone: 1, isGuest: false, badges: [{ type: 'verified', label: 'Verified' }], score: null } });
        const roots = [badged('c1'), badged('c2'), badged('c3')];
        const t = await open({ page: { autoMount: false }, wait: false, server: { roots } });
        const { page } = t;
        await page.waitForFunction(() => window.Comments);
        await page.evaluate(async () => {
            const element = document.querySelector('[data-comments-root]');
            window.__reports = [];
            window.__controller = await window.Comments.mountThread(element, {
                loadMode: 'eager',
                parts: { content: { component: 'Content' }, comment: { component: 'Comment' }, badge: { component: 'Badge' } },
                onHosts: (hosts) => {
                    window.__reports.push(hosts.map((host) => host.key));
                    for (const host of hosts) {
                        if (host.name === 'content') host.context.renderDefaultInto(host.element);
                        if (host.name === 'comment' && !host.element.querySelector(':scope > .mine')) {
                            const into = Object.assign(document.createElement('div'), { className: 'mine' });
                            host.element.append(into);
                            host.context.renderDefaultInto(into);
                        }
                        if (host.name === 'badge' && !host.element.firstChild) host.element.textContent = '★';
                    }
                },
            });
            await new Promise((resolve) => { const wait = () => (element.dataset.commentsPhase === 'ready' && element.querySelector('.mine .comments__content') ? resolve() : setTimeout(wait, 20)); wait(); });
            await new Promise((resolve) => setTimeout(resolve, 200));
        });
        roots.splice(0, 1);
        const result = await page.evaluate(async () => {
            const count = window.__reports.length;
            window.__controller.refresh();
            await new Promise((resolve) => setTimeout(resolve, 600));
            const first = window.__reports.slice(count).find((keys) => !keys.includes('comment:c1:comment'));
            return { first, stale: window.__controller.getHosts().filter((host) => !host.element.isConnected).map((host) => host.key), keys: window.__controller.getHosts().map((host) => host.key).sort() };
        });
        check('hosts: a removed comment’s hosts go in one report, its own-element default and the hosts inside its default included', result.first && !result.first.some((key) => key.startsWith('comment:c1:')) && !result.stale.length && result.keys.length === 6, JSON.stringify(result));
        await t.close();
    }

    // Gate 2 with a component host on every comment of a long thread, drawn as an adapter would.
    if (engine === 'chromium') {
        const many = Array.from({ length: 50 }, (_, index) => comment(`h${index}`, { replies: { count: 3, items: [1, 2, 3].map((n) => comment(`h${index}-${n}`, { parentUid: `h${index}`, depth: 1 })), next: null } }));
        const t = await open({ page: { autoMount: false }, wait: false, server: { roots: many, delay: { thread: 300 } } });
        const cdp = await t.context.newCDPSession(t.page);
        await t.page.waitForFunction(() => window.Comments);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        await t.page.evaluate(() => {
            window.__tasks = [];
            window.__reports = 0;
            new PerformanceObserver((list) => list.getEntries().forEach((entry) => window.__tasks.push({ start: entry.startTime, duration: entry.duration }))).observe({ type: 'longtask' });
            document.addEventListener('comments:before-render', (event) => {
                if (event.detail.reason !== 'thread' || window.__drawn) return;
                requestAnimationFrame(() => setTimeout(() => {
                    const response = performance.getEntriesByType('resource').find((entry) => entry.name.includes('/api/thread'));
                    window.__drawn = performance.now() - response.responseEnd;
                    window.__frame = performance.now();
                }, 0));
            });
            return window.Comments.mountThread(document.querySelector('[data-comments-root]'), {
                loadMode: 'eager',
                parts: { authorName: { component: (element, context) => { element.textContent = context.comment.author.name; } } },
                onHosts: (hosts) => { window.__reports++; window.__hosts = hosts.length; hosts.forEach((host) => { if (!host.element.textContent) host.component(host.element, host.context); }); },
            });
        });
        await t.page.waitForFunction(() => window.__hosts === 200, null, { timeout: 15000 });
        await t.page.waitForTimeout(300);
        const result = await t.page.evaluate(() => ({ drawn: window.__drawn, reports: window.__reports, longest: window.__tasks.filter((task) => task.start + task.duration > window.__frame && task.start >= window.__frame - 1).reduce((max, task) => Math.max(max, task.duration), 0) }));
        check('gate 2: with a component host on each of 200 comments, the first frame draws in under 100 ms at 4× CPU slowdown, and no task after it is over 50 ms', result.drawn < 100 && result.longest <= 50, `${result.drawn.toFixed(0)} ms to the first frame, longest task after it ${result.longest.toFixed(0)} ms, ${result.reports} reports`);
        check('gate 2: the 200 hosts are reported once per batch, not once per host', result.reports <= 10, `${result.reports} reports`);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 });
        await t.close();
    }
}

/**
 * `@verbb/comments-web-components`, bundled from its source the way a site's build would, with the
 * browser package's built renderer and a stand-in for `@verbb/editor` that registers the fake Editor.
 * Served from `/wc/`.
 */
let wcFiles = null;

async function webComponentsBundle() {
    if (wcFiles) return wcFiles;

    const { build } = await import('vite');
    const output = await build({
        configFile: false,
        logLevel: 'silent',
        root: plugin,
        plugins: [{
            name: 'editor-stand-in',
            enforce: 'pre',
            resolveId: (id) => (id === '@verbb/editor' ? '\0editor-stand-in' : null),
            load: (id) => (id === '\0editor-stand-in' ? `export function registerVerbbEditorElement() { window.__editorRegistered = (window.__editorRegistered ?? 0) + 1; if (!customElements.get('verbb-editor')) {${FAKE_EDITOR}} }` : null),
        }],
        build: {
            write: false,
            minify: false,
            target: 'es2022',
            // The element, the renderer an adapter imports and the main entry, sharing one copy of it.
            lib: { entry: { wc: path.join(plugin, 'packages/comments-web-components/src/index.ts'), renderer: path.join(plugin, 'packages/comments-browser/dist/renderer.js'), browser: path.join(plugin, 'packages/comments-browser/dist/index.js') }, formats: ['es'] },
            rolldownOptions: { output: { entryFileNames: '[name].js', chunkFileNames: 'wc-[name]-[hash].js' } },
        },
    });
    wcFiles = new Map([output].flat().flatMap((result) => result.output).filter((chunk) => chunk.type === 'chunk').map((chunk) => [`/wc/${chunk.fileName}`, chunk.code]));
    return wcFiles;
}

/** A page with `<verbb-comments>` elements, and the events every thread sends, by element. */
function wcPage({ body, before = '', plugin: withPlugin = false, pluginFirst = false }) {
    return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Thread</title><link rel="stylesheet" href="/comments.css">
        <script>window.__events = []; ['comments:mounted', 'comments:destroyed', 'comments:roots-loaded', 'comments:sorted'].forEach((name) => document.addEventListener(name, (event) => window.__events.push({ name, target: event.detail.root.id, from: event.target === event.detail.root, same: event.detail.controller === event.detail.root.controller })));</script>
        </head><body><main><h1>Post</h1>${body}<p id="after">After the thread.</p></main>${before}
        ${withPlugin && pluginFirst ? '<script type="module" src="/comments.js"></script>' : ''}
        <script type="module">import { registerCommentsWebComponents } from '/wc/wc.js'; registerCommentsWebComponents(); window.__wcReady = true;</script>
        ${withPlugin && !pluginFirst ? '<script type="module" src="/comments.js"></script>' : ''}</body></html>`;
}

const WC = `endpoint="${ORIGIN}/actions/comments/api" site-id="1"`;

async function webComponents() {
    const files = await webComponentsBundle();
    const threads = (api) => api.requests.filter((request) => request.action === 'thread');
    const events = (page, name, target = 'wc') => page.evaluate(([n, id]) => window.__events.filter((event) => event.name === n && event.target === id), [name, target]);

    // Comments.configure() reaches the element's thread, given before the element and the renderer are
    // even imported, as it does the threads the plugin's script draws.
    {
        const html = `<!doctype html><html lang="en" class="site-dark"><head><meta charset="utf-8"><title>Thread</title><link rel="stylesheet" href="/comments.css"></head><body><main><verbb-comments id="wc" owner-id="109" ${WC} load-mode="eager" theme="light"></verbb-comments></main>
            <script type="module">import { configure } from '/wc/browser.js'; configure({ darkSelector: 'html.site-dark', labels: { reply: 'Answer' } }); const { registerCommentsWebComponents } = await import('/wc/wc.js'); registerCommentsWebComponents();</script></body></html>`;
        const t = await open({ files, html });
        const element = t.page.locator('verbb-comments');
        await element.locator('[data-comments-action="reply"]').first().waitFor();
        const before = { theme: await element.getAttribute('data-comments-theme'), reply: (await element.locator('[data-comments-action="reply"]').first().textContent()).trim() };
        await t.page.evaluate(() => document.documentElement.classList.remove('site-dark'));
        await t.page.waitForTimeout(100);
        check('web components: configure() given before the element reaches its thread (darkSelector, labels)', before.theme === 'dark' && before.reply === 'Answer' && await element.getAttribute('data-comments-theme') === 'light', JSON.stringify(before));
        check('web components: configure() with the element: no errors in the page', !t.errors.length, t.errors.join(' | '));
        await t.close();
    }

    // Drawn in the light DOM from its attributes, with the browser events coming from the element.
    {
        const t = await open({ files, html: wcPage({ body: `<verbb-comments id="wc" owner-id="109" ${WC} load-mode="eager"></verbb-comments>` }) });
        const { page, api } = t;
        const element = page.locator('verbb-comments');
        check('web components: <verbb-comments> draws its thread in the light DOM', await element.locator('.comments__list > [data-comments-comment]').count() === 3 && await page.evaluate(() => !document.querySelector('verbb-comments').shadowRoot) && await element.getAttribute('data-comments-js') === '1');
        check('web components: the element is a block, styled as a thread', (await element.evaluate((node) => getComputedStyle(node).display)) !== 'inline' && (await element.locator('.comments__list').evaluate((list) => getComputedStyle(list).display)) === 'grid');
        check('web components: the thread is read with its attributes', threads(api).length === 1 && threads(api)[0].url.searchParams.get('ownerId') === '109' && threads(api)[0].url.searchParams.get('siteId') === '1');
        const mounted = await events(page, 'comments:mounted');
        check('web components: browser events come from the element, with its controller', mounted.length === 1 && mounted[0].same && mounted[0].from && (await events(page, 'comments:roots-loaded')).length === 1);

        // sort and theme change the drawn thread in place.
        const before = await page.evaluate(() => { window.__first = document.querySelector('verbb-comments').controller; return window.__first.sort; });
        await page.evaluate(() => { const wc = document.querySelector('verbb-comments'); wc.setAttribute('sort', 'top'); wc.setAttribute('theme', 'dark'); });
        await page.waitForFunction(() => document.querySelector('verbb-comments').controller.sort === 'top');
        check('web components: sort sorts the drawn thread and theme switches it, without starting again', before === 'newest' && threads(api).at(-1).url.searchParams.get('sort') === 'top' && await element.getAttribute('data-comments-theme') === 'dark' && await page.evaluate(() => document.querySelector('verbb-comments').controller === window.__first) && (await events(page, 'comments:mounted')).length === 1);

        // Properties redraw the thread.
        await page.evaluate(() => {
            const wc = document.querySelector('verbb-comments');
            wc.themeConfig = { quietButton: { attributes: { class: 'btn-ghost' } } };
            wc.parts = { authorName: ({ html, comment }) => html`<b class="custom-author">${comment.author.name}</b>` };
            wc.labels = { reply: 'Answer' };
        });
        await page.waitForTimeout(200);
        await page.evaluate(() => { const wc = document.querySelector('verbb-comments'); wc.parts.badge = undefined; wc.parts.authorName = ({ html, comment }) => html`<b class="custom-author in-place">${comment.author.name}</b>`; wc.parts = wc.parts; });
        await page.waitForTimeout(200);
        check('web components: parts changed in place and set again redraw the thread', await element.locator('.in-place').count() === 4);
        check('web components: themeConfig, parts and labels redraw the thread', await element.locator('.custom-author').count() === 4 && (await element.locator('[data-comments-action="reply"]').first().getAttribute('class')).includes('btn-ghost') && (await element.locator('[data-comments-action="reply"]').first().textContent()).trim() === 'Answer');

        // Moved on the page, it keeps its thread and what was typed.
        await element.locator('form[data-comments-form="create"] textarea').fill('Half a thought');
        await page.evaluate(() => document.querySelector('main').append(document.querySelector('verbb-comments')));
        await page.waitForTimeout(200);
        check('web components: moving the element keeps its thread and what was typed', threads(api).length === 2 && await page.evaluate(() => document.querySelector('verbb-comments').controller === window.__first) && (await element.locator('form[data-comments-form="create"] textarea').inputValue()) === 'Half a thought' && (await events(page, 'comments:destroyed')).length === 0);

        // Changing which thread starts it again, once for attributes changed together.
        await page.evaluate(() => { const wc = document.querySelector('verbb-comments'); wc.removeAttribute('owner-id'); wc.setAttribute('owner-uri', 'blog/other'); wc.setAttribute('site-id', '2'); });
        await page.waitForFunction(() => document.querySelector('verbb-comments').controller && document.querySelector('verbb-comments').controller !== window.__first);
        await page.waitForSelector('verbb-comments[data-comments-phase="ready"]');
        const last = threads(api).at(-1).url.searchParams;
        check('web components: owner-uri and site-id changed together start one new thread', threads(api).length === 3 && last.get('ownerUri') === 'blog/other' && last.get('siteId') === '2' && (await events(page, 'comments:destroyed')).length === 1 && (await events(page, 'comments:mounted')).length === 2 && await element.locator('.custom-author').count() === 4);

        // Taken off the page it stops, and put back it starts again.
        await page.evaluate(async () => {
            const wc = document.querySelector('verbb-comments');
            wc.remove();
            await new Promise((resolve) => setTimeout(resolve, 50));
            window.__removed = { controller: wc.controller, root: wc.hasAttribute('data-comments-root'), children: wc.childElementCount };
            document.querySelector('main').append(wc);
        });
        await page.waitForSelector('verbb-comments[data-comments-phase="ready"]');
        const removed = await page.evaluate(() => window.__removed);
        check('web components: taken off the page it stops, and put back it starts again', removed.controller === null && !removed.root && removed.children === 0 && (await events(page, 'comments:destroyed')).length === 2 && (await events(page, 'comments:mounted')).length === 3);

        // Turbo Drive's cache keeps an empty element, and the copy it restores starts afresh.
        await page.evaluate(() => document.dispatchEvent(new Event('turbo:before-cache')));
        const cached = await page.evaluate(() => { const wc = document.querySelector('verbb-comments'); return { controller: wc.controller, root: wc.hasAttribute('data-comments-root'), js: wc.hasAttribute('data-comments-js'), children: wc.childElementCount }; });
        await page.evaluate(() => { const wc = document.querySelector('verbb-comments'); wc.replaceWith(wc.cloneNode(true)); });
        await page.waitForSelector('verbb-comments[data-comments-phase="ready"]');
        check('web components: Turbo’s before-cache stops the thread, and the restored copy starts again', cached.controller === null && !cached.root && !cached.js && cached.children === 0 && await element.locator('.comments__list > [data-comments-comment]').count() === 3);

        // A morph that takes the drawn thread away: the same controller draws it again, with the
        // element's properties, and without reading the thread again.
        const threadsBeforeMorph = threads(api).length;
        const beforeMorph = await page.evaluate(() => { const wc = document.querySelector('verbb-comments'); wc.parts = { authorName: ({ html, comment }) => html`<b class="custom-author">${comment.author.name}</b>` }; wc.labels = { reply: 'Answer' }; window.__beforeMorph = wc.controller; wc.replaceChildren(); return Boolean(window.__beforeMorph); });
        await page.waitForSelector('verbb-comments[data-comments-phase="ready"] .comments__list > [data-comments-comment]');
        check('web components: a morph that takes the drawn thread away draws it again with the same controller and properties', beforeMorph && await page.evaluate(() => document.querySelector('verbb-comments').controller === window.__beforeMorph) && await element.locator('.comments__list > [data-comments-comment]').count() === 3 && await element.locator('.custom-author').count() === 4 && (await element.locator('[data-comments-action="reply"]').first().textContent()).trim() === 'Answer' && threads(api).length === threadsBeforeMorph);

        check('web components: nothing went wrong', !t.errors.length, t.errors.join(' | '));
        await t.close();
    }

    // A property set before the element is defined, waiting for an owner, and Editor from npm by default.
    {
        const t = await open({
            files,
            wait: false,
            server: { editor: { authoring: 'rich', richEditor: { context: 'comments.reply', module: `${ORIGIN}/editor.js`, css: `${ORIGIN}/editor.css` } } },
            html: wcPage({ body: `<verbb-comments id="wc" ${WC} load-mode="eager"></verbb-comments>`, before: '<script>document.querySelector("verbb-comments").labels = { post: "Send it" };</script>' }),
        });
        const { page, api, fetched } = t;
        await page.waitForFunction(() => window.__wcReady);
        await page.waitForTimeout(200);
        check('web components: without owner-id or owner-uri it waits', !threads(api).length && await page.evaluate(() => document.querySelector('verbb-comments').controller === null));
        await page.evaluate(() => { document.querySelector('verbb-comments').ownerId = 109; });
        await page.waitForSelector('verbb-comments[data-comments-phase="ready"]');
        check('web components: an owner set later starts it, with a label set before the element was defined', threads(api).length === 1 && (await page.locator('verbb-comments form[data-comments-form="create"] .comments__submit-label').textContent()) === 'Send it');

        const box = page.locator('verbb-comments form[data-comments-form="create"] textarea');
        await box.click();
        await box.type('Rich');
        await page.waitForSelector('verbb-comments verbb-editor [contenteditable]', { timeout: 5000 }).catch(() => undefined);
        const editor = { registered: await page.evaluate(() => window.__editorRegistered), mounted: await page.locator('verbb-comments form[data-comments-editor-mounted] verbb-editor').count(), fetched: fetched.includes('/editor.js') };
        check('web components: Editor comes from the app’s own bundle by default, not the data’s URL', editor.registered === 1 && editor.mounted === 1 && !editor.fetched, JSON.stringify(editor));
        await t.close();
    }

    // Two elements for threads by URI, with IDs of their own, and axe over both.
    {
        // Two owners whose URIs make the same slug, the second with comments of its own.
        const others = [comment('x1'), comment('x2')];
        const t = await open({
            files,
            wait: false,
            server: { override: { thread: (query) => (query.get('ownerUri') === 'Blog/A' ? { body: { apiVersion: 1, status: 'ok', threadVersion: 'v1', conversation: { uid: 'x', ownerId: 556, siteId: 1, status: 'open', closedNotice: null, commentCount: 2, rootCount: 2, sort: 'newest', sorts: ['newest'], interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: false, reportReasons: [] }, editor: { authoring: 'plain', maxLength: null, richEditor: null }, comments: others, page: { next: null, limit: 20 }, labels: LABELS, locale: 'en' } } : null) } },
            html: wcPage({ body: `<verbb-comments owner-uri="blog/a" ${WC} load-mode="eager"></verbb-comments><verbb-comments owner-uri="Blog/A" ${WC} load-mode="eager"></verbb-comments>` }),
        });
        const { page } = t;
        await page.waitForFunction(() => document.querySelectorAll('verbb-comments[data-comments-phase="ready"] .comments__list').length === 2);
        const ids = await page.evaluate(() => [...document.querySelectorAll('verbb-comments')].map((element) => element.id));
        await page.addScriptTag({ content: axe });
        const violations = await page.evaluate(async () => (await window.axe.run(document, { resultTypes: ['violations'] })).violations.map((violation) => `${violation.id}: ${violation.nodes.length}`));
        check('web components: two elements by URI get IDs of their own', ids[0] === 'comments-blog-a' && ids[1] === 'comments-blog-a-2', ids.join(', '));
        check('web components: axe finds nothing on a page with two elements', !violations.length, violations.join(' | '));
        await t.close();
    }

    // Comments.mount() leaves the element's thread to it, and its owner set as a property before the element was defined.
    for (const pluginFirst of [false, true]) {
        const t = await open({ files, wait: false, html: wcPage({ plugin: true, pluginFirst, body: `<verbb-comments id="wc" ${WC} load-mode="eager"></verbb-comments>`, before: '<script>document.querySelector("verbb-comments").ownerUri = "blog/other";</script>' }) });
        const { page, api } = t;
        await page.waitForFunction(() => document.querySelector('verbb-comments')?.dataset.commentsPhase === 'ready' && window.Comments);
        await page.evaluate(() => { window.__wcFirst = document.querySelector('verbb-comments').controller; return window.Comments.mount(); });
        await page.waitForTimeout(600);
        const result = await page.evaluate(() => ({ same: document.querySelector('verbb-comments').controller === window.__wcFirst, plugin: Boolean(window.Comments.get(document.querySelector('verbb-comments'))), comments: document.querySelectorAll('verbb-comments .comments__list > li').length }));
        const votes = api.requests.filter((request) => request.action === 'vote').length;
        await page.locator('verbb-comments [data-comments-action="vote"]').first().click();
        await page.waitForTimeout(500);
        check(`web components: Comments.mount() (${pluginFirst ? 'the plugin’s script first' : 'the element first'}) leaves the element’s thread to it, whose owner was set before it was defined`, result.same && !result.plugin && result.comments === 3 && threads(api).length === 1 && threads(api)[0].url.searchParams.get('ownerUri') === 'blog/other' && api.requests.filter((request) => request.action === 'vote').length === votes + 1, JSON.stringify(result));
        await t.close();
    }

    // An adapter's mount point: marked, the plugin's script never starts it; unmarked and already
    // drawn by the plugin's script, /renderer stops that thread and draws its own with its options.
    {
        const section = (id, extra) => `<section id="${id}" class="comments" data-comments-root data-comments-api-version="1" data-comments-endpoint="${ORIGIN}/actions/comments/api" data-comments-owner-id="109" data-comments-site-id="1" data-comments-load-mode="eager"${extra}></section>`;
        const t = await open({ files, wait: false, html: wcPage({ plugin: true, pluginFirst: true, body: `${section('marked', ' data-comments-adapter')}${section('unmarked', '')}` }) });
        const { page } = t;
        await page.waitForFunction(() => window.Comments && document.querySelector('#unmarked')?.dataset.commentsPhase === 'ready');
        await page.evaluate(() => window.Comments.mount());
        await page.waitForTimeout(400);
        const marked = await page.evaluate(() => ({ js: document.querySelector('#marked').hasAttribute('data-comments-js'), phase: document.querySelector('#marked').dataset.commentsPhase ?? null }));
        check('adapters: the plugin’s script never starts a mount point marked data-comments-adapter, even through Comments.mount()', !marked.js && marked.phase === null, JSON.stringify(marked));
        const adopted = await page.evaluate(async () => {
            const { mountThread } = await import('/wc/renderer.js');
            const element = document.querySelector('#unmarked');
            const pluginHad = Boolean(window.Comments.get(element));
            const mine = mountThread(element, { loadMode: 'eager', labels: { reply: 'Answer' } });
            await new Promise((resolve) => setTimeout(resolve, 800));
            return { pluginHad, pluginHas: Boolean(window.Comments.get(element)), mine: mine.root === element, reply: element.querySelector('[data-comments-action="reply"]')?.textContent.trim(), lists: element.querySelectorAll('.comments__list').length };
        });
        check('adapters: /renderer stops a thread the plugin’s script drew and draws its own, with its own options', adopted.pluginHad && !adopted.pluginHas && adopted.mine && adopted.reply === 'Answer' && adopted.lists === 1, JSON.stringify(adopted));
        await t.close();
    }

    // Beside the plugin's own script, whichever starts first: each thread is drawn once, by its own copy.
    for (const pluginFirst of [false, true]) {
        const order = pluginFirst ? 'the plugin’s script first' : 'the element first';
        const t = await open({
            files,
            wait: false,
            html: wcPage({ plugin: true, pluginFirst, body: `<section id="comments-109-1" class="comments" data-comments-root data-comments-api-version="1" data-comments-endpoint="${ORIGIN}/actions/comments/api" data-comments-owner-id="109" data-comments-site-id="1" data-comments-load-mode="eager"></section><verbb-comments id="wc" owner-uri="blog/other" ${WC} load-mode="eager"></verbb-comments>` }),
        });
        const { page, api } = t;
        await page.waitForFunction(() => document.querySelectorAll('[data-comments-phase="ready"]').length === 2);
        await page.evaluate(() => document.dispatchEvent(new Event('turbo:load')));
        await page.waitForTimeout(600);
        const result = await page.evaluate(() => ({ guard: Boolean(globalThis[Symbol.for('verbb.comments')]), loaderHasWc: Boolean(window.Comments.get(document.querySelector('verbb-comments'))), loaderHasSection: Boolean(window.Comments.get(document.querySelector('#comments-109-1'))) }));
        check(`web components: beside the plugin’s script (${order}), each thread is drawn once, by its own copy`, threads(api).length === 2 && threads(api).filter((request) => request.url.searchParams.get('ownerUri') === 'blog/other').length === 1 && (await events(page, 'comments:mounted')).length === 1 && (await events(page, 'comments:mounted', 'comments-109-1')).length === 1 && result.guard && !result.loaderHasWc && result.loaderHasSection, JSON.stringify(result));
        // The renderer warns when it mounts while the plugin's script runs; drawn first, nothing is running yet.
        if (pluginFirst) check('web components: the console says the page runs two copies of the renderer', t.warnings.some((warning) => warning.includes('Load Bundled JavaScript')));
        await t.close();
    }
}

/** Gate 9's review of SD-46 and stage M's renderer half: failures, races and the edges it found. */
async function reviewFixes() {
    const F = { uid: 'u', instructions: null, required: false, multiple: false, options: [], attributes: {}, public: true, placement: 'details' };
    const PHOTOS = { ...F, handle: 'photos', label: 'Photos', type: 'file', multiple: true, accept: ['.jpg', '.png'], maxFiles: 5, maxSize: 1024 * 1024 };
    const file = (name, mimeType = 'image/jpeg', size = 2048) => ({ name, mimeType, buffer: Buffer.alloc(size, 1) });
    const create = `${root} form[data-comments-form="create"]`;
    const phase = (page) => page.locator(root).getAttribute('data-comments-phase');
    const failing = (part, times = 1) => {
        let left = times;
        return (path) => path.includes(part) && left-- > 0;
    };

    // I2: the renderer's chunk failing in each load mode draws what went wrong, and Try again works.
    for (const load of ['button', 'eager', 'visible']) {
        const t = await open({ page: { load }, wait: false, failChunk: failing('/threads-') });
        if (load === 'button') await t.page.getByRole('button', { name: 'Show comments (8)' }).click();
        if (load === 'visible') await t.page.locator(root).scrollIntoViewIfNeeded();
        await t.page.waitForSelector(`${root}[data-comments-phase="error"]`, { timeout: 5000 }).catch(() => undefined);
        const shown = (await phase(t.page)) === 'error' && (await t.page.locator(`${root} .comments__loading`).textContent()) === 'Something went wrong. Please try again.' && !t.errors.some((error) => /Unhandled|Uncaught/i.test(error));
        await t.page.getByRole('button', { name: 'Try again' }).click();
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`, { timeout: 5000 }).catch(() => undefined);
        check(`chunks (${load}): a renderer that can’t load says so, with Try again, which loads the thread`, shown && (await phase(t.page)) === 'ready', `${await phase(t.page)}; ${t.errors.join(' | ').slice(0, 200)}`);
        await t.close();
    }

    // I2: the forms chunk failing: said in place of the form, not fetched again on every draw, and
    // tried again on intent or Try again.
    {
        let broken = true;
        const t = await open({ failChunk: (path) => broken && path.includes('/form-controller-') });
        await t.page.waitForSelector(`${root} [data-comments-form-placeholder] .comments__form-error`, { timeout: 5000 }).catch(() => undefined);
        const fetches = () => t.fetched.filter((path) => path.includes('form-controller')).length;
        const before = fetches();
        await actions(t.page, 'vote', 'c1').click();
        await t.page.waitForTimeout(300);
        check('chunks (forms): a form that can’t load says so with Try again, and isn’t fetched again by every draw', before === 1 && fetches() === 1 && (await t.page.locator(`${root} [data-comments-form-placeholder] .comments__form-error`).textContent()) === 'The comment form couldn’t load. Please try again.');
        await actions(t.page, 'reply', 'c1').click();
        // Firefox can take a few seconds to give up on an aborted module under load.
        await t.page.waitForFunction(() => document.querySelector('[data-comments-root] .comments__toast')?.textContent.includes('couldn’t load'), null, { timeout: 10000 }).catch(() => undefined);
        check('chunks (forms): Reply tries again on intent, and says why when it still can’t', fetches() >= 2 && (await t.page.locator(`${root} .comments__toast`).textContent()).includes('The comment form couldn’t load.'), `${fetches()} fetches; ${await t.page.locator(`${root} .comments__toast`).textContent()}; ${await live(t.page)}; ${await t.page.locator(`${root} form[data-comments-form="reply"]`).count()}; ${t.errors.join(' | ').slice(0, 300)}`);
        broken = false;
        await t.page.locator(`${root} [data-comments-action="retry-forms"]`).click();
        await t.page.waitForSelector(`${create} textarea[data-comments-body]`, { state: 'attached', timeout: 5000 }).catch(() => undefined);
        check('chunks (forms): Try again loads the form', await t.page.locator(`${create} textarea[data-comments-body]`).count() === 1);
        await t.close();
    }

    // I1: the tray's chunk failing on an edit keeps the comment's files, and says so with Try again.
    {
        const t = await open({ failChunk: (path) => path.includes('/uploads-'), server: {
            permissions: (uid) => (uid === 'c1' ? { canEdit: true, isAuthor: true } : {}),
            fields: { comment: [PHOTOS], reply: [] },
            editEditorFields: [PHOTOS],
            editFields: { photos: [{ id: 41, url: `${ORIGIN}/uploads/old.jpg`, filename: 'old.jpg', kind: 'image', width: 10, height: 10 }] },
        } });
        await t.page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c1"]`).click();
        await t.page.locator(`${root} [data-comments-action="edit"][data-comments-comment-uid="c1"]`).click();
        const edit = `${root} form[data-comments-form="edit"]`;
        await t.page.waitForSelector(`${edit} [data-comments-upload-action="reload"]`, { timeout: 5000 }).catch(() => undefined);
        check('chunks (tray): a tray that can’t load says so, with Try again', await t.page.locator(`${edit} [data-comments-upload-action="reload"]`).count() === 1);
        await t.page.getByRole('button', { name: 'Save comment' }).click();
        await t.page.waitForTimeout(300);
        check('chunks (tray): saving the edit meanwhile keeps the comment’s files', JSON.stringify(t.api.requests.find((request) => request.action === 'update')?.body.fields) === JSON.stringify({ photos: [41] }), JSON.stringify(t.api.requests.find((request) => request.action === 'update')?.body.fields));
        await t.close();

        const u = await open({ failChunk: failing('/uploads-'), server: { fields: { comment: [PHOTOS], reply: [] } } });
        await u.page.waitForSelector(`${create} [data-comments-upload-action="reload"]`, { state: 'attached', timeout: 5000 });
        await u.page.locator(`${create} textarea[data-comments-body]`).fill('Opening up.');
        await u.page.locator(`${create} [data-comments-upload-action="reload"]`).click();
        await u.page.waitForSelector(`${create} input[data-comments-upload-input="photos"]`, { state: 'attached', timeout: 5000 }).catch(() => undefined);
        // The failed field's Try again carries `comments__upload-add` too, so the tray is its file
        // input, with Try again gone, and what was written kept.
        check('chunks (tray): Try again loads the tray, keeping what’s written', await u.page.locator(`${create} input[data-comments-upload-input="photos"]`).count() === 1 && await u.page.locator(`${create} [data-comments-upload-action="reload"]`).count() === 0 && (await u.page.locator(`${create} textarea[data-comments-body]`).inputValue()) === 'Opening up.', `${u.fetched.filter((path) => path.includes('/uploads-')).length} fetches; ${u.errors.join(' | ').slice(0, 200)}`);
        await u.close();
    }

    // I4: a thread stopped or taken away while the renderer loads isn't mounted when it arrives.
    for (const [name, stop] of [
        ['Comments.destroy()', () => window.Comments.destroy()],
        ['removed from the page', () => { window.__root = document.querySelector('[data-comments-root]'); window.__root.remove(); }],
        ['Turbo’s before-cache', () => document.dispatchEvent(new Event('turbo:before-cache'))],
    ]) {
        // Firefox and WebKit hold the load event for the import, so this doesn't wait for it.
        const t = await open({ page: { load: 'eager' }, wait: false, waitUntil: 'commit', chunkDelay: (path) => (path.includes('/threads-') ? 2500 : 0) });
        // Stopped once the loader has asked for the renderer, and before it arrives.
        for (let i = 0; i < 100 && !t.fetched.some((path) => path.includes('/threads-')); i++) await t.page.waitForTimeout(20);
        await t.page.evaluate(stop);
        await t.page.waitForTimeout(3000);
        const mounted = await t.page.evaluate(() => (window.__root ?? document.querySelector('[data-comments-root]')).dataset.commentsJs);
        check(`loading: a thread stopped by ${name} while the renderer loads stays stopped`, mounted === undefined && !t.api.requests.some((request) => request.action === 'thread'), `mounted ${mounted}, ${t.api.requests.map((request) => request.action).join()}, ${t.errors.join(' | ').slice(0, 200)}`);
        await t.close();
    }

    // I5: a thread another copy of the renderer mounted (as /renderer does) is left to it by the loader.
    {
        const t = await open();
        const name = [...chunks.keys()].find((chunk) => chunk.startsWith('threads-'));
        const result = await t.page.evaluate(async (chunk) => {
            const other = await import(`/chunks/${chunk}?another-copy`);
            const host = document.createElement('section');
            document.querySelector('main').append(host);
            other.mountThread(host, { ownerId: 109, siteId: 1, endpoint: 'https://comments.test/actions/comments/api', loadMode: 'eager' });
            await new Promise((resolve) => setTimeout(resolve, 600));
            let mounted = 0;
            document.addEventListener('comments:mounted', () => mounted++);
            document.dispatchEvent(new Event('turbo:load'));
            await new Promise((resolve) => setTimeout(resolve, 600));
            return { mounted, marked: host.dataset.commentsJs, ours: Boolean(window.Comments.get(host)) };
        }, name);
        check('two copies: the loader leaves a thread another copy of the renderer mounted, by its data-comments-js mark', result.mounted === 0 && result.marked === '1' && !result.ours && t.api.requests.filter((request) => request.action === 'thread').length === 2, JSON.stringify(result));
        await t.close();
    }

    // I3: several guest files at once, with a preparation's minimum time, all upload, one at a time.
    {
        const guest = { ...MEMBER, isGuest: true, name: null, canComment: true, canVote: false, loginUrl: '/login', follow: { available: false, following: false, needsEmail: true } };
        let n = 0;
        const prep = () => {
            const now = Math.floor(Date.now() / 1000);
            n++;
            return { version: 1, manifest: { version: 1, instanceId: `pi-${n}`, preparation: `prep-${n}`, issuedAt: now, notBefore: now + 1, expiresAt: now + 600, honeypot: 'a1b2c3', javascript: true, challenges: [] }, expiresAt: now + 600 };
        };
        const t = await open({ server: { viewer: guest, guest: { show: true }, fields: { comment: [PHOTOS], reply: [] }, override: { protection: () => ({ body: { apiVersion: 1, status: 'ok', protection: prep(), csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } } }) } } });
        const { page, api } = t;
        await page.getByText('Comment as a guest').click();
        await page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        await page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('a.jpg'), file('b.jpg'), file('c.jpg')]);
        await page.waitForFunction(() => !document.querySelector('.comments__upload--uploading'), null, { timeout: 30000 }).catch(() => undefined);
        const states = await page.locator(`${create} .comments__upload`).evaluateAll((items) => items.map((item) => item.className.split(' ').pop()));
        const used = api.requests.filter((request) => request.action === 'upload').map((request) => JSON.parse(request.body.protection ?? '{}').preparation);
        check('guest: three files chosen at once all upload, each with its own preparation', states.length === 3 && states.every((state) => state === 'comments__upload--done') && new Set(used).size === 3 && used.every(Boolean), `${states.join()} / ${used.join()}`);
        check('guest: the three uploads are all announced', (await live(page)).split('uploaded.').length - 1 >= 1);
        await t.close();
    }

    // I6: focus after Try again, and after the last file a field takes.
    {
        let attempts = 0;
        const t = await open({ server: { fields: { comment: [PHOTOS], reply: [] }, delay: { upload: 200 }, override: { upload: () => (++attempts === 1 ? { status: 500, body: { apiVersion: 1, status: 'error', errors: [{ category: 'server', code: 'server_error', message: 'Nope.', fieldErrors: {}, recoverable: true, retryAfter: null }] } } : null) } } });
        await t.page.locator(`${create} textarea[data-comments-body]`).fill('Photos.');
        await t.page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        await t.page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('run.jpg')]);
        await t.page.waitForSelector(`${create} .comments__upload--failed`);
        await t.page.locator(`${create} .comments__upload-retry`).focus();
        await t.page.keyboard.press('Enter');
        await t.page.waitForTimeout(50);
        check('tray: after Try again, the keyboard is on the file’s Remove', await t.page.evaluate(() => document.activeElement?.matches('.comments__upload-remove')));
        await t.close();

        const one = await open({ server: { fields: { comment: [{ ...PHOTOS, multiple: false, maxFiles: 1 }], reply: [] } } });
        await one.page.locator(`${create} textarea[data-comments-body]`).fill('One photo.');
        await one.page.locator(`${create} .comments__upload-add`).focus();
        await one.page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('one.jpg')]);
        await one.page.waitForTimeout(100);
        check('tray: when the last file a field takes hides Add files, the keyboard is on its Remove', await one.page.evaluate(() => document.activeElement?.matches('.comments__upload-remove')));
        await one.close();
    }

    // Minor 1 and 2: a choice group's error, and a required custom field with nothing to draw it.
    {
        const error = (fieldErrors) => ({ status: 422, body: { apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Fix the errors.', fieldErrors, recoverable: false, retryAfter: null }] } });
        const t = await open({ server: {
            fields: { comment: [{ ...F, handle: 'size', label: 'Size', type: 'radio', options: [{ label: 'S', value: 's' }, { label: 'L', value: 'l' }], required: true }, { ...F, handle: 'special', label: 'Special', type: 'custom', required: true }], reply: [] },
            override: { create: (_q, _b, requests) => (requests.filter((request) => request.action === 'create').length === 1 ? error({ 'fields.size': ['Size cannot be blank.'] }) : error({ 'fields.special': ['Special cannot be blank.'] })) },
        } });
        await t.page.locator(`${create} textarea[data-comments-body]`).fill('x');
        await t.page.locator(`${create} .comments__submit`).click({ force: true });
        await t.page.waitForTimeout(300);
        check('errors: a choice group’s error marks and focuses its first choice', await t.page.evaluate(() => document.activeElement?.matches('input[type="radio"][aria-invalid="true"]') && document.activeElement.getAttribute('aria-describedby')?.endsWith('fields-size-error')));
        await t.page.locator(`${create} .comments__submit`).click({ force: true });
        await t.page.waitForTimeout(300);
        check('errors: a required custom field with no override shows its error with the form’s, and the console says why', (await t.page.locator(`${create} .comments__form-error`).textContent()) === 'Fix the errors.' && t.warnings.some((warning) => warning.includes('"special" field needs an input override')));
        await t.close();
    }

    // Field overrides from render()'s config, as custom element names, on the form and on a comment.
    {
        const t = await open({
            wait: false,
            page: {
                config: { sort: 'newest', limit: 20, fields: { rating: { input: 'acme-stars-input', display: 'acme-stars' } } },
                before: '<script type="module" nonce="">customElements.define("acme-stars", class extends HTMLElement { connectedCallback() { this.textContent = "★".repeat(Number(this.value)); } }); customElements.define("acme-stars-input", class extends HTMLElement { connectedCallback() { const b = document.createElement("button"); b.type = "button"; b.textContent = "Five"; b.onclick = () => this.setValue(5); this.append(b); } });</script>',
            },
            server: {
                fields: { comment: [{ ...F, handle: 'rating', label: 'Rating', type: 'number' }], reply: [] },
                roots: [comment('c1', { fields: [{ handle: 'rating', label: 'Rating', type: 'number', placement: 'details', value: 3, text: '3' }] })],
            },
        });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        await t.page.locator(`${create} textarea[data-comments-body]`).fill('Five stars.');
        await t.page.waitForSelector(`${create} acme-stars-input button`);
        await t.page.locator(`${create} acme-stars-input button`).click();
        await t.page.locator(`${create} .comments__submit`).click({ force: true });
        await t.page.waitForTimeout(300);
        check('overrides from render(): custom elements draw a field on the form and on a comment', (await t.page.locator(`${root} acme-stars`).textContent()) === '★★★' && JSON.stringify(t.api.requests.find((request) => request.action === 'create')?.body.fields) === JSON.stringify({ rating: 5 }));
        await t.close();
    }

    // I7: an input override on an Assets field gets the files and their IDs, and can upload.
    {
        const t = await open({
            page: { autoMount: false },
            wait: false,
            server: {
                permissions: (uid) => (uid === 'c1' ? { canEdit: true, isAuthor: true } : {}),
                fields: { comment: [PHOTOS], reply: [] },
                editEditorFields: [PHOTOS],
                editFields: { photos: [{ id: 41, url: `${ORIGIN}/uploads/old.jpg`, filename: 'old.jpg', kind: 'image' }] },
            },
        });
        await t.page.waitForFunction(() => window.Comments);
        await t.page.evaluate(async () => {
            window.Comments.configure({ fields: { photos: { input: (context) => {
                window.__photos = context;
                return context.html`<p class="my-files">${context.files.map((item) => item.filename).join(', ')} (${JSON.stringify(context.value)})</p>`;
            } } } });
            await window.Comments.mount();
        });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        await t.page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="c1"]`).click();
        await t.page.locator(`${root} [data-comments-action="edit"][data-comments-comment-uid="c1"]`).click();
        await t.page.waitForSelector(`${root} form[data-comments-form="edit"] .my-files`);
        const shown = await t.page.locator(`${root} form[data-comments-form="edit"] .my-files`).textContent();
        await t.page.evaluate(async () => {
            const uploaded = await window.__photos.upload(new File(['x'], 'new.jpg', { type: 'image/jpeg' }));
            window.__photos.setValue([...window.__photos.value, uploaded.id]);
        });
        await t.page.getByRole('button', { name: 'Save comment' }).click();
        await t.page.waitForTimeout(300);
        check('overrides: an Assets field’s override gets the files and their IDs, uploads, and saves IDs', shown === 'old.jpg ([41])' && JSON.stringify(t.api.requests.find((request) => request.action === 'update')?.body.fields) === JSON.stringify({ photos: [41, 501] }), `${shown} / ${JSON.stringify(t.api.requests.find((request) => request.action === 'update')?.body.fields)}`);
        await t.close();
    }

    // Minor 3 and 4: Reply while the forms arrive, typing into the stand-in, and focus after Show comments.
    {
        const t = await open({ chunkDelay: (path) => (path.includes('/form-controller-') ? 800 : 0) });
        await t.page.locator(`${root} [data-comments-form-placeholder] textarea`).click();
        await t.page.keyboard.type('Hello there');
        await actions(t.page, 'reply', 'c2').evaluate(() => undefined);
        await t.page.waitForSelector(`${create} textarea[data-comments-body]`, { timeout: 5000 });
        await t.page.waitForTimeout(100);
        check('forms: what’s typed before the form arrives carries over, with the keyboard', (await t.page.locator(`${create} textarea[data-comments-body]`).inputValue()) === 'Hello there' && await t.page.evaluate(() => document.activeElement?.matches('textarea[data-comments-body]')));
        await t.close();

        const u = await open({ chunkDelay: (path) => (path.includes('/form-controller-') ? 800 : 0) });
        await actions(u.page, 'reply', 'c1').click();
        const busy = await actions(u.page, 'reply', 'c1').getAttribute('aria-busy');
        await u.page.waitForSelector(`${root} form[data-comments-form="reply"]`, { timeout: 5000 });
        check('forms: Reply shows it’s busy while the forms arrive', busy === 'true');
        await u.close();

        const v = await open({ page: { load: 'button' }, wait: false, server: { delay: { thread: 400 } } });
        await v.page.getByRole('button', { name: 'Show comments (8)' }).focus();
        await v.page.keyboard.press('Enter');
        await v.page.waitForTimeout(150);
        check('Show comments: while the thread loads, the keyboard is on the thread’s heading', await v.page.evaluate(() => document.activeElement?.matches('.comments__heading')));
        await v.close();
    }

    // Minor 6 and 7: Try again within the field's limit, and failed files stopping a post.
    {
        let attempts = 0;
        const t = await open({ server: { fields: { comment: [{ ...PHOTOS, maxFiles: 1 }], reply: [] }, override: { upload: () => (++attempts === 1 ? { status: 500, body: { apiVersion: 1, status: 'error', errors: [{ category: 'server', code: 'server_error', message: 'Nope.', fieldErrors: {}, recoverable: true, retryAfter: null }] } } : null) } } });
        const { page, api } = t;
        await page.locator(`${create} textarea[data-comments-body]`).fill('A photo.');
        await page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        await page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('a.jpg')]);
        await page.waitForSelector(`${create} .comments__upload--failed`);
        await page.locator(`${create} .comments__submit`).click({ force: true });
        await page.waitForTimeout(200);
        check('tray: a file that didn’t upload stops the post until it’s removed or tried again', !api.requests.some((request) => request.action === 'create') && (await page.locator(`${create} .comments__field--files .comments__field-error`).textContent()).includes('didn’t upload'));
        await page.locator(`${create} input[data-comments-upload-input="photos"]`).setInputFiles([file('b.jpg')]);
        await page.waitForSelector(`${create} .comments__upload--done`);
        await page.locator(`${create} .comments__upload-retry`).click();
        await page.waitForTimeout(150);
        check('tray: Try again doesn’t take a field past its limit', (await page.locator(`${create} .comments__field--files .comments__field-error`).textContent()) === 'You can add up to 1 files here.' && await page.locator(`${create} .comments__upload--failed`).count() === 1);
        await t.close();
    }

    // Minor 13: a path that leaves the site isn't linked; and the tray reads the data's translated words.
    {
        const t = await open({
            labels: { addFiles: 'Dateien hinzufügen', uploadLimits: 'Bis zu {count} Dateien, je {size}.' },
            server: {
                labels: { addFiles: 'Dateien hinzufügen', uploadLimits: 'Bis zu {count} Dateien, je {size}.' },
                fields: { comment: [PHOTOS], reply: [] },
                roots: [comment('c1', { fields: [{ handle: 'files', label: 'Files', type: 'file', placement: 'details', text: 'a.pdf', value: [{ url: '/\t/evil.test/a.pdf', filename: 'a.pdf', kind: 'pdf' }, { url: '/uploads/b.pdf', filename: 'b.pdf', kind: 'pdf' }] }] })],
            },
        });
        const hrefs = await t.page.locator(`${root} .comments__field-file[href]`).evaluateAll((links) => links.map((link) => link.href));
        check('displays: a path that leaves the site isn’t linked, and one on it is', JSON.stringify(hrefs) === JSON.stringify([`${ORIGIN}/uploads/b.pdf`]), JSON.stringify(hrefs));
        await t.page.waitForSelector(`${create} .comments__upload-add`, { state: 'attached' });
        check('tray: the data’s translated words reach the tray', (await t.page.locator(`${create} .comments__upload-add`).textContent()) === 'Dateien hinzufügen' && (await t.page.locator(`${create} .comments__upload-limit`).textContent()) === 'Bis zu 5 Dateien, je 1 MB.');
        await t.close();
    }
}

/** A long thread (SD-46): drawn in batches, with links, focus, Load more, menus and announcements working throughout. */
async function longThreads() {
    const many = () => Array.from({ length: 125 }, (_, index) => comment(`l${index}`, { replies: { count: 3, items: [1, 2, 3].map((n) => comment(`l${index}-${n}`, { parentUid: `l${index}`, depth: 1 })), next: null } }));
    const count = (page) => page.locator(`${root} [data-comments-comment]`).count();
    const watchFirstDraw = (page) => page.evaluate(() => document.addEventListener('comments:before-render', (event) => {
        if (event.detail.reason === 'thread' && window.__firstDraw === undefined) queueMicrotask(() => { window.__firstDraw = document.querySelectorAll('[data-comments-root] [data-comments-comment]').length; });
    }));

    {
        const t = await open({ server: { roots: many(), delay: { thread: 200 } }, wait: false });
        await watchFirstDraw(t.page);
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        const menusBefore = await t.page.locator(`${root} .comments__menu-items`).count();
        // Said while batches are still drawing, and still there once they've finished.
        const pending = await t.page.evaluate(() => {
            window.Comments.get(document.querySelector('[data-comments-root]')).announce('Checking the live region.');
            return document.querySelectorAll('[data-comments-root] [data-comments-comment]').length;
        });
        await t.page.waitForFunction(() => document.querySelectorAll('[data-comments-root] [data-comments-comment]').length === 500, null, { timeout: 15000 });
        await t.page.waitForTimeout(100);
        check('long thread: the first draw holds 45 comments', await t.page.evaluate(() => window.__firstDraw) === 45);
        check('long thread: the rest draw in batches until every comment is on the page, for find in page', await count(t.page) === 500);
        check('long thread: an announcement made during the batches is read out', pending < 500 && (await live(t.page)) === 'Checking the live region.', `${pending} drawn when it was made`);
        check('long thread: no ··· menu builds its items before it opens', menusBefore === 0 && await t.page.locator(`${root} .comments__menu-items`).count() === 0);
        await t.page.locator(`${root} .comments__menu-toggle[data-comments-comment-uid="l120-2"]`).click();
        // The details element's toggle event comes in a task after the click.
        await t.page.waitForSelector(`${root} .comments__menu[open] .comments__menu-item`, { timeout: 2000 }).catch(() => undefined);
        check('long thread: a menu builds its items when it opens', await t.page.locator(`${root} .comments__menu-items`).count() === 1 && await t.page.locator(`${root} .comments__menu[open] .comments__menu-item`).count() > 0);
        await t.close();
    }
    {
        const t = await open({ server: { roots: many(), delay: { thread: 200 } }, wait: false });
        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        const drawn = await count(t.page);
        await t.page.getByRole('button', { name: 'Load more comments' }).click();
        await t.page.waitForFunction(() => document.activeElement?.closest('[data-comments-comment]')?.dataset.commentsCommentUid === 'c4', null, { timeout: 5000 }).catch(() => undefined);
        check('long thread: Load more during the batches draws and focuses the first new comment', (await t.page.evaluate(() => document.activeElement?.closest('[data-comments-comment]')?.dataset.commentsCommentUid)) === 'c4', `${drawn} drawn when it was pressed`);
        await t.close();
    }
    {
        const roots = many();
        const number = roots[118].replies.items[2].permalink.split('#comment-')[1];
        const t = await open({ server: { roots }, hash: `#comment-${number}` });
        await t.page.waitForFunction(() => document.activeElement?.closest('[data-comments-comment]')?.dataset.commentsCommentUid === 'l118-3', null, { timeout: 5000 }).catch(() => undefined);
        check('long thread: a link to a comment late in the thread draws, outlines and focuses it at once', await t.page.locator(`${root} .comments__comment--highlight[data-comments-comment-uid="l118-3"]`).count() === 1 && (await t.page.evaluate(() => document.activeElement?.closest('[data-comments-comment]')?.dataset.commentsCommentUid)) === 'l118-3');
        await t.close();
    }
}

async function performance() {
    if (engine !== 'chromium') return;

    check('Editor is never in the comments script or its chunks', !/prosemirror|tiptap/i.test(allScript));

    // Layout shift: a reader scrolling down meets a thread that loaded just before it came into
    // view, and a thread at the top of a page loads into its reserved space.
    for (const [name, load, scroll, formsDelay] of [['scrolling down to it', 'visible', true, 0], ['in view as the page loads', 'eager', false, 0], ['in view, with a member’s comment form arriving after the thread', 'eager', false, 600]]) {
        const t = await open({ page: { load }, wait: false, server: { delay: { thread: 200 } }, formsDelay });
        await t.page.evaluate(() => {
            window.__shift = 0;
            new PerformanceObserver((list) => list.getEntries().forEach((entry) => {
                if (entry.hadRecentInput) return;
                window.__shift += entry.value;
                window.__sources = [...(window.__sources ?? []), ...entry.sources.map((source) => `${source.node?.nodeName}.${source.node?.className ?? ''} ${Math.round(source.previousRect.y)}→${Math.round(source.currentRect.y)}`)];
            })).observe({ type: 'layout-shift', buffered: true });
        });

        if (scroll) {
            // Scrolled in steps, as a reader does, until the thread is in view.
            for (let step = 0; step < 40 && !(await t.page.locator(`${root}[data-comments-phase="ready"]`).count()); step++) {
                await t.page.mouse.wheel(0, 120);
                await t.page.waitForTimeout(60);
            }
        }

        await t.page.waitForSelector(`${root}[data-comments-phase="ready"]`);
        await t.page.waitForTimeout(400);
        await t.page.waitForSelector(`${root} form[data-comments-form="create"]`);
        await t.page.locator(root).scrollIntoViewIfNeeded();
        await t.page.waitForTimeout(300);
        const shift = await t.page.evaluate(() => window.__shift);
        const sources = await t.page.evaluate(() => (window.__sources ?? []).slice(0, 4).join(', '));
        check(`gate 2: layout shift under 0.02, ${name}`, shift < 0.02, `${shift.toFixed(4)}${sources ? `: ${sources}` : ''}`);
        await t.close();
    }

    // Responsiveness with the CPU slowed four times: a like and opening a reply paint within 100 ms.
    {
        const t = await open({ server: { delay: { vote: 1000 } } });
        const cdp = await t.context.newCDPSession(t.page);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        const time = async (action) => t.page.evaluate(async (selector) => {
            const target = document.querySelector(selector);
            const start = performance.now();
            target.click();
            await new Promise((resolve) => requestAnimationFrame(() => setTimeout(resolve, 0)));
            return performance.now() - start;
        }, action);
        const like = await time(`${root} [data-comments-action="vote"][data-comments-comment-uid="c1"]`);
        const reply = await time(`${root} [data-comments-action="reply"][data-comments-comment-uid="c2"]`);
        check('gate 2: a like responds in under 100 ms at 4× CPU slowdown', like < 100, `${like.toFixed(1)} ms`);
        check('gate 2: Reply opens its form in under 100 ms at 4× CPU slowdown', reply < 100, `${reply.toFixed(1)} ms`);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 });
        await t.close();
    }

    // A long thread at 4× CPU slowdown (SD-46): its first frame, 45 comments, draws in under 100 ms, from
    // the thread's response ending to the frame that shows them (reading the data and the browser's
    // style and layout work included), and the rest draw in idle batches with no task over 50 ms.
    {
        const many = Array.from({ length: 50 }, (_, index) => comment(`m${index}`, { replies: { count: 3, items: [1, 2, 3].map((n) => comment(`m${index}-${n}`, { parentUid: `m${index}`, depth: 1 })), next: null } }));
        const t = await open({ server: { roots: many, delay: { thread: 300 } }, page: { load: 'button' }, wait: false });
        const cdp = await t.context.newCDPSession(t.page);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        await t.page.evaluate(() => {
            window.__tasks = [];
            new PerformanceObserver((list) => list.getEntries().forEach((entry) => window.__tasks.push({ start: entry.startTime, duration: entry.duration }))).observe({ type: 'longtask' });
            document.addEventListener('comments:before-render', (event) => {
                if (event.detail.reason !== 'thread' || window.__drawn) return;
                const start = performance.now();
                if (window.__profile) console.profile('draw');
                queueMicrotask(() => { window.__script = performance.now() - start; });
                requestAnimationFrame(() => setTimeout(() => {
                    if (window.__profile) console.profileEnd('draw');
                    // From the thread's data arriving (its response ending), which includes reading it.
                    const response = performance.getEntriesByType('resource').find((entry) => entry.name.includes('/api/thread'));
                    window.__drawn = performance.now() - (response?.responseEnd || start);
                    window.__frame = performance.now();
                    window.__first = document.querySelectorAll('[data-comments-root] [data-comments-comment]').length;
                    window.__elements = document.querySelectorAll('[data-comments-root] *').length;
                    window.__kinds = [...document.querySelectorAll('[data-comments-root] [data-comments-comment]')][1].querySelectorAll('*').length;
                    window.__sample = [...document.querySelectorAll('[data-comments-root] [data-comments-comment]')][1].outerHTML;
                }, 0));
            });
        });

        let profiled = null;

        if (process.env.PROFILE) {
            await cdp.send('Profiler.enable');
            await cdp.send('Profiler.setSamplingInterval', { interval: 100 });
            cdp.on('Profiler.consoleProfileFinished', ({ profile }) => { profiled = profile; });
            await t.page.evaluate(() => { window.__profile = true; });
        }

        const traced = [];
        if (process.env.TRACE) {
            cdp.on('Tracing.dataCollected', ({ value }) => traced.push(...value));
            await cdp.send('Tracing.start', { categories: 'toplevel,devtools.timeline,blink.user_timing', transferMode: 'ReportEvents' });
        }

        await t.page.getByRole('button', { name: 'Show comments (8)' }).click();
        await t.page.waitForFunction(() => window.__drawn !== undefined);

        if (process.env.TRACE) {
            const done = new Promise((resolve) => cdp.once('Tracing.tracingComplete', resolve));
            await cdp.send('Tracing.end');
            await done;
            const names = new Map();
            traced.filter((event) => event.ph === 'X' && event.dur > 5000).forEach((event) => names.set(event.name, (names.get(event.name) ?? 0) + 1));
            console.log('task names', [...names].slice(0, 30).join(' | '));
            const t0 = Math.min(...traced.filter((event) => event.ph === 'X' && event.dur > 5000).map((event) => event.ts));
            traced.filter((event) => event.ph === 'X' && event.dur > 3000 && event.name !== 'RunTask').sort((a, b) => a.ts - b.ts).forEach((event) => console.log(`ev ${((event.ts - t0) / 1000).toFixed(1)} ${event.name} ${(event.dur / 1000).toFixed(1)} ${JSON.stringify(event.args?.data ?? {}).slice(0, 200)}`));
            const tasks = traced.filter((event) => event.ph === 'X' && event.dur > 2000 && event.name === 'RunTask');
            const inside = (task) => traced.filter((event) => event.ph === 'X' && event.tid === task.tid && event.ts >= task.ts && event.ts + (event.dur ?? 0) <= task.ts + task.dur && event !== task);
            for (const task of tasks.slice(-8)) {
                const sums = new Map();
                inside(task).forEach((event) => sums.set(event.name, (sums.get(event.name) ?? 0) + event.dur));
                console.log(`task ${(task.dur / 1000).toFixed(1)} ms: ${[...sums].sort((a, b) => b[1] - a[1]).slice(0, 8).map(([name, dur]) => `${name} ${(dur / 1000).toFixed(1)}`).join(', ')}`);
            }
        }

        await t.page.waitForFunction(() => document.querySelectorAll('[data-comments-root] [data-comments-comment]').length === 200, null, { timeout: 15000 });
        const finished = await t.page.evaluate(() => performance.now());
        await t.page.waitForTimeout(300);

        if (profiled) {
            const profile = profiled;
            const self = new Map();
            const counts = new Map();
            profile.samples.forEach((sample) => counts.set(sample, (counts.get(sample) ?? 0) + 1));
            for (const node of profile.nodes) {
                const key = `${node.callFrame.functionName || '(anonymous)'} ${node.callFrame.url.split('/').pop()}:${node.callFrame.lineNumber}:${node.callFrame.columnNumber}`;
                self.set(key, (self.get(key) ?? 0) + (counts.get(node.id) ?? 0));
            }
            console.log([...self].sort((a, b) => b[1] - a[1]).slice(0, 25).map(([key, n]) => `${(n / profile.samples.length * 100).toFixed(1)}% ${key}`).join('\n'));
            // Inclusive time: each sample counts once for every distinct function on its stack.
            const parent = new Map();
            profile.nodes.forEach((node) => (node.children ?? []).forEach((child) => parent.set(child, node.id)));
            const byId = new Map(profile.nodes.map((node) => [node.id, node]));
            const inclusive = new Map();
            for (const [id, n] of counts) {
                const seen = new Set();
                for (let current = id; current !== undefined; current = parent.get(current)) {
                    const node = byId.get(current);
                    seen.add(`${node.callFrame.functionName || '(anonymous)'} ${node.callFrame.url.split('/').pop()}:${node.callFrame.lineNumber}:${node.callFrame.columnNumber}`);
                }
                seen.forEach((key) => inclusive.set(key, (inclusive.get(key) ?? 0) + n));
            }
            console.log('INCLUSIVE\n' + [...inclusive].sort((a, b) => b[1] - a[1]).slice(0, 45).map(([key, n]) => `${(n / profile.samples.length * 100).toFixed(1)}% ${key}`).join('\n'));
        }

        const { time, scripted, first, frame, tasks, elements, kinds } = await t.page.evaluate(() => ({ time: window.__drawn, scripted: window.__script, first: window.__first, frame: window.__frame, tasks: window.__tasks, elements: window.__elements, kinds: window.__kinds }));
        if (process.env.TRACE) console.log('elements', elements, 'per comment', kinds, await t.page.evaluate(() => window.__sample));
        // A long task that ends after the first frame, other than the one that drew it.
        const later = tasks.filter((task) => task.start + task.duration > frame && task.start >= frame - 1);
        const longest = later.reduce((max, task) => Math.max(max, task.duration), 0);
        check('gate 2: a long thread’s first frame (45 comments) draws in under 100 ms at 4× CPU slowdown', first === 45 && time < 100, `${first} comments, ${time.toFixed(0)} ms from the response to the frame, ${scripted.toFixed(0)} ms of it the renderer's script`);
        check('gate 2: the rest of a 200-comment thread draws with no task over 50 ms at 4× CPU slowdown', longest <= 50, `${later.length ? `longest ${longest.toFixed(0)} ms` : 'no long tasks'}; all 200 drawn ${(finished - frame).toFixed(0)} ms after the first frame`);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 });
        await t.close();
    }

    // Gate 2 after the first load: re-sorting a 200-comment thread, after Load more, with a component
    // host on each comment, and a morph that takes the drawn thread, each draw their first batch in one
    // frame under 100 ms and the rest in idle batches with no task over 50 ms, at 4× CPU slowdown.
    for (const variant of ['re-sort', 'Load more then a re-sort', 're-sort with a component host per comment', 'morph']) {
        const many = Array.from({ length: 50 }, (_, index) => comment(`r${index}`, { replies: { count: 3, items: [1, 2, 3].map((n) => comment(`r${index}-${n}`, { parentUid: `r${index}`, depth: 1 })), next: null } }));
        const hosts = variant.includes('host');
        const t = await open({ server: { roots: many }, page: { autoMount: !hosts }, wait: !hosts });
        const { page } = t;

        if (hosts) {
            await page.waitForFunction(() => window.Comments);
            await page.evaluate(() => window.Comments.mountThread(document.querySelector('[data-comments-root]'), { loadMode: 'eager', parts: { authorName: { component: 'A' } }, onHosts: (list) => list.forEach((host) => { if (!host.element.firstChild) host.element.textContent = host.context.comment.author.name; }) }));
        }

        await page.waitForFunction(() => document.querySelectorAll('[data-comments-root] [data-comments-comment]').length === 200, null, { timeout: 15000 });

        if (variant.startsWith('Load more')) {
            await page.locator(`${root} [data-comments-action="load-more"]`).click();
            await page.waitForFunction(() => document.querySelectorAll('[data-comments-root] [data-comments-comment]').length === 202, null, { timeout: 15000 });
        }

        await page.waitForTimeout(400);
        many.reverse();
        const total = await page.evaluate(() => document.querySelectorAll('[data-comments-root] [data-comments-comment]').length);
        const cdp = await t.context.newCDPSession(page);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        await page.evaluate((morph) => {
            window.__tasks = [];
            new PerformanceObserver((list) => list.getEntries().forEach((entry) => window.__tasks.push({ start: entry.startTime, duration: entry.duration }))).observe({ type: 'longtask' });
            window.__start = performance.now();
            const element = document.querySelector('[data-comments-root]');
            if (morph) element.replaceChildren(Object.assign(document.createElement('h2'), { textContent: '8 comments' }));
            else window.Comments.get(element).refresh({ sort: 'oldest' });
        }, variant === 'morph');
        const expected = variant === 'morph' ? total : 200;
        // Drawn again in full: re-sorted, the thread's last comment comes first; morphed, its first does.
        const lead = variant === 'morph' ? 'r0' : 'r49';
        const done = await page.waitForFunction(([n, uid]) => document.querySelectorAll('[data-comments-root] [data-comments-comment]').length === n && document.querySelector('[data-comments-root] [data-comments-comment]')?.dataset.commentsCommentUid === uid, [expected, lead], { timeout: 15000 }).then(() => true, () => false);
        await page.waitForTimeout(1500);
        const tasks = await page.evaluate(() => window.__tasks.filter((task) => task.start >= window.__start).map((task) => Math.round(task.duration)));
        const [first = 0, ...rest] = tasks;
        const restLongest = Math.max(0, ...rest);
        check(`gate 2: ${variant} of a 200-comment thread draws its first batch under 100 ms and the rest with no task over 50 ms at 4× CPU slowdown`, done && first < 100 && restLongest <= 50, `${done ? 'drawn again in full' : 'not drawn again'}, long tasks ${JSON.stringify(tasks)}`);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 });
        await t.close();
    }

    // Memory: 50 mounts and destroys leave the heap flat and no detached nodes.
    {
        const t = await open();
        const cdp = await t.context.newCDPSession(t.page);
        const heap = async () => {
            await cdp.send('HeapProfiler.collectGarbage');
            await cdp.send('HeapProfiler.collectGarbage');
            return (await cdp.send('Runtime.getHeapUsage')).usedSize;
        };
        const cycle = () => t.page.evaluate(async () => {
            const section = document.querySelector('[data-comments-root]');
            window.Comments.destroy();
            window.Comments.mount();
            await new Promise((resolve) => {
                const wait = () => (section.dataset.commentsPhase === 'ready' ? resolve() : setTimeout(wait, 10));
                wait();
            });
        });

        for (let i = 0; i < 5; i++) await cycle();
        const before = await heap();
        for (let i = 0; i < 50; i++) await cycle();
        const after = await heap();
        const detached = await t.page.evaluate(() => document.querySelectorAll('[data-comments-root]').length);
        check('gate 2: 50 mount and destroy cycles grow the heap by under 1 MB', after - before < 1024 * 1024, `${((after - before) / 1024).toFixed(0)} KB`);
        check('gate 2: one thread on the page after the cycles', detached === 1);
        await t.close();
    }
}

for (const name of engines) {
    engine = name;
    const executablePath = name === 'firefox' ? process.env.PW_FIREFOX : name === 'webkit' ? process.env.PW_WEBKIT : undefined;
    browser = await playwright[name].launch(executablePath ? { executablePath } : {});

    try {
        // COMMENTS_SECTIONS runs only some sections, such as `performance,startup`.
        const sections = { loadModes, startup, interactions, ownComments, moderators, themes, richAuthoring, editingAndPosting, pagesAndMounting, overridesAndSafety, presentation, accessibility, customFields, hosts, webComponents, reviewFixes, longThreads, performance };
        const only = process.env.COMMENTS_SECTIONS?.split(',');

        for (const [section, run] of Object.entries(sections)) {
            if (!only || only.includes(section)) await run();
        }
    } catch (error) {
        check('the run finished', false, error.stack);
    }

    await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length} of ${results.length} checks passed${failed.length ? `; failed: ${failed.map((result) => `[${result.engine}] ${result.name}`).join('; ')}` : ''}.`);
process.exit(failed.length ? 1 : 0);
