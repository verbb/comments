import { destroyThreads } from '@verbb/comments-browser/renderer';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { COMMENTS_THREAD_TAG, CommentsThreadElement, registerCommentsWebComponents } from '../src/index';
// The labels the data API sends, as the browser package's checks use them.
import LABELS from '../../comments-browser/tests/browser/labels.json';

/**
 * `<verbb-comments>`'s lifecycle in happy-dom, against a stubbed data API: what starts a thread,
 * what changes it in place, what starts it again, and what stops it.
 */

const ENDPOINT = 'https://comments.test/actions/comments/api';
const requests: URL[] = [];

function respond(body: unknown): Response {
    return new Response(JSON.stringify({ apiVersion: 1, status: 'ok', ...body as object }), { headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } });
}

const comment = (uid: string) => ({
    uid, parentUid: null, depth: 0, state: 'approved', content: { format: 'plain', html: null, text: `Comment ${uid}`, gifUrl: null, gifWidth: null, gifHeight: null },
    author: { name: 'Alex Morgan', initials: 'AM', avatarUrl: null, avatarTone: 3, isGuest: false, badges: [], score: null }, createdAt: '2026-11-09T21:04:00+00:00', editedAt: null,
    display: { timeAgo: '2 hours ago', fullDate: '9 November 2026' }, permalink: `https://comments.test/post#comment-${uid}`, fields: [], interaction: { upvotes: 0, downvotes: 0 }, folded: null, custom: {},
    replies: { count: 0, items: [], next: null },
});

const stub = (async (input: RequestInfo | URL) => {
    const url = new URL(String(input instanceof Request ? input.url : input));
    const action = url.pathname.split('/').pop();
    requests.push(url);

    if (action === 'thread') {
        return respond({
            threadVersion: 'v1',
            conversation: { uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: 2, rootCount: 2, sort: url.searchParams.get('sort') ?? 'newest', sorts: ['newest', 'oldest', 'top'], interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: false, reportReasons: [] },
            editor: { authoring: 'plain', maxLength: null, richEditor: null },
            comments: [comment('c1'), comment('c2')],
            page: { next: null, limit: 20 },
            labels: LABELS,
            locale: 'en',
        });
    }

    // A guest without a session, as the slim viewer read answers.
    return respond({
        threadVersion: 'v1',
        viewer: { isGuest: true, hasSession: false, name: null, initials: '', avatarUrl: null, avatarTone: 0, badges: [], canComment: false, canVote: false, canReport: false, isModerator: false, blocked: false, loginUrl: '/login', follow: { available: false, following: false, needsEmail: true } },
        editor: { draftKey: 'comments-109-1-guest', guest: { show: false, required: true, name: null, email: null, offerReplyEmails: false }, fields: { comment: [], reply: [] }, protection: null, richEditor: null },
        comments: {}, extraComments: [], moderation: null, csrf: null, custom: {},
    });
}) as typeof fetch;

const threads = () => requests.filter((url) => url.pathname.endsWith('/thread'));

async function settle(until: () => boolean): Promise<void> {
    for (let i = 0; i < 100; i++) {
        await new Promise((resolve) => setTimeout(resolve, 10));
        if (until()) return;
    }

    throw new Error('Timed out waiting for the thread.');
}

function element(attributes: Record<string, string> = { 'owner-id': '109' }): CommentsThreadElement {
    const node = document.createElement(COMMENTS_THREAD_TAG);
    Object.entries({ endpoint: ENDPOINT, 'site-id': '1', 'load-mode': 'eager', ...attributes }).forEach(([name, value]) => node.setAttribute(name, value));
    return node;
}

const drawn = (node: CommentsThreadElement) => node.dataset.commentsPhase === 'ready' && node.querySelectorAll('[data-comments-comment]').length === 2;

beforeAll(() => {
    registerCommentsWebComponents();
});

beforeEach(() => {
    requests.length = 0;
    vi.stubGlobal('fetch', stub);
});

afterEach(() => {
    destroyThreads(document);
    document.body.replaceChildren();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('registerCommentsWebComponents()', () => {
    it('defines <verbb-comments> once, however often it’s called', () => {
        registerCommentsWebComponents();
        registerCommentsWebComponents();

        expect(customElements.get('verbb-comments')).toBe(CommentsThreadElement);
        expect(document.createElement('verbb-comments')).toBeInstanceOf(CommentsThreadElement);
    });

    it('says so when another copy of the package defined the tag first', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        const define = vi.spyOn(customElements, 'get').mockReturnValue(class extends HTMLElement {});

        registerCommentsWebComponents();

        expect(warn).toHaveBeenCalledWith(expect.stringContaining('another copy of @verbb/comments-web-components'));
        define.mockRestore();
    });
});

describe('<verbb-comments>', () => {
    it('starts its thread from its attributes when it’s added, and draws it inside itself', async () => {
        const node = element();
        expect(node.controller).toBeNull();
        document.body.append(node);

        expect(node.controller).not.toBeNull();
        expect(node.hasAttribute('data-comments-root')).toBe(true);
        expect(node.dataset.commentsJs).toBe('1');
        await settle(() => drawn(node));

        expect(node.shadowRoot).toBeNull();
        expect(threads()).toHaveLength(1);
        expect(threads()[0]!.searchParams.get('ownerId')).toBe('109');
        expect(threads()[0]!.searchParams.get('siteId')).toBe('1');
        expect(threads()[0]!.origin + threads()[0]!.pathname).toBe(`${ENDPOINT}/thread`);
    });

    it('sends the browser events from itself', async () => {
        const node = element();
        const mounted = vi.fn();
        const loaded = vi.fn();
        node.addEventListener('comments:mounted', mounted);
        node.addEventListener('comments:roots-loaded', loaded);
        document.body.append(node);
        await settle(() => loaded.mock.calls.length > 0);

        expect(mounted).toHaveBeenCalledTimes(1);
        expect((mounted.mock.calls[0]![0] as CustomEvent).detail.controller).toBe(node.controller);
    });

    it('waits for owner-id or owner-uri, and starts once one is set', async () => {
        const node = element({});
        document.body.append(node);
        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(node.controller).toBeNull();
        expect(threads()).toHaveLength(0);

        node.ownerUri = 'blog/hello';
        await settle(() => drawn(node));
        expect(threads()).toHaveLength(1);
        expect(threads()[0]!.searchParams.get('ownerUri')).toBe('blog/hello');
    });

    it('starts again, once, when attributes that say which thread change together', async () => {
        const node = element();
        document.body.append(node);
        await settle(() => drawn(node));
        const first = node.controller;

        node.setAttribute('owner-id', '110');
        node.setAttribute('site-id', '2');
        node.setAttribute('endpoint', 'https://comments.test/other/api');
        await settle(() => node.controller !== first && drawn(node));

        expect(threads()).toHaveLength(2);
        expect(threads()[1]!.searchParams.get('ownerId')).toBe('110');
        expect(threads()[1]!.searchParams.get('siteId')).toBe('2');
        expect(threads()[1]!.pathname).toBe('/other/api/thread');
    });

    it('sorts and themes the drawn thread in place, and a waiting thread starts again sorted', async () => {
        const node = element();
        document.body.append(node);
        await settle(() => drawn(node));
        const first = node.controller!;

        node.theme = 'dark';
        expect(node.dataset.commentsTheme).toBe('dark');
        node.sort = 'top';
        await settle(() => threads().length === 2);
        expect(node.controller).toBe(first);
        expect(first.sort).toBe('top');

        const waiting = element({ 'owner-id': '111', 'load-mode': 'button' });
        document.body.append(waiting);
        const before = waiting.controller;
        waiting.sort = 'oldest';
        await settle(() => waiting.controller !== before);
        expect(waiting.controller!.sort).toBe('oldest');
    });

    it('reads load once', async () => {
        const node = element({ 'owner-id': '109', 'load-mode': 'button' });
        document.body.append(node);
        const first = node.controller;
        node.loadMode = 'eager';
        await new Promise((resolve) => setTimeout(resolve, 30));

        expect(node.controller).toBe(first);
        expect(threads()).toHaveLength(0);
    });

    it('keeps its thread when it’s moved, and stops it when it’s taken away', async () => {
        const node = element();
        const box = document.createElement('div');
        document.body.append(node, box);
        await settle(() => drawn(node));
        const first = node.controller;

        box.append(node);
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(node.controller).toBe(first);
        expect(threads()).toHaveLength(1);

        const destroyed = vi.fn();
        node.addEventListener('comments:destroyed', destroyed);
        node.remove();
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(destroyed).toHaveBeenCalledTimes(1);
        expect(node.controller).toBeNull();
        expect(node.hasAttribute('data-comments-root')).toBe(false);
        expect(node.hasAttribute('data-comments-js')).toBe(false);
        expect(node.childElementCount).toBe(0);

        document.body.append(node);
        await settle(() => drawn(node));
        expect(node.controller).not.toBe(first);
    });

    it('stops for Turbo Drive’s cache, leaving an element that starts afresh when restored', async () => {
        const node = element();
        document.body.append(node);
        await settle(() => drawn(node));

        document.dispatchEvent(new Event('turbo:before-cache'));
        expect(node.controller).toBeNull();
        expect(node.hasAttribute('data-comments-root')).toBe(false);

        // A visit that didn't go ahead leaves it on the page, and Turbo's load starts it again.
        document.dispatchEvent(new Event('turbo:load'));
        await settle(() => drawn(node));
        document.dispatchEvent(new Event('turbo:before-cache'));

        const restored = node.cloneNode(true) as CommentsThreadElement;
        node.replaceWith(restored);
        await settle(() => drawn(restored));
        expect(restored.controller).not.toBeNull();
    });

    it('lets go of a thread something else stopped', async () => {
        const node = element();
        document.body.append(node);
        await settle(() => drawn(node));

        node.controller!.destroy();
        expect(node.controller).toBeNull();
        expect(node.hasAttribute('data-comments-root')).toBe(false);

        // Started again by a change, it's a new thread, not the one that was stopped.
        node.setAttribute('owner-id', '112');
        await settle(() => drawn(node));
        expect(node.controller).not.toBeNull();
    });

    it('keeps its controller when a morph takes the drawn thread away, which is drawn again', async () => {
        const node = element();
        document.body.append(node);
        await settle(() => drawn(node));
        const first = node.controller;

        node.replaceChildren();
        await settle(() => drawn(node));
        expect(node.controller).toBe(first);
        expect(node.dataset.commentsJs).toBe('1');
        expect(threads()).toHaveLength(1);
    });

    it('redraws for parts changed in place and set again', async () => {
        const node = element();
        node.parts = {};
        document.body.append(node);
        await settle(() => drawn(node));

        node.parts!.authorName = ({ html }) => html`<b class="in-place">Someone</b>`;
        node.parts = node.parts;
        await settle(() => node.querySelectorAll('.in-place').length === 2);
    });

    it('draws { component } overrides as hosts its controller reports', async () => {
        const node = element();
        node.parts = { authorName: { component: 'Author' } };
        document.body.append(node);
        await settle(() => drawn(node) && node.controller!.getHosts().length === 2);

        expect(node.controller!.getHosts().map((host) => host.key).sort()).toEqual(['comment:c1:authorName', 'comment:c2:authorName']);
        expect(node.querySelectorAll('verbb-comments-host')).toHaveLength(2);
    });

    it('gives themeConfig, parts, fields and labels to the drawn thread, which redraws', async () => {
        const node = element();
        document.body.append(node);
        await settle(() => drawn(node));
        const setOptions = vi.spyOn(node.controller!, 'setOptions');

        node.themeConfig = { quietButton: { attributes: { class: 'btn-ghost' } } };
        node.parts = { authorName: ({ html }) => html`<b class="custom-author">Someone</b>` };
        node.labels = { reply: 'Answer' };
        node.fields = { rating: { display: 'acme-stars' } };
        await settle(() => node.querySelectorAll('.custom-author').length === 2);

        expect(setOptions).toHaveBeenCalledTimes(4);
        expect(node.themeConfig).toEqual({ quietButton: { attributes: { class: 'btn-ghost' } } });
        expect(node.fields).toEqual({ rating: { display: 'acme-stars' } });
    });

    it('takes properties set before it was defined, and starts again for a new auth', async () => {
        const name = 'verbb-comments-later';
        const node = document.createElement(name) as CommentsThreadElement;
        Object.entries({ 'owner-id': '109', endpoint: ENDPOINT, 'load-mode': 'eager' }).forEach(([attribute, value]) => node.setAttribute(attribute, value));
        (node as unknown as Record<string, unknown>).labels = { comments: 'Discussion' };
        // Properties that stand for attributes, set before the element is defined, too.
        (node as unknown as Record<string, unknown>).ownerId = 120;
        (node as unknown as Record<string, unknown>).sort = 'top';
        document.body.append(node);
        customElements.define(name, class extends CommentsThreadElement {});
        await settle(() => node.dataset.commentsPhase === 'ready');
        expect(node.labels).toEqual({ comments: 'Discussion' });
        expect(node.getAttribute('owner-id')).toBe('120');
        expect(threads().at(-1)!.searchParams.get('ownerId')).toBe('120');
        expect(threads().at(-1)!.searchParams.get('sort')).toBe('top');
        expect(node.querySelector('.comments__heading')!.textContent).toContain('Discussion');

        const first = node.controller;
        node.auth = undefined;
        await settle(() => node.controller !== first && node.controller !== null);
    });

    it('defaults to Editor from npm, and a site can give its own', async () => {
        const node = element();
        document.body.append(node);
        const loader = () => (node.controller as unknown as { loader(): unknown }).loader();
        expect(node.loadEditor).toBeUndefined();
        expect(typeof loader()).toBe('function');

        const own = vi.fn(async () => undefined);
        node.loadEditor = own;
        expect(node.loadEditor).toBe(own);
        expect(loader()).toBe(own);
    });
});
