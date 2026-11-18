/**
 * The renderer's own words: what it shows before the thread's data arrives (which the mount point
 * normally gives in the site's language), what it says when a request fails, and the few it needs
 * that the data's `labels` don't carry yet. Everything else comes from the data, translated by the
 * site through Craft's static translations in the `comments` category, or from `labels` in
 * `Comments.configure()`.
 */
export declare const FALLBACK_LABELS: {
    readonly comments: "Comments";
    readonly showComments: "Show comments";
    readonly showCommentsCount: "Show comments ({count})";
    readonly loading: "Loading comments…";
    readonly guest: "Guest";
    readonly error: "Something went wrong. Please try again.";
    readonly tryAgain: "Try again";
    readonly moderation: "Moderation";
    readonly onThisPage: "on this page";
    readonly hold: "Hold";
    readonly removedNote: "Removed · only moderators see this";
    readonly chooseOption: "Choose…";
    readonly formFailed: "The comment form couldn’t load. Please try again.";
    readonly gif: "GIF";
    readonly gifAdd: "Add a GIF";
    readonly gifSearch: "Search GIPHY";
    readonly gifResults: "GIFs";
    readonly gifRemove: "Remove GIF";
    readonly gifEmpty: "No GIFs found.";
    readonly gifPoweredBy: "Powered by GIPHY";
    readonly gifUnavailable: "GIFs can’t be loaded right now.";
    readonly foldedReports: "Comment marked as inappropriate";
    readonly foldedDownvotes: "Comment hidden due to low rating";
    readonly showFolded: "Show comment";
    readonly authorScore: "Total votes across this user’s comments";
};
export type Labels = Record<string, string>;
export declare function resolveLabels(...sources: Array<Record<string, string> | null | undefined>): Labels;
/** Fills `{name}` placeholders. A placeholder without a value is left as it is. */
export declare function format(template: string, values: Record<string, string | number>): string;
