import type { SignInProvider } from '@verbb/comments-core';
/** A sign-in provider that follows the latest one given, from `latestAuth()`. */
export type LatestAuth = SignInProvider & {
    /** Starts following the latest provider now, so its `reset()` reaches the thread. Call it whenever the app gives a new one. */
    sync(): void;
};
/**
 * A sign-in provider that hands every call to the latest one the app gave, so a provider made
 * afresh on each render doesn't start the thread again and lose what someone was typing. A
 * provider with another profile is another way of signing in, so callers start the thread again
 * for that instead. Each provider keeps its own token, so one made afresh starts without one:
 * `guestSession()` without `storage` begins a new guest session, which is why the docs say to
 * make providers once.
 */
export declare function latestAuth(read: () => SignInProvider): LatestAuth;
/** A `fetch` that calls the latest one the app gave, or the global one. */
export declare function latestFetch(read: () => typeof fetch | undefined): typeof fetch;
/**
 * Follows a site's own dark-mode toggle, as `Comments.configure({ darkSelector })` does for the
 * plugin's threads: dark while `selector` matches `<html>`, light when it stops. Returns a stop function.
 */
export declare function followDarkSelector(selector: string | undefined, apply: (dark: boolean) => void): () => void;
