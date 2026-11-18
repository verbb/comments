/**
 * The mount point `<CommentsThread>` renders, the same one `craft.comments.render()` writes: the
 * reserved space, the heading with the count, and a line for visitors without JavaScript. On the
 * server this is all that's rendered, so there's nothing inside it for hydration to compare, and in
 * the browser the renderer draws the thread over it.
 */

/**
 * Marks a mount point an adapter draws, so the plugin's own script leaves it alone even when a
 * site calls `Comments.mount()` over the whole page.
 */
export const ADAPTER_ATTRIBUTE = 'data-comments-adapter';

export type MountPointInput = {
    id: string;
    count?: number | null;
    labels?: Record<string, string> | null;
};

const escape = (value: string): string => value.replace(/[&<>"']/g, (character) => `&#${character.charCodeAt(0)};`);

/**
 * Which of the base stylesheet's reserved heights suits a thread of this many comments, as the
 * server picks it, so the page below doesn't move when the thread is drawn.
 */
export function reserveBucket(count: number): number {
    if (count <= 0) return 0;
    if (count === 1) return 1;
    if (count <= 3) return 2;
    if (count <= 6) return 3;
    return 4;
}

/**
 * The heading and the line for visitors without JavaScript, as HTML. The count is written without
 * locale formatting, so the server and the browser always agree on it.
 */
export function mountPointHtml({ id, count, labels }: MountPointInput): string {
    const total = Math.max(0, Math.floor(Number(count) || 0));
    const heading = escape(labels?.comments || 'Comments');
    const counted = total ? ` <span class="comments__count"><span class="comments__visually-hidden">: </span>${total}</span>` : '';
    const noscript = escape(labels?.noscript || 'Turn on JavaScript to read and post comments.');

    return `<h2 id="${escape(id)}-heading" class="comments__heading" tabindex="-1">${heading}${counted}</h2><noscript><p class="comments__noscript">${noscript}</p></noscript>`;
}

/** The words the renderer shows before the thread's data arrives, from the labels given. */
export function waitingLabels(labels: Record<string, string> | null | undefined): string | undefined {
    const keys = ['comments', 'loading', 'showComments', 'showCommentsCount', 'error', 'tryAgain'];
    const picked = Object.fromEntries(keys.filter((key) => typeof labels?.[key] === 'string').map((key) => [key, labels![key]]));

    return Object.keys(picked).length ? JSON.stringify(picked) : undefined;
}

/** An element ID from a framework's own generated ID, which can hold characters an ID shouldn't. */
export function threadId(ownerId: number | null | undefined, siteId: number | null | undefined, generated: string): string {
    if (ownerId !== undefined && ownerId !== null) {
        return `comments-${ownerId}${siteId !== undefined && siteId !== null ? `-${siteId}` : ''}`;
    }

    return `comments-${generated.replace(/[^\w-]/g, '') || 'thread'}`;
}
