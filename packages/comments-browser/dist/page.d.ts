/**
 * What the startup loader needs to know about the page, kept apart from the renderer's helpers so
 * the loader stays small.
 */
/**
 * Where a link points in the thread: `#comment-1446` or `?comment=1446` (a comment's number), or
 * `#comment-<uid>`. Other in-page links, such as a table of contents, aren't comments.
 */
export declare function deepLink(location?: Location): {
    anchor: string;
    uid: string | null;
} | null;
/**
 * Whether a site's dark-mode selector, such as `html.dark`, matches `<html>` now: true or false,
 * or null when there's no selector or it isn't valid.
 */
export declare function darkState(selector: string | undefined): boolean | null;
