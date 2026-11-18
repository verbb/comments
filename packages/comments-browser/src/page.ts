/**
 * What the startup loader needs to know about the page, kept apart from the renderer's helpers so
 * the loader stays small.
 */

/**
 * Where a link points in the thread: `#comment-1446` or `?comment=1446` (a comment's number), or
 * `#comment-<uid>`. Other in-page links, such as a table of contents, aren't comments.
 */
export function deepLink(location: Location = window.location): { anchor: string; uid: string | null } | null {
    const hash = location.hash.startsWith('#comment-') ? location.hash.slice('#comment-'.length) : '';
    const query = new URLSearchParams(location.search).get('comment') ?? '';
    const value = hash || query;

    if (/^\d+$/.test(value)) {
        return { anchor: `comment-${value}`, uid: null };
    }

    if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value)) {
        return { anchor: `comment-${value}`, uid: value.toLowerCase() };
    }

    return null;
}

/**
 * Whether a site's dark-mode selector, such as `html.dark`, matches `<html>` now: true or false,
 * or null when there's no selector or it isn't valid.
 */
export function darkState(selector: string | undefined): boolean | null {
    if (!selector || typeof document === 'undefined') return null;

    try {
        return document.documentElement.matches(selector);
    } catch {
        return null;
    }
}
