import { afterEach, describe, expect, it, vi } from 'vitest';
import { COMMENTS_ERROR_CODES, createCommentsClient, createThreadStore, guestSession, memberToken, type TokenStorage } from '../src/index';
import { fixture } from './support/fixtures';
import { ENDPOINT, json, mockServer, type RecordedRequest } from './support/server';

afterEach(() => {
    vi.useRealTimers();
});

const refusal = (code: string, status = 401) => () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'auth', code, message: 'Sign in again.', fieldErrors: {}, recoverable: true, retryAfter: null }] }, status);
const now = () => Math.floor(Date.now() / 1000);
const bearer = (request: RecordedRequest) => request.headers.get('Authorization');

function member(getToken: () => Promise<{ token: string; expiresAt: number }>, timeoutMs = 30000) {
    const server = mockServer();
    const auth = memberToken({ getToken });
    const client = createCommentsClient({ endpoint: ENDPOINT, siteId: 1, fetch: server.fetch, auth, timeoutMs });

    return { server, client, auth };
}

describe('memberToken()', () => {
    it('sends a bearer token on identified requests only, with no cookies or CSRF', async () => {
        const getToken = vi.fn(async () => ({ token: 'tok-1', expiresAt: now() + 900 }));
        const { server, client } = member(getToken);
        server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-member.json')).on('vote', fixture('vote.json'));

        await client.thread({ ownerId: 109 });
        await client.viewer({ ownerId: 109 });
        await client.vote({ uid: 'c2', vote: 'upvote' });
        const [thread, viewer, vote] = server.requests;

        expect(bearer(thread!)).toBeNull();
        expect([viewer, vote].map((request) => bearer(request!))).toEqual(['Bearer tok-1', 'Bearer tok-1']);
        expect([viewer, vote].map((request) => request!.headers.get('X-Comments-Profile'))).toEqual(['cross-origin-member', 'cross-origin-member']);
        expect(server.requests.every((request) => request.credentials === 'omit')).toBe(true);
        expect(vote!.headers.has('X-CSRF-Token')).toBe(false);
        expect(getToken).toHaveBeenCalledTimes(1);
        expect(server.sent('session')).toHaveLength(0);
    });

    it('fetches the next token shortly before expiresAt, sharing one call between requests', async () => {
        vi.useFakeTimers({ now: new Date('2026-11-11T10:00:00Z') });
        let n = 0;
        const getToken = vi.fn(async () => ({ token: `tok-${++n}`, expiresAt: now() + 900 }));
        const { server, client } = member(getToken);
        server.on('viewer', fixture('viewer-member.json'));

        await Promise.all([client.viewer({ ownerId: 109 }), client.viewer({ ownerId: 109 })]);
        vi.setSystemTime(Date.now() + 830_000);
        await client.viewer({ ownerId: 109 });
        vi.setSystemTime(Date.now() + 15_000);
        await client.viewer({ ownerId: 109 });

        expect(server.sent('viewer').map(bearer)).toEqual(['Bearer tok-1', 'Bearer tok-1', 'Bearer tok-1', 'Bearer tok-2']);
        expect(getToken).toHaveBeenCalledTimes(2);
    });

    it.each(['token_expired', 'token_revoked'])('fetches a new token and retries once after %s', async (code) => {
        let n = 0;
        const getToken = vi.fn(async () => ({ token: `tok-${++n}`, expiresAt: now() + 900 }));
        const { server, client } = member(getToken);
        server.on('vote', refusal(code), fixture('vote.json'));

        await client.vote({ uid: 'c2', vote: 'upvote' });
        const votes = server.sent('vote');

        expect(votes.map(bearer)).toEqual(['Bearer tok-1', 'Bearer tok-2']);
        expect(votes[1]!.body.operationId).toBe(votes[0]!.body.operationId);
    });

    it('gives up after a second refusal', async () => {
        const { server, client } = member(async () => ({ token: 'tok', expiresAt: now() + 900 }));
        server.on('viewer', refusal('token_revoked'));

        await expect(client.viewer({ ownerId: 109 })).rejects.toMatchObject({ code: 'token_revoked', category: 'auth' });
        expect(server.sent('viewer')).toHaveLength(2);
    });

    it('lets the request time out while getToken() hangs', async () => {
        const { server, client } = member(() => new Promise(() => undefined), 20);
        server.on('viewer', fixture('viewer-member.json'));

        await expect(client.viewer({ ownerId: 109 })).rejects.toMatchObject({ code: 'network_error', message: 'The request took too long. Please try again.' });
        expect(server.requests).toHaveLength(0);
    });

    it('drops the token on reset(), tells listeners, and fetches a new one next time', async () => {
        let n = 0;
        const { server, client, auth } = member(async () => ({ token: `tok-${++n}`, expiresAt: now() + 900 }));
        server.on('viewer', fixture('viewer-member.json'));
        const listener = vi.fn();
        auth.onReset?.(listener);

        await client.viewer({ ownerId: 109 });
        auth.reset?.();
        await client.viewer({ ownerId: 109 });

        expect(listener).toHaveBeenCalledTimes(1);
        expect(server.sent('viewer').map(bearer)).toEqual(['Bearer tok-1', 'Bearer tok-2']);
    });
});

function memoryStorage(initial: Record<string, string> = {}): TokenStorage & { items: Record<string, string> } {
    const items = { ...initial };

    return {
        items,
        getItem: (key) => items[key] ?? null,
        setItem: (key, value) => { items[key] = value; },
        removeItem: (key) => { delete items[key]; },
    };
}

function guest(storage?: TokenStorage) {
    const server = mockServer();
    const auth = guestSession({ storage });
    const client = createCommentsClient({ endpoint: ENDPOINT, siteId: 1, fetch: server.fetch, auth });

    return { server, client, auth };
}

const withSession = (payload: unknown, token: string, status = 200) => () => json(payload, status, { 'X-Comments-Session': token });

describe('guestSession()', () => {
    it('reads without a session, gets one from the session read before its first write, and sends it from then on', async () => {
        const { server, client } = guest();
        server.on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-guest.json'))
            .on('session', withSession({ ...fixture('session.json'), csrf: null }, 'gs-1'))
            .on('report', withSession(fixture('report.json'), 'gs-2'));

        await client.thread({ ownerId: 109 });
        await client.viewer({ ownerId: 109 });
        await client.report({ uid: 'c2', reason: 'spam' });
        await client.viewer({ ownerId: 109 });
        const [thread, viewer, session, report, again] = server.requests;

        expect(thread!.headers.has('X-Comments-Session')).toBe(false);
        expect(viewer!.headers.has('X-Comments-Session')).toBe(false);
        expect(session!.action).toBe('session');
        expect(report!.headers.get('X-Comments-Session')).toBe('gs-1');
        expect(report!.headers.has('X-CSRF-Token')).toBe(false);
        // Every answer may carry a refreshed token.
        expect(again!.headers.get('X-Comments-Session')).toBe('gs-2');
        expect(server.requests.every((request) => request.credentials === 'omit')).toBe(true);
        expect(viewer!.headers.get('X-Comments-Profile')).toBe('cross-origin-public');
    });

    it('reads session again and retries once after session_expired', async () => {
        const { server, client } = guest();
        server.on('session', withSession({ ...fixture('session.json'), csrf: null }, 'gs-1'), withSession({ ...fixture('session.json'), csrf: null }, 'gs-2'))
            .on('vote', refusal('session_expired'), fixture('vote.json'));

        await client.vote({ uid: 'c2', vote: 'upvote' });
        const votes = server.sent('vote');

        expect(votes.map((request) => request.headers.get('X-Comments-Session'))).toEqual(['gs-1', 'gs-2']);
        expect(votes[1]!.body.operationId).toBe(votes[0]!.body.operationId);
        expect(server.sent('session')).toHaveLength(2);
    });

    it('keeps its token in memory unless given storage, which it keys by endpoint', async () => {
        const storage = memoryStorage({ [`verbb-comments-session:${ENDPOINT}`]: 'gs-stored' });
        const stored = guest(storage);
        stored.server.on('vote', withSession(fixture('vote.json'), 'gs-next'));

        await stored.client.vote({ uid: 'c2', vote: 'upvote' });

        expect(stored.server.sent('vote')[0]!.headers.get('X-Comments-Session')).toBe('gs-stored');
        expect(storage.items).toEqual({ [`verbb-comments-session:${ENDPOINT}`]: 'gs-next' });
        expect(stored.server.sent('session')).toHaveLength(0);

        stored.auth.reset?.();
        expect(storage.items).toEqual({});
    });

    it('carries on in memory when storage refuses', async () => {
        const broken: TokenStorage = { getItem: () => { throw new Error('denied'); }, setItem: () => { throw new Error('full'); }, removeItem: () => undefined };
        const { server, client } = guest(broken);
        server.on('session', withSession({ ...fixture('session.json'), csrf: null }, 'gs-1')).on('vote', fixture('vote.json'));

        await client.vote({ uid: 'c2', vote: 'upvote' });

        expect(server.sent('vote')[0]!.headers.get('X-Comments-Session')).toBe('gs-1');
    });

    it('shows editing and moderation as unavailable in a thread store, and refuses them without asking', async () => {
        const viewer = fixture('viewer-moderator.json');
        viewer.comments.c1 = { ...viewer.comments.c1, canEdit: true };
        const { server, client } = guest();
        server.on('thread', fixture('thread.json')).on('viewer', viewer);
        const store = createThreadStore(client, { ownerId: 109 });

        await store.load();
        const state = store.getState();

        expect(state.unavailable).toEqual(['edit', 'mention', 'moderate']);
        expect(state.permissions.c1?.canEdit).toBe(false);
        expect(state.viewer?.isModerator).toBe(false);
        expect(state.moderation).toBeNull();
        await expect(store.update('c1', { content: { format: 'plain', text: 'x' } })).rejects.toMatchObject({ code: 'forbidden' });
        await expect(store.moderate('c1', 'approve')).rejects.toMatchObject({ code: 'forbidden' });
        expect(server.sent('update')).toHaveLength(0);
        expect(server.sent('moderate')).toHaveLength(0);
    });
});

describe('stage C’s codes and mentions', () => {
    it('lists the codes only a site’s own server sees', () => {
        expect(COMMENTS_ERROR_CODES.signature_invalid).toEqual({ category: 'auth', status: 401 });
        expect(COMMENTS_ERROR_CODES.user_not_eligible).toEqual({ category: 'auth', status: 403 });
        expect(COMMENTS_ERROR_CODES.origin_not_allowed).toEqual({ category: 'auth', status: 403 });
    });

    it('sends Editor’s scope as a JSON string', async () => {
        const { server, client } = member(async () => ({ token: 'tok', expiresAt: now() + 900 }));
        server.on('mentions', () => json({ apiVersion: 1, status: 'ok', results: [] }));

        await client.mentions({ context: 'comments.reply', query: 'al', scope: { ownerId: 109, siteId: 1 } });
        const { searchParams } = server.sent('mentions')[0]!.url;

        expect(searchParams.get('context')).toBe('comments.reply');
        expect(searchParams.get('query')).toBe('al');
        expect(JSON.parse(searchParams.get('scope')!)).toEqual({ ownerId: 109, siteId: 1 });
        expect(bearer(server.sent('mentions')[0]!)).toBe('Bearer tok');
    });
});
