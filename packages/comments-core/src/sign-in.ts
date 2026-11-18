import type { CommentsRequestError } from './errors.js';
import { abortable } from './signal.js';
import type { CsrfData, SessionResponse } from './types.js';

/**
 * How a front end reaches Craft, which decides how the person is identified:
 *
 * - `same-origin-browser` (tier 1): Craft's own pages, or an app on Craft's domain, by session cookie.
 * - `same-site-credentialed` (tier 2): another subdomain of the same site, by a parent-domain cookie and credentialed CORS.
 * - `cross-origin-member` (tier 3): a different site, by a short-lived member token.
 * - `cross-origin-public` (tier 3): guests on a different site, by an opaque guest session token.
 */
export type RequestProfile = 'same-origin-browser' | 'same-site-credentialed' | 'cross-origin-member' | 'cross-origin-public';

/** Sent on every request that reads who's asking. A custom header forces a CORS preflight. */
export const COMMENTS_PROFILE_HEADER = 'X-Comments-Profile';

/** Craft reads the CSRF token from this header on JSON writes. */
export const CSRF_HEADER = 'X-CSRF-Token';

/** Sent on every identified request by a tier 3 guest, and refreshed on every answer. */
export const COMMENTS_SESSION_HEADER = 'X-Comments-Session';

/** Things a sign-in can't do at all, whatever the viewer read says. */
export type UnavailableAction = 'edit' | 'mention' | 'upload' | 'moderate';

export type SignInContext = {
    /** Whether the request is a write. */
    write: boolean;
    /** The data API's base URL, such as for keying a stored token. The client always gives it. */
    endpoint?: string;
    /**
     * The `session` read, made through the same client, for a provider that needs a CSRF token. It
     * isn't tied to any one request, so several callers can share it.
     */
    session(): Promise<SessionResponse>;
    /** Aborts when this request is cancelled or times out, so a provider can stop waiting on shared work. */
    signal?: AbortSignal;
};

/**
 * Identifies the person on every request that reads who's asking (`viewer`, `session`,
 * `protection`, `edit`, `mentions`, `upload` and every write). Public reads never use it.
 */
export interface SignInProvider {
    readonly profile: RequestProfile;
    /** The fetch credentials mode for those requests. */
    readonly credentials: RequestCredentials;
    /** Actions this sign-in never allows, which a thread store shows as unavailable. */
    readonly unavailable?: readonly UnavailableAction[];
    /** Headers to add, such as the CSRF token or a bearer token. May make a request first. */
    headers(context: SignInContext): Promise<Record<string, string>>;
    /** Sees each identity-reading response, to keep a CSRF or session token it carries. */
    observe?(payload: unknown, headers: Headers): void;
    /** After an `auth` error, gets ready to try again. Returns true for one retry. */
    recover?(error: CommentsRequestError, context: SignInContext): Promise<boolean>;
    /** Forgets anything held in memory, such as on sign-out, and tells `onReset()` listeners. */
    reset?(): void;
    /**
     * Called after `reset()`. A thread store listens, so a sign-out or a change of user clears
     * everything private and reads the viewer again.
     */
    onReset?(listener: () => void): () => void;
}

export type CookieSessionOptions = {
    /** `same-origin-browser` (the default) or `same-site-credentialed` for a sibling subdomain. */
    profile?: 'same-origin-browser' | 'same-site-credentialed';
};

/**
 * Tiers 1 and 2: Craft's session cookie identifies the person, and writes carry the CSRF token
 * from the `viewer` or `session` read, kept in memory only. A write before either has given one
 * calls `session` first, which starts a guest's session.
 */
export function cookieSession(options: CookieSessionOptions = {}): SignInProvider {
    const profile = options.profile ?? 'same-origin-browser';

    if (profile !== 'same-origin-browser' && profile !== 'same-site-credentialed') {
        throw new Error(`Comments: cookieSession() is for the same-origin-browser and same-site-credentialed profiles, not ${String(profile)}.`);
    }

    let csrf: CsrfData | null = null;
    let fetching: Promise<unknown> | null = null;
    const listeners = new Set<() => void>();

    return {
        profile,
        credentials: profile === 'same-site-credentialed' ? 'include' : 'same-origin',
        async headers(context) {
            if (!context.write) {
                return {};
            }

            if (!csrf) {
                // Writes started together share one `session` read, which none of them can cancel
                // for the others; each stops waiting when its own request is cancelled.
                fetching ??= context.session().finally(() => { fetching = null; });
                await abortable(fetching, context.signal);
            }

            const headers: Record<string, string> = {};

            if (csrf) {
                headers[CSRF_HEADER] = csrf.value;
            }

            return headers;
        },
        observe(payload) {
            const token = (payload as { csrf?: unknown } | null)?.csrf as CsrfData | null | undefined;

            if (token && typeof token.value === 'string' && token.value) {
                csrf = { name: String(token.name ?? ''), value: token.value };
            }
        },
        async recover(error) {
            if (error.code !== 'csrf_failed' && error.code !== 'session_expired') {
                return false;
            }

            csrf = null;
            return true;
        },
        reset() {
            csrf = null;
            listeners.forEach((listener) => listener());
        },
        onReset(listener) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
    };
}

/** Calls each listener, for `onReset()`. */
function resets() {
    const listeners = new Set<() => void>();

    return {
        notify: () => listeners.forEach((listener) => listener()),
        add(listener: () => void) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
    };
}

export type MemberToken = {
    token: string;
    /** Unix seconds, on the server's clock. */
    expiresAt: number;
};

export type MemberTokenOptions = {
    /**
     * Asks the site's own server for a token, which mints one with a signed `member-token`
     * request. Called on the first identified request, shortly before the token expires, and after
     * `token_expired` or `token_revoked`.
     */
    getToken(): Promise<MemberToken>;
    /** Seconds before `expiresAt` to fetch the next token. Defaults to 60. */
    refreshBefore?: number;
};

/**
 * Tier 3 for members: a short-lived token from the site's own server, sent as
 * `Authorization: Bearer` on every identified request and kept in memory only. Public reads never
 * carry it. Requests go without cookies, so no CSRF token is needed.
 */
export function memberToken(options: MemberTokenOptions): SignInProvider {
    const margin = (options.refreshBefore ?? 60) * 1000;
    const listeners = resets();
    let current: MemberToken | null = null;
    let fetching: Promise<MemberToken> | null = null;

    // Calls started together share one `getToken()`, which none of them can cancel for the others.
    const fetchToken = () => (fetching ??= options.getToken().then((next) => {
        if (!next || typeof next.token !== 'string' || !next.token || typeof next.expiresAt !== 'number') {
            throw new Error('Comments: getToken() must return { token, expiresAt }.');
        }

        current = next;
        return next;
    }).finally(() => { fetching = null; }));

    return {
        profile: 'cross-origin-member',
        credentials: 'omit',
        async headers(context) {
            // A visitor's clock may be off; a token that expires early is caught by `recover()`.
            if (!current || Date.now() >= current.expiresAt * 1000 - margin) {
                await abortable(fetchToken(), context.signal);
            }

            return { Authorization: `Bearer ${current!.token}` };
        },
        async recover(error) {
            if (error.code !== 'token_expired' && error.code !== 'token_revoked') {
                return false;
            }

            current = null;
            return true;
        },
        reset() {
            current = null;
            listeners.notify();
        },
        onReset: listeners.add,
    };
}

/** What `guestSession()` can keep its token in, such as `sessionStorage`. */
export type TokenStorage = {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
};

export type GuestSessionOptions = {
    /**
     * Keeps the guest's token across page loads, such as `sessionStorage`, under a key per data
     * API endpoint. Left out, the token lives in memory and a reload starts a new guest session.
     */
    storage?: TokenStorage;
};

/**
 * Tier 3 for guests: an opaque guest session token from the `session` read, sent as
 * `X-Comments-Session` on every identified request and replaced by the one each answer carries.
 * Reads work without one. Before a write without one, or after `session_expired`, it reads
 * `session` first. Guests can't edit, mention or moderate, and upload only to the custom fields
 * they're offered (Editor's own uploads are for members).
 */
export function guestSession(options: GuestSessionOptions = {}): SignInProvider {
    const listeners = resets();
    let token: string | null = null;
    let key: string | null = null;
    let fetching: Promise<unknown> | null = null;

    const save = (next: string | null) => {
        token = next;

        try {
            if (key && options.storage) next ? options.storage.setItem(key, next) : options.storage.removeItem(key);
        } catch {
            // Storage that's full or refused (a private window) leaves the token in memory.
        }
    };

    return {
        profile: 'cross-origin-public',
        credentials: 'omit',
        unavailable: ['edit', 'mention', 'moderate'],
        async headers(context) {
            if (!key) {
                key = `verbb-comments-session:${context.endpoint ?? ''}`;

                try {
                    token ??= options.storage?.getItem(key) ?? null;
                } catch {
                    // Unreadable storage means starting without a token.
                }
            }

            if (!token && context.write) {
                fetching ??= context.session().finally(() => { fetching = null; });
                await abortable(fetching, context.signal);
            }

            const headers: Record<string, string> = {};

            if (token) {
                headers[COMMENTS_SESSION_HEADER] = token;
            }

            return headers;
        },
        observe(_payload, headers) {
            const next = headers.get(COMMENTS_SESSION_HEADER);

            if (next) save(next);
        },
        async recover(error) {
            if (error.code !== 'session_expired') {
                return false;
            }

            save(null);
            return true;
        },
        reset() {
            save(null);
            listeners.notify();
        },
        onReset: listeners.add,
    };
}
