// @vitest-environment happy-dom
import { createCommentsClient, createThreadStore, type ThreadStore } from '@verbb/comments-core';
import { afterEach, describe, expect, it } from 'vitest';
import { commentForm } from '../src/form';
import { LABELS } from './support/thread';

/**
 * `commentForm()` with no framework: a site's own form posting to core's store, as the web components
 * starter's "Your own interface" page does. React's and Vue's `useCommentForm()` tests cover it
 * through the hooks as well.
 */

const ENDPOINT = 'https://comments.test/actions/comments/api';
const GUEST = { isGuest: true, hasSession: false, name: null, initials: null, avatarUrl: null, avatarTone: 0, badges: [], canComment: true, canVote: true, canReport: false, isModerator: false, blocked: false, loginUrl: null, follow: { available: false, following: false, needsEmail: true } };

type Recorded = { action: string; body: any };

function fakeApi(override: Record<string, () => { status: number; body: unknown }> = {}) {
    const requests: Recorded[] = [];
    const ok = (body: Record<string, unknown>) => ({ apiVersion: 1, status: 'ok', ...body });
    let protections = 0;
    const answers: Record<string, (body: any) => unknown> = {
        thread: () => ok({
            threadVersion: 'v1',
            conversation: { uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: 0, rootCount: 0, sort: 'newest', sorts: ['newest'], interactionMode: 'like', maxLength: null, guestPolicy: { allowed: true, nameAndEmail: 'required', canVote: true, canReport: false }, followAvailable: false, reportReasons: [] },
            editor: { authoring: 'plain', maxLength: null, richEditor: null }, comments: [], page: { next: null, limit: 20 }, labels: LABELS, locale: 'en',
        }),
        viewer: () => ok({ threadVersion: 'v1', viewer: GUEST, editor: { draftKey: 'd', guest: { show: true, required: true, name: null, email: null, offerReplyEmails: false }, fields: { comment: [], reply: [] }, protection: null, richEditor: null }, comments: {}, extraComments: [], moderation: null, csrf: null, custom: {} }),
        session: () => ok({ viewer: GUEST, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } }),
        protection: () => {
            const now = Math.floor(Date.now() / 1000);
            const n = ++protections;
            return ok({ protection: { version: 1, manifest: { version: 1, instanceId: `pi-${n}`, preparation: `prep-${n}`, issuedAt: now, expiresAt: now + 600, honeypot: 'a1b2c3', javascript: true, challenges: [] }, expiresAt: now + 600 }, csrf: null });
        },
        create: (body) => ok({
            replayed: false,
            comment: { uid: 'new-1', parentUid: body.parentUid ?? null, depth: 0, state: 'pending', content: { format: 'plain', html: null, text: body.content.text, gifUrl: null, gifWidth: null, gifHeight: null }, author: { name: body.guest?.name ?? 'Someone', initials: 'S', avatarUrl: null, avatarTone: 0, isGuest: true, badges: [], score: null }, createdAt: '2026-11-14T00:00:00+00:00', editedAt: null, display: { timeAgo: 'just now', fullDate: '14 November 2026' }, permalink: 'https://comments.test/post#comment-1500', fields: [], interaction: { upvotes: 0, downvotes: 0 }, folded: null, custom: {}, replies: { count: 0, items: [], next: null }, viewer: { canReply: false, canEdit: false, canDelete: false, canVote: false, canDownvote: false, canReport: false, vote: null, reported: false, isAuthor: true, moderation: null, custom: {} } },
            moderationState: 'pending', conversation: { commentCount: 0 }, announcement: null,
        }),
    };

    const fetch = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);
        const action = url.pathname.split('/').pop() ?? '';
        const body = typeof init.body === 'string' ? JSON.parse(init.body) : null;
        requests.push({ action, body });

        const replaced = override[action]?.();
        const answer = replaced ? replaced.body : answers[action]?.(body) ?? { apiVersion: 1, status: 'error', errors: [] };

        return new Response(JSON.stringify(answer), { status: replaced?.status ?? (answers[action] ? 200 : 404), headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } });
    };

    return { fetch: fetch as typeof globalThis.fetch, of: (action: string) => requests.filter((request) => request.action === action) };
}

async function until<T>(check: () => T, timeout = 3000): Promise<NonNullable<T>> {
    const start = Date.now();

    while (Date.now() - start < timeout) {
        const result = check();
        if (result) return result as NonNullable<T>;
        await new Promise((resolve) => setTimeout(resolve, 10));
    }

    throw new Error(`Timed out waiting: ${String(check)}`);
}

let store: ThreadStore | null = null;

afterEach(() => {
    store?.destroy();
    store = null;
    document.body.innerHTML = '';
});

async function setUp(api: ReturnType<typeof fakeApi>) {
    store = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: api.fetch }), { ownerId: 109, siteId: 1 });
    await store.load();

    document.body.innerHTML = `<form>
        <input name="guest[name]" value="Pat">
        <input name="guest[email]" value="pat@example.test">
        <textarea name="content">Hello from my own form</textarea>
        <input name="fields[rating]" value="4">
    </form>`;

    return document.querySelector('form')!;
}

describe('commentForm()', () => {
    it('mounts Protect once someone starts writing, posts what the form holds with its evidence, and empties the comment', async () => {
        const api = fakeApi();
        const element = await setUp(api);
        const form = commentForm(element, store!);

        expect(api.of('protection')).toHaveLength(0);
        element.querySelector('textarea')!.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        await until(() => element.querySelector('[name="a1b2c3"], .protect-honeypot'));

        const response = await form.handleSubmit(new Event('submit', { cancelable: true }));
        const [create] = api.of('create');

        expect(response?.comment.uid).toBe('new-1');
        expect(create!.body).toMatchObject({
            ownerId: 109,
            parentUid: null,
            content: { format: 'plain', text: 'Hello from my own form' },
            guest: { name: 'Pat', email: 'pat@example.test', notifyReplies: false },
            fields: { rating: '4' },
        });
        expect(create!.body.protection).toBeTruthy();
        // The comment is emptied, and a guest's details stay for their next one.
        expect(element.querySelector('textarea')!.value).toBe('');
        expect(element.querySelector<HTMLInputElement>('[name="guest[name]"]')!.value).toBe('Pat');
        expect(form.getState()).toEqual({ fieldErrors: {}, error: null, posting: false });

        form.destroy();
    });

    it('gives the server’s field errors and message to subscribers, and keeps the operation for a retry', async () => {
        const refused = { status: 422, body: { apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Check the highlighted fields.', fieldErrors: { 'guest.email': ['Enter a valid email.'] }, recoverable: false, retryAfter: null }] } };
        const api = fakeApi({ create: () => refused });
        const element = await setUp(api);
        const form = commentForm(element, store!);
        const seen: boolean[] = [];
        form.subscribe((state) => seen.push(state.posting));

        expect(await form.submit({ content: 'Again' })).toBeNull();
        expect(await form.submit({ content: 'Again' })).toBeNull();

        expect(form.getState().fieldErrors).toEqual({ 'guest.email': ['Enter a valid email.'] });
        expect(form.getState().error).toBe('Check the highlighted fields.');
        expect(seen).toContain(true);
        expect(seen.at(-1)).toBe(false);
        expect(api.of('create')[0]!.body.operationId).toBe(api.of('create')[1]!.body.operationId);

        form.reset();
        expect(form.getState().error).toBeNull();
        form.destroy();
    });

    it('posts a reply under the parent it was last set to', async () => {
        const api = fakeApi();
        const element = await setUp(api);
        const form = commentForm(element, store!, { mode: 'reply', parentUid: 'c1' });

        form.set({ parentUid: 'c2' });
        await form.submit();

        expect(api.of('create')[0]!.body.parentUid).toBe('c2');
        form.destroy();
    });
});
