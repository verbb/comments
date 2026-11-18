/**
 * The renderer's own words: what it shows before the thread's data arrives (which the mount point
 * normally gives in the site's language), what it says when a request fails, and the few it needs
 * that the data's `labels` don't carry yet. Everything else comes from the data, translated by the
 * site through Craft's static translations in the `comments` category, or from `labels` in
 * `Comments.configure()`.
 */
export const FALLBACK_LABELS = {
    comments: 'Comments',
    showComments: 'Show comments',
    showCommentsCount: 'Show comments ({count})',
    loading: 'Loading comments…',
    guest: 'Guest',
    error: 'Something went wrong. Please try again.',
    tryAgain: 'Try again',
    moderation: 'Moderation',
    onThisPage: 'on this page',
    hold: 'Hold',
    removedNote: 'Removed · only moderators see this',
    chooseOption: 'Choose…',
    formFailed: 'The comment form couldn’t load. Please try again.',
    gif: 'GIF',
    gifAdd: 'Add a GIF',
    gifSearch: 'Search GIPHY',
    gifResults: 'GIFs',
    gifRemove: 'Remove GIF',
    gifEmpty: 'No GIFs found.',
    gifPoweredBy: 'Powered by GIPHY',
    gifUnavailable: 'GIFs can’t be loaded right now.',
    foldedReports: 'Comment marked as inappropriate',
    foldedDownvotes: 'Comment hidden due to low rating',
    showFolded: 'Show comment',
    authorScore: 'Total votes across this user’s comments',
};
export function resolveLabels(...sources) {
    const labels = { ...FALLBACK_LABELS };
    for (const source of sources) {
        for (const [key, value] of Object.entries(source ?? {})) {
            if (typeof value === 'string' && value !== '')
                labels[key] = value;
        }
    }
    return labels;
}
/** Fills `{name}` placeholders. A placeholder without a value is left as it is. */
export function format(template, values) {
    return template.replace(/\{(\w+)\}/g, (match, key) => (key in values ? String(values[key]) : match));
}
