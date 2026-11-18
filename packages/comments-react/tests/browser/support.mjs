// The browser checks' plumbing: a stubbed Comments data API answered through Playwright's routing,
// as the browser package's own checks do, the plugin's built script for the checks that run beside
// it, and a page that serves an app's bundle. Shared in shape with the Vue package's checks.
import { readdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const here = path.dirname(fileURLToPath(import.meta.url));
const plugin = path.resolve(here, '../../../..');
const asset = path.join(plugin, 'src/web/assets/frontend/dist');

export const ORIGIN = 'https://comments.test';
export const ENDPOINT = `${ORIGIN}/actions/comments/api`;
const LABELS = JSON.parse(readFileSync(path.join(here, '../support/labels.json'), 'utf8'));

export function loadPlaywright() {
    for (const candidate of [process.env.PLAYWRIGHT_CORE, 'playwright-core', path.join(process.env.HOME ?? '', 'node_modules/playwright-core')].filter(Boolean)) {
        try {
            return require(candidate);
        } catch {
            // Try the next copy.
        }
    }

    console.error('These checks need playwright-core and its browsers: npm install --no-save playwright-core && npx playwright-core install chromium');
    process.exit(1);
}

/** The plugin's own script and its chunks, served as Craft would serve them. */
export const pluginScript = {
    entry: readFileSync(path.join(asset, 'js/comments.js'), 'utf8'),
    chunks: new Map(readdirSync(path.join(asset, 'js/chunks')).map((file) => [file, readFileSync(path.join(asset, 'js/chunks', file), 'utf8')])),
    css: readFileSync(path.join(asset, 'css/comments.css'), 'utf8'),
};

let id = 1400;

function comment(uid, overrides = {}) {
    return {
        uid, parentUid: null, depth: 0, state: 'approved',
        content: { format: 'plain', html: null, text: `Comment ${uid}`, gifUrl: null, gifWidth: null, gifHeight: null },
        author: { name: `Author ${uid}`, initials: 'AU', avatarUrl: null, avatarTone: 3, isGuest: false, badges: [], score: null },
        createdAt: '2026-11-09T21:04:00+00:00', editedAt: null, display: { timeAgo: '2 hours ago', fullDate: '9 November 2026, 9:04 pm' },
        permalink: `${ORIGIN}/post#comment-${++id}`, fields: [], interaction: { upvotes: 0, downvotes: 0 }, folded: null, custom: {},
        replies: { count: 0, items: [], next: null }, ...overrides,
    };
}

const permissions = (overrides = {}) => ({ canReply: true, canEdit: false, canDelete: true, canVote: true, canDownvote: false, canReport: true, vote: null, reported: false, isAuthor: false, moderation: null, custom: {}, ...overrides });
const MEMBER = { isGuest: false, hasSession: true, name: 'Aroha Ngata', initials: 'AN', avatarUrl: null, avatarTone: 6, badges: [], canComment: true, canVote: true, canReport: true, isModerator: false, blocked: false, loginUrl: null, follow: { available: true, following: false, needsEmail: false } };
export const RATING = { handle: 'rating', uid: 'f-rating', label: 'Rating', instructions: null, required: false, input: 'number', multiple: false, options: [], attributes: {}, show: true, display: 'inline' };

/** A data API with one thread of three comments and a reply, answering the actions the checks use. */
export function fakeApi(options = {}) {
    let roots = [comment('c1', { interaction: { upvotes: 2, downvotes: 0 } }), comment('c2', { replies: { count: 1, items: [comment('c2-1', { parentUid: 'c2', depth: 1 })], next: null } }), comment('c3')];
    const requests = [];
    const ok = (body) => ({ apiVersion: 1, status: 'ok', ...body });
    const all = () => roots.flatMap((root) => [root, ...root.replies.items]);
    const answers = {
        thread: () => ok({
            threadVersion: 'v1', editor: { authoring: 'plain', maxLength: null, richEditor: null }, comments: roots, page: { next: null, limit: 20 }, labels: LABELS, locale: 'en',
            conversation: { uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: all().length, rootCount: roots.length, sort: 'newest', sorts: ['newest', 'oldest', 'top'], interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: true, reportReasons: [{ value: 'spam', label: 'Spam', description: null }] },
        }),
        viewer: () => ok({
            threadVersion: 'v1', viewer: MEMBER, comments: Object.fromEntries(all().map((item) => [item.uid, permissions()])), extraComments: [], moderation: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' }, custom: {},
            editor: { draftKey: 'comments-109-1-7', guest: { show: false, required: true, name: null, email: null, offerReplyEmails: false }, fields: { comment: [RATING], reply: [] }, protection: null, richEditor: null },
        }),
        session: () => ok({ viewer: MEMBER, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } }),
        protection: () => ok({ protection: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } }),
        create: (body) => ok({ replayed: false, comment: { ...comment(`new-${requests.length}`, { content: { format: 'plain', html: null, text: body.content.text, gifUrl: null, gifWidth: null, gifHeight: null } }), viewer: permissions({ isAuthor: true }) }, moderationState: 'approved', conversation: { commentCount: all().length + 1 }, announcement: 'Comment posted.' }),
        vote: (body) => ok({ replayed: false, comment: { uid: body.uid, interaction: { upvotes: body.vote === 'upvote' ? 3 : 2, downvotes: 0 }, viewer: permissions({ vote: body.vote }) }, announcement: null }),
        delete: (body) => {
            roots = roots.filter((root) => root.uid !== body.uid);
            return ok({ replayed: false, uid: body.uid, conversation: { commentCount: all().length }, announcement: 'Comment deleted.' });
        },
    };

    return {
        requests,
        of: (action) => requests.filter((request) => request.action === action),
        handle(route) {
            const request = route.request();
            const url = new URL(request.url());
            const action = url.pathname.split('/').pop();
            const body = request.postData() ? JSON.parse(request.postData()) : null;
            requests.push({ action, url, body });

            // A refused post, with the server's errors by field.
            if (action === 'create' && options.refuseCreate) {
                return route.fulfill({ status: 422, contentType: 'application/json', headers: { 'X-Comments-Api-Version': '1' }, body: JSON.stringify({ apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Check the highlighted fields.', fieldErrors: options.refuseCreate, recoverable: false, retryAfter: null }] }) });
            }

            return answers[action]
                ? route.fulfill({ contentType: 'application/json', headers: { 'X-Comments-Api-Version': '1' }, body: JSON.stringify(answers[action](body)) })
                : route.fulfill({ status: 404, body: '' });
        },
    };
}

/**
 * Opens a page with the app's bundle at `/app/client.js`, an optional server render inside
 * `#root`, and optionally the plugin's own script, and records console errors and warnings.
 */
export async function openPage(browser, { bundle, html = '', settings = {}, withPluginScript = false, api: apiOptions = {} }) {
    const context = await browser.newContext({ viewport: { width: 1000, height: 900 } });
    const page = await context.newPage();
    const api = fakeApi(apiOptions);
    const errors = [];
    const warnings = [];

    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') errors.push(message.text());
        if (message.type() === 'warning') warnings.push(message.text());
    });

    await page.route(`${ORIGIN}/**`, (route) => {
        const url = new URL(route.request().url());

        if (url.pathname.startsWith('/actions/comments/api/')) return api.handle(route);
        if (url.pathname.startsWith('/app/') && bundle.has(url.pathname.slice(5))) return route.fulfill({ contentType: 'text/javascript', body: bundle.get(url.pathname.slice(5)) });
        if (url.pathname === '/plugin/js/comments.js') return route.fulfill({ contentType: 'text/javascript', body: pluginScript.entry });
        if (url.pathname.startsWith('/plugin/js/chunks/') && pluginScript.chunks.has(url.pathname.slice(18))) return route.fulfill({ contentType: 'text/javascript', body: pluginScript.chunks.get(url.pathname.slice(18)) });
        if (url.pathname === '/comments.css') return route.fulfill({ contentType: 'text/css', body: pluginScript.css });

        return route.fulfill({
            contentType: 'text/html',
            body: `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Post</title><link rel="stylesheet" href="/comments.css"></head><body>
                <main><h1>Post</h1><div id="root">${html}</div></main>
                <script>window.__settings = ${JSON.stringify({ endpoint: ENDPOINT, ...settings })};</script>
                ${withPluginScript ? '<script type="module" src="/plugin/js/comments.js"></script>' : ''}
                <script type="module" src="/app/client.js"></script>
            </body></html>`,
        });
    });

    await page.goto(`${ORIGIN}/post`);

    return { page, api, errors, warnings, close: () => context.close() };
}

/** Bundles an app for the browser with Vite, keeping every chunk in memory by its file name. */
export async function bundle(build, entry, options = {}) {
    const output = await build({
        configFile: false,
        logLevel: 'silent',
        root: path.dirname(entry),
        define: { 'process.env.NODE_ENV': JSON.stringify('development') },
        ...options,
        build: {
            write: false,
            minify: false,
            lib: { entry, formats: ['es'], fileName: () => 'client.js' },
            rolldownOptions: { output: { chunkFileNames: 'chunks/[name]-[hash].js' } },
        },
    });

    return new Map([output].flat().flatMap((result) => result.output).filter((chunk) => chunk.type === 'chunk').map((chunk) => [chunk.fileName, chunk.code]));
}

/** Runs each engine in COMMENTS_ENGINES, as the browser package's checks do, and reports. */
export async function runEngines(playwright, sections) {
    const results = [];
    const engines = (process.env.COMMENTS_ENGINES ?? 'chromium').split(',');

    for (const engine of engines) {
        const executablePath = engine === 'firefox' ? process.env.PW_FIREFOX : engine === 'webkit' ? process.env.PW_WEBKIT : undefined;
        const browser = await playwright[engine].launch(executablePath ? { executablePath } : {});
        const check = (name, ok, detail = '') => {
            results.push({ engine, name, ok: Boolean(ok) });
            console.log(`${ok ? 'PASS' : 'FAIL'}  [${engine}] ${name}${detail ? `  (${detail})` : ''}`);
        };

        try {
            for (const section of sections) await section(browser, check);
        } catch (error) {
            check('the run finished', false, error.stack);
        }

        await browser.close();
    }

    const failed = results.filter((result) => !result.ok);
    console.log(`\n${results.length - failed.length} of ${results.length} checks passed${failed.length ? `; failed: ${failed.map((result) => `[${result.engine}] ${result.name}`).join('; ')}` : ''}.`);
    process.exit(failed.length ? 1 : 0);
}
