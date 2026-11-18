import { afterEach, describe, expect, it, vi } from 'vitest';
import { CommentsRequestError, createCommentsClient, createProtectionFlow, createThreadStore, type EvidenceProvider, type ProtectionData } from '../src/index';
import { fixture } from './support/fixtures';
import { ENDPOINT, json, mockServer } from './support/server';

afterEach(() => {
    vi.useRealTimers();
});

/** A protection answer whose preparation lasts `seconds`, issued now on the fake clock. */
function preparation(name: string, seconds = 600) {
    const issuedAt = Math.floor(Date.now() / 1000);
    const response = fixture('protection.json');
    response.protection.manifest = { ...response.protection.manifest, preparation: name, issuedAt, expiresAt: issuedAt + seconds };
    response.protection.expiresAt = issuedAt + seconds;
    return response;
}

const evidence: EvidenceProvider = async (protection: ProtectionData) => ({
    version: 1,
    preparation: protection.manifest.preparation,
    honeypot: {},
    javascript: '1',
    challenges: {},
});

function setup() {
    const server = mockServer();
    const client = createCommentsClient({ endpoint: ENDPOINT, siteId: 1, fetch: server.fetch });
    const flow = createProtectionFlow({ client, ownerId: 109 });

    return { server, client, flow };
}

describe('the protection flow', () => {
    it('fetches a preparation the first time one is needed, and reuses it until it’s used', async () => {
        const { server, flow } = setup();
        server.on('protection', preparation('prep-a'));

        expect((await flow.ensure())?.manifest.preparation).toBe('prep-a');
        expect((await flow.ensure())?.manifest.preparation).toBe('prep-a');
        expect(server.sent('protection')).toHaveLength(1);
    });

    it('hands a preparation it took to listeners once Protect has checked it, without fetching', async () => {
        const { server, flow } = setup();
        const seen = new Promise<string | undefined>((resolve) => flow.subscribe((protection) => resolve(protection?.manifest.preparation)));
        flow.accept(preparation('prep-later').protection);

        expect(await seen).toBe('prep-later');
        expect(flow.current()?.manifest.preparation).toBe('prep-later');
        expect(server.sent('protection')).toHaveLength(0);
    });

    it('takes the viewer’s preparation without fetching', async () => {
        const { server, flow } = setup();
        flow.accept(preparation('prep-viewer').protection);

        expect((await flow.ensure())?.manifest.preparation).toBe('prep-viewer');
        expect(server.sent('protection')).toHaveLength(0);
    });

    it('refreshes at 80% of the lifetime while active, and not while inactive', async () => {
        vi.useFakeTimers();
        const { server, flow } = setup();
        const seen: string[] = [];
        flow.subscribe((protection) => seen.push(protection?.manifest.preparation ?? 'none'));
        server.on('protection', () => json(preparation('prep-1', 100)), () => json(preparation('prep-2', 100)), () => json(preparation('prep-3', 100)));

        await flow.ensure();
        await vi.advanceTimersByTimeAsync(90_000);
        expect(server.sent('protection')).toHaveLength(1);

        flow.activate();
        await vi.advanceTimersByTimeAsync(0);
        expect(server.sent('protection')).toHaveLength(2);

        await vi.advanceTimersByTimeAsync(79_000);
        expect(server.sent('protection')).toHaveLength(2);
        await vi.advanceTimersByTimeAsync(1_000);
        expect(server.sent('protection')).toHaveLength(3);

        flow.deactivate();
        await vi.advanceTimersByTimeAsync(500_000);
        expect(server.sent('protection')).toHaveLength(3);
        expect(seen).toEqual(['prep-1', 'prep-2', 'prep-3']);
        flow.destroy();
    });

    it('refreshes a stale preparation when asked, such as when a hidden tab becomes visible', async () => {
        vi.useFakeTimers();
        const { server, flow } = setup();
        server.on('protection', () => json(preparation('prep-1', 100)), () => json(preparation('prep-2', 100)));

        await flow.ensure();
        await flow.refreshIfStale();
        expect(server.sent('protection')).toHaveLength(1);

        vi.setSystemTime(Date.now() + 85_000);
        await flow.refreshIfStale();
        expect(flow.current()?.manifest.preparation).toBe('prep-2');
    });

    it('uses a preparation once, then fetches a new one', async () => {
        const { server, flow } = setup();
        server.on('protection', preparation('prep-1'), preparation('prep-2'));
        const send = vi.fn(async (protection: unknown) => protection);

        await flow.run(send, evidence, { action: 'create' });
        await flow.run(send, evidence, { action: 'create' });

        expect(send.mock.calls.map(([protection]) => (protection as { preparation: string }).preparation)).toEqual(['prep-1', 'prep-2']);
    });

    it('checks each preparation with Protect, ignoring a bad one from the viewer and refusing one from the server', async () => {
        const { server, flow } = setup();
        const broken = preparation('prep-bad');
        broken.protection.manifest.honeypot = '<script>';
        server.on('protection', broken);

        flow.accept(broken.protection);
        expect(flow.current()).toBeNull();
        await expect(flow.ensure()).rejects.toMatchObject({ code: 'invalid_response' });
        expect(flow.current()).toBeNull();
    });

    it('fetches a new preparation and retries once after protection_expired', async () => {
        const { server, flow } = setup();
        server.on('protection', preparation('prep-1'), preparation('prep-2'));
        const send = vi.fn()
            .mockRejectedValueOnce(await errorFrom('error-protection-expired.json'))
            .mockResolvedValueOnce('posted');

        await expect(flow.run(send, evidence, { action: 'create' })).resolves.toBe('posted');
        expect(send.mock.calls.map(([protection]) => protection.preparation)).toEqual(['prep-1', 'prep-2']);
    });

    it('never retries challenge_failed or possible_spam', async () => {
        const { server, flow } = setup();
        server.on('protection', preparation('prep-1'));
        const send = vi.fn().mockRejectedValue(await errorFrom('error-challenge-failed.json'));

        await expect(flow.run(send, evidence, { action: 'create' })).rejects.toMatchObject({ code: 'challenge_failed' });
        expect(send).toHaveBeenCalledTimes(1);
        expect(server.sent('protection')).toHaveLength(1);
    });

    it('sends without evidence when the site doesn’t use protection, or no provider is given', async () => {
        const { server, flow } = setup();
        server.on('protection', { apiVersion: 1, status: 'ok', protection: null });
        const send = vi.fn(async (protection: unknown) => protection);

        await flow.run(send, evidence, { action: 'create' });
        await flow.run(send, evidence, { action: 'create' });
        await flow.run(send, undefined, { action: 'create' });

        expect(send.mock.calls).toEqual([[undefined], [undefined], [undefined]]);
        expect(server.sent('protection')).toHaveLength(1);
    });
});

describe('protection in the thread store', () => {
    it('posts with evidence from the viewer’s preparation, then retries an expired one with the same operationId', async () => {
        const server = mockServer()
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-member.json'))
            .on('protection', preparation('prep-fresh'))
            .on('create', () => json(fixture('error-protection-expired.json'), 422), fixture('create.json'));
        const provider = vi.fn(evidence);
        const store = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch }), { ownerId: 109, evidence: provider });

        await store.load();
        await store.create({ content: { format: 'plain', text: 'Hello' } });
        const creates = server.sent('create');

        expect(provider.mock.calls.map(([protection, context]) => [protection.manifest.preparation, context])).toEqual([
            ['prep-1', { action: 'create', parentUid: null }],
            ['prep-fresh', { action: 'create', parentUid: null }],
        ]);
        expect(creates.map((request) => request.body.protection.preparation)).toEqual(['prep-1', 'prep-fresh']);
        expect(creates[1]!.body.operationId).toBe(creates[0]!.body.operationId);
        expect(server.sent('protection')[0]!.url.searchParams.get('ownerId')).toBe('109');
        expect(store.getState().comments.n1).toBeDefined();
    });

    it('keeps a separate flow for editing each comment', async () => {
        const server = mockServer()
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-member.json'))
            .on('protection', preparation('prep-edit'))
            .on('update', fixture('update.json'));
        const store = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch }), { ownerId: 109, evidence });

        await store.load();
        await store.update('r1', { content: { format: 'plain', text: 'Edited.' } });

        expect(store.protection('r1')).not.toBe(store.protection());
        expect(server.sent('protection')[0]!.url.searchParams.get('commentUid')).toBe('r1');
        expect(server.sent('update')[0]!.body.protection.preparation).toBe('prep-edit');
    });
});

async function errorFrom(name: string) {
    const server = mockServer().on('create', () => json(fixture(name), 422)).on('session', fixture('session.json'));
    const client = createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch });

    return client.create({ ownerId: 109, content: { format: 'plain', text: 'x' } }).catch((error: unknown) => error);
}

describe('protection retries and sharing', () => {
    it('fetches a new preparation and retries once after protection_required', async () => {
        const { server, flow } = setup();
        server.on('protection', preparation('prep-1'), preparation('prep-2'));
        const required = new CommentsRequestError([{ category: 'protection', code: 'protection_required', message: 'Complete verification.', fieldErrors: {}, recoverable: true, retryAfter: null }]);
        const send = vi.fn().mockRejectedValueOnce(required).mockResolvedValueOnce('posted');

        await expect(flow.run(send, evidence, { action: 'create' })).resolves.toBe('posted');
        expect(send.mock.calls.map(([protection]) => protection.preparation)).toEqual(['prep-1', 'prep-2']);
    });

    it('never retries possible_spam', async () => {
        const { server, flow } = setup();
        server.on('protection', preparation('prep-1'));
        const spam = new CommentsRequestError([{ category: 'protection', code: 'possible_spam', message: 'This looks like spam.', fieldErrors: {}, recoverable: false, retryAfter: null }]);
        const send = vi.fn().mockRejectedValue(spam);

        await expect(flow.run(send, evidence, { action: 'create' })).rejects.toMatchObject({ code: 'possible_spam' });
        expect(send).toHaveBeenCalledTimes(1);
        expect(server.sent('protection')).toHaveLength(1);
    });

    it('never hands one preparation to two forms submitting together', async () => {
        const { server, flow } = setup();
        server.on('protection', preparation('prep-1'), preparation('prep-2'));
        const send = vi.fn(async (protection: unknown) => protection);

        const results = await Promise.all([flow.run(send, evidence, { action: 'create' }), flow.run(send, evidence, { action: 'create', parentUid: 'c1' })]);

        expect(results.map((result) => (result as { preparation: string }).preparation).sort()).toEqual(['prep-1', 'prep-2']);
        expect(server.sent('protection')).toHaveLength(2);
    });

    it('gives three uploads run together a preparation each, used once (a guest’s files)', async () => {
        const { server, flow } = setup();
        let n = 0;
        server.on('protection', () => json(preparation(`prep-${++n}`)));

        const send = vi.fn(async (sent: { preparation: string } | undefined) => sent?.preparation);
        const used = await Promise.all([1, 2, 3].map(() => flow.run(send as never, evidence, { action: 'upload' })));

        expect(new Set(used).size).toBe(3);
        expect(used.every((preparation) => typeof preparation === 'string')).toBe(true);
    });

    it('tries a failed timed refresh again while active, and recovers', async () => {
        vi.useFakeTimers();
        const { server, flow } = setup();
        server.on('protection', () => json(preparation('prep-1', 100)), () => Promise.reject(new TypeError('Failed to fetch')), () => json(preparation('prep-2', 100)));

        await flow.ensure();
        flow.activate();
        await vi.advanceTimersByTimeAsync(80_000);
        expect(server.sent('protection')).toHaveLength(2);
        expect(flow.current()?.manifest.preparation).toBe('prep-1');

        await vi.advanceTimersByTimeAsync(30_000);
        expect(server.sent('protection')).toHaveLength(3);
        expect(flow.current()?.manifest.preparation).toBe('prep-2');
        flow.destroy();
    });

    it('lets two creates in one store each post with their own preparation', async () => {
        const second = fixture('create.json');
        second.comment.uid = 'n2';
        const server = mockServer()
            .on('thread', fixture('thread.json'))
            .on('viewer', fixture('viewer-member.json'))
            .on('protection', preparation('prep-a'), preparation('prep-b'))
            .on('create', fixture('create.json'), second);
        const store = createThreadStore(createCommentsClient({ endpoint: ENDPOINT, fetch: server.fetch }), { ownerId: 109, evidence });

        await store.load();
        await Promise.all([
            store.create({ content: { format: 'plain', text: 'One' } }),
            store.create({ parentUid: 'c1', content: { format: 'plain', text: 'Two' } }),
        ]);

        expect(new Set(server.sent('create').map((request) => request.body.protection.preparation)).size).toBe(2);
    });
});
