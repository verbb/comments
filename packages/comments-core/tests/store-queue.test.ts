import { describe, expect, it, vi } from 'vitest';
import { cookieSession, createCommentsClient, createThreadStore, selectReplies, selectRoots } from '../src/index';
import { fixture } from './support/fixtures';
import { ENDPOINT, json, mockServer } from './support/server';

type Server = ReturnType<typeof mockServer>;

const uids = (comments: Array<{ uid: string }>) => comments.map((comment) => comment.uid);

/** A response held open until `release()` is called. */
function held(payload: () => unknown) {
    let release: () => void = () => undefined;
    const promise = new Promise<Response>((resolve) => { release = () => resolve(json(payload())); });

    return { respond: () => promise, release: () => release() };
}

async function loaded(viewer = 'viewer-guest.json', configure: (server: Server) => void = () => undefined, auth = cookieSession()) {
    const server = mockServer()
        .on('thread', fixture('thread.json'))
        .on('viewer', fixture(viewer))
        .on('session', fixture('session.json'))
        .on('protection', { apiVersion: 1, status: 'ok', protection: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'csrf-protection' } });
    configure(server);
    const store = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch, auth }), { ownerId: 109, siteId: 1, limit: 2 });
    await store.load();

    return { server, store };
}

describe('actions on the same comment', () => {
    it('send an unlike made while the like is in flight, after it, so the last one wins', async () => {
        const like = held(() => fixture('vote.json'));
        const unlike = fixture('vote.json');
        unlike.comment.viewer.vote = null;
        unlike.comment.interaction.upvotes = 0;
        const { server, store } = await loaded('viewer-member.json', (server) => server.on('vote', like.respond, unlike));

        const first = store.vote('c2', 'upvote');
        const second = store.vote('c2', null);
        await vi.waitFor(() => expect(server.sent('vote')).toHaveLength(1));
        await new Promise((resolve) => setTimeout(resolve, 5));
        expect(server.sent('vote')).toHaveLength(1);

        like.release();
        await Promise.all([first, second]);

        expect(server.sent('vote').map((request) => request.body.vote)).toEqual(['upvote', null]);
        expect(store.getState().permissions.c2?.vote).toBeNull();
        expect(store.getState().comments.c2?.interaction?.upvotes).toBe(0);
        expect(store.getState().busy).toEqual({});
    });

    it('send a withdrawal made during a report', async () => {
        const report = held(() => fixture('report.json'));
        const withdrawn = fixture('report.json');
        withdrawn.comment.viewer.reported = false;
        const { server, store } = await loaded('viewer-member.json', (server) => server.on('report', report.respond, withdrawn));

        const first = store.report('c2', { reason: 'spam' });
        const second = store.withdrawReport('c2');
        report.release();
        await Promise.all([first, second]);

        expect(server.sent('report').map((request) => request.body.withdraw ?? false)).toEqual([false, true]);
        expect(store.getState().permissions.c2?.reported).toBe(false);
    });

    it('post a second comment with different text instead of claiming the first was it', async () => {
        const second = fixture('create.json');
        second.comment.uid = 'n2';
        second.comment.content.text = 'Something else.';
        second.conversation.commentCount = 8;
        const first = held(() => fixture('create.json'));
        const { server, store } = await loaded('viewer-guest.json', (server) => server.on('create', first.respond, second));

        const a = store.create({ content: { format: 'plain', text: 'A new comment.' } });
        const b = store.create({ content: { format: 'plain', text: 'Something else.' } });
        first.release();
        const [one, two] = await Promise.all([a, b]);

        expect(server.sent('create').map((request) => request.body.content.text)).toEqual(['A new comment.', 'Something else.']);
        expect(new Set(server.sent('create').map((request) => request.body.operationId)).size).toBe(2);
        expect([one.comment.uid, two.comment.uid]).toEqual(['n1', 'n2']);
        expect(uids(selectRoots(store.getState()))).toEqual(['n2', 'n1', 'c1', 'c2']);
        expect(store.getState().conversation?.commentCount).toBe(8);
    });

    it('send one request for the same input asked twice, even while another waits ahead of it', async () => {
        const like = held(() => fixture('vote.json'));
        const { server, store } = await loaded('viewer-member.json', (server) => server.on('vote', like.respond, fixture('vote.json')));

        const first = store.vote('c2', 'upvote');
        const second = store.vote('c2', null);
        const third = store.vote('c2', null);
        expect(third).toBe(second);
        like.release();
        await Promise.all([first, second]);

        expect(server.sent('vote')).toHaveLength(2);
    });
});

describe('private data after signing out', () => {
    it('is cleared by refreshViewer() when a moderator becomes a guest', async () => {
        const { store, server } = await loaded('viewer-moderator.json');
        server.on('viewer', fixture('viewer-guest.json'));
        expect(store.getState().comments.h1?.state).toBe('held');
        expect(store.getState().viewer?.isModerator).toBe(true);

        await store.refreshViewer();
        const state = store.getState();

        expect(uids(selectRoots(state))).toEqual(['c1', 'c2']);
        expect(uids(selectReplies(state, 'c1'))).toEqual(['r1']);
        expect(state.comments.h1).toBeUndefined();
        expect(state.comments.h2).toBeUndefined();
        expect(state.permissions).toEqual({});
        expect(state.moderation).toBeNull();
        expect(state.viewer?.isGuest).toBe(true);
        expect(Object.values(state.comments).some((comment) => comment.moderation)).toBe(false);
    });

    it('puts a reported comment back to approved and drops stale edit rights', async () => {
        const reported = fixture('viewer-member.json');
        reported.extraComments = [{ ...fixture('thread.json').comments[1], state: 'reported', moderation: { reviewLabel: 'Reported', openReportCount: 1, actions: ['keep', 'remove'] } }];
        reported.comments.c2 = { ...reported.comments.c2, canEdit: true, canDelete: true };
        const { store, server } = await loaded('viewer-guest.json', (server) => server.on('viewer', reported));
        expect(store.getState().comments.c2?.state).toBe('reported');
        server.on('viewer', fixture('viewer-guest.json'));

        await store.refreshViewer();

        expect(store.getState().comments.c2).toMatchObject({ state: 'approved' });
        expect(store.getState().comments.c2).not.toHaveProperty('moderation');
        expect(store.getState().permissions.c2).toBeUndefined();
    });

    it('is cleared at once when the sign-in provider resets, then read again', async () => {
        const auth = cookieSession();
        const { store, server } = await loaded('viewer-moderator.json', () => undefined, auth);
        const guest = held(() => fixture('viewer-guest.json'));
        server.on('viewer', guest.respond);

        auth.reset?.();
        expect(store.getState().comments.h1).toBeUndefined();
        expect(store.getState().viewer).toBeNull();
        expect(store.getState().moderation).toBeNull();

        guest.release();
        await vi.waitFor(() => expect(store.getState().viewer?.isGuest).toBe(true));
        expect(server.sent('viewer')).toHaveLength(2);
    });

    it('ignores a private answer asked for before the reset', async () => {
        const auth = cookieSession();
        const late = held(() => fixture('viewer-moderator.json'));
        const { store, server } = await loaded('viewer-guest.json', (server) => server.on('replies', fixture('replies.json')), auth);
        server.on('viewer', late.respond, fixture('viewer-guest.json'));

        const replies = store.loadReplies('c1');
        await vi.waitFor(() => expect(server.sent('viewer')).toHaveLength(2));
        auth.reset?.();
        late.release();
        await replies;
        await vi.waitFor(() => expect(server.sent('viewer')).toHaveLength(3));

        expect(store.getState().comments.h1).toBeUndefined();
        expect(store.getState().moderation).toBeNull();
    });
});

describe('loading during a sort change', () => {
    it('never hands a later load more the one for the old sort', async () => {
        const more = held(() => fixture('thread-page-2.json'));
        const top = { ...fixture('thread.json'), conversation: { ...fixture('thread.json').conversation, sort: 'top' } };
        const server = mockServer()
            .on('viewer', fixture('viewer-guest.json'))
            .on('thread', fixture('thread.json'), more.respond, top, fixture('thread-page-2.json'));
        const store = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch }), { ownerId: 109, limit: 2 });
        await store.load();

        const stale = store.loadMore();
        await store.setSort('top');
        const fresh = store.loadMore();
        more.release();
        await Promise.all([stale, fresh]);

        expect(server.sent('thread').map((request) => `${request.url.searchParams.get('sort')}:${request.url.searchParams.get('cursor')}`))
            .toEqual(['newest:null', 'newest:cursor-roots-2', 'top:null', 'top:cursor-roots-2']);
        expect(store.getState().sort).toBe('top');
        expect(uids(selectRoots(store.getState()))).toEqual(['c1', 'c2', 'c3', 'p3']);
    });
});

describe('counts after a replay', () => {
    const dropped = () => Promise.reject(new TypeError('Failed to fetch'));

    it('count an approval whose first answer was lost', async () => {
        const approved = fixture('moderate.json');
        Object.assign(approved.comment, { uid: 'h2', parentUid: 'c1', state: 'approved' });
        const { store } = await loaded('viewer-moderator.json', (server) => server.on('moderate', dropped, { ...approved, replayed: true }));

        await store.moderate('h2', 'approve');

        expect(store.getState().comments.h2?.state).toBe('approved');
        expect(store.getState().conversation?.commentCount).toBe(7);
    });

    it('count a removal whose first answer was lost, once', async () => {
        const replay = { ...fixture('delete.json'), conversation: null, replayed: true };
        const { store } = await loaded('viewer-member.json', (server) => server.on('delete', dropped, replay, replay));

        await store.delete('c2');
        expect(store.getState().conversation?.commentCount).toBe(5);

        await store.delete('c2', { operationId: 'again' });
        expect(store.getState().conversation?.commentCount).toBe(5);
    });
});

describe('destroy()', () => {
    it('lets a write in flight finish for its caller without changing the state, and rejects one still waiting', async () => {
        const create = held(() => fixture('create.json'));
        const { store, server } = await loaded('viewer-guest.json', (server) => server.on('create', create.respond));
        const listener = vi.fn();
        const created = vi.fn();
        store.subscribe(listener);
        store.on('comments:comment-created', created);

        const running = store.create({ content: { format: 'plain', text: 'A new comment.' } });
        const waiting = store.create({ content: { format: 'plain', text: 'Another.' } });
        await vi.waitFor(() => expect(server.sent('create')).toHaveLength(1));
        const before = store.getState();
        listener.mockClear();
        store.destroy();
        create.release();

        await expect(running).resolves.toMatchObject({ comment: { uid: 'n1' } });
        await expect(waiting).rejects.toMatchObject({ code: 'invalid_request' });
        expect(server.sent('create')).toHaveLength(1);
        expect(store.getState()).toBe(before);
        expect(listener).not.toHaveBeenCalled();
        expect(created).not.toHaveBeenCalled();
    });
});

describe('placement next to a placeholder', () => {
    it('passes over a placeholder when placing a pending comment', async () => {
        const page = fixture('thread.json');
        page.comments = [fixture('thread-page-2.json').comments[2], ...page.comments];
        const viewer = fixture('viewer-member.json');
        viewer.extraComments[0].createdAt = '2026-11-09T20:30:00+00:00';
        const server = mockServer().on('thread', page).on('viewer', viewer);
        const store = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch }), { ownerId: 109 });

        await store.load();

        // p1 is older than c1 and newer than c2; the placeholder at the top has no date and stays put.
        expect(uids(selectRoots(store.getState()))).toEqual(['p3', 'c1', 'p1', 'c2']);
    });
});
