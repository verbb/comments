import { selectRoots } from '@verbb/comments-core';
import { html, nothing } from 'lit-html';
import { repeat } from 'lit-html/directives/repeat.js';
import { attributes } from '../directives.js';
import { format } from '../labels.js';
import { avatar, commentItem } from './comment.js';
import { icon } from './icons.js';
/**
 * The whole thread: the live region, header, the editor or a way in, the list, the toast and the
 * report dialog. Before the thread loads, the list is a loading line or the Show comments button.
 */
export function threadTemplate(view) {
    const ready = view.phase === 'ready';
    // The waiting placeholder and the drawn thread are different elements, so what arrives is new
    // content in the reserved space rather than something already there being pushed down.
    return html `<div data-comments-region="live" class="comments__live" role="status" aria-live="polite" aria-atomic="true">${view.ui.live}</div>${view.part('header', partData(view), () => header(view))}${ready
        ? html `<div data-comments-region="editor">${editorRegion(view)}</div><div data-comments-region="list">${list(view)}</div>`
        : html `<div data-comments-region="placeholder">${waiting(view)}</div>`}<div data-comments-region="toast">${toast(view)}</div>${ready && view.reportDialog ? html `<div data-comments-region="report-dialog">${view.reportDialog(view)}</div>` : nothing}`;
}
// The thread's parts have no comment to act on, so their actions are the same empty object each time.
const NO_ACTIONS = Object.freeze({});
function partData(view) {
    return { viewer: view.state.viewer, conversation: view.state.conversation, state: view.state, actions: NO_ACTIONS };
}
function header(view) {
    const { labels, state } = view;
    const conversation = state.conversation;
    const sorts = conversation?.sorts ?? [];
    const sortLabels = { newest: labels.sortNewest, oldest: labels.sortOldest, top: labels.sortTop };
    // Top first, as the Twig thread ordered them.
    const ordered = ['top', 'newest', 'oldest'].filter((sort) => sorts.includes(sort));
    const follow = state.viewer?.follow;
    const showFollow = Boolean(follow && (follow.following || (follow.available && !follow.needsEmail)));
    return html `<header class="comments__header" data-comments-region="header"><div class="comments__header-main"><h2 class="comments__heading" id=${`${view.instanceId}-heading`} tabindex="-1">${labels.comments}${view.count > 0 ? html ` <span class="comments__count"><span class="comments__visually-hidden">: </span>${view.count.toLocaleString(state.locale ?? undefined)}</span>` : nothing}</h2>${view.phase === 'ready' && ordered.length > 1
        ? html `<nav class="comments__sort" aria-label=${labels.sortLabel}>${ordered.map((sort) => html `<button ${attributes(view.control('quietButton', { identify: 'comments__sort-option', attributes: { type: 'button', 'data-comments-action': 'sort', 'data-comments-sort': sort, 'aria-pressed': state.sort === sort ? 'true' : 'false', 'aria-busy': view.ui.busy.has(`sort:${sort}`) ? 'true' : null } }))}>${sortLabels[sort] ?? sort}</button>`)}</nav>`
        : view.phase === 'ready' ? nothing : html `<span class="comments__sort" aria-hidden="true" data-comments-placeholder><span class="comments__sort-option comments__button comments__button--quiet">&nbsp;</span></span>`}${view.phase === 'ready' && showFollow ? followButton(view) : nothing}</div>${view.phase === 'ready' ? moderatorBar(view) : nothing}</header>`;
}
/** Follow or stop following. A guest who'd have to give an address follows from the join card instead. */
function followButton(view, withEmail = false) {
    const { labels } = view;
    const following = Boolean(view.state.viewer?.follow.following);
    return html `<form class="comments__follow-form" data-comments-form="follow" novalidate>${withEmail
        ? html `<label class="comments__field" for=${`${view.instanceId}-follow-email`}><span class="comments__field-label">${labels.yourEmail}</span><input ${attributes(view.control('input', { attributes: { id: `${view.instanceId}-follow-email`, name: 'email', type: 'email', autocomplete: 'email', required: true } }))}></label>`
        : nothing}<button ${attributes(view.control('secondaryButton', { identify: 'comments__follow', attributes: { type: 'submit', 'data-comments-action': 'follow', 'aria-pressed': following ? 'true' : 'false', 'aria-busy': view.ui.busy.has('follow') ? 'true' : null } }))}>${icon('bell')}${following ? labels.following : labels.follow}</button></form>`;
}
function moderatorBar(view) {
    const { labels, state } = view;
    const moderation = state.moderation;
    if (!state.viewer?.isModerator || !moderation || (!moderation.heldCount && !moderation.reportedCount)) {
        return nothing;
    }
    const parts = [
        moderation.heldCount ? format(labels.heldCount, { count: moderation.heldCount }) : '',
        moderation.reportedCount ? format(labels.reportedCount, { count: moderation.reportedCount }) : '',
    ].filter(Boolean);
    return html `<div class="comments__moderator-bar" role="note"><p><strong>${labels.moderatorView}</strong> ${parts.join(' · ')} ${labels.onThisPage}</p><div class="comments__moderator-bar-actions">${moderation.heldCount
        ? html `<button ${attributes(view.control('secondaryButton', { attributes: { type: 'button', 'data-comments-action': 'toggle-held', 'aria-pressed': view.ui.hideHeld ? 'true' : 'false' } }))}>${view.ui.hideHeld ? labels.showHeld : labels.hideHeld}</button>`
        : nothing}${moderation.reviewUrl
        ? html `<a ${attributes(view.control('secondaryButton', { attributes: { href: moderation.reviewUrl, target: '_blank', rel: 'noopener' } }))}>${labels.reviewInComments}</a>`
        : nothing}</div></div>`;
}
/** What goes above the list: the editor, a way in for guests, or why nobody can post. */
function editorRegion(view) {
    const { state, labels } = view;
    const viewer = state.viewer;
    const conversation = state.conversation;
    const closed = conversation?.status === 'closed';
    const follow = viewer?.follow;
    const guestFollow = Boolean(follow && follow.available && follow.needsEmail && !follow.following);
    if (viewer && !viewer.isGuest && viewer.canComment && !closed) {
        return view.form('create');
    }
    if (viewer && viewer.isGuest && !closed && (viewer.canComment || viewer.loginUrl)) {
        return view.part('join', partData(view), () => html `<section class="comments__join" aria-labelledby=${`${view.instanceId}-join-title`}><h3 class="comments__join-title" id=${`${view.instanceId}-join-title`}>${labels.joinTitle}</h3><p class="comments__join-text">${viewer.canComment ? labels.signInOrGuest : labels.signInToComment}</p><div class="comments__join-actions">${viewer.loginUrl
            ? html `<a ${attributes(view.control('primaryButton', { attributes: { href: viewer.loginUrl } }))}>${labels.signIn}</a>`
            : nothing}${viewer.canComment
            ? html `<details class="comments__join-guest"><summary ${attributes(view.control('secondaryButton', { attributes: { 'data-comments-action': 'join-guest' } }))}>${labels.commentAsGuest}</summary>${view.ui.joinOpen || view.mode === 'string' ? view.form('create') : nothing}</details>`
            : nothing}${guestFollow ? followByEmail(view) : nothing}</div></section>`);
    }
    const notice = closed ? conversation?.closedNotice || labels.closed : '';
    return html `${notice ? view.part('notice', { ...partData(view), notice }, () => html `<p class="comments__closed-notice">${notice}</p>`) : nothing}${guestFollow ? html `<div class="comments__join-actions">${followByEmail(view)}</div>` : nothing}`;
}
function followByEmail(view) {
    return html `<details class="comments__join-guest comments__join-follow"><summary ${attributes(view.control('secondaryButton', {}))}>${view.labels.followByEmail}</summary>${followButton(view, true)}</details>`;
}
function list(view) {
    const { state, labels } = view;
    const roots = selectRoots(state);
    if (!roots.length) {
        return html `${view.part('empty', partData(view), () => html `<div class="comments__empty"><p>${labels.empty}</p>${state.viewer?.canComment ? html `<p>${labels.beFirst}</p>` : nothing}</div>`)}`;
    }
    return html `<ol class="comments__list" id=${`${view.instanceId}-list`} role="list" aria-busy=${state.status === 'loading' ? 'true' : nothing}>${repeat(roots, (comment) => comment.uid, (comment) => commentItem(view, comment))}</ol>${state.next
        ? html `<div class="comments__load-more"><button ${attributes(view.control('secondaryButton', { identify: 'comments__load-more-button', attributes: { type: 'button', 'data-comments-action': 'load-more', 'aria-controls': `${view.instanceId}-list`, 'aria-busy': state.loadingMore ? 'true' : null } }))}>${labels.loadMore}</button></div>`
        : nothing}`;
}
/** The reserved space before the thread arrives: a quiet loading line, or Show comments. */
function waiting(view) {
    const { labels } = view;
    if (view.phase === 'button') {
        return view.part('showButton', partData(view), () => html `<div class="comments__show-comments" data-comments-placeholder><button ${attributes(view.control('secondaryButton', { identify: 'comments__show-comments-button', attributes: { type: 'button', 'data-comments-action': 'show-comments', 'aria-busy': view.ui.busy.has('show') ? 'true' : null } }))}>${view.count ? format(labels.showCommentsCount, { count: view.count }) : labels.showComments}</button></div>`);
    }
    if (view.phase === 'error') {
        // The thread couldn't load: why, with a way to try again, in place of the loading line.
        return html `<div class="comments__load-error" data-comments-placeholder><p class="comments__loading">${view.state.error?.message ?? labels.error}</p><button ${attributes(view.control('secondaryButton', { identify: 'comments__retry', attributes: { type: 'button', 'data-comments-action': 'retry' } }))}>${labels.tryAgain}</button></div>`;
    }
    return view.part('loading', partData(view), () => html `<p class="comments__loading" data-comments-placeholder aria-busy="true">${labels.loading}</p>`);
}
function toast(view) {
    const toast = view.ui.toast;
    return html `<div class="comments__toast" data-comments-state=${toast ? toast.kind : nothing}>${toast ? toast.message : nothing}${toast?.retry ? html `<button type="button" data-comments-action="retry">${view.labels.tryAgain}</button>` : nothing}</div>`;
}
/**
 * Stands in for a member's collapsed editor until the form chunk arrives, drawn the same size, so
 * the list below doesn't move. Focusing it starts the load, and the real box takes the focus. Its box
 * sits in a `comments__frame-box`, as the real form's does, because a collapsed frame shows nothing else.
 */
export function formPlaceholder(view, failed = false) {
    const viewer = view.state.viewer;
    // Typing can start before the form arrives; what's typed carries over. If the form couldn't
    // load, it says so, with Try again, which is also tried when someone heads for it.
    return html `<div class="comments__editor comments__editor--create" data-comments-form-placeholder><div class="comments__editor-row">${viewer && !viewer.isGuest ? avatar(view, viewer, 36) : nothing}<div class="comments__frame" ?data-comments-collapsed=${!failed}>${failed
        ? html `<p class="comments__form-error" role="alert">${view.labels.formFailed}</p><button ${attributes(view.control('secondaryButton', { attributes: { type: 'button', 'data-comments-action': 'retry-forms' } }))}>${view.labels.tryAgain}</button>`
        : html `<div class="comments__frame-box"><textarea ${attributes(view.control('textarea', { identify: 'comments__textarea-fallback', attributes: { rows: 1, placeholder: view.labels.editorPlaceholder, 'aria-label': view.labels.yourComment } }))}></textarea></div>`}</div></div></div>`;
}
