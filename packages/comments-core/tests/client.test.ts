import { describe, expect, it, vi } from 'vitest';
import { apiUrl } from '../src/url';
import {
    COMMENTS_API_VERSION,
    COMMENTS_ERROR_CODES,
    CommentsRequestError,
    assertApiVersion,
    cookieSession,
    createCommentsClient,
    type FieldUploadResponse,
    type SignInProvider,
} from '../src/index';
import { fixture } from './support/fixtures';
import { ENDPOINT, json, mockServer } from './support/server';

function setup(options: Partial<Parameters<typeof createCommentsClient>[0]> = {}) {
    const server = mockServer();
    const client = createCommentsClient({ endpoint: ENDPOINT, siteId: 1, fetch: server.fetch, ...options });

    return { server, client };
}

describe('apiUrl', () => {
    it('appends the action and query to an absolute endpoint', () => {
        expect(apiUrl('https://example.com/actions/comments/api/', 'thread', { ownerId: 109, sort: 'top', cursor: null, limit: undefined }))
            .toBe('https://example.com/actions/comments/api/thread?ownerId=109&sort=top');
    });

    it('keeps relative and protocol-relative endpoints relative', () => {
        expect(apiUrl('/actions/comments/api', 'viewer', { uids: ['a', 'b'] })).toBe('/actions/comments/api/viewer?uids%5B%5D=a&uids%5B%5D=b');
        expect(apiUrl('//cms.example.com/actions/comments/api', 'thread')).toBe('//cms.example.com/actions/comments/api/thread');
    });

    it('keeps Craft’s query-string route when pretty URLs are off', () => {
        expect(apiUrl('https://example.com/index.php?p=actions/comments/api', 'replies', { commentUid: 'c1' }))
            .toBe('https://example.com/index.php?p=actions%2Fcomments%2Fapi%2Freplies&commentUid=c1');
    });
});

describe('public reads', () => {
    it('send no credentials or sign-in headers, so any cache can hold them', async () => {
        const { server, client } = setup();
        server.on('thread', fixture('thread.json'));

        const thread = await client.thread({ ownerId: 109, sort: 'newest', limit: 2 });
        const [request] = server.sent('thread');

        expect(thread.comments.map((comment) => comment.uid)).toEqual(['c1', 'c2']);
        expect(request!.url.toString()).toBe(`${ENDPOINT}/thread?ownerId=109&siteId=1&sort=newest&limit=2`);
        expect(request!.method).toBe('GET');
        expect(request!.credentials).toBe('omit');
        expect(request!.headers.get('X-Comments-Api-Version')).toBe('1');
        expect(request!.headers.has('X-Comments-Profile')).toBe(false);
        expect(request!.headers.has('X-CSRF-Token')).toBe(false);
    });

    it('cover replies, a single comment and structured data', async () => {
        const { server, client } = setup();
        server.on('replies', fixture('replies.json')).on('comment', fixture('comment.json')).on('structured-data', fixture('structured-data.json'));

        await client.replies({ parentUid: 'c1', cursor: 'cursor-replies-c1', limit: 20 });
        await client.comment('r9');
        const data = await client.structuredData({ ownerUri: 'blog/trail-running-knees' });

        expect(server.requests.map((request) => `${request.url.pathname.split('/').pop()}${request.url.search}`)).toEqual([
            'replies?parentUid=c1&cursor=cursor-replies-c1&limit=20',
            'comment?uid=r9',
            'structured-data?ownerUri=blog%2Ftrail-running-knees&siteId=1',
        ]);
        expect(server.requests.every((request) => request.credentials === 'omit')).toBe(true);
        expect(data.structuredData['@type']).toBe('Article');
    });
});

describe('private reads and the same-origin profile', () => {
    it('send the session cookie and the profile header', async () => {
        const { server, client } = setup();
        server.on('viewer', fixture('viewer-member.json'));

        await client.viewer({ ownerId: 109, sort: 'newest', uids: ['r1'] });
        const [request] = server.sent('viewer');

        expect(request!.credentials).toBe('same-origin');
        expect(request!.headers.get('X-Comments-Profile')).toBe('same-origin-browser');
        expect(request!.url.searchParams.getAll('uids[]')).toEqual(['r1']);
    });

    it('include credentials for a sibling subdomain', async () => {
        const { server, client } = setup({ auth: cookieSession({ profile: 'same-site-credentialed' }) });
        server.on('viewer', fixture('viewer-guest.json'));

        await client.viewer({ ownerId: 109 });

        expect(server.requests[0]!.credentials).toBe('include');
        expect(server.requests[0]!.headers.get('X-Comments-Profile')).toBe('same-site-credentialed');
    });

    it('ask for protection with the transport’s channel, keeping the CSRF token it carries', async () => {
        const { server, client } = setup();
        server.on('protection', fixture('protection.json')).on('vote', fixture('vote.json'));

        const response = await client.protection({ ownerId: 109, commentUid: 'r1' });
        await client.vote({ uid: 'c2', vote: 'upvote' });

        expect(server.requests[0]!.url.search).toBe('?ownerId=109&siteId=1&commentUid=r1&channel=browser');
        expect(response.protection?.manifest.preparation).toBe('prep-2');
        expect(server.sent('vote')[0]!.headers.get('X-CSRF-Token')).toBe('csrf-protection');
    });
});

describe('writes', () => {
    it('send JSON with an operationId and the CSRF token from the viewer read', async () => {
        const { server, client } = setup();
        server.on('viewer', fixture('viewer-member.json')).on('vote', fixture('vote.json'));

        await client.viewer({ ownerId: 109 });
        await client.vote({ uid: 'c2', vote: 'upvote' });
        const [request] = server.sent('vote');

        expect(request!.method).toBe('POST');
        expect(request!.headers.get('Content-Type')).toBe('application/json');
        expect(request!.headers.get('X-CSRF-Token')).toBe('csrf-member');
        expect(request!.body).toEqual({ operationId: expect.stringMatching(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/), uid: 'c2', vote: 'upvote' });
        expect(server.sent('session')).toHaveLength(0);
    });

    it('give each write its own operationId unless one is passed', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('report', fixture('report.json'));

        await client.report({ uid: 'c2', reason: 'spam' });
        await client.report({ uid: 'c2', reason: 'spam' });
        await client.report({ uid: 'c2', reason: 'spam' }, { operationId: 'op-mine' });
        const ids = server.sent('report').map((request) => request.body.operationId);

        expect(new Set(ids).size).toBe(3);
        expect(ids[2]).toBe('op-mine');
    });

    it('fetch a CSRF token from the session read before a guest’s first write, once for writes started together', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('vote', fixture('vote.json'));

        await Promise.all([client.vote({ uid: 'c1', vote: 'upvote' }), client.vote({ uid: 'c2', vote: 'upvote' })]);

        expect(server.sent('session')).toHaveLength(1);
        expect(server.sent('vote').map((request) => request.headers.get('X-CSRF-Token'))).toEqual(['csrf-session', 'csrf-session']);
    });

    it('get a new CSRF token and retry once with the same operationId after csrf_failed', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json'))
            .on('vote', () => json(fixture('error-csrf.json'), 403), fixture('vote.json'));

        await client.vote({ uid: 'c2', vote: 'upvote' });
        const votes = server.sent('vote');

        expect(server.sent('session')).toHaveLength(2);
        expect(votes).toHaveLength(2);
        expect(votes[1]!.body.operationId).toBe(votes[0]!.body.operationId);
    });

    it('send a write once more with the same operationId after a dropped connection, and take the replay', async () => {
        const replayed = { ...fixture('create.json'), replayed: true };
        const { server, client } = setup();
        server.on('session', fixture('session.json'))
            .on('create', () => Promise.reject(new TypeError('Failed to fetch')), replayed);

        const response = await client.create({ ownerId: 109, content: { format: 'plain', text: 'Hello' } });
        const creates = server.sent('create');

        expect(response.replayed).toBe(true);
        expect(creates).toHaveLength(2);
        expect(creates[1]!.body.operationId).toBe(creates[0]!.body.operationId);
        expect(creates[0]!.body).toMatchObject({ ownerId: 109, siteId: 1, parentUid: null, content: { format: 'plain', text: 'Hello' } });
    });

    it('give up after the second dropped connection, keeping the operationId for a manual retry', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('create', () => Promise.reject(new TypeError('Failed to fetch')));

        const error = await client.create({ ownerId: 109, content: { format: 'plain', text: 'Hello' } }).catch((caught: unknown) => caught);

        expect(error).toBeInstanceOf(CommentsRequestError);
        expect(error).toMatchObject({ code: 'network_error', category: 'network', recoverable: true, operationId: server.sent('create')[0]!.body.operationId });
        expect(server.sent('create')).toHaveLength(2);
    });

    it('never resend a read or an upload', async () => {
        const { server, client } = setup();
        server.on('thread', () => Promise.reject(new TypeError('Failed to fetch')));

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'network_error' });
        expect(server.sent('thread')).toHaveLength(1);
    });

    it('upload files as multipart, answered in Editor’s format', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json'))
            .on('upload', () => new Response(JSON.stringify({ id: 12, url: '/u/run.jpg' }), { headers: { 'X-Comments-Api-Version': '1' } }));

        const result = await client.upload<{ id: number }>({ file: new Blob(['x'], { type: 'image/jpeg' }), filename: 'run.jpg', field: 'photo' });
        const [request] = server.sent('upload');
        const body = request!.body as FormData;

        expect(result.id).toBe(12);
        expect(body.get('field')).toBe('photo');
        expect((body.get('file') as File).name).toBe('run.jpg');
        expect(request!.headers.has('Content-Type')).toBe(false);
        expect(request!.headers.get('X-CSRF-Token')).toBe('csrf-session');
    });

    it('upload a custom field’s file with its thread, its parent and a guest’s evidence', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('upload', () => json(fixture('upload-field.json')));

        const evidence = { version: 1, preparationId: 'p1', challenges: {} } as never;
        const result = await client.upload<FieldUploadResponse>({ file: new Blob(['x']), filename: 'run.jpg', field: 'photo', ownerId: 109, siteId: 1, parentUid: 'c1', commentUid: undefined, protection: evidence });
        const body = server.sent('upload')[0]!.body as FormData;

        expect(result.file.id).toBe(4412);
        expect([...body.keys()]).toEqual(['field', 'ownerId', 'siteId', 'parentUid', 'protection', 'file']);
        expect(body.get('ownerId')).toBe('109');
        expect(JSON.parse(String(body.get('protection')))).toEqual(evidence);
    });
});

describe('errors', () => {
    it('carry the code, category, field errors and operationId', async () => {
        const { server, client } = setup();
        server.on('session', fixture('session.json')).on('create', () => json(fixture('error-validation.json'), 422));

        const error = await client.create({ ownerId: 109, content: { format: 'plain', text: '' } }, { operationId: 'op-1' }).catch((caught: unknown) => caught) as CommentsRequestError;

        expect(error.code).toBe('validation_failed');
        expect(error.category).toBe('validation');
        expect(error.status).toBe(422);
        expect(error.message).toBe('Please fix the errors below.');
        expect(error.fieldErrors).toEqual({ content: ['Write something first.'], 'fields.rating': ['Rating cannot be blank.'] });
        expect(error.operationId).toBe('op-1');
        expect(error.has('validation_failed')).toBe(true);
    });

    it('keep Retry-After’s seconds for rate limits', async () => {
        const { server, client } = setup();
        server.on('thread', () => json(fixture('error-rate-limited.json'), 429, { 'Retry-After': '30' }));

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'rate_limited', category: 'rate', retryAfter: 30, status: 429 });
    });

    it('fall back on the category for a code this version doesn’t know', async () => {
        const { server, client } = setup();
        server.on('thread', () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'permission', code: 'owner_archived', message: 'Archived.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 403));

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'owner_archived', category: 'permission' });
    });

    it('turn a server error page into server_error', async () => {
        const { server, client } = setup();
        server.on('thread', () => new Response('<h1>Internal Server Error</h1>', { status: 500 }));

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'server_error', category: 'server', status: 500, recoverable: true });
    });

    it('turn a timeout into network_error, but let the caller’s own abort through', async () => {
        const hang = (request: { signal?: AbortSignal }) => new Promise<Response>((_resolve, reject) => {
            request.signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
        });
        const timed = setup({ timeoutMs: 5 });
        timed.server.on('thread', hang);

        await expect(timed.client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'network_error', message: 'The request took too long. Please try again.' });

        const aborted = setup();
        aborted.server.on('thread', hang);
        const controller = new AbortController();
        const pending = aborted.client.thread({ ownerId: 109 }, { signal: controller.signal });
        controller.abort();

        await expect(pending).rejects.toMatchObject({ name: 'AbortError' });
    });

    it('list a category and status for every code', () => {
        for (const [code, entry] of Object.entries(COMMENTS_ERROR_CODES)) {
            expect(entry.category, code).toMatch(/^[a-z_]+$/);
            expect(typeof entry.status, code).toBe('number');
        }

        expect(Object.keys(COMMENTS_ERROR_CODES)).toEqual(expect.arrayContaining(['validation_failed', 'protection_expired', 'challenge_failed', 'possible_spam', 'rate_limited', 'csrf_failed', 'token_expired', 'not_found', 'conflict', 'limit_exceeded', 'invalid_cursor', 'unsupported_api_version']));
        expect(new Set(Object.values(COMMENTS_ERROR_CODES).map((entry) => entry.category))).toEqual(new Set(['validation', 'protection', 'rate', 'auth', 'policy', 'state', 'request', 'server', 'network']));
    });
});

describe('the API version', () => {
    it('is 1, sent on every request', async () => {
        const { server, client } = setup();
        server.on('thread', fixture('thread.json')).on('viewer', fixture('viewer-guest.json'));

        await client.thread({ ownerId: 109 });
        await client.viewer({ ownerId: 109 });

        expect(COMMENTS_API_VERSION).toBe(1);
        expect(server.requests.map((request) => request.headers.get('X-Comments-Api-Version'))).toEqual(['1', '1']);
    });

    it('refuses a response in another version, by body or by header', async () => {
        const { server, client } = setup();
        server.on('thread', { ...fixture('thread.json'), apiVersion: 2 }, () => json(fixture('thread.json'), 200, { 'X-Comments-Api-Version': '2' }));

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({
            code: 'unsupported_api_version',
            category: 'request',
            message: 'Comments couldn’t load. Please refresh the page.',
            detail: 'API version 2, expected 1',
        });
        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'unsupported_api_version' });
    });

    it('passes on the server’s own refusal of this version', async () => {
        const { server, client } = setup();
        server.on('thread', () => json(fixture('error-unsupported-version.json'), 400));

        await expect(client.thread({ ownerId: 109 })).rejects.toMatchObject({ code: 'unsupported_api_version', message: 'This site serves version 1 of the comments API.', status: 400 });
    });

    it('refuses JSON that isn’t from the data API', () => {
        expect(() => assertApiVersion({ comments: [] })).toThrow(expect.objectContaining({ code: 'invalid_response' }));
        expect(() => assertApiVersion({ id: 1 }, '1', false)).not.toThrow();
        expect(() => assertApiVersion({ id: 1 }, '2', false)).toThrow(expect.objectContaining({ code: 'unsupported_api_version' }));
    });
});

describe('sign-in providers', () => {
    it('let a provider refresh a token and retry once after a sign-in error', async () => {
        let token = 'expired';
        const provider: SignInProvider = {
            profile: 'cross-origin-member',
            credentials: 'omit',
            headers: async () => ({ Authorization: `Bearer ${token}` }),
            recover: vi.fn(async (error) => {
                if (error.code !== 'token_expired') return false;
                token = 'fresh';
                return true;
            }),
        };
        const { server, client } = setup({ auth: provider });
        const expired = { apiVersion: 1, status: 'error', errors: [{ category: 'auth', code: 'token_expired', message: 'Sign in again.', fieldErrors: {}, recoverable: true, retryAfter: null }] };
        server.on('viewer', () => json(expired, 401), fixture('viewer-member.json'));

        await client.viewer({ ownerId: 109 });

        expect(server.sent('viewer').map((request) => request.headers.get('Authorization'))).toEqual(['Bearer expired', 'Bearer fresh']);
        expect(server.sent('viewer').map((request) => request.headers.get('X-Comments-Profile'))).toEqual(['cross-origin-member', 'cross-origin-member']);
        expect(server.requests.every((request) => request.credentials === 'omit')).toBe(true);
    });

    it('don’t retry a second sign-in error', async () => {
        const recover = vi.fn(async () => true);
        const { server, client } = setup({ auth: { profile: 'cross-origin-member', credentials: 'omit', headers: async () => ({}), recover } });
        server.on('viewer', () => json({ apiVersion: 1, status: 'error', errors: [{ category: 'auth', code: 'token_revoked', message: 'Signed out.', fieldErrors: {}, recoverable: false, retryAfter: null }] }, 401));

        await expect(client.viewer({ ownerId: 109 })).rejects.toMatchObject({ code: 'token_revoked' });
        expect(recover).toHaveBeenCalledTimes(1);
        expect(server.sent('viewer')).toHaveLength(2);
    });

    it('need a provider for the tier 3 profiles', () => {
        expect(() => createCommentsClient({ endpoint: ENDPOINT, profile: 'cross-origin-member' })).toThrow('needs a sign-in provider');
        expect(() => cookieSession({ profile: 'cross-origin-public' as never })).toThrow('cookieSession() is for');
    });
});
