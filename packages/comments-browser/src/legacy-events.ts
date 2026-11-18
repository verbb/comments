import type { CommentsEventName } from './events.js';

/*
 * Comments 3's event names, dispatched on `document` alongside Comments 4's own, for a thread whose
 * mount point Craft marks with `data-comments-compatibility`, which it does while the plugin's
 * `compatibilityMode` is on. Listeners keyed to the old names keep firing. Their `detail` is Comments 4's,
 * because Comments 3's held objects from its own script, which no longer exists.
 */

// The editor each thread last opened, so a new comment or a closed editor can be told apart as a reply.
const modes = new WeakMap<HTMLElement, string>();

type Detail = Record<string, unknown>;

const LEGACY_NAMES: Partial<Record<CommentsEventName, (detail: Detail, root: HTMLElement) => string | null>> = {
    'comments:mounted': () => 'comments:init',
    'comments:comment-created': (_detail, root) => (modes.get(root) === 'reply' ? 'comments:reply-submit' : 'comments:submit'),
    'comments:comment-updated': () => 'comments:edit-submit',
    'comments:editor-opened': (detail, root) => {
        modes.set(root, String(detail.mode));

        return detail.mode === 'reply' ? 'comments:reply-open' : detail.mode === 'edit' ? 'comments:edit-open' : null;
    },
    'comments:editor-closed': (_detail, root) => {
        const mode = modes.get(root);

        return mode === 'reply' ? 'comments:reply-close' : mode === 'edit' ? 'comments:edit-close' : null;
    },
    'comments:comment-deleted': () => 'comments:delete',
    'comments:report-changed': () => 'comments:flag',
    'comments:vote-changed': (detail) => (detail.vote === 'upvote' ? 'comments:upvote' : detail.vote === 'downvote' ? 'comments:downvote' : null),
    'comments:follow-changed': () => 'comments:subscribe',
};

export function dispatchLegacyEvent(root: HTMLElement, name: CommentsEventName, detail: object): void {
    if (!root.hasAttribute('data-comments-compatibility')) {
        return;
    }

    const legacyName = LEGACY_NAMES[name]?.(detail as Detail, root);

    if (legacyName) {
        document.dispatchEvent(new CustomEvent(legacyName, { detail }));
    }

    // A new comment or a closed editor ends the reply or edit it was for.
    if (name === 'comments:comment-created' || name === 'comments:editor-closed') {
        modes.delete(root);
    }
}
