import { describe, expect, it } from 'vitest';
import { createCommentsClient, createThreadStore, selectReplies, selectRoots } from '../src/index';
import { graphqlTransport } from '../src/graphql';
import { fixture } from './support/fixtures';
import { ENDPOINT, GRAPHQL_ENDPOINT, json, mockServer } from './support/server';

type Server = ReturnType<typeof mockServer>;

const uids = (comments: Array<{ uid: string }>) => comments.map((comment) => comment.uid);
const notFound = () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'state', code: 'not_found', message: 'This can’t be shown.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 404);

function store(configure: (server: Server) => void, options: Parameters<typeof createThreadStore>[1] = { ownerId: 109 }) {
    const server = mockServer();
    configure(server);

    return { server, store: createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch }), options) };
}

describe('CSRF on GraphQL writes', () => {
    it('sends the CSRF token on every mutation, always as a POST, and never on reads', async () => {
        const server = mockServer();
        const client = createCommentsClient({ endpoint: ENDPOINT, transport: graphqlTransport({ endpoint: GRAPHQL_ENDPOINT, fetch: server.fetch }), fetch: server.fetch });
        const viewer = { data: { commentsViewer: { apiVersion: 1, data: (({ apiVersion: _a, status: _s, ...rest }) => rest)(fixture('viewer-member.json')) } } };
        const created = fixture('create.json').comment;
        server.on('commentsViewer', viewer)
            .on('createComment', { data: { createComment: { commentsData: created } } })
            .on('saveComment', { data: { saveComment: { commentsData: created } } })
            .on('voteComment', { data: { voteComment: { comment: { commentsData: created } } } })
            .on('flagComment', { data: { flagComment: null } })
            .on('deleteComment', { data: { deleteComment: true } })
            .on('subscribeComment', { data: { subscribeComment: 'followed' } })
            .on('moderateComment', { data: { moderateComment: { commentsData: created } } });

        await client.viewer({ ownerId: 109 });
        await client.create({ ownerId: 109, content: { format: 'plain', text: 'Hi' } });
        await client.update({ uid: 'n1', content: { format: 'plain', text: 'Edited' } });
        await client.vote({ uid: 'n1', vote: 'upvote' });
        await client.report({ uid: 'n1', reason: 'spam' });
        await client.delete({ uid: 'n1' });
        await client.follow({ ownerId: 109, following: true });
        await client.moderate({ uid: 'n1', action: 'approve' });
        const [read, ...writes] = server.requests;

        expect(read!.headers.has('X-CSRF-Token')).toBe(false);
        expect(writes.map((request) => request.action)).toEqual(['createComment', 'saveComment', 'voteComment', 'flagComment', 'deleteComment', 'subscribeComment', 'moderateComment']);
        expect(writes.map((request) => `${request.method} ${request.headers.get('X-CSRF-Token')}`)).toEqual(Array(7).fill('POST csrf-member'));
        expect(server.sent('session')).toHaveLength(0);
    });
});

describe('a thread older than the viewer’s data', () => {
    it('is fetched again once, with v and no-cache, when the versions differ', async () => {
        const fresh = { ...fixture('thread.json'), threadVersion: 'v2', comments: [fixture('create.json').comment, ...fixture('thread.json').comments] };
        const viewer = { ...fixture('viewer-member.json'), threadVersion: 'v2' };
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json'), fresh).on('viewer', viewer), { ownerId: 109, limit: 2 });

        await thread.load();
        const [first, second] = server.sent('thread');

        expect(server.sent('thread')).toHaveLength(2);
        expect(first!.url.searchParams.has('v')).toBe(false);
        expect(second!.url.search).toBe(`${first!.url.search}&v=v2`);
        expect(second!.cache).toBe('no-cache');
        expect(thread.getState().threadVersion).toBe('v2');
        expect(uids(selectRoots(thread.getState()))).toContain('n1');
    });

    it('is drawn as it is when the fetch again is still old, without asking a third time', async () => {
        const viewer = { ...fixture('viewer-guest.json'), threadVersion: 'v2' };
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json')).on('viewer', viewer));

        await thread.load();

        expect(server.sent('thread')).toHaveLength(2);
        expect(thread.getState()).toMatchObject({ status: 'ready', threadVersion: 'v1' });
    });

    it('is drawn as it is when the fetch again fails', async () => {
        const viewer = { ...fixture('viewer-guest.json'), threadVersion: 'v2' };
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json'), () => Promise.reject(new TypeError('cache mode not supported'))).on('viewer', viewer));

        await thread.load();

        expect(server.sent('thread')).toHaveLength(2);
        expect(thread.getState()).toMatchObject({ status: 'ready', threadVersion: 'v1' });
    });

    it('isn’t fetched again when the versions match, or with no viewer read', async () => {
        const matching = store((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-guest.json')));
        await matching.store.load();
        expect(matching.server.sent('thread')).toHaveLength(1);

        const without = store((server) => server.on('thread', fixture('thread.json')), { ownerId: 109, viewer: false });
        await without.store.load();
        expect(without.server.sent('thread')).toHaveLength(1);
    });

    it('sends v over REST only, since GraphQL has no such argument', async () => {
        const server = mockServer();
        const wrapped = { data: { commentsThread: { apiVersion: 1, data: (({ apiVersion: _a, status: _s, ...rest }) => rest)(fixture('thread.json')) } } };
        server.on('commentsThread', wrapped);
        const client = createCommentsClient({ endpoint: ENDPOINT, transport: graphqlTransport({ endpoint: GRAPHQL_ENDPOINT, fetch: server.fetch }), fetch: server.fetch });

        await client.thread({ ownerId: 109, threadVersion: 'v2' }, { cache: 'no-cache' });

        expect(server.requests[0]!.body.variables).toEqual({ ownerId: 109, siteId: null });
    });
});

describe('a deep link to a comment that isn’t public', () => {
    it('asks the viewer for it and places it, such as the person’s own waiting reply', async () => {
        const waiting = { ...fixture('thread.json').comments[0].replies.items[0], uid: 'w1', state: 'pending', createdAt: '2026-11-09T23:30:00+00:00' };
        const found = { ...fixture('viewer-member.json'), extraComments: [waiting] };
        const { server, store: thread } = store((server) => server
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-member.json'), found)
            .on('comment', notFound));

        await thread.load();
        await thread.loadComment('w1');

        expect(server.sent('viewer')[1]!.url.searchParams.getAll('uids[]')).toEqual(['w1']);
        expect(uids(selectReplies(thread.getState(), 'c1'))).toEqual(['r1', 'w1']);
        expect(thread.getState().comments.w1?.state).toBe('pending');
    });

    it('passes on not_found when the viewer can’t see it either, and doesn’t ask twice', async () => {
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-guest.json')).on('comment', notFound));

        await thread.load();
        await expect(thread.loadComment('gone')).rejects.toMatchObject({ code: 'not_found' });
        await thread.refreshViewer();

        expect(server.sent('viewer').map((request) => request.url.searchParams.getAll('uids[]').includes('gone'))).toEqual([false, true, false]);
    });

    it('rides on the first load’s viewer read when the link comes first', async () => {
        const held = { ...fixture('viewer-moderator.json').extraComments[0], uid: 'x1' };
        const viewer = { ...fixture('viewer-moderator.json'), extraComments: [held] };
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json')).on('viewer', viewer).on('comment', notFound));

        await thread.loadComment('x1');
        await thread.load();

        expect(server.sent('viewer')[0]!.url.searchParams.getAll('uids[]')).toEqual(['x1']);
        expect(thread.getState().comments.x1?.state).toBe('held');
    });
});

describe('a deep link by the number in a permalink', () => {
    it('reads the comment by id, and by viewer ids[] when it isn’t public', async () => {
        const waiting = { ...fixture('thread.json').comments[0].replies.items[0], uid: 'w7', state: 'pending', permalink: 'https://example.com/post#comment-777', createdAt: '2026-11-09T23:30:00+00:00' };
        const found = { ...fixture('viewer-member.json'), extraComments: [waiting] };
        const { server, store: thread } = store((server) => server
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-member.json'), found)
            .on('comment', notFound));

        await thread.load();
        await thread.loadComment({ id: 777 });

        expect(server.sent('comment')[0]!.url.searchParams.get('id')).toBe('777');
        expect(server.sent('comment')[0]!.url.searchParams.has('uid')).toBe(false);
        expect(server.sent('viewer')[1]!.url.searchParams.getAll('ids[]')).toEqual(['777']);
        expect(thread.getState().comments.w7?.state).toBe('pending');
    });

    it('places a comment from an answer already fetched, without asking again', async () => {
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-guest.json')));

        await thread.load();
        await thread.loadComment({ id: 9 }, fixture('comment.json'));

        expect(server.sent('comment')).toHaveLength(0);
        expect(uids(selectReplies(thread.getState(), 'c9'))).toEqual(['r9']);
    });

    it('goes straight to the viewer read when a lookup already found it isn’t public', async () => {
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-guest.json')));

        await thread.load();
        await expect(thread.loadComment({ id: 5 }, null)).rejects.toMatchObject({ code: 'not_found' });

        expect(server.sent('comment')).toHaveLength(0);
        expect(server.sent('viewer')[1]!.url.searchParams.getAll('ids[]')).toEqual(['5']);
    });
});

describe('viewer reads in batches', () => {
    it('asks about at most 100 comments a read', async () => {
        const thread = fixture('thread.json');
        const many = Array.from({ length: 150 }, (_, index) => ({ ...thread.comments[1], uid: `m${index}`, replies: { count: 0, items: [], next: null } }));
        const { server, store: big } = store((server) => server.on('thread', { ...thread, comments: many }).on('viewer', fixture('viewer-member.json')));

        await big.load();
        await big.refreshViewer();
        const sizes = server.sent('viewer').slice(1).map((request) => request.url.searchParams.getAll('uids[]').length);

        expect(sizes[0]).toBe(100);
        expect(sizes.reduce((sum, size) => sum + size, 0)).toBe(Object.keys(big.getState().comments).length);
        expect(sizes.every((size) => size <= 100)).toBe(true);
    });
});

describe('errors and cursors', () => {
    it('lists invalid_request as a 400 in the request category', async () => {
        const { server, store: thread } = store((server) => server.on('thread', () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'request', code: 'invalid_request', message: 'That request isn’t valid.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 400)));

        await expect(thread.load()).rejects.toMatchObject({ code: 'invalid_request', category: 'request', status: 400 });
        expect(server.sent('thread')).toHaveLength(1);
    });

    it('reads recoverable and retryAfter from a GraphQL read error’s extensions', async () => {
        const server = mockServer().on('commentsThread', { errors: [{ message: 'Too many requests.', extensions: { code: 'rate_limited', category: 'rate', recoverable: true, retryAfter: 12 } }] });
        const client = createCommentsClient({ endpoint: ENDPOINT, transport: graphqlTransport({ endpoint: GRAPHQL_ENDPOINT, fetch: server.fetch }), fetch: server.fetch });

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'rate_limited', category: 'rate', recoverable: true, retryAfter: 12, message: 'Too many requests.' });
    });

    it('pages at the limit the server used for the first page', async () => {
        const first = { ...fixture('thread.json'), page: { next: 'cursor-roots-2', limit: 20 } };
        const { server, store: thread } = store((server) => server.on('thread', first, fixture('thread-page-2.json')).on('viewer', fixture('viewer-guest.json')));

        await thread.load();
        await thread.loadMore();

        expect(server.sent('thread').map((request) => request.url.searchParams.get('limit'))).toEqual([null, '20']);
        expect(server.sent('viewer').map((request) => request.url.searchParams.get('limit'))).toEqual([null, '20']);
    });

    it('starts again from the first page when the server refuses a cursor', async () => {
        const invalid = () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'request', code: 'invalid_cursor', message: 'That page link isn’t valid any more.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 400);
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json'), invalid, fixture('thread.json')).on('viewer', fixture('viewer-guest.json')), { ownerId: 109, limit: 2 });

        await thread.load();
        await thread.loadMore();

        expect(server.sent('thread').map((request) => request.url.searchParams.get('cursor'))).toEqual([null, 'cursor-roots-2', null]);
        expect(thread.getState()).toMatchObject({ status: 'ready', next: 'cursor-roots-2', loadingMore: false });
    });
});

describe('refused actions', () => {
    const refusal = (code: string) => () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'policy', code, message: 'You can’t do that here.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 403);

    it('treats blocked like forbidden, never retrying it, and shows everything as unavailable', async () => {
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-member.json')).on('vote', refusal('blocked')));
        await thread.load();

        await expect(thread.vote('c2', 'upvote')).rejects.toMatchObject({ code: 'blocked', category: 'policy', status: 403, recoverable: false });
        const state = thread.getState();

        expect(server.sent('vote')).toHaveLength(1);
        expect(state.permissions.c2).toMatchObject({ canVote: false, canDownvote: false, canReport: false, canReply: false });
        expect(state.viewer).toMatchObject({ blocked: true, canComment: false, canVote: false, canReport: false, follow: { available: false } });
    });

    it('shows only the refused action as unavailable after forbidden', async () => {
        const { server, store: thread } = store((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-member.json')).on('report', refusal('forbidden')).on('follow', refusal('forbidden')));
        await thread.load();

        await expect(thread.report('c2', { reason: 'spam' })).rejects.toMatchObject({ code: 'forbidden' });
        await expect(thread.follow(true)).rejects.toMatchObject({ code: 'forbidden' });

        expect(server.sent('report')).toHaveLength(1);
        expect(thread.getState().permissions.c2).toMatchObject({ canReport: false, canVote: true });
        expect(thread.getState().viewer).toMatchObject({ blocked: false, canComment: true, follow: { available: false } });
    });
});
