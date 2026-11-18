/**
 * A fake Comments data API, answered through a `fetch` the tests hand to the provider: one thread
 * of three root comments (the second with a reply and more to load), a signed-in member, and every
 * write the tests make, each recorded. Shapes follow the browser package's Playwright stub.
 */
import LABELS from './labels.json';

export const ENDPOINT = 'https://comments.test/actions/comments/api';

let id = 1400;

export function comment(uid: string, overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        uid, parentUid: null, depth: 0, state: 'approved',
        content: { format: 'plain', html: null, text: `Comment ${uid}`, gifUrl: null, gifWidth: null, gifHeight: null },
        author: { name: `Author ${uid}`, initials: 'AU', avatarUrl: null, avatarTone: 3, isGuest: false, badges: [], score: null },
        createdAt: '2026-11-09T21:04:00+00:00', editedAt: null, display: { timeAgo: '2 hours ago', fullDate: '9 November 2026, 9:04 pm' },
        permalink: `https://comments.test/post#comment-${++id}`, fields: [], interaction: { upvotes: 0, downvotes: 0 }, folded: null, custom: {},
        replies: { count: 0, items: [], next: null }, ...overrides,
    };
}

export function permissions(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return { canReply: true, canEdit: false, canDelete: false, canVote: true, canDownvote: false, canReport: true, vote: null, reported: false, isAuthor: false, moderation: null, custom: {}, ...overrides };
}

export const MEMBER = { isGuest: false, hasSession: true, name: 'Aroha Ngata', initials: 'AN', avatarUrl: null, avatarTone: 6, badges: [], canComment: true, canVote: true, canReport: true, isModerator: false, blocked: false, loginUrl: null, follow: { available: true, following: false, needsEmail: false } };

export type Recorded = { action: string; url: URL; body: any; headers: Headers };

export type FakeApiOptions = {
    roots?: Array<Record<string, unknown>>;
    fields?: { comment?: unknown[]; reply?: unknown[] };
    /** Answers to replace the default for an action, or undefined to keep it. */
    override?: Record<string, (url: URL, body: any) => { status?: number; body: unknown } | undefined>;
    /** A Protect preparation with a honeypot and the JavaScript check on each `protection` read. */
    protect?: boolean;
    /** Holds every answer until `release()`. */
    hold?: boolean;
};

export function fakeApi(options: FakeApiOptions = {}) {
    let roots = options.roots ?? [
        comment('c1', { interaction: { upvotes: 2, downvotes: 0 } }),
        comment('c2', { replies: { count: 3, items: [comment('c2-1', { parentUid: 'c2', depth: 1 })], next: 'rc' } }),
        comment('c3'),
    ];
    const requests: Recorded[] = [];
    const waiting: Array<() => void> = [];
    let held = options.hold ?? false;
    const ok = (body: Record<string, unknown>) => ({ apiVersion: 1, status: 'ok', ...body });
    const conversation = () => ({
        uid: 'conversation-1', ownerId: 109, siteId: 1, status: 'open', closedNotice: null, commentCount: roots.length, rootCount: roots.length, sort: 'newest', sorts: ['newest', 'oldest', 'top'],
        interactionMode: 'like', maxLength: null, guestPolicy: { allowed: false, nameAndEmail: 'required', canVote: false, canReport: false }, followAvailable: true,
        reportReasons: [{ value: 'spam', label: 'Spam', description: null }],
    });
    const all = () => roots.flatMap((root) => [root, ...((root.replies as { items: Array<Record<string, unknown>> }).items)]);
    let protections = 0;

    const answers: Record<string, (url: URL, body: any) => unknown> = {
        thread: () => ok({ threadVersion: 'v1', conversation: conversation(), editor: { authoring: 'plain', maxLength: null, richEditor: null }, comments: roots, page: { next: null, limit: 20 }, labels: LABELS, locale: 'en' }),
        viewer: () => ok({
            threadVersion: 'v1', viewer: MEMBER,
            editor: { draftKey: 'comments-109-1-7', guest: { show: false, required: true, name: null, email: null, offerReplyEmails: false }, fields: { comment: options.fields?.comment ?? [], reply: options.fields?.reply ?? [] }, protection: null, richEditor: null },
            comments: Object.fromEntries(all().map((item) => [item.uid, permissions({ canDelete: true })])), extraComments: [], moderation: null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' }, custom: {},
        }),
        session: () => ok({ viewer: MEMBER, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } }),
        protection: () => {
            const now = Math.floor(Date.now() / 1000);
            const n = ++protections;
            return ok({ protection: options.protect ? { version: 1, manifest: { version: 1, instanceId: `pi-${n}`, preparation: `prep-${n}`, issuedAt: now, expiresAt: now + 600, honeypot: 'a1b2c3', javascript: true, challenges: [] }, expiresAt: now + 600 } : null, csrf: { name: 'CRAFT_CSRF_TOKEN', value: 'token' } });
        },
        'structured-data': () => ok({ structuredData: { '@context': 'https://schema.org', '@type': 'DiscussionForumPosting', commentCount: roots.length } }),
        create: (_url, body) => {
            const created = comment(`new-${requests.length}`, { parentUid: body.parentUid ?? null, content: { format: 'plain', html: null, text: body.content?.text ?? '', gifUrl: null, gifWidth: null, gifHeight: null } });
            return ok({ replayed: false, comment: { ...created, viewer: permissions({ isAuthor: true, canEdit: true }) }, moderationState: 'approved', conversation: { commentCount: roots.length + 1 }, announcement: 'Comment posted.' });
        },
        vote: (_url, body) => ok({ replayed: false, comment: { uid: body.uid, interaction: { upvotes: body.vote === 'upvote' ? 3 : 2, downvotes: 0 }, viewer: permissions({ vote: body.vote, canDelete: true }) }, announcement: null }),
        delete: (_url, body) => {
            roots = roots.filter((root) => root.uid !== body.uid);
            return ok({ replayed: false, uid: body.uid, conversation: { commentCount: roots.length }, announcement: 'Comment deleted.' });
        },
    };

    async function fetch(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
        const url = new URL(typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);
        const action = url.pathname.split('/').pop() ?? '';
        const text = typeof init.body === 'string' ? init.body : null;
        const body = text ? JSON.parse(text) : null;
        requests.push({ action, url, body, headers: new Headers(init.headers) });

        if (held) await new Promise<void>((resolve) => waiting.push(resolve));

        const replaced = options.override?.[action]?.(url, body);
        const status = replaced?.status ?? (answers[action] ? 200 : 404);
        const answer = replaced ? replaced.body : answers[action]?.(url, body) ?? { apiVersion: 1, status: 'error', errors: [] };

        return new Response(JSON.stringify(answer), { status, headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } });
    }

    return {
        fetch: fetch as typeof globalThis.fetch,
        requests,
        of: (action: string) => requests.filter((request) => request.action === action),
        release() {
            held = false;
            waiting.splice(0).forEach((resolve) => resolve());
        },
    };
}

/** Waits until `check` passes, or fails with its last error. */
export async function until<T>(check: () => T | Promise<T>, timeout = 3000): Promise<NonNullable<T>> {
    const start = Date.now();
    let last: unknown;

    while (Date.now() - start < timeout) {
        try {
            const result = await check();
            if (result) return result as NonNullable<T>;
        } catch (error) {
            last = error;
        }

        await new Promise((resolve) => setTimeout(resolve, 10));
    }

    throw last instanceof Error ? last : new Error(`Timed out waiting: ${String(check)}`);
}

export const validationFailed = (fieldErrors: Record<string, string[]>) => ({
    status: 422,
    body: { apiVersion: 1, status: 'error', errors: [{ category: 'validation', code: 'validation_failed', message: 'Check the highlighted fields.', fieldErrors, recoverable: false, retryAfter: null }] },
});
