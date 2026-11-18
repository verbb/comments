// Checks when the plugin's built comments script loads a waiting thread, drawn into the mount point
// exactly as `craft.comments.render()` writes it (`mount-point.html`, which a Pest test compares with the
// server's output), against a stubbed data API, so it needs no Craft site. Run `npm run build`, then
// `node tests/browser/load-modes.mjs`.
//
// It needs `playwright-core` and a browser, resolved from PLAYWRIGHT_CORE, this checkout or the home
// folder. COMMENTS_ENGINES picks engines (`chromium,firefox,webkit`), and PW_FIREFOX and PW_WEBKIT point
// at browser builds to launch with `executablePath`.
import { readdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const here = path.dirname(fileURLToPath(import.meta.url));
const dist = path.resolve(here, '../../src/web/assets/frontend/dist');
const script = readFileSync(path.join(dist, 'js/comments.js'), 'utf8');
const chunks = new Map(readdirSync(path.join(dist, 'js/chunks')).map((file) => [file, readFileSync(path.join(dist, 'js/chunks', file), 'utf8')]));
const css = readFileSync(path.join(dist, 'css/comments.css'), 'utf8');
const mountPoint = readFileSync(path.join(here, 'mount-point.html'), 'utf8').trim();
const labels = JSON.parse(readFileSync(path.resolve(here, '../../packages/comments-browser/tests/browser/labels.json'), 'utf8'));

let playwright;

for (const candidate of [process.env.PLAYWRIGHT_CORE, 'playwright-core', path.join(process.env.HOME ?? '', 'node_modules/playwright-core')]) {
    try {
        if (candidate) {
            playwright = require(candidate);
            break;
        }
    } catch {
        // Try the next place.
    }
}

if (!playwright) {
    console.error('These checks need playwright-core and its Chromium: npm install --no-save playwright-core && npx playwright-core install chromium');
    process.exit(1);
}

const ORIGIN = 'https://comments.test';
const engines = (process.env.COMMENTS_ENGINES ?? 'chromium').split(',');
const results = [];
let engine = '';

function check(name, ok, detail = '') {
    results.push(Boolean(ok));
    console.log(`${ok ? 'PASS' : 'FAIL'}  [${engine}] ${name}${detail ? `  (${detail})` : ''}`);
}

// Data
// -----------------------------------------------------------------------------

function comment(n) {
    return {
        uid: `c${n}`, parentUid: null, depth: 0, state: 'approved',
        content: { format: 'plain', html: null, text: `Comment ${n}`, gifUrl: null },
        author: { name: 'Alex Morgan', initials: 'AM', avatarUrl: null, avatarTone: 3, isGuest: false, badges: [] },
        createdAt: '2026-11-09T21:04:00+00:00', editedAt: null, display: { timeAgo: '2 hours ago', fullDate: '9 November 2026, 9:04 pm' },
        permalink: `${ORIGIN}/post#comment-${n}`, fields: [], interaction: { upvotes: 0, downvotes: 0 }, custom: {},
        replies: { count: 0, items: [], next: null },
    };
}

const COMMENTS = [comment(11), comment(12)];
const ok = (body) => ({ apiVersion: 1, status: 'ok', ...body });
const GUEST = { isGuest: true, hasSession: false, name: null, initials: '', avatarUrl: null, avatarTone: 0, badges: [], canComment: false, canVote: false, canReport: false, isModerator: false, blocked: false, loginUrl: null, follow: { available: false, following: false, needsEmail: false } };

const answers = {
    thread: () => ok({
        threadVersion: 'v1',
        conversation: {
            uid: 'conversation-5', ownerId: 5, siteId: 1, status: 'open', closedNotice: null, commentCount: 2, rootCount: 2, sort: 'newest', sorts: ['newest', 'oldest'],
            interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: false, reportReasons: [],
        },
        editor: { authoring: 'plain', maxLength: null, richEditor: null },
        comments: COMMENTS, page: { next: null, limit: 20 }, labels, locale: 'en',
    }),
    viewer: () => ok({
        threadVersion: 'v1', viewer: GUEST,
        editor: { draftKey: 'comments-5-1-guest', guest: { show: false, required: true, name: null, email: null, offerReplyEmails: false }, fields: { comment: [], reply: [] }, protection: null, richEditor: null },
        comments: Object.fromEntries(COMMENTS.map((item) => [item.uid, { canReply: false, canEdit: false, canDelete: false, canVote: false, canDownvote: false, canReport: false, vote: null, reported: false, isAuthor: false, moderation: null, custom: {} }])),
        extraComments: [], moderation: null, csrf: null, custom: {},
    }),
};

/** A page with a long article and the server's mount point below it, waiting in `loadMode`. */
function page(loadMode) {
    const thread = mountPoint
        .replaceAll('{ownerId}', '5')
        .replaceAll('{siteId}', '1')
        .replace('{endpoint}', `${ORIGIN}/actions/comments/api`)
        .replace('data-comments-load-mode="visible"', `data-comments-load-mode="${loadMode}"`);

    return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Load modes</title><link rel="stylesheet" href="/comments.css"></head><body>
        <nav><a id="toc" href="#chapter-2">Chapter 2</a></nav>
        <h2 id="chapter-2">Chapter 2</h2>
        <div style="height: 4000px">An article long enough that the comments start well below the fold.</div>
        ${thread}
        <script type="module" src="/comments.js"></script>
    </body></html>`;
}

const root = '[data-comments-root]';
let browser;

/** Opens the page with `loadMode`, answering the thread with each of `responses` in turn. */
async function open(loadMode, { hash = '', responses = ['ok'] } = {}) {
    const context = await browser.newContext({ viewport: { width: 1000, height: 700 } });
    const tab = await context.newPage();
    const threads = [];
    const errors = [];
    tab.on('pageerror', (error) => errors.push(error.message));

    await tab.route(`${ORIGIN}/**`, async (route) => {
        const url = new URL(route.request().url());

        if (url.pathname === '/comments.js') return route.fulfill({ contentType: 'text/javascript', body: script });
        if (url.pathname.startsWith('/chunks/') && chunks.has(url.pathname.slice(8))) return route.fulfill({ contentType: 'text/javascript', body: chunks.get(url.pathname.slice(8)) });
        if (url.pathname === '/comments.css') return route.fulfill({ contentType: 'text/css', body: css });

        if (url.pathname.startsWith('/actions/comments/api/')) {
            const action = url.pathname.split('/').pop();

            if (action === 'thread') {
                const response = responses[Math.min(threads.length, responses.length - 1)];
                threads.push(url.search);

                if (response === 'fail') {
                    return route.fulfill({ status: 500, contentType: 'application/json', headers: { 'X-Comments-Api-Version': '1' }, body: JSON.stringify({ apiVersion: 1, status: 'error', errors: [{ category: 'server', code: 'server_error', message: 'Something went wrong. Please try again.', fieldErrors: {}, recoverable: true, retryAfter: null }] }) });
                }
            }

            return answers[action]
                ? route.fulfill({ contentType: 'application/json', headers: { 'X-Comments-Api-Version': '1' }, body: JSON.stringify(answers[action]()) })
                : route.fulfill({ status: 404, body: '' });
        }

        return route.fulfill({ contentType: 'text/html', body: page(loadMode) });
    });

    await tab.goto(`${ORIGIN}/post${hash}`);
    await tab.waitForSelector(`${root}[data-comments-phase]`);
    await tab.waitForTimeout(400);

    return { context, tab, threads, errors };
}

const drawn = (tab) => tab.locator(`${root} article[id^="comment-"]`).count();
const ready = (tab) => tab.waitForSelector(`${root}[data-comments-phase="ready"]`, { timeout: 10000 });

async function run() {
    // visible: waits until the thread is near, and an in-page link elsewhere doesn't start it.
    {
        const { context, tab, threads, errors } = await open('visible');
        check('visible: the server’s heading and count show while it waits', (await tab.locator(`${root} .comments__heading`).textContent()).includes('2'));
        check('visible: nothing loads while the thread is far below', threads.length === 0);
        await tab.click('#toc');
        await tab.waitForTimeout(400);
        check('visible: a link to another part of the page doesn’t load it', threads.length === 0 && await drawn(tab) === 0, `hash ${await tab.evaluate(() => location.hash)}`);
        await tab.locator(root).scrollIntoViewIfNeeded();
        await ready(tab);
        check('visible: scrolling near loads it once', threads.length === 1 && await drawn(tab) === 2);
        check('visible: no page errors', errors.length === 0, errors.join(' | '));
        await context.close();
    }

    // button: waits for Show comments, then moves focus into what arrived.
    {
        const { context, tab, threads, errors } = await open('button');
        const button = tab.getByRole('button', { name: 'Show comments (2)' });
        await tab.click('#toc');
        await tab.waitForTimeout(400);
        check('button: nothing loads on its own or from another in-page link', threads.length === 0 && await button.count() === 1);
        await button.click();
        await ready(tab);
        await tab.waitForTimeout(100);
        check('button: pressing it loads the comments in place of the button', threads.length === 1 && await drawn(tab) === 2 && await button.count() === 0);
        check('button: focus moves to the first comment', await tab.evaluate(() => document.activeElement?.id) === 'comment-11', await tab.evaluate(() => document.activeElement?.id || document.activeElement?.tagName));
        check('button: no page errors', errors.length === 0, errors.join(' | '));
        await context.close();
    }

    // button failure: the error shows, and the button works again.
    {
        const { context, tab, threads } = await open('button', { responses: ['fail', 'ok'] });
        const button = tab.getByRole('button', { name: 'Show comments (2)' });
        await button.click();
        await tab.waitForSelector(`${root} .comments__toast[data-comments-state="error"]`);
        check('button failure: the error shows, and the button stays to try again', await button.count() === 1 && (await tab.locator(`${root} .comments__toast`).textContent()).includes('Something went wrong'));
        await button.click();
        await ready(tab);
        check('button failure: pressing it again loads the comments', threads.length === 2 && await drawn(tab) === 2);
        await context.close();
    }

    // A link to a comment loads straight away, on the page or after it's open.
    {
        const { context, tab, threads } = await open('button', { hash: '#comment-12' });
        await ready(tab);
        await tab.waitForTimeout(300);
        check('deep link: opening the page with one loads a button thread without a press', threads.length === 1 && await drawn(tab) === 2);
        check('deep link: the linked comment is highlighted', await tab.locator(`${root} #comment-12`).evaluate((element) => Boolean(element.closest('.comments__comment--highlight'))));
        await context.close();
    }

    {
        const { context, tab, threads } = await open('visible');
        await tab.evaluate(() => { location.hash = '#comment-11'; });
        await ready(tab);
        check('deep link: following one after the page has loaded starts a waiting thread', threads.length === 1);
        await context.close();
    }
}

for (const name of engines) {
    engine = name;
    const executablePath = name === 'firefox' ? process.env.PW_FIREFOX : name === 'webkit' ? process.env.PW_WEBKIT : undefined;
    browser = await playwright[name].launch(executablePath ? { executablePath } : {});

    try {
        await run();
    } finally {
        await browser.close();
    }
}

console.log(`${results.filter(Boolean).length}/${results.length} passed`);
process.exit(results.every(Boolean) ? 0 : 1);
