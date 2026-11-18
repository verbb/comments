/**
 * The parts a site can override, each one public API. Deliberately fewer than the elements in the
 * markup reference: everything else is reached with stable class names, tokens and controls.
 */
export const PART_NAMES = [
    'header', 'empty', 'showButton', 'loading', 'notice',
    'comment', 'avatar', 'authorName', 'badge', 'meta', 'content', 'fields', 'actions', 'review', 'placeholder',
    'join', 'postingAs', 'formFields',
];
export const emptyUi = () => ({
    collapsed: new Set(),
    fullDates: new Set(),
    hideHeld: false,
    highlight: null,
    toast: null,
    live: '',
    reportUid: null,
    busy: new Set(),
    pendingVotes: new Map(),
    openForm: null,
    reportError: null,
    joinOpen: false,
    menus: new Set(),
    unfolded: new Set(),
});
/** The comment's anchor, `comment-1446`, from its permalink, so links and `:target` keep working. */
export function anchorOf(comment) {
    const hash = comment.permalink ? comment.permalink.slice(comment.permalink.indexOf('#') + 1) : '';
    return /^comment-[\w-]+$/.test(hash) ? hash : `comment-${comment.uid}`;
}
/** The number in a comment's anchor, as today's events report it, or null. */
export function idOf(comment) {
    const match = comment ? /^comment-(\d+)$/.exec(anchorOf(comment)) : null;
    return match ? match[1] : null;
}
