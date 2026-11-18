import type { CommentsRequestError } from './errors.js';
import type { SessionResponse } from './types.js';
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
export declare const COMMENTS_PROFILE_HEADER = "X-Comments-Profile";
/** Craft reads the CSRF token from this header on JSON writes. */
export declare const CSRF_HEADER = "X-CSRF-Token";
/** Sent on every identified request by a tier 3 guest, and refreshed on every answer. */
export declare const COMMENTS_SESSION_HEADER = "X-Comments-Session";
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
export declare function cookieSession(options?: CookieSessionOptions): SignInProvider;
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
export declare function memberToken(options: MemberTokenOptions): SignInProvider;
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
export declare function guestSession(options?: GuestSessionOptions): SignInProvider;
