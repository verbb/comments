// Compiled by scripts/check-package-pack.mjs against the packed tarball, under bundler and Node
// resolution, to prove the published types resolve and read the way the docs use them.
import {
    COMMENTS_API_VERSION,
    COMMENTS_ERROR_CODES,
    COMMENTS_EVENT_NAMES,
    COMMENTS_SESSION_HEADER,
    CommentsRequestError,
    cookieSession,
    createCommentsClient,
    createProtectionFlow,
    createThreadStore,
    selectReplies,
    selectRoots,
    type CommentData,
    type CommentsErrorCode,
    type CommentsEventName,
    type EvidenceProvider,
    type PartName,
    type ProtectionData,
    type SignInProvider,
    type ThemeConfig,
    type ThreadResponse,
    type ThreadStoreEventMap,
    type ViewerResponse,
    type VoteValue,
} from '@verbb/comments-core';
import { graphqlTransport } from '@verbb/comments-core/graphql';

const client = createCommentsClient({ endpoint: 'https://example.com/actions/comments/api', auth: cookieSession({ profile: 'same-site-credentialed' }) });
const evidence: EvidenceProvider = async (protection: ProtectionData) => ({ version: 1, preparation: protection.manifest.preparation, honeypot: {}, javascript: '1', challenges: {} });
const store = createThreadStore(client, { ownerUri: 'blog/post', sort: 'top', evidence });
const roots: CommentData[] = selectRoots(store.getState());
const replies: CommentData[] = selectReplies(store.getState(), roots[0]?.uid ?? '');
const version: 1 = COMMENTS_API_VERSION;
const code: CommentsErrorCode = 'protection_expired';
const event: CommentsEventName = COMMENTS_EVENT_NAMES[0];
const theme: ThemeConfig = { primaryButton: { resetClass: true, attributes: { class: ['btn', 'btn-primary'] } } };
const part: PartName = 'badge';
const flow = createProtectionFlow({ client, ownerId: 1 });
const overGraphql = createCommentsClient({ endpoint: '/actions/comments/api', transport: graphqlTransport({ endpoint: '/api' }), headers: { private: { 'X-Site': 'blog' } } });
const provider: SignInProvider = client.auth;

async function read(): Promise<[ThreadResponse, ViewerResponse]> {
    return Promise.all([client.thread({ ownerId: 1, limit: 20 }), client.viewer({ ownerId: 1, uids: ['a'] })]);
}

async function post(): Promise<string> {
    try {
        const response = await store.create({ content: { format: 'plain', text: 'Hello' }, guest: { name: 'Jo', email: 'jo@example.com', notifyReplies: false } });
        return response.comment.uid;
    } catch (error) {
        return error instanceof CommentsRequestError ? `${error.code} ${error.category} ${COMMENTS_ERROR_CODES.rate_limited.status}` : 'unknown';
    }
}

// @ts-expect-error A sort the API doesn't have.
void client.thread({ ownerId: 1, sort: 'random' });

// The store's events are typed, with the browser events' keys.
const header: 'X-Comments-Session' = COMMENTS_SESSION_HEADER;
const stop = store.on('comments:vote-changed', (detail) => {
    const vote: VoteValue | null = detail.vote;
    const uid: string = detail.commentUid;
    return [vote, uid];
});
const reported: ThreadStoreEventMap['comments:report-changed']['reported'] = true;
// @ts-expect-error The store doesn't emit the page's own events.
store.on('comments:before-submit', () => undefined);

export { event, flow, header, overGraphql, part, post, provider, read, replies, reported, stop, theme, version, code };
