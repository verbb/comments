import { describe, expect, it } from 'vitest';
import { createCommentsClient, createThreadStore } from '../src/index';
import { graphqlTransport } from '../src/graphql';
import { fieldArguments, graphqlLiteral } from '../src/graphql-literal';
import { fixture } from './support/fixtures';
import { ENDPOINT, GRAPHQL_ENDPOINT, json, mockServer } from './support/server';

type Body = Record<string, unknown>;

/** The GraphQL read's answer for a REST fixture: `{ apiVersion, data }` with the rest inside. */
function wrapped(field: string, name: string) {
    const { apiVersion, status: _status, ...data } = fixture(name) as Body;
    return { data: { [field]: { apiVersion, data } } };
}

function setup() {
    const server = mockServer();
    const client = createCommentsClient({ endpoint: ENDPOINT, siteId: 1, transport: graphqlTransport({ endpoint: GRAPHQL_ENDPOINT, fetch: server.fetch }), fetch: server.fetch });

    return { server, client };
}

describe('GraphQL reads', () => {
    it('use the four queries and answer in the REST shapes', async () => {
        const { server, client } = setup();
        server.on('commentsThread', wrapped('commentsThread', 'thread.json'))
            .on('commentsReplies', wrapped('commentsReplies', 'replies.json'))
            .on('commentsComment', wrapped('commentsComment', 'comment.json'))
            .on('commentsViewer', wrapped('commentsViewer', 'viewer-member.json'));

        expect(await client.thread({ ownerId: 109, sort: 'top', limit: 2 })).toEqual(fixture('thread.json'));
        expect(await client.replies({ parentUid: 'c1', cursor: 'cursor-replies-c1' })).toEqual(fixture('replies.json'));
        expect(await client.comment('r9')).toEqual(fixture('comment.json'));
        expect(await client.viewer({ ownerId: 109, uids: ['r1'] })).toEqual(fixture('viewer-member.json'));

        const [thread, replies, , viewer] = server.requests;

        expect(thread!.url.toString()).toBe(GRAPHQL_ENDPOINT);
        expect(thread!.body.variables).toEqual({ ownerId: 109, siteId: 1, sort: 'top', limit: 2 });
        expect(thread!.credentials).toBe('omit');
        expect(replies!.body.variables).toEqual({ parentUid: 'c1', cursor: 'cursor-replies-c1' });
        expect(viewer!.body.variables).toEqual({ ownerId: 109, siteId: 1, uids: ['r1'] });
        expect(viewer!.credentials).toBe('same-origin');
        expect(viewer!.headers.get('X-Comments-Profile')).toBe('same-origin-browser');
    });

    it('read a comment and the viewer by the number in a permalink', async () => {
        const { server, client } = setup();
        server.on('commentsComment', wrapped('commentsComment', 'comment.json')).on('commentsViewer', wrapped('commentsViewer', 'viewer-member.json'));

        await client.comment({ id: 1446 });
        await client.viewer({ ownerId: 109, ids: [1446] });
        const [comment, viewer] = server.requests;

        expect(comment!.body.query).toContain('$id: Int');
        expect(comment!.body.variables).toEqual({ id: 1446 });
        expect(viewer!.body.query).toContain('$ids: [Int]');
        expect(viewer!.body.variables).toEqual({ ownerId: 109, siteId: 1, ids: [1446] });
    });

    it('give a thread store exactly the state REST gives it', async () => {
        const rest = mockServer().on('thread', fixture('thread.json')).on('viewer', fixture('viewer-moderator.json'));
        const graphql = mockServer()
            .on('commentsThread', wrapped('commentsThread', 'thread.json'))
            .on('commentsViewer', wrapped('commentsViewer', 'viewer-moderator.json'));
        const restStore = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: rest.fetch }), { ownerId: 109 });
        const graphqlStore = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, transport: graphqlTransport({ endpoint: GRAPHQL_ENDPOINT, fetch: graphql.fetch }), fetch: graphql.fetch }), { ownerId: 109 });

        await Promise.all([restStore.load(), graphqlStore.load()]);

        expect(graphqlStore.getState()).toEqual(restStore.getState());
        expect(restStore.getState().roots.length).toBeGreaterThan(0);
    });

    it('refuse a read in another API version', async () => {
        const { server, client } = setup();
        const answer = wrapped('commentsThread', 'thread.json') as unknown as { data: { commentsThread: Body } };
        answer.data.commentsThread.apiVersion = 2;
        server.on('commentsThread', answer);

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'unsupported_api_version' });
    });

    it('turn GraphQL errors into request errors, reading the code from extensions', async () => {
        const { server, client } = setup();
        server.on('commentsThread', { errors: [{ message: 'That entry can’t be shown.', extensions: { code: 'not_found', category: 'not_found' } }], data: { commentsThread: null } });

        await expect(client.thread({ ownerId: 9 })).rejects.toMatchObject({ code: 'not_found', category: 'not_found', message: 'That entry can’t be shown.' });

        server.on('commentsThread', { errors: [{ message: 'Cannot query field "commentsThread".' }] });

        await expect(client.thread({ ownerId: 9 })).rejects.toMatchObject({ code: 'server_error', category: 'server' });
    });

    it('send what GraphQL has no query for through REST, with the headless protection channel', async () => {
        const { server, client } = setup();
        server.on('protection', fixture('protection.json')).on('structured-data', fixture('structured-data.json'));

        await client.protection({ ownerId: 109 });
        await client.structuredData({ ownerId: 109 });

        expect(server.requests.map((request) => request.url.toString())).toEqual([
            `${ENDPOINT}/protection?ownerId=109&siteId=1&channel=headless`,
            `${ENDPOINT}/structured-data?ownerId=109&siteId=1`,
        ]);
    });
});

describe('GraphQL writes', () => {
    it('create through createComment, with Protect’s evidence and custom fields as arguments', async () => {
        const { server, client } = setup();
        const created = fixture('create.json');
        server.on('session', fixture('session.json')).on('createComment', { data: { createComment: { commentsData: created.comment } } });

        const response = await client.create({
            ownerId: 109,
            parentUid: 'c1',
            content: { format: 'rich', document: { type: 'doc', content: [] } },
            guest: { name: 'Jo', email: 'jo@example.com', notifyReplies: true },
            fields: { rating: 5, location: 'Rotorua "North"', tags: ['a', 'b'] },
            protection: { version: 1, preparation: 'prep-1', honeypot: {}, javascript: '1', challenges: { 'recaptcha-v3': 'token-r', turnstile: 'token-t' } },
        }, { operationId: 'op-g' });
        const [request] = server.sent('createComment');

        expect(request!.body.query).toContain('createComment(operationId: $operationId');
        expect(request!.body.query).toContain(', rating: 5, location: "Rotorua \\"North\\"", tags: ["a", "b"]) { commentsData }');
        expect(request!.body.variables).toEqual({
            operationId: 'op-g',
            comment: null,
            contentFormat: 'rich',
            contentDocument: '{"type":"doc","content":[]}',
            protection: { version: 1, preparation: 'prep-1', challenges: { recaptchaV3: 'token-r', turnstile: 'token-t' } },
            ownerId: 109,
            siteId: 1,
            parentUid: 'c1',
            name: 'Jo',
            email: 'jo@example.com',
            notifyReplies: true,
        });
        expect(request!.headers.get('X-CSRF-Token')).toBe('csrf-session');
        expect(response).toEqual({ apiVersion: 1, status: 'ok', replayed: false, comment: created.comment, moderationState: 'approved', conversation: null, announcement: null });
    });

    it('map every other write onto its mutation and back to the REST shape', async () => {
        const { server, client } = setup();
        const updated = fixture('update.json').comment;
        const voted = { ...fixture('thread.json').comments[1], viewer: fixture('vote.json').comment.viewer, interaction: { upvotes: 1, downvotes: 0 } };
        const moderated = fixture('moderate.json').comment;
        server.on('session', fixture('session.json'))
            .on('saveComment', { data: { saveComment: { commentsData: updated } } })
            .on('deleteComment', { data: { deleteComment: true } })
            .on('voteComment', { data: { voteComment: { comment: { commentsData: voted } } } })
            .on('flagComment', { data: { flagComment: null } })
            .on('subscribeComment', { data: { subscribeComment: 'followed' } })
            .on('moderateComment', { data: { moderateComment: { commentsData: moderated } } });

        expect(await client.update({ uid: 'r1', content: { format: 'plain', text: 'Edited.' } })).toMatchObject({ comment: updated, moderationState: 'approved' });
        expect(await client.delete({ uid: 'c2' }, { operationId: 'op-r' })).toEqual({ apiVersion: 1, status: 'ok', replayed: false, announcement: null, uid: 'c2', conversation: null });
        expect((await client.vote({ uid: 'c2', vote: null })).comment).toEqual({ uid: 'c2', interaction: { upvotes: 1, downvotes: 0 }, viewer: voted.viewer });
        expect((await client.report({ uid: 'c2', withdraw: true })).comment).toEqual({ uid: 'c2' });
        expect(await client.follow({ ownerId: 109, following: true })).toMatchObject({ viewer: { follow: { available: true, following: true, needsEmail: false } }, outcome: 'followed' });
        expect((await client.moderate({ uid: 'h1', action: 'spam' })).comment.state).toBe('spam');

        expect(server.sent('saveComment')[0]!.body.variables).toMatchObject({ uid: 'r1', comment: 'Edited.', contentFormat: 'plain', contentDocument: null });
        expect(server.sent('voteComment')[0]!.body.variables).toMatchObject({ uid: 'c2', vote: null });
        expect(server.sent('flagComment')[0]!.body.variables).toMatchObject({ uid: 'c2', withdraw: true });
        expect(server.sent('subscribeComment')[0]!.body.variables).toMatchObject({ ownerId: 109, siteId: 1, following: true });
        expect(server.sent('moderateComment')[0]!.body.variables).toMatchObject({ uid: 'h1', action: 'spam' });
    });

    it('upload through REST', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('upload', () => json({ id: 1 }));

        await client.upload({ file: new Blob(['x']), filename: 'a.txt' });

        expect(server.sent('upload')[0]!.url.toString()).toBe(`${ENDPOINT}/upload`);
    });
});

describe('choosing GraphQL', () => {
    it('takes a transport, not a name', () => {
        expect(() => createCommentsClient({ endpoint: ENDPOINT, transport: 'graphql' as never })).toThrow('graphqlTransport()');
    });
});

describe('GraphQL literals', () => {
    it('write JSON values the way GraphQL reads them', () => {
        expect(graphqlLiteral({ a: [1, true, null], b: 'x\ny' })).toBe('{a: [1, true, null], b: "x\\ny"}');
        expect(fieldArguments({ rating: 4 })).toBe(', rating: 4');
        expect(fieldArguments(undefined)).toBe('');
    });

    it('refuse a handle that would break out of the argument list', () => {
        expect(() => fieldArguments({ 'rating) { evil }': 1 })).toThrow(expect.objectContaining({ code: 'invalid_request', detail: expect.stringContaining('bad handle') }));
        expect(() => graphqlLiteral({ 'a b': 1 })).toThrow(expect.objectContaining({ code: 'invalid_request', detail: expect.stringContaining('bad key') }));
    });
});
