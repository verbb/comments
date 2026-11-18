// @vitest-environment happy-dom
import type { CommentData, GifsData } from '@verbb/comments-core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { GiphyResult } from '../src/gif-picker';
import { createStringView, renderToString, threadTemplate } from '../src/render';
import { destroyThreads, mountThread } from '../src/renderer';
import type { CommentsController } from '../src/index';
import { comment, LABELS, MEMBER, permissions, threadState } from './support/thread';

/**
 * GIF search through GIPHY: the browser asks GIPHY itself, shows its results untouched, and posts the
 * chosen GIF with the comment, against a stubbed data API and a stubbed GIPHY. GIPHY's requests and
 * pings are Editor's (`@verbb/editor/gifs`) and tested there.
 */

const ENDPOINT = 'https://comments.test/actions/comments/api';
const GIFS: GifsData = { apiKey: 'giphy-key', rating: 'pg', limit: 12, language: 'en' };

const result = (id: string): GiphyResult => ({
    id,
    title: `GIF ${id}`,
    images: {
        fixed_width: { url: `https://media1.giphy.com/media/${id}/200w.gif?cid=abc&rid=200w.gif`, webp: `https://media1.giphy.com/media/${id}/200w.webp?cid=abc&rid=200w.webp`, width: '200', height: '150' },
        original: { url: `https://media2.giphy.com/media/${id}/giphy.gif?cid=abc&rid=giphy.gif`, webp: `https://media2.giphy.com/media/${id}/giphy.webp?cid=abc&rid=giphy.webp`, width: '480', height: '360' },
    },
    analytics: {
        onload: { url: `https://giphy-analytics.giphy.com/v2/pingback_simple?action_type=SEEN&id=${id}` },
        onclick: { url: `https://giphy-analytics.giphy.com/v2/pingback_simple?action_type=CLICK&id=${id}` },
        onsent: { url: `https://giphy-analytics.giphy.com/v2/pingback_simple?action_type=SENT&id=${id}` },
    },
});

/** GIPHY, as the browser's own `fetch` reaches it. Every request is kept. */
function giphy(results: GiphyResult[] = [result('b'), result('a')]) {
    const requests: Array<{ url: URL; init: RequestInit | undefined }> = [];
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = new URL(String(input instanceof Request ? input.url : input));
        requests.push({ url, init });

        if (url.hostname === 'giphy-analytics.giphy.com') return new Response('', { status: 200 });
        if (url.pathname === '/v1/randomid') return Response.json({ data: { random_id: 'browser-1' } });

        return Response.json({ data: results, pagination: {}, meta: { status: 200 } });
    });

    return { fetcher, requests, of: (path: string) => requests.filter((request) => request.url.pathname === path), pings: (type: string) => requests.filter((request) => request.url.searchParams.get('action_type') === type) };
}

function api(options: { gifs?: GifsData | null; comments?: CommentData[]; edit?: Record<string, unknown> } = {}) {
    const requests: Array<{ action: string; body: Record<string, any> | null }> = [];
    const comments = options.comments ?? [];
    const json = (body: unknown, status = 200) => new Response(JSON.stringify({ apiVersion: 1, status: status === 200 ? 'ok' : 'error', ...body as object }), { status, headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } });
    const saved = (uid: string, body: Record<string, any>) => ({
        ...comment({ uid, content: { format: 'plain', html: null, text: body.content.text ?? '', gifUrl: body.content.gif?.url ?? null, gifWidth: body.content.gif?.width ?? null, gifHeight: body.content.gif?.height ?? null } }),
        viewer: permissions({ isAuthor: true, canEdit: true }),
    });

    const fetcher = (async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = new URL(String(input instanceof Request ? input.url : input));
        const action = url.pathname.split('/').pop()!;
        const body = typeof init?.body === 'string' ? JSON.parse(init.body) : null;
        requests.push({ action, body });

        switch (action) {
            case 'thread':
                return json({
                    threadVersion: 'v1',
                    conversation: { uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: comments.length, rootCount: comments.length, sort: 'newest', sorts: ['newest'], interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: false, reportReasons: [] },
                    editor: { authoring: 'plain', maxLength: null, richEditor: null }, comments, page: { next: null, limit: 20 }, labels: LABELS, locale: 'en',
                });
            case 'viewer':
                return json({
                    threadVersion: 'v1', viewer: MEMBER,
                    editor: { draftKey: 'comments-109-1-7', guest: { show: false, required: true, name: null, email: null, offerReplyEmails: false }, fields: { comment: [], reply: [] }, protection: null, richEditor: null, gifs: options.gifs === undefined ? GIFS : options.gifs },
                    comments: Object.fromEntries(comments.map((item) => [item.uid, permissions({ isAuthor: true, canEdit: true })])), extraComments: [], moderation: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' }, custom: {},
                });
            case 'protection':
                return json({ protection: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } });
            case 'edit':
                return json(options.edit ?? {});
            case 'create':
                return json({ replayed: false, comment: saved('new-1', body), moderationState: 'approved', conversation: { commentCount: 1 }, announcement: 'Comment posted.' });
            case 'update':
                return json({ replayed: false, comment: saved(body.uid, body), moderationState: 'approved', announcement: 'Comment updated.' });
            default:
                return json({}, 404);
        }
    }) as typeof fetch;

    return { fetch: fetcher, requests, of: (action: string) => requests.filter((request) => request.action === action) };
}

let mounted: CommentsController[] = [];
let stubbedGiphy: ReturnType<typeof giphy>;

function mount(server: ReturnType<typeof api>): CommentsController {
    const root = document.createElement('section');
    document.body.append(root);
    const controller = mountThread(root, { ownerId: 109, siteId: 1, endpoint: ENDPOINT, loadMode: 'eager', fetch: server.fetch });
    mounted.push(controller);
    return controller;
}

async function settle(until: () => unknown): Promise<void> {
    for (let i = 0; i < 200; i++) {
        await new Promise((resolve) => setTimeout(resolve, 10));
        if (until()) return;
    }

    throw new Error(`Timed out waiting: ${String(until)}`);
}

const createForm = (controller: CommentsController) => controller.root.querySelector<HTMLFormElement>('form[data-comments-form="create"]');

beforeEach(() => {
    stubbedGiphy = giphy();
    vi.stubGlobal('fetch', stubbedGiphy.fetcher);
    try {
        sessionStorage.clear();
    } catch {
        // Not every environment has it.
    }
});

afterEach(() => {
    destroyThreads(document);
    mounted = [];
    document.body.replaceChildren();
    vi.unstubAllGlobals();
});

describe('the GIF picker in the comment form', () => {
    it('offers no GIF button, and never loads the picker, when the site hasn’t turned GIPHY on', async () => {
        const controller = mount(api({ gifs: null }));
        await settle(() => createForm(controller)?.querySelector('textarea[data-comments-body]'));

        expect(createForm(controller)!.querySelector('.comments__gif-button')).toBeNull();
        expect(stubbedGiphy.requests).toHaveLength(0);
    });

    it('talks to GIPHY only once the picker opens, shows trending with the attribution, and closes on Escape', async () => {
        const controller = mount(api());
        await settle(() => createForm(controller)?.querySelector('.comments__gif-button'));
        const form = createForm(controller)!;
        const button = form.querySelector<HTMLButtonElement>('.comments__gif-button')!;

        expect(stubbedGiphy.requests).toHaveLength(0);
        expect(button.getAttribute('aria-label')).toBe('Add a GIF');
        expect(button.getAttribute('aria-expanded')).toBe('false');

        button.click();
        await settle(() => form.querySelectorAll('.comments__gif-result').length === 2);

        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(form.querySelector('.comments__gif-attribution')!.textContent).toBe('Powered by GIPHY');
        expect([...form.querySelectorAll<HTMLImageElement>('.comments__gif-result-image')].map((image) => image.alt)).toEqual(['GIF b', 'GIF a']);
        expect(document.activeElement).toBe(form.querySelector('[data-comments-gif-search]'));
        await settle(() => stubbedGiphy.pings('SEEN').length === 2);

        form.querySelector('[data-comments-gif-search]')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await settle(() => !form.querySelector('.comments__gif-picker'));
        expect(document.activeElement).toBe(button);
    });

    it('searches as someone types, and Enter in the search doesn’t post the comment', async () => {
        const server = api();
        const controller = mount(server);
        await settle(() => createForm(controller)?.querySelector('.comments__gif-button'));
        const form = createForm(controller)!;
        form.querySelector<HTMLButtonElement>('.comments__gif-button')!.click();
        await settle(() => form.querySelector('[data-comments-gif-search]'));

        const search = form.querySelector<HTMLInputElement>('[data-comments-gif-search]')!;
        search.value = 'cats';
        search.dispatchEvent(new Event('input', { bubbles: true }));
        search.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        await settle(() => stubbedGiphy.of('/v1/gifs/search').length);

        expect(stubbedGiphy.of('/v1/gifs/search')[0]!.url.searchParams.get('q')).toBe('cats');
        expect(server.of('create')).toHaveLength(0);
    });

    it('posts the chosen GIF with the comment, or on its own, and tells GIPHY it was chosen and sent', async () => {
        const server = api();
        const controller = mount(server);
        await settle(() => createForm(controller)?.querySelector('.comments__gif-button'));
        const form = createForm(controller)!;
        const button = form.querySelector<HTMLButtonElement>('.comments__gif-button')!;

        button.click();
        await settle(() => form.querySelectorAll('.comments__gif-result').length === 2);
        form.querySelector<HTMLButtonElement>('[data-comments-gif-index="1"]')!.click();
        await settle(() => form.querySelector('.comments__gif-preview'));

        expect(form.querySelector('.comments__gif-picker')).toBeNull();
        expect(document.activeElement).toBe(button);
        expect(form.querySelector<HTMLImageElement>('.comments__gif-preview-image')!.getAttribute('src')).toBe('https://media2.giphy.com/media/a/giphy.webp?cid=abc&rid=giphy.webp');
        await settle(() => stubbedGiphy.pings('CLICK').length === 1);

        // Only the GIF: Post doesn't wait for text.
        form.requestSubmit();
        await settle(() => server.of('create').length === 1);
        expect(server.of('create')[0]!.body!.content).toEqual({ format: 'plain', text: '', gif: { url: 'https://media2.giphy.com/media/a/giphy.webp?cid=abc&rid=giphy.webp', width: 480, height: 360 } });
        await settle(() => stubbedGiphy.pings('SENT').length === 1);
        // The form is empty again for the next comment.
        await settle(() => !form.querySelector('.comments__gif-preview'));

        // Text and a GIF together.
        button.click();
        await settle(() => form.querySelectorAll('.comments__gif-result').length === 2);
        form.querySelector<HTMLButtonElement>('[data-comments-gif-index="0"]')!.click();
        form.querySelector<HTMLTextAreaElement>('textarea[data-comments-body]')!.value = 'Ha.';
        form.requestSubmit();
        await settle(() => server.of('create').length === 2);
        expect(server.of('create')[1]!.body!.content).toMatchObject({ format: 'plain', text: 'Ha.', gif: { url: 'https://media2.giphy.com/media/b/giphy.webp?cid=abc&rid=giphy.webp' } });
    });

    it('posts no GIF once it’s been taken off', async () => {
        const server = api();
        const controller = mount(server);
        await settle(() => createForm(controller)?.querySelector('.comments__gif-button'));
        const form = createForm(controller)!;

        form.querySelector<HTMLButtonElement>('.comments__gif-button')!.click();
        await settle(() => form.querySelectorAll('.comments__gif-result').length === 2);
        form.querySelector<HTMLButtonElement>('[data-comments-gif-index="0"]')!.click();
        await settle(() => form.querySelector('.comments__gif-remove'));
        form.querySelector<HTMLButtonElement>('.comments__gif-remove')!.click();
        await settle(() => !form.querySelector('.comments__gif-preview'));

        form.querySelector<HTMLTextAreaElement>('textarea[data-comments-body]')!.value = 'Just words.';
        form.requestSubmit();
        await settle(() => server.of('create').length === 1);
        expect(server.of('create')[0]!.body!.content).toEqual({ format: 'plain', text: 'Just words.' });
    });

    it('shows that GIFs can’t be loaded when GIPHY fails', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => new Response('nope', { status: 500 })));
        const controller = mount(api());
        await settle(() => createForm(controller)?.querySelector('.comments__gif-button'));
        const form = createForm(controller)!;
        form.querySelector<HTMLButtonElement>('.comments__gif-button')!.click();

        await settle(() => form.querySelector('.comments__gif-status')?.textContent === 'GIFs can’t be loaded right now.');
        expect(form.querySelectorAll('.comments__gif-result')).toHaveLength(0);
    });
});

describe('editing a comment’s GIF', () => {
    const GIF = { url: 'https://media0.giphy.com/media/z/giphy.webp?cid=old', width: 320, height: 240 };
    const own = comment({ uid: 'mine', content: { format: 'plain', html: null, text: 'Old words.', gifUrl: GIF.url, gifWidth: 320, gifHeight: 240 } });
    const edit = (gif: typeof GIF | null) => ({ comment: { uid: 'mine', content: { format: 'plain', text: 'Old words.', document: null, gif } }, fields: {}, editor: { richEditor: null, fields: [], gifs: GIFS } });

    async function openEdit(server: ReturnType<typeof api>) {
        const controller = mount(server);
        await settle(() => controller.root.dataset.commentsPhase === 'ready' && controller.root.querySelector('[data-comments-comment-uid="mine"]'));
        controller.openEditor({ mode: 'edit', commentUid: 'mine' });
        await settle(() => controller.root.querySelector('form[data-comments-form="edit"] textarea[data-comments-body]'));
        return controller.root.querySelector<HTMLFormElement>('form[data-comments-form="edit"]')!;
    }

    it('leaves the GIF out of an edit that doesn’t change it', async () => {
        const server = api({ comments: [own], edit: edit(GIF) });
        const form = await openEdit(server);

        expect(form.querySelector<HTMLImageElement>('.comments__gif-preview-image')!.getAttribute('src')).toBe(GIF.url);
        form.querySelector<HTMLTextAreaElement>('textarea[data-comments-body]')!.value = 'New words.';
        form.requestSubmit();
        await settle(() => server.of('update').length === 1);

        expect(server.of('update')[0]!.body!.content).toEqual({ format: 'plain', text: 'New words.' });
    });

    it('sends null to take the GIF off', async () => {
        const server = api({ comments: [own], edit: edit(GIF) });
        const form = await openEdit(server);

        form.querySelector<HTMLButtonElement>('.comments__gif-remove')!.click();
        await settle(() => !form.querySelector('.comments__gif-preview'));
        form.requestSubmit();
        await settle(() => server.of('update').length === 1);

        expect(server.of('update')[0]!.body!.content).toEqual({ format: 'plain', text: 'Old words.', gif: null });
    });
});

describe('a comment’s GIF in the thread', () => {
    it('is drawn at its size, so the page doesn’t move when it loads', async () => {
        const state = await threadState({ comments: [comment({ uid: 'g', content: { format: 'plain', html: null, text: 'Look.', gifUrl: 'https://media.giphy.com/media/g/giphy.webp', gifWidth: 480, gifHeight: 270 } })] });
        const html = renderToString(threadTemplate(createStringView({ state })));

        expect(html).toMatch(/<img class="comments__gif" src="https:\/\/media\.giphy\.com\/media\/g\/giphy\.webp" alt="GIF" loading="lazy" width="480" height="270">/);
    });

    it('leaves the size off an old GIF without one', async () => {
        const state = await threadState({ comments: [comment({ uid: 'g', content: { format: 'plain', html: null, text: '', gifUrl: 'https://example.com/old.gif', gifWidth: null, gifHeight: null } })] });
        const html = renderToString(threadTemplate(createStringView({ state })));

        expect(html).toMatch(/<img class="comments__gif" src="https:\/\/example\.com\/old\.gif" alt="GIF" loading="lazy">/);
    });
});
