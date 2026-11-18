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
export declare const ADAPTER_ATTRIBUTE = "data-comments-adapter";
export type MountPointInput = {
    id: string;
    count?: number | null;
    labels?: Record<string, string> | null;
};
/**
 * Which of the base stylesheet's reserved heights suits a thread of this many comments, as the
 * server picks it, so the page below doesn't move when the thread is drawn.
 */
export declare function reserveBucket(count: number): number;
/**
 * The heading and the line for visitors without JavaScript, as HTML. The count is written without
 * locale formatting, so the server and the browser always agree on it.
 */
export declare function mountPointHtml({ id, count, labels }: MountPointInput): string;
/** The words the renderer shows before the thread's data arrives, from the labels given. */
export declare function waitingLabels(labels: Record<string, string> | null | undefined): string | undefined;
/** An element ID from a framework's own generated ID, which can hold characters an ID shouldn't. */
export declare function threadId(ownerId: number | null | undefined, siteId: number | null | undefined, generated: string): string;
