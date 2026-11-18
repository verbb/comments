import { describe, expect, it, vi } from 'vitest';
import { createCommentsClient, createThreadStore, selectReplies, selectRoots, type ThreadStoreOptions } from '../src/index';
import { fixture } from './support/fixtures';
import { ENDPOINT, json, mockServer } from './support/server';

type Server = ReturnType<typeof mockServer>;

function setup(configure: (server: Server) => void, options: ThreadStoreOptions = { ownerId: 109, siteId: 1, limit: 2 }) {
    const server = mockServer();
    configure(server);
    const client = createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch });
    const store = createThreadStore(client, options);

    return { server, client, store };
}

const uids = (comments: Array<{ uid: string }>) => comments.map((comment) => comment.uid);

describe('loading', () => {
    it('reads the thread and the viewer in parallel and merges them', async () => {
        const { server, store } = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-member.json')));
        const states: string[] = [];
        store.subscribe((state) => states.push(state.status));

        await store.load();
        const state = store.getState();

        expect(server.requests.map((request) => request.action).sort()).toEqual(['thread', 'viewer']);
        expect(server.sent('viewer')[0]!.url.search).toBe(server.sent('thread')[0]!.url.search);
        expect(states[0]).toBe('loading');
        expect(state.status).toBe('ready');
        expect(state.conversation?.commentCount).toBe(6);
        expect(state.labels.reply).toBe('Reply');
        // The member's own pending comment is the newest, so it goes first.
        expect(uids(selectRoots(state))).toEqual(['p1', 'c1', 'c2']);
        expect(uids(selectReplies(state, 'c1'))).toEqual(['r1']);
        expect(state.replies.c1).toEqual({ uids: ['r1'], count: 2, next: 'cursor-replies-c1', loading: false });
        expect(state.permissions.c1?.vote).toBe('upvote');
        expect(state.permissions.p1?.isAuthor).toBe(true);
        expect(state.comments.p1).not.toHaveProperty('viewer');
        expect(state.comments.c1!.replies.items).toEqual([]);
        expect(state.next).toBe('cursor-roots-2');
        expect(state.viewer?.name).toBe('Alex Morgan');
        expect(state.viewerEditor?.fields.comment.map((field) => field.handle)).toEqual(['rating', 'photo']);
    });

    it('says who’s reading when the viewer arrives', async () => {
        const { store } = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-member.json')));
        const loaded = vi.fn();
        store.on('comments:viewer-loaded', loaded);

        await store.load();

        expect(loaded).toHaveBeenCalledTimes(1);
        expect(loaded.mock.calls[0]![0].viewer?.name).toBe('Alex Morgan');
        expect(loaded.mock.calls[0]![0].viewer).toEqual(store.getState().viewer);
    });

    it('reads by owner URI, then uses the owner’s ID', async () => {
        const { server, store } = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-guest.json')).on('session', fixture('session.json')).on('follow', fixture('follow.json')), { ownerUri: 'blog/trail-running-knees' });

        await store.load();
        await store.follow(true, 'jo@example.com');

        expect(server.sent('thread')[0]!.url.search).toBe('?ownerUri=blog%2Ftrail-running-knees&sort=newest');
        expect(server.sent('follow')[0]!.body).toMatchObject({ ownerId: 109, siteId: 1, following: true, email: 'jo@example.com' });
        expect(store.getState().viewer?.follow.following).toBe(true);
    });

    it('can skip the viewer read', async () => {
        const { server, store } = setup((server) => server.on('thread', fixture('thread.json')), { ownerId: 109, viewer: false });

        await store.load();

        expect(server.sent('viewer')).toHaveLength(0);
        expect(store.getState().viewer).toBeNull();
    });

    it('shows the thread when the viewer read fails, and reports the failure', async () => {
        const { store } = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', () => json(fixture('error-rate-limited.json'), 429)));
        const errors = vi.fn();
        store.on('comments:request-error', errors);

        await store.load();

        expect(store.getState().status).toBe('ready');
        expect(store.getState().viewer).toBeNull();
        expect(errors).toHaveBeenCalledWith(expect.objectContaining({ apiVersion: 1, reason: 'viewer', code: 'rate_limited', category: expect.any(String), message: expect.any(String), recoverable: true, error: expect.objectContaining({ code: 'rate_limited' }) }));
    });

    it('keeps the error when the thread can’t load', async () => {
        const { store } = setup((server) => server.on('thread', () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'not_found', code: 'not_found', message: 'Not found.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 404)).on('viewer', fixture('viewer-guest.json')));

        await expect(store.load()).rejects.toMatchObject({ code: 'not_found' });
        expect(store.getState()).toMatchObject({ status: 'error', error: expect.objectContaining({ code: 'not_found' }) });
    });

    it('pages on with the cursor, keeping a comment that moved between pages once', async () => {
        const { server, store } = setup((server) => server
            .on('thread', fixture('thread.json'), fixture('thread-page-2.json'))
            .on('viewer', fixture('viewer-guest.json')));

        await store.load();
        await store.loadMore();
        await store.loadMore();

        expect(uids(selectRoots(store.getState()))).toEqual(['c1', 'c2', 'c3', 'p3']);
        expect(store.getState().comments.p3).toMatchObject({ state: 'placeholder', author: null, createdAt: null });
        expect(uids(selectReplies(store.getState(), 'p3'))).toEqual(['p3r']);
        expect(store.getState().next).toBeNull();
        expect(server.sent('thread')[1]!.url.searchParams.get('cursor')).toBe('cursor-roots-2');
        expect(server.sent('viewer')[1]!.url.searchParams.get('cursor')).toBe('cursor-roots-2');
        expect(server.sent('thread')).toHaveLength(2);
    });

    it('loads replies with their cursor, de-duplicated, then the viewer for the new ones', async () => {
        const { server, store } = setup((server) => server
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-member.json'))
            .on('replies', fixture('replies.json')));

        await store.load();
        await store.loadReplies('c1');
        const state = store.getState();

        expect(server.sent('replies')[0]!.url.search).toBe('?parentUid=c1&cursor=cursor-replies-c1');
        expect(uids(selectReplies(state, 'c1'))).toEqual(['r1', 'r2']);
        expect(state.replies.c1).toMatchObject({ next: null, loading: false, count: 2 });
        expect(server.sent('viewer')[1]!.url.searchParams.getAll('uids[]')).toEqual(['r2']);
    });

    it('reads the viewer for the previews nested in newly loaded replies too', async () => {
        const replies = fixture('replies.json');
        const nested = { ...replies.replies.items[1], uid: 'r2a', parentUid: 'r2', depth: 2, replies: { count: 0, items: [], next: null } };
        replies.replies.items[1] = { ...replies.replies.items[1], replies: { count: 1, items: [nested], next: null } };
        const { server, store } = setup((server) => server
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-member.json'))
            .on('replies', replies));

        await store.load();
        await store.loadReplies('c1');

        expect(uids(selectReplies(store.getState(), 'r2'))).toEqual(['r2a']);
        expect(server.sent('viewer')[1]!.url.searchParams.getAll('uids[]')).toEqual(['r2', 'r2a']);
    });

    it('drops a response for the old sort once the sort changes', async () => {
        let release: (response: Response) => void = () => undefined;
        const slow = new Promise<Response>((resolve) => { release = resolve; });
        const top = { ...fixture('thread-page-2.json'), page: { next: null, limit: 2 } };
        const { server, store } = setup((server) => server
            .on('thread', () => slow, top)
            .on('viewer', fixture('viewer-guest.json')));
        const sorted = vi.fn();
        store.on('comments:sorted', sorted);

        const first = store.load();
        const second = store.setSort('top');
        await second;
        release(json(fixture('thread.json')));
        await first;

        expect(store.getState().sort).toBe('top');
        expect(uids(selectRoots(store.getState()))).toEqual(['c2', 'c3', 'p3']);
        expect(server.sent('thread')[1]!.url.searchParams.get('sort')).toBe('top');
        expect(sorted).toHaveBeenCalledWith({ apiVersion: 1, sort: 'top' });
    });

    it('loads a deep-linked comment with its ancestors', async () => {
        const { server, store } = setup((server) => server
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-guest.json'))
            .on('comment', fixture('comment.json')));

        await store.load();
        await store.loadComment('r9');
        const state = store.getState();

        // c9 is the oldest root, so it sorts last under newest.
        expect(uids(selectRoots(state))).toEqual(['c1', 'c2', 'c9']);
        expect(uids(selectReplies(state, 'c9'))).toEqual(['r9']);
        expect(state.replies.c9?.count).toBe(4);
        expect(server.sent('viewer')[1]!.url.searchParams.getAll('uids[]')).toEqual(['c9', 'r9']);
    });

    it('places a moderator’s held and reported comments, dropping a reply whose parent isn’t loaded', async () => {
        const { store } = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-moderator.json')));

        await store.load();
        const state = store.getState();

        // h1 is newer than c1, and h2 newer than r1.
        expect(uids(selectRoots(state))).toEqual(['h1', 'c1', 'c2']);
        expect(uids(selectReplies(state, 'c1'))).toEqual(['r1', 'h2']);
        expect(state.comments.h3).toBeUndefined();
        expect(state.comments.h1?.moderation?.actions).toEqual(['approve', 'spam', 'remove']);
        expect(state.moderation?.heldCount).toBe(2);
    });

    it('never changes a state object a listener already has', async () => {
        const { store } = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-member.json')).on('vote', fixture('vote.json')));
        await store.load();
        const before = store.getState();
        const snapshot = structuredClone(before);

        await store.vote('c2', 'upvote');

        expect(before).toEqual(snapshot);
        expect(store.getState()).not.toBe(before);
    });
});

describe('writing', () => {
    async function loaded(configure: (server: Server) => void = () => undefined) {
        const context = setup((server) => {
            server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-guest.json')).on('session', fixture('session.json')).on('protection', { apiVersion: 1, status: 'ok', protection: null });
            configure(server);
        });
        await context.store.load();
        return context;
    }

    it('adds a new root comment at the top under newest, with its permissions and the new count', async () => {
        const { store } = await loaded((server) => server.on('create', fixture('create.json')));
        const created = vi.fn();
        store.on('comments:comment-created', created);

        const response = await store.create({ content: { format: 'plain', text: 'A new comment.' } });
        const state = store.getState();

        expect(response.comment.uid).toBe('n1');
        expect(uids(selectRoots(state))).toEqual(['n1', 'c1', 'c2']);
        expect(state.permissions.n1?.canEdit).toBe(true);
        expect(state.conversation).toMatchObject({ commentCount: 7, rootCount: 4 });
        expect(created).toHaveBeenCalledWith({ apiVersion: 1, commentUid: 'n1', parentUid: null, moderationState: 'approved', replayed: false });
    });

    it('adds a reply under its parent and counts it when the server gives no count', async () => {
        const reply = fixture('create.json');
        reply.comment.parentUid = 'c1';
        reply.conversation = null;
        const { server, store } = await loaded((server) => server.on('create', reply));

        await store.create({ parentUid: 'c1', content: { format: 'plain', text: 'A reply.' } });
        const state = store.getState();

        expect(server.sent('create')[0]!.body).toMatchObject({ ownerId: 109, siteId: 1, parentUid: 'c1' });
        expect(uids(selectReplies(state, 'c1'))).toEqual(['r1', 'n1']);
        expect(state.replies.c1?.count).toBe(3);
        expect(state.conversation?.commentCount).toBe(7);
    });

    it('sends one request for a double click, and adds a replayed comment once', async () => {
        const { server, store } = await loaded((server) => server.on('create', fixture('create.json'), { ...fixture('create.json'), replayed: true }));
        const input = { content: { format: 'plain' as const, text: 'A new comment.' } };

        const [first, second] = await Promise.all([store.create(input), store.create(input)]);
        expect(first).toBe(second);
        expect(server.sent('create')).toHaveLength(1);

        await store.create(input, { operationId: server.sent('create')[0]!.body.operationId });

        expect(uids(selectRoots(store.getState()))).toEqual(['n1', 'c1', 'c2']);
        expect(store.getState().conversation?.rootCount).toBe(4);
    });

    it('marks an action busy while it’s in flight', async () => {
        const { store } = await loaded((server) => server.on('vote', fixture('vote.json')));

        const pending = store.vote('c2', 'upvote');
        expect(store.getState().busy).toEqual({ 'vote:c2': true });
        await pending;

        expect(store.getState().busy).toEqual({});
        expect(store.getState().comments.c2?.interaction?.upvotes).toBe(1);
        expect(store.getState().permissions.c2?.vote).toBe('upvote');
    });

    it('updates a comment’s content and keeps its loaded replies', async () => {
        const updated = fixture('update.json');
        updated.comment.uid = 'c1';
        updated.comment.parentUid = null;
        updated.comment.depth = 0;
        updated.comment.replies = { count: 2, items: [], next: 'cursor-replies-c1' };
        const { store } = await loaded((server) => server.on('update', updated));

        await store.update('c1', { content: { format: 'plain', text: 'Edited.' } });

        expect(store.getState().comments.c1?.content?.text).toBe('Edited.');
        expect(uids(selectReplies(store.getState(), 'c1'))).toEqual(['r1']);
    });

    it('removes a comment without replies, and leaves a placeholder for one with replies', async () => {
        const { store } = await loaded((server) => server.on('delete', fixture('delete.json'), { ...fixture('delete.json'), uid: 'c1', conversation: { commentCount: 4 } }));

        await store.delete('c2');
        expect(uids(selectRoots(store.getState()))).toEqual(['c1']);
        expect(store.getState().conversation).toMatchObject({ commentCount: 5, rootCount: 2 });

        await store.delete('c1');
        expect(store.getState().comments.c1).toMatchObject({ state: 'placeholder', content: null, author: null });
        expect(uids(selectReplies(store.getState(), 'c1'))).toEqual(['r1']);
        expect(store.getState().conversation?.commentCount).toBe(4);
    });

    it('records a report and its withdrawal', async () => {
        const withdrawn = fixture('report.json');
        withdrawn.comment.viewer.reported = false;
        const { server, store } = await loaded((server) => server.on('report', fixture('report.json'), withdrawn));

        await store.report('c2', { reason: 'spam' });
        expect(store.getState().permissions.c2?.reported).toBe(true);

        await store.withdrawReport('c2');
        expect(store.getState().permissions.c2?.reported).toBe(false);
        expect(server.sent('report').map((request) => request.body)).toEqual([
            expect.objectContaining({ uid: 'c2', reason: 'spam', details: null }),
            expect.objectContaining({ uid: 'c2', withdraw: true }),
        ]);
    });

    it('emits its events with the browser events’ detail keys', async () => {
        const withdrawn = fixture('report.json');
        withdrawn.comment.viewer.reported = false;
        const { store } = await loaded((server) => server
            .on('delete', fixture('delete.json'))
            .on('report', fixture('report.json'), withdrawn)
            .on('follow', fixture('follow.json')));
        const seen: unknown[] = [];
        store.on('comments:comment-deleted', (detail) => seen.push(['deleted', detail.commentUid]));
        store.on('comments:report-changed', (detail) => seen.push(['report', detail.commentUid, detail.reported]));
        store.on('comments:follow-changed', (detail) => seen.push(['follow', detail.following]));

        await store.delete('c2');
        await store.report('c1', { reason: 'spam' });
        await store.withdrawReport('c1');
        await store.follow(true);

        expect(seen).toEqual([['deleted', 'c2'], ['report', 'c1', true], ['report', 'c1', false], ['follow', fixture('follow.json').viewer.follow.following]]);
    });

    it('gives the number in a comment’s permalink as commentId, as the browser events do', async () => {
        const thread = fixture('thread.json');
        thread.comments[1].permalink = 'https://example.com/blog/trail-running-knees#comment-1446';
        const context = setup((server) => server.on('thread', thread).on('viewer', fixture('viewer-guest.json')).on('session', fixture('session.json')).on('vote', fixture('vote.json')));
        await context.store.load();
        const changed = vi.fn();
        context.store.on('comments:vote-changed', changed);

        await context.store.vote('c2', 'upvote');

        expect(changed).toHaveBeenCalledWith({ apiVersion: 1, commentUid: 'c2', commentId: '1446', vote: 'upvote' });
    });

    it('names a moderator’s action and the state it left the comment in', async () => {
        const context = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-moderator.json')).on('session', fixture('session.json')).on('moderate', fixture('moderate.json')));
        await context.store.load();
        const moderated = vi.fn();
        context.store.on('comments:comment-moderated', moderated);

        await context.store.moderate('h1', 'spam');

        expect(moderated).toHaveBeenCalledWith({ apiVersion: 1, commentUid: 'h1', commentId: null, moderationAction: 'spam', state: fixture('moderate.json').comment.state });
    });

    it('takes a comment marked as spam out of the thread, and counts an approval', async () => {
        const context = setup((server) => server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-moderator.json')).on('session', fixture('session.json')));
        await context.store.load();
        const approved = fixture('moderate.json');
        approved.comment.uid = 'h2';
        approved.comment.parentUid = 'c1';
        approved.comment.state = 'approved';
        context.server.on('moderate', fixture('moderate.json'), approved);

        await context.store.moderate('h1', 'spam');
        expect(uids(selectRoots(context.store.getState()))).toEqual(['c1', 'c2']);
        expect(context.store.getState().conversation?.commentCount).toBe(6);

        await context.store.moderate('h2', 'approve', { note: 'Fine.' });
        expect(context.store.getState().comments.h2?.state).toBe('approved');
        expect(context.store.getState().conversation?.commentCount).toBe(7);
        expect(context.server.sent('moderate')[1]!.body).toMatchObject({ uid: 'h2', action: 'approve', reason: null, note: 'Fine.' });

        context.server.on('moderate', fixture('moderate-removed.json'));
        await context.store.moderate('h2', 'remove', { reason: 'off-topic' });
        expect(uids(selectReplies(context.store.getState(), 'c1'))).toEqual(['r1']);
        expect(context.store.getState().conversation?.commentCount).toBe(6);
    });

    it('throws an action’s error to its caller and leaves the comments as they were', async () => {
        const { store } = await loaded((server) => server.on('vote', () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'policy', code: 'forbidden', message: 'No.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 403)));
        const before = structuredClone(store.getState().comments);

        await expect(store.vote('c2', 'upvote')).rejects.toMatchObject({ code: 'forbidden' });
        expect(store.getState().comments).toEqual(before);
        expect(store.getState().error).toBeNull();
    });

    it('emits request events around each action', async () => {
        const { store } = await loaded((server) => server.on('vote', fixture('vote.json')));
        const seen: string[] = [];
        store.on('comments:request-start', (detail) => seen.push(`start:${detail.reason}`));
        store.on('comments:request-end', (detail) => seen.push(`end:${detail.reason}`));
        store.on('comments:vote-changed', (detail) => seen.push(`changed:${detail.commentUid}:${String(detail.vote)}`));

        await store.vote('c2', 'upvote');

        expect(seen).toEqual(['start:vote', 'changed:c2:upvote', 'end:vote']);
    });

    it('ignores everything after destroy()', async () => {
        let release: (response: Response) => void = () => undefined;
        const { store } = setup((server) => server.on('thread', () => new Promise<Response>((resolve) => { release = resolve; })).on('viewer', fixture('viewer-guest.json')));
        const listener = vi.fn();
        store.subscribe(listener);

        const pending = store.load();
        listener.mockClear();
        store.destroy();
        release(json(fixture('thread.json')));
        await pending;

        expect(listener).not.toHaveBeenCalled();
    });
});
