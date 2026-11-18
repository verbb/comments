import { describe, expect, it } from 'vitest';
import { createCommentsClient, type CommentsTransport } from '../src/index';
import { graphqlTransport } from '../src/graphql';
import { fieldArguments, graphqlLiteral } from '../src/graphql-literal';
import { fixture } from './support/fixtures';
import { ENDPOINT, GRAPHQL_ENDPOINT, json, mockServer, type RecordedRequest } from './support/server';

function setup(options: Partial<Parameters<typeof createCommentsClient>[0]> = {}) {
    const server = mockServer();
    const client = createCommentsClient({ endpoint: ENDPOINT, siteId: 1, fetch: server.fetch, ...options });

    return { server, client };
}

/** Never answers, until the request's signal aborts it. */
const hang = (request: RecordedRequest) => new Promise<Response>((_resolve, reject) => {
    request.signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
});

describe('resending', () => {
    it('never resends an upload after a dropped connection', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('upload', () => Promise.reject(new TypeError('Failed to fetch')));

        await expect(client.upload({ file: new Blob(['x']), filename: 'a.txt' })).rejects.toMatchObject({ code: 'network_error' });
        expect(server.sent('upload')).toHaveLength(1);
    });

    it('resends a write that timed out with the same operationId', async () => {
        const { server, client } = setup({ timeoutMs: 20 });
        server.on('session', fixture('session.json')).on('vote', hang, fixture('vote.json'));

        await client.vote({ uid: 'c2', vote: 'upvote' });
        const votes = server.sent('vote');

        expect(votes).toHaveLength(2);
        expect(votes[1]!.body.operationId).toBe(votes[0]!.body.operationId);
    });

    it('never resends a write the caller cancelled', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('vote', hang);
        const controller = new AbortController();

        const pending = client.vote({ uid: 'c2', vote: 'upvote' }, { signal: controller.signal });
        await new Promise((resolve) => setTimeout(resolve, 5));
        controller.abort();

        await expect(pending).rejects.toMatchObject({ name: 'AbortError' });
        expect(server.sent('vote')).toHaveLength(1);
    });

    it('treats an error from code, rather than the connection, as invalid_request and never resends it', async () => {
        let calls = 0;
        const transport: CommentsTransport = {
            name: 'broken',
            protectionChannel: 'browser',
            send: async () => {
                calls++;
                throw new RangeError('Something in the transport broke.');
            },
        };
        const client = createCommentsClient({ endpoint: ENDPOINT, transport, auth: { profile: 'same-origin-browser', credentials: 'same-origin', headers: async () => ({}) } });

        const error = await client.vote({ uid: 'c2', vote: 'upvote' }).catch((caught: unknown) => caught);

        expect(error).toMatchObject({ code: 'invalid_request', category: 'request', message: 'Something went wrong. Please try again.' });
        expect((error as { detail: string }).detail).toContain('Something in the transport broke.');
        expect((error as { cause: unknown }).cause).toBeInstanceOf(RangeError);
        expect(calls).toBe(1);
    });
});

describe('what each request carries', () => {
    it('never sends the CSRF token, cookies or private headers on a read', async () => {
        const { server, client } = setup({ headers: { public: { 'X-Site': 'blog' }, private: { 'X-Secret': 'token' } } });
        server.on('viewer', fixture('viewer-member.json')).on('thread', fixture('thread.json')).on('edit', fixture('edit.json')).on('vote', fixture('vote.json'));

        await client.viewer({ ownerId: 109 });
        await client.thread({ ownerId: 109 });
        await client.edit('r1');
        await client.vote({ uid: 'c2', vote: 'upvote' });
        const [viewer, thread, edit, vote] = server.requests;

        expect([viewer, thread, edit].map((request) => request!.headers.has('X-CSRF-Token'))).toEqual([false, false, false]);
        expect(vote!.headers.get('X-CSRF-Token')).toBe('csrf-member');
        expect(thread!.headers.get('X-Site')).toBe('blog');
        expect(thread!.headers.has('X-Secret')).toBe(false);
        expect(thread!.credentials).toBe('omit');
        expect(thread!.redirect).toBe('follow');
        expect(viewer!.headers.get('X-Secret')).toBe('token');
        expect(viewer!.headers.has('X-Site')).toBe(false);
        expect([viewer, edit, vote].map((request) => request!.redirect)).toEqual(['manual', 'manual', 'manual']);
    });

    it('refuses a redirect on a request that identifies the person, without following it', async () => {
        const { server, client } = setup();
        server.on('viewer', () => new Response(null, { status: 302, headers: { Location: 'https://elsewhere.example/' } }));

        const error = await client.viewer({ ownerId: 109 }).catch((caught: unknown) => caught);

        expect(error).toMatchObject({ code: 'invalid_response', status: 302 });
        expect((error as { detail: string }).detail).toContain('redirect');
        expect(server.requests).toHaveLength(1);
    });

    it('reads Retry-After from the header when the body has none, in seconds or as a date', async () => {
        const { server, client } = setup();
        const limited = fixture('error-rate-limited.json');
        limited.errors[0].retryAfter = null;
        server.on('thread', () => json(limited, 429, { 'Retry-After': '45' }), () => json(limited, 429, { 'Retry-After': new Date(Date.now() + 90_000).toUTCString() }));

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ retryAfter: 45 });
        const dated = await client.thread({ ownerId: 109 }).then(() => ({ retryAfter: -1 }), (caught: { retryAfter: number }) => caught);
        expect(dated.retryAfter).toBeGreaterThanOrEqual(88);
        expect(dated.retryAfter).toBeLessThanOrEqual(90);
    });
});

describe('sign-in within the timeout', () => {
    it('gives up on a session read that never answers, as a timeout', async () => {
        const { server, client } = setup({ timeoutMs: 20 });
        server.on('session', hang);

        await expect(client.delete({ uid: 'c2' })).rejects.toMatchObject({ code: 'network_error', message: 'The request took too long. Please try again.' });
        expect(server.sent('delete')).toHaveLength(0);
    });

    it('lets a write leave a shared session read without cancelling it for the others', async () => {
        let release: () => void = () => undefined;
        const { server, client } = setup();
        server.on('session', () => new Promise<Response>((resolve) => { release = () => resolve(json(fixture('session.json'))); })).on('vote', fixture('vote.json'));
        const controller = new AbortController();

        const cancelled = client.vote({ uid: 'c1', vote: 'upvote' }, { signal: controller.signal });
        const kept = client.vote({ uid: 'c2', vote: 'upvote' });
        await new Promise((resolve) => setTimeout(resolve, 5));
        controller.abort();
        await expect(cancelled).rejects.toMatchObject({ name: 'AbortError' });
        release();
        await kept;

        expect(server.sent('session')).toHaveLength(1);
        expect(server.sent('session')[0]!.signal?.aborted).toBe(false);
        expect(server.sent('vote').map((request) => request.body.uid)).toEqual(['c2']);
    });
});

describe('GraphQL edges', () => {
    function graphql() {
        const server = mockServer();
        const client = createCommentsClient({ endpoint: ENDPOINT, siteId: 1, transport: graphqlTransport({ endpoint: GRAPHQL_ENDPOINT, fetch: server.fetch }), fetch: server.fetch });
        server.on('session', fixture('session.json'));
        return { server, client };
    }

    it('refuse a comment without commentsData as invalid_response', async () => {
        const { server, client } = graphql();
        server.on('createComment', { data: { createComment: { id: '5' } } });

        const error = await client.create({ ownerId: 109, content: { format: 'plain', text: 'Hi' } }).catch((caught: unknown) => caught);

        expect(error).toMatchObject({ code: 'invalid_response', message: 'Something went wrong. Please try again.' });
        expect(server.sent('createComment')).toHaveLength(1);
    });

    it('read the follow state from the outcome', async () => {
        const { server, client } = graphql();
        server.on('subscribeComment', { data: { subscribeComment: 'confirming' } }, { data: { subscribeComment: 'unfollowed' } }, { data: { subscribeComment: 'odd' } });

        expect(await client.follow({ ownerId: 109, following: true, email: 'jo@example.com' })).toMatchObject({ outcome: 'confirming', viewer: { follow: { following: false, needsEmail: true } } });
        expect(await client.follow({ ownerId: 109, following: false })).toMatchObject({ outcome: 'unfollowed', viewer: { follow: { following: false, needsEmail: false } } });
        await expect(client.follow({ ownerId: 109, following: true })).rejects.toMatchObject({ code: 'invalid_response' });
    });

    it('show a visitor a safe message for a GraphQL error that isn’t one of Comments’, keeping the original as detail', async () => {
        const { server, client } = graphql();
        server.on('commentsThread', { errors: [{ message: 'Cannot query field "commentsThread" on type "Query".' }] });

        const error = await client.thread({ ownerId: 1 }).catch((caught: unknown) => caught);

        expect(error).toMatchObject({ code: 'server_error', message: 'Something went wrong. Please try again.', detail: 'Cannot query field "commentsThread" on type "Query".' });
    });

    it('refuse a custom field named like one of the mutation’s own arguments', async () => {
        const { server, client } = graphql();

        for (const handle of ['comment', 'email', 'protection', 'uid', 'title']) {
            await expect(client.create({ ownerId: 109, content: { format: 'plain', text: 'Hi' }, fields: { [handle]: 'x' } })).rejects.toMatchObject({ code: 'invalid_request' });
        }

        expect(server.sent('createComment')).toHaveLength(0);
        expect(() => fieldArguments({ rating: 5 }, ['comment'])).not.toThrow();
    });

    it('write control characters as escapes and replace unpaired surrogates', () => {
        expect(graphqlLiteral('a\u0000b\u001fc\nd\t"e"\\')).toBe('"a\\u0000b\\u001fc\\nd\\t\\"e\\"\\\\"');
        expect(graphqlLiteral('x\uD800y\uDC00z')).toBe('"x�y�z"');
        expect(graphqlLiteral('emoji 😀')).toBe('"emoji 😀"');
    });

    it('send unpaired surrogates in JSON bodies as U+FFFD, over REST and GraphQL', async () => {
        const rest = setup();
        rest.server.on('session', fixture('session.json')).on('create', fixture('create.json'));
        await rest.client.create({ ownerId: 109, content: { format: 'plain', text: 'half \uD83D' } });
        expect(rest.server.sent('create')[0]!.body.content.text).toBe('half �');

        const { server, client } = graphql();
        server.on('createComment', { data: { createComment: { commentsData: fixture('create.json').comment } } });
        await client.create({ ownerId: 109, content: { format: 'plain', text: 'half \uDE00' } });
        expect(server.sent('createComment')[0]!.body.variables.comment).toBe('half �');
    });
});
