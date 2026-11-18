/**
 * A sign-in provider that hands every call to the latest one the app gave, so a provider made
 * afresh on each render doesn't start the thread again and lose what someone was typing. A
 * provider with another profile is another way of signing in, so callers start the thread again
 * for that instead. Each provider keeps its own token, so one made afresh starts without one:
 * `guestSession()` without `storage` begins a new guest session, which is why the docs say to
 * make providers once.
 */
export function latestAuth(read) {
    const listeners = new Set();
    let attached = null;
    let detach;
    // Listening to the latest provider's reset() as soon as it's given, so a sign-out through it
    // clears the thread's private state even before any request has used it.
    const current = () => {
        const provider = read();
        if (provider !== attached) {
            detach?.();
            attached = provider;
            detach = provider.onReset?.(() => listeners.forEach((listener) => listener()));
        }
        return provider;
    };
    return {
        get profile() {
            return current().profile;
        },
        get credentials() {
            return current().credentials;
        },
        get unavailable() {
            return current().unavailable;
        },
        headers: (context) => current().headers(context),
        observe: (payload, headers) => current().observe?.(payload, headers),
        recover: (error, context) => current().recover?.(error, context) ?? Promise.resolve(false),
        reset: () => current().reset?.(),
        onReset(listener) {
            current();
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        sync() {
            current();
        },
    };
}
/** A `fetch` that calls the latest one the app gave, or the global one. */
export function latestFetch(read) {
    return (input, init) => (read() ?? globalThis.fetch)(input, init);
}
/**
 * Follows a site's own dark-mode toggle, as `Comments.configure({ darkSelector })` does for the
 * plugin's threads: dark while `selector` matches `<html>`, light when it stops. Returns a stop function.
 */
export function followDarkSelector(selector, apply) {
    if (!selector || typeof MutationObserver === 'undefined')
        return () => undefined;
    const check = () => {
        try {
            apply(document.documentElement.matches(selector));
        }
        catch {
            console.warn(`Comments: darkSelector "${selector}" isn't a valid selector.`);
        }
    };
    const observer = new MutationObserver(check);
    observer.observe(document.documentElement, { attributes: true });
    check();
    return () => observer.disconnect();
}
