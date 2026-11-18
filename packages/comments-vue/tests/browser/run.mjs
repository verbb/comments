// The Vue package's browser checks: an app rendered to HTML in Node, hydrated in a real browser
// against a stubbed data API, with component overrides, a field input, a new post by key, and the
// plugin's own script on the same page. Run `npm run build:packages` and `npm run build:assets`
// first, then `node packages/comments-vue/tests/browser/run.mjs`. COMMENTS_ENGINES,
// PLAYWRIGHT_CORE, PW_FIREFOX and PW_WEBKIT work as in the browser package's checks.
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'vite';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { CommentsPlugin } from '@verbb/comments-vue';
import { App } from './app.js';
import { bundle, ENDPOINT, loadPlaywright, openPage, runEngines } from './support.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
// Vue's bundler build asks the app's build for these, as its own Vite plugin would set them.
const client = await bundle(build, path.join(here, 'client.js'), {
    define: { 'process.env.NODE_ENV': JSON.stringify('development'), __VUE_OPTIONS_API__: 'true', __VUE_PROD_DEVTOOLS__: 'false', __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'true' },
});
const html = await renderToString(createSSRApp(App).use(CommentsPlugin, { endpoint: ENDPOINT }).provide('tone', 'warm'));

const ready = (page, count = 1) => page.waitForFunction((n) => document.querySelectorAll('[data-comments-root][data-comments-phase="ready"]').length === n, count, { timeout: 10000 });
const checks = (page) => page.evaluate(() => globalThis.__checks);

async function hydration(browser, check) {
    const t = await openPage(browser, { bundle: client, html, settings: { hydrate: true } });

    await ready(t.page);
    await t.page.waitForFunction(() => document.querySelectorAll('.custom-author').length === 4);

    check('hydration: the server rendered only the mount point', !html.includes('comment-c1') && html.includes('data-comments-root') && html.includes('data-comments-auto-mount="false"'));
    check('hydration: no mismatch, warning or error', !t.errors.length && !t.warnings.some((message) => /hydration|mismatch/i.test(message)) && !(await checks(t.page)).some(([kind]) => kind === 'vue-warn'), [...t.errors, ...(await checks(t.page)).filter(([kind]) => kind === 'vue-warn').map(([, message]) => message)].join(' | '));
    check('hydration: the thread is drawn by the renderer, once', t.api.of('thread').length === 1 && await t.page.locator('article[id^="comment-"]').count() === 4);
    check('hydration: the ID the server gave is kept', await t.page.locator('section[data-comments-root]').getAttribute('id') === /<section id="([^"]+)"/.exec(html)[1]);
    check('overrides: a component part reads the app’s provide', await t.page.locator('.custom-author[data-tone="warm"]').count() === 4);
    check('overrides: each sits in its host, inside its comment', await t.page.locator('article[id^="comment-"] verbb-comments-host > .custom-author').count() === 4);
    check('overrides: only declared props reach the component', await t.page.locator('.custom-author').first().evaluate((element) => element.getAttributeNames().sort().join()) === 'class,data-tone,data-uid');

    const before = await t.page.locator('.custom-author[data-uid="c1"]').elementHandle();
    await t.page.locator('[data-comments-action="vote"]').first().click();
    await t.page.waitForFunction(() => document.querySelector('.custom-author[data-uid="c1"]')?.textContent?.includes('(3)'));
    check('overrides: a redraw keeps the teleport and passes new props', await before.evaluate((element) => element.isConnected && element.textContent.includes('(3)')));
    check('overrides: the component wasn’t mounted again', (await checks(t.page)).filter(([kind, uid]) => kind === 'author-mounted' && uid === 'c1').length === 1);

    await t.page.evaluate(() => globalThis.__controller.store.delete('c3'));
    await t.page.waitForFunction(() => !document.querySelector('.custom-author[data-uid="c3"]'));
    check('overrides: a removed comment’s teleport is unmounted', (await checks(t.page)).some(([kind, uid]) => kind === 'author-unmounted' && uid === 'c3'));

    // The field input component's value is posted with the comment. Writing opens the collapsed form.
    await t.page.locator('textarea[name="content"]').first().fill('Four stars');
    await t.page.locator('form .stars [data-star="4"]').click();
    await t.page.waitForSelector('form .stars [data-star="4"][aria-pressed="true"]');
    await t.page.locator('form:has(textarea[name="content"]) .comments__submit').first().click();
    await t.page.waitForFunction(() => globalThis.__checks.some(([kind]) => kind === 'created'));
    const created = t.api.of('create')[0]?.body;
    check('fields: an input component’s value is posted', created?.fields?.rating === 4 && created.content.text === 'Four stars', JSON.stringify(created?.fields));

    await t.page.locator('#next-post').click();
    await t.page.waitForFunction(() => document.querySelector('[data-comments-root][data-comments-phase="ready"]') && globalThis.__checks.filter(([kind]) => kind === 'mounted').length === 2);
    await ready(t.page);
    check('remount: a new owner starts a new thread', t.api.of('thread').map((request) => request.url.searchParams.get('ownerUri')).join() === 'blog/first,blog/second');
    check('remount: the old thread stopped, once', (await checks(t.page)).filter(([kind]) => kind === 'destroyed').length === 1 && await t.page.locator('section[data-comments-root]').count() === 1);

    await t.page.evaluate(() => globalThis.__root.unmount());
    check('unmount: the thread, its teleports and its mount point go', await t.page.locator('section, .custom-author, verbb-comments-host').count() === 0);
    check('no errors or Vue warnings throughout', !t.errors.length && !(await checks(t.page)).some(([kind]) => kind === 'vue-warn'), t.errors.join(' | '));
    await t.close();
}

async function besidePluginScript(browser, check) {
    const t = await openPage(browser, { bundle: client, html, settings: { hydrate: true }, withPluginScript: true });

    await ready(t.page);
    await t.page.waitForTimeout(300);
    check('plugin script: the thread is started once, by the component', t.api.of('thread').length === 1 && await t.page.locator('article[id^="comment-"]').count() === 4);
    check('plugin script: its loader is on the page', await t.page.evaluate(() => typeof globalThis.Comments?.mount === 'function'));
    check('plugin script: the guard says to run one copy', t.warnings.some((message) => message.includes('Load Bundled JavaScript')));
    check('plugin script: no errors', !t.errors.length, t.errors.join(' | '));
    await t.close();
}

async function besidePageMount(browser, check) {
    for (const [label, early] of [['after the adapter mounted', false], ['before its renderer arrived', true]]) {
        const t = await openPage(browser, { bundle: client, html, settings: { hydrate: true, mountEarly: early }, withPluginScript: true });

        await ready(t.page);
        if (!early) await t.page.evaluate(() => globalThis.Comments.mount());
        await t.page.waitForTimeout(400);
        check(`Comments.mount() ${label}: the thread is still the adapter’s, drawn once`, t.api.of('thread').length === 1 && await t.page.locator('article[id^="comment-"]').count() === 4 && await t.page.locator('.custom-author').count() === 4);
        check(`Comments.mount() ${label}: the thread started once`, (await checks(t.page)).filter(([kind]) => kind === 'mounted').length === 1 && !(await checks(t.page)).some(([kind]) => kind === 'destroyed'));

        await t.page.locator('[data-comments-action="vote"]').first().click();
        await t.page.waitForTimeout(400);
        check(`Comments.mount() ${label}: one press sends one vote`, t.api.of('vote').length === 1);

        await t.page.evaluate(() => globalThis.__root.unmount());
        await t.page.waitForTimeout(200);
        check(`Comments.mount() ${label}: no errors, through unmounting`, !t.errors.length, t.errors.join(' | '));
        await t.close();
    }
}

async function focusAfterRefusal(browser, check) {
    const t = await openPage(browser, { bundle: client, html, settings: { hydrate: true }, api: { refuseCreate: { 'fields.rating': ['Rating must be at least 3.'] } } });

    await ready(t.page);
    await t.page.locator('textarea[name="content"]').first().fill('Hi');
    await t.page.waitForSelector('form .stars');
    await t.page.locator('form:has(textarea[name="content"]) .comments__submit').first().click();
    await t.page.waitForSelector('form .stars[aria-invalid="true"]');
    await t.page.waitForFunction(() => document.activeElement?.closest('.stars'), null, { timeout: 3000 }).catch(() => undefined);
    const focus = await t.page.evaluate(() => ({ inside: Boolean(document.activeElement?.closest('.stars')), tag: document.activeElement?.outerHTML.slice(0, 80) }));
    check('fields: a refused post moves focus into the component input', focus.inside, focus.tag);
    check('fields: the component shows the server’s error', (await t.page.locator('form:has(.stars)').textContent()).includes('Rating must be at least 3.'));
    await t.close();
}

await runEngines(loadPlaywright(), [hydration, besidePluginScript, besidePageMount, focusAfterRefusal]);
