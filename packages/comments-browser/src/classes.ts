/**
 * The renderer's class names, each marked stable or internal. Stable classes are the parts and
 * states a site would reasonably style; they change only in a major release, and a test fails if
 * one stops being drawn. Internal classes are helpers inside a part, documented so a site can see
 * them, but free to change in any release. `markup/classes.json` is generated from the renderer's
 * output with these lists (`npm run classes`), and a `*` stands for a value, such as a badge type.
 */
export const STABLE_CLASSES = [
    'comments',
    'comments__header', 'comments__heading', 'comments__count', 'comments__sort', 'comments__sort-option',
    'comments__moderator-bar', 'comments__follow',
    'comments__list', 'comments__comment', 'comments__comment--held', 'comments__comment--pending', 'comments__comment--reported', 'comments__comment--highlight',
    'comments__author', 'comments__avatar', 'comments__avatar--initials', 'comments__avatar--guest', 'comments__author-name',
    'comments__author-score', 'comments__author-score--positive', 'comments__author-score--negative',
    'comments__badge', 'comments__badge--*', 'comments__permalink', 'comments__date', 'comments__edited', 'comments__meta-item',
    'comments__content', 'comments__content-plain', 'comments__content-rich', 'comments__gif', 'comments__gif--portrait',
    'comments__folded', 'comments__folded-label', 'comments__folded-show',
    'comments__fields', 'comments__field-value', 'comments__field-value--*',
    'comments__field-link', 'comments__field-files', 'comments__field-file', 'comments__field-file--image', 'comments__field-thumb',
    'comments__uploads', 'comments__upload', 'comments__upload--image', 'comments__upload--file', 'comments__upload-add', 'comments__upload-remove', 'comments__upload-retry',
    'comments__actions', 'comments__vote', 'comments__vote--up', 'comments__vote--down', 'comments__vote-count', 'comments__score', 'comments__action',
    'comments__menu', 'comments__menu-toggle', 'comments__menu-items', 'comments__menu-item', 'comments__menu-item--danger',
    'comments__review', 'comments__review-label', 'comments__moderation-actions',
    'comments__placeholder', 'comments__replies', 'comments__replies-list', 'comments__toggle-replies', 'comments__load-more', 'comments__load-more-button',
    'comments__show-comments', 'comments__show-comments-button', 'comments__load-error', 'comments__retry',
    'comments__editor', 'comments__editor--create', 'comments__editor--reply', 'comments__editor--edit', 'comments__frame', 'comments__frame--fields',
    'comments__posting-as', 'comments__guest-fields', 'comments__field', 'comments__field-label', 'comments__field-error', 'comments__form-error',
    'comments__submit', 'comments__cancel',
    'comments__gif-button', 'comments__gif-preview', 'comments__gif-remove', 'comments__gif-picker', 'comments__gif-search', 'comments__gif-results', 'comments__gif-result', 'comments__gif-attribution',
    'comments__button', 'comments__button--primary', 'comments__button--secondary', 'comments__button--quiet',
    'comments__input', 'comments__textarea', 'comments__select', 'comments__checkbox', 'comments__radio', 'comments__link',
    'comments__join', 'comments__join-title', 'comments__join-text', 'comments__join-actions', 'comments__join-guest',
    'comments__empty', 'comments__closed-notice', 'comments__loading', 'comments__toast',
    'comments__report-dialog', 'comments__report-form', 'comments__report-reason',
    'comments__icon', 'comments__icon--*', 'comments__live', 'comments__visually-hidden',
] as const;

export const INTERNAL_CLASSES = [
    'comments__author-meta', 'comments__interactions', 'comments__vote-label', 'comments__vote-total',
    'comments__menu-group', 'comments__menu-heading', 'comments__contextual-editor', 'comments__header-main', 'comments__moderator-bar-actions', 'comments__follow-form',
    'comments__editor-row', 'comments__editor-fields', 'comments__editor-fields--above', 'comments__field-cell', 'comments__upload-row', 'comments__upload-limit', 'comments__frame-box', 'comments__textarea-fallback',
    'comments__frame-head', 'comments__frame-foot', 'comments__frame-meta', 'comments__frame-status', 'comments__frame-status-text', 'comments__frame-count',
    'comments__frame-icon', 'comments__frame-reply', 'comments__frame-reply-text', 'comments__frame-reply-cancel', 'comments__frame-notice', 'comments__frame-notice-text', 'comments__frame-link',
    'comments__submit-label', 'comments__submit-keys', 'comments__submit-icon', 'comments__ring-bg', 'comments__ring-fg',
    'comments__field--files', 'comments__upload-thumb', 'comments__upload-icon', 'comments__upload-text', 'comments__upload-name', 'comments__upload-meta',
    'comments__field--checkbox', 'comments__field-required', 'comments__switch', 'comments__field-custom', 'comments__field-instructions', 'comments__field-hint', 'comments__field-name', 'comments__field-text', 'comments__label',
    'comments__join-follow',
    'comments__report-header', 'comments__report-title', 'comments__report-close', 'comments__report-quote', 'comments__report-fields', 'comments__report-reason-label', 'comments__report-reason-description', 'comments__report-actions',
    'comments--reserve-*', 'comments__noscript', 'verbb-editor__button',
    'comments__frame-count--warn', 'comments__frame-count--bad', 'comments__frame-status--bad', 'comments__frame-status--faded',
    'comments__upload--uploading', 'comments__upload--failed', 'comments__upload--done',
    'comments__gif-area', 'comments__gif-preview-image', 'comments__gif-cell', 'comments__gif-result-image', 'comments__gif-status',
] as const;

/**
 * The `data-comments-*` attributes a site can rely on, like the stable classes: the mount point's
 * `root`, `auto-mount`, `adapter` and `theme`, the thread's `phase`, a comment's `state`, and the
 * `control` and `action` on the controls. Every other `data-comments-*` attribute is internal.
 */
export const PUBLIC_ATTRIBUTES = [
    'data-comments-root', 'data-comments-auto-mount', 'data-comments-adapter', 'data-comments-theme',
    'data-comments-phase', 'data-comments-state', 'data-comments-control', 'data-comments-action',
] as const;

/**
 * The attributes on a thread's root that `craft.comments.render()`, the adapters or the script itself
 * write, rather than the templates, and those on the empty element a component override is drawn into.
 */
export const MOUNT_POINT_ATTRIBUTES = [
    'data-comments-root', 'data-comments-api-version', 'data-comments-endpoint', 'data-comments-owner-id',
    'data-comments-site-id', 'data-comments-load-mode', 'data-comments-theme', 'data-comments-config',
    'data-comments-count', 'data-comments-labels', 'data-comments-auto-mount', 'data-comments-adapter',
    'data-comments-phase', 'data-comments-js',
] as const;
export const HOST_ATTRIBUTES = ['data-comments-host', 'data-comments-host-kind', 'data-comments-host-name'] as const;

/** Whether a class drawn by the renderer matches a name or pattern in a list. */
export function classMatches(name: string, list: readonly string[]): string | undefined {
    return list.find((entry) => (entry.endsWith('*') ? name.startsWith(entry.slice(0, -1)) && name.length > entry.length - 1 : entry === name));
}
