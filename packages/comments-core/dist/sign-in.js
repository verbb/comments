import { abortable } from './signal.js';
/** Sent on every request that reads who's asking. A custom header forces a CORS preflight. */
export const COMMENTS_PROFILE_HEADER = 'X-Comments-Profile';
/** Craft reads the CSRF token from this header on JSON writes. */
export const CSRF_HEADER = 'X-CSRF-Token';
/** Sent on every identified request by a tier 3 guest, and refreshed on every answer. */
export const COMMENTS_SESSION_HEADER = 'X-Comments-Session';
/**
 * Tiers 1 and 2: Craft's session cookie identifies the person, and writes carry the CSRF token
 * from the `viewer` or `session` read, kept in memory only. A write before either has given one
 * calls `session` first, which starts a guest's session.
 */
export function cookieSession(options = {}) {
    const profile = options.profile ?? 'same-origin-browser';
    if (profile !== 'same-origin-browser' && profile !== 'same-site-credentialed') {
        throw new Error(`Comments: cookieSession() is for the same-origin-browser and same-site-credentialed profiles, not ${String(profile)}.`);
    }
    let csrf = null;
    let fetching = null;
    const listeners = new Set();
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
            const headers = {};
            if (csrf) {
                headers[CSRF_HEADER] = csrf.value;
            }
            return headers;
        },
        observe(payload) {
            const token = payload?.csrf;
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
    const listeners = new Set();
    return {
        notify: () => listeners.forEach((listener) => listener()),
        add(listener) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
    };
}
/**
 * Tier 3 for members: a short-lived token from the site's own server, sent as
 * `Authorization: Bearer` on every identified request and kept in memory only. Public reads never
 * carry it. Requests go without cookies, so no CSRF token is needed.
 */
export function memberToken(options) {
    const margin = (options.refreshBefore ?? 60) * 1000;
    const listeners = resets();
    let current = null;
    let fetching = null;
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
            return { Authorization: `Bearer ${current.token}` };
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
/**
 * Tier 3 for guests: an opaque guest session token from the `session` read, sent as
 * `X-Comments-Session` on every identified request and replaced by the one each answer carries.
 * Reads work without one. Before a write without one, or after `session_expired`, it reads
 * `session` first. Guests can't edit, mention or moderate, and upload only to the custom fields
 * they're offered (Editor's own uploads are for members).
 */
export function guestSession(options = {}) {
    const listeners = resets();
    let token = null;
    let key = null;
    let fetching = null;
    const save = (next) => {
        token = next;
        try {
            if (key && options.storage)
                next ? options.storage.setItem(key, next) : options.storage.removeItem(key);
        }
        catch {
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
                }
                catch {
                    // Unreadable storage means starting without a token.
                }
            }
            if (!token && context.write) {
                fetching ??= context.session().finally(() => { fetching = null; });
                await abortable(fetching, context.signal);
            }
            const headers = {};
            if (token) {
                headers[COMMENTS_SESSION_HEADER] = token;
            }
            return headers;
        },
        observe(_payload, headers) {
            const next = headers.get(COMMENTS_SESSION_HEADER);
            if (next)
                save(next);
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
