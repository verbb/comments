import type { BadgeData, CommentData, CommentViewerData, ModerationAction, ModerationData } from '@verbb/comments-core';
import { selectReplies } from '@verbb/comments-core';
import { html, noChange, nothing, type TemplateResult } from 'lit-html';
import { repeat } from 'lit-html/directives/repeat.js';
import { attributes, richHtml } from '../directives.js';
import { format } from '../labels.js';
import { anchorOf, type ThreadView } from '../view.js';
import { detailFields, inlineFields } from './fields.js';
import { icon, type IconName } from './icons.js';

/** Replies indent this many levels, then share the last level's indent. */
export const MAX_VISUAL_DEPTH = 4;

const MODERATION_LABELS: Record<ModerationAction, string> = {
    approve: 'approve',
    keep: 'keep',
    remove: 'remove',
    restore: 'restore',
    spam: 'spam',
    'not-spam': 'notSpam',
    hold: 'hold',
};

/** States a moderator, or a comment's author, sees in a tinted box with a review strip. */
const REVIEWED_STATES = new Set(['held', 'spam', 'pending', 'removed', 'reported']);

function permissionsOf(view: ThreadView, uid: string): CommentViewerData | null {
    return view.state.permissions[uid] ?? null;
}

function moderationOf(view: ThreadView, comment: CommentData): ModerationData | null {
    return comment.moderation ?? permissionsOf(view, comment.uid)?.moderation ?? null;
}

/** States Hide held takes out of a moderator's view, replies included. */
const HELD_STATES = new Set(['held', 'spam', 'removed']);

/** One comment and its replies, as a list item. */
export function commentItem(view: ThreadView, comment: CommentData): unknown {
    if (view.ui.hideHeld && HELD_STATES.has(comment.state)) {
        return nothing;
    }

    // A comment drawn once stays drawn; a new one waits for a later pass once this one's share is used.
    const budget = view.budget;

    if (budget && !budget.drawn.has(comment.uid)) {
        if (budget.left <= 0) {
            budget.more = true;
            return nothing;
        }

        budget.left--;
        budget.drawn.add(comment.uid);
    } else if (budget?.adding && allDrawn(view, comment, budget.drawn)) {
        // A pass that only adds comments leaves this one, with all its replies drawn, as it is.
        return noChange;
    }

    // A component override of the whole comment draws inside Comments' list item and article, with
    // the comment's form and replies after it, so Reply, Edit, links and focus keep working.
    return view.part('comment', partData(view, comment), () => defaultComment(view, comment), {
        around: (host) => commentFrame(view, comment, html`${host}${formSlot(view, comment)}`),
        inside: () => commentInside(view, comment),
    });
}

/** Whether every reply under a comment, however deep, is drawn. */
function allDrawn(view: ThreadView, comment: CommentData, drawn: Set<string>): boolean {
    return selectReplies(view.state, comment.uid).every((reply) => (view.ui.hideHeld && HELD_STATES.has(reply.state)) || (drawn.has(reply.uid) && allDrawn(view, reply, drawn)));
}

// One part context per comment per draw, shared by its parts, since a thread can hold hundreds.
const contexts = new WeakMap<ThreadView, Map<string, Record<string, unknown>>>();

function partData(view: ThreadView, comment: CommentData): Record<string, unknown> {
    let byComment = contexts.get(view);

    if (!byComment) {
        byComment = new Map();
        contexts.set(view, byComment);
    }

    let data = byComment.get(comment.uid);

    if (!data || data.comment !== comment) {
        data = {
            comment,
            permissions: permissionsOf(view, comment.uid),
            viewer: view.state.viewer,
            conversation: view.state.conversation,
            actions: view.actions(comment.uid),
            // For an override of the whole comment: where its reply or edit form, and its replies, go.
            form: () => contextualForm(view, comment),
            replies: () => replies(view, comment),
        };
        byComment.set(comment.uid, data);
    }

    return data;
}

function defaultComment(view: ThreadView, comment: CommentData): TemplateResult {
    return commentFrame(view, comment, comment.state === 'placeholder' ? commentInside(view, comment) : html`${commentInside(view, comment)}${formSlot(view, comment)}`);
}

/** What's inside a comment: its author, text, field values, actions and review strip, or the placeholder. */
function commentInside(view: ThreadView, comment: CommentData): unknown {
    if (comment.state === 'placeholder') {
        return view.part('placeholder', partData(view, comment), () => html`<p class="comments__placeholder">${view.labels.unavailable}</p>`);
    }

    // A folded comment keeps its author and actions, with a way to read it in place of what it says.
    return isFolded(view, comment)
        ? html`${author(view, comment)}${folded(view, comment)}${actions(view, comment)}${review(view, comment)}`
        : html`${author(view, comment)}${view.part('content', partData(view, comment), () => content(view, comment))}${fieldDetails(view, comment)}${actions(view, comment)}${review(view, comment)}`;
}

/** Whether the comment is folded away, as Comments 3 hid it, and the reader hasn't opened it. */
function isFolded(view: ThreadView, comment: CommentData): boolean {
    return Boolean(comment.folded) && !view.ui.unfolded.has(comment.uid);
}

/** What a folded comment shows in place of its text, GIF and field values. */
function folded(view: ThreadView, comment: CommentData): TemplateResult {
    const { labels } = view;
    const label = comment.folded === 'downvotes' ? labels.foldedDownvotes : labels.foldedReports;

    return html`<div class="comments__folded" data-comments-folded=${comment.folded ?? nothing}><p class="comments__folded-label">${label}</p><button ${attributes(view.control('quietButton', {
        identify: 'comments__folded-show',
        attributes: { type: 'button', 'data-comments-action': 'unfold', 'data-comments-comment-uid': comment.uid },
    }))}>${labels.showFolded}</button></div>`;
}

/** The comment's list item and article around what's inside it, with its replies at the end. */
function commentFrame(view: ThreadView, comment: CommentData, inside: unknown): TemplateResult {
    const { labels } = view;
    const placeholder = comment.state === 'placeholder';
    const state = comment.state;
    const classes = [
        'comments__comment',
        state === 'held' || state === 'spam' || state === 'removed' ? 'comments__comment--held' : '',
        state === 'pending' ? 'comments__comment--pending' : '',
        state === 'reported' || (state === 'approved' && permissionsOf(view, comment.uid)?.moderation?.openReportCount) ? 'comments__comment--reported' : '',
        view.ui.highlight === comment.uid ? 'comments__comment--highlight' : '',
    ].filter(Boolean).join(' ');
    const name = comment.author?.name ?? labels.guest;

    return html`<li class=${classes} data-comments-comment data-comments-comment-uid=${comment.uid} data-comments-depth=${comment.depth} data-comments-visual-depth=${Math.min(comment.depth, MAX_VISUAL_DEPTH)} data-comments-state=${state}><article id=${anchorOf(comment)} tabindex="-1" aria-label=${placeholder ? nothing : format(labels.commentBy, { name })}>${inside}${replies(view, comment)}</article></li>`;
}

/** Where the comment's reply or edit form opens. */
function formSlot(view: ThreadView, comment: CommentData): TemplateResult {
    return html`<div class="comments__contextual-editor" data-comments-region="contextual-editor" data-comments-comment-uid=${comment.uid}>${contextualForm(view, comment)}</div>`;
}

function contextualForm(view: ThreadView, comment: CommentData): unknown {
    const open = view.ui.openForm;
    return open && open.uid === comment.uid ? view.form(`${open.mode}:${comment.uid}`) : nothing;
}

/** The avatar for a comment's author, or a guest's. */
export function avatar(view: Pick<ThreadView, 'control'>, person: { avatarUrl: string | null; initials: string; avatarTone: number } | null, size = 32): unknown {
    if (person?.avatarUrl) {
        return html`<img ${attributes(view.control('avatar', { attributes: { src: person.avatarUrl, alt: '', width: size, height: size, loading: 'lazy' } }))}>`;
    }

    return html`<span ${attributes(view.control('avatar', { style: 'comments__avatar--initials', attributes: { 'data-tone': person?.avatarTone ?? 0, 'aria-hidden': 'true' } }))}>${person?.initials ?? ''}</span>`;
}

function author(view: ThreadView, comment: CommentData): TemplateResult {
    const data = partData(view, comment);
    const badges = comment.author?.badges ?? [];

    return html`<div class="comments__author">${view.part('avatar', data, () => avatar(view, comment.author))}<div class="comments__author-meta">${view.part('authorName', data, () => comment.author?.name ? html`<span class="comments__author-name">${comment.author.name}</span>` : nothing)}${authorScore(view, comment)}${badges.map((badge) => view.part('badge', { ...data, badge }, () => badgeTemplate(badge)))}${view.part('meta', data, () => meta(view, comment))}</div></div>`;
}

/** The author's net votes across their comments, as Comments 3 showed it, while the site shows scores. */
function authorScore(view: ThreadView, comment: CommentData): unknown {
    const score = comment.author?.isGuest ? null : comment.author?.score;

    if (typeof score !== 'number') {
        return nothing;
    }

    const tone = score > 0 ? ' comments__author-score--positive' : score < 0 ? ' comments__author-score--negative' : '';

    return html`<span class=${`comments__author-score${tone}`} title=${view.labels.authorScore}><span class="comments__visually-hidden">${view.labels.authorScore}: </span>${score > 0 ? `+${score}` : String(score)}</span>`;
}

export function badgeTemplate(badge: BadgeData): TemplateResult {
    return html`<span class=${`comments__badge comments__badge--${badge.type.replace(/[^\w-]/g, '')}`}>${badge.label}</span>`;
}

function meta(view: ThreadView, comment: CommentData): TemplateResult {
    const display = comment.display;
    const full = view.ui.fullDates.has(comment.uid);

    // A folded comment's field values stay folded with its text.
    const fields = isFolded(view, comment) ? nothing : inlineFields(view, comment);

    return html`${display && comment.createdAt ? html`<a ${attributes(view.control('link', { identify: 'comments__permalink', attributes: { href: comment.permalink ?? `#${anchorOf(comment)}`, 'data-comments-action': 'toggle-date', 'data-comments-comment-uid': comment.uid } }))}><time class="comments__date" datetime=${comment.createdAt} title=${display.fullDate}>${full ? display.fullDate : display.timeAgo || display.fullDate}</time></a>` : nothing}${fields}${comment.editedAt ? html`<span class="comments__edited" title=${comment.editedAt}>${view.labels.edited}</span>` : nothing}`;
}

/** A portrait GIF is sized from its height, so the theme can fit it to the same square as a landscape one. */
function gifClass(width: number | null | undefined, height: number | null | undefined): string {
    return width && height && height > width ? 'comments__gif comments__gif--portrait' : 'comments__gif';
}

function content(view: ThreadView, comment: CommentData): TemplateResult {
    const body = comment.content;

    if (!body) {
        return html`<div class="comments__content" dir="auto"></div>`;
    }

    const lines = body.text.split('\n');

    return html`<div class="comments__content" dir="auto">${body.format === 'rich' && body.html
        ? html`<div class="comments__content-rich" ${richHtml(body.html)}></div>`
        : body.text ? html`<p class="comments__content-plain">${lines.map((line, index) => index ? html`<br>${line}` : line)}</p>` : nothing}${body.gifUrl ? html`<img class=${gifClass(body.gifWidth, body.gifHeight)} src=${body.gifUrl} alt=${view.labels.gif} loading="lazy" width=${body.gifWidth ?? nothing} height=${body.gifHeight ?? nothing}>` : nothing}</div>`;
}

/** Custom field values shown under the text. */
function fieldDetails(view: ThreadView, comment: CommentData): unknown {
    return detailFields(view, comment, partData(view, comment));
}

function actions(view: ThreadView, comment: CommentData): unknown {
    // Comments waiting for a decision only have the review strip's decisions.
    if (comment.state !== 'approved' && comment.state !== 'reported') {
        return nothing;
    }

    return view.part('actions', partData(view, comment), () => defaultActions(view, comment));
}

function defaultActions(view: ThreadView, comment: CommentData): unknown {
    const { labels } = view;
    const permissions = permissionsOf(view, comment.uid);
    const mode = view.state.conversation?.interactionMode ?? 'like';
    const interaction = mode === 'none' ? null : comment.interaction;
    const pending = view.ui.pendingVotes.has(comment.uid) ? view.ui.pendingVotes.get(comment.uid)! : undefined;
    const current = pending !== undefined ? pending : permissions?.vote ?? null;
    // Shown before the server answers, so a like responds at once.
    const before = permissions?.vote ?? null;
    const delta = (vote: 'upvote' | 'downvote') => (pending === undefined ? 0 : (current === vote ? 1 : 0) - (before === vote ? 1 : 0));
    const upvotes = (interaction?.upvotes ?? 0) + delta('upvote');
    const downvotes = (interaction?.downvotes ?? 0) + delta('downvote');
    const updown = mode === 'updown';
    const moderation = moderationOf(view, comment);
    const menuModeration = comment.state === 'approved' && !moderation?.openReportCount ? moderation?.actions ?? [] : [];
    const canCopy = Boolean(comment.permalink);
    const canReport = Boolean(permissions?.canReport && !permissions.reported);
    const hasMenu = canCopy || permissions?.canEdit || permissions?.canDelete || canReport || menuModeration.length > 0;
    const anchor = anchorOf(comment);

    if (!interaction && !permissions?.canReply && !hasMenu) {
        return nothing;
    }

    const interactions = interaction ? html`<div class="comments__interactions" data-comments-interaction-mode=${mode}>${permissions?.canVote
        ? html`<button ${attributes(view.control('quietButton', { identify: 'comments__vote comments__vote--up', attributes: { type: 'button', 'data-comments-action': 'vote', 'data-comments-vote': 'upvote', 'data-comments-comment-uid': comment.uid, 'aria-pressed': current === 'upvote' ? 'true' : 'false' } }))}>${icon(updown ? 'up' : 'heart')}<span class=${updown || upvotes > 0 ? 'comments__visually-hidden' : 'comments__vote-label'}>${updown ? labels.upvote : labels.like}</span>${!updown && upvotes > 0 ? html`<span class="comments__vote-count">${upvotes}</span>` : nothing}</button>`
        : !updown && upvotes > 0 ? html`<span class="comments__vote-total">${icon('heart')}<span class="comments__visually-hidden">${labels.likes}: </span><span class="comments__vote-count">${upvotes}</span></span>` : nothing}${updown ? html`<span class="comments__score"><span class="comments__visually-hidden">${labels.score}: </span>${upvotes - downvotes}</span>` : nothing}${updown && permissions?.canDownvote
        ? html`<button ${attributes(view.control('quietButton', { identify: 'comments__vote comments__vote--down', attributes: { type: 'button', 'data-comments-action': 'vote', 'data-comments-vote': 'downvote', 'data-comments-comment-uid': comment.uid, 'aria-pressed': current === 'downvote' ? 'true' : 'false' } }))}>${icon('down')}<span class="comments__visually-hidden">${labels.downvote}</span></button>`
        : nothing}</div>` : nothing;

    const reply = permissions?.canReply
        ? html`<button ${attributes(view.control('quietButton', { identify: 'comments__action', attributes: { type: 'button', 'data-comments-action': 'reply', 'data-comments-comment-uid': comment.uid, 'aria-busy': view.ui.busy.has(`reply:${comment.uid}`) ? 'true' : null } }))}>${icon('reply')}${labels.reply}</button>`
        : nothing;

    const item = (action: string, label: string, extra: Record<string, string> = {}, danger = false) => html`<button type="button" class=${`comments__menu-item${danger ? ' comments__menu-item--danger' : ''}`} data-comments-action=${action} data-comments-comment-uid=${comment.uid} data-comments-moderation-action=${extra.moderation ?? nothing}>${label}</button>`;

    // A menu's items are built when it's first opened, which keeps a long thread quick to draw.
    const built = view.mode === 'string' || view.ui.menus.has(comment.uid);
    const menu = hasMenu
        ? html`<details class="comments__menu"><summary ${attributes(view.control('quietButton', { identify: 'comments__action comments__menu-toggle', attributes: { 'data-comments-comment-uid': comment.uid, 'aria-busy': view.ui.busy.has(`menu:${comment.uid}`) ? 'true' : null } }))}>${icon('more')}<span class="comments__visually-hidden">${labels.moreActions}</span></summary>${built ? html`<div class="comments__menu-items">${canCopy ? item('copy-link', labels.copyLink) : nothing}${permissions?.canEdit ? item('edit', labels.edit) : nothing}${canReport ? item('report', labels.report) : nothing}${permissions?.canDelete ? item('delete', labels.delete, {}, true) : nothing}${menuModeration.length
            ? html`<div class="comments__menu-group" role="group" aria-labelledby=${`${anchor}-moderator-actions`}><span class="comments__menu-heading" id=${`${anchor}-moderator-actions`}>${labels.moderator}</span>${menuModeration.map((action) => item('moderate', moderationLabel(view, action, true), { moderation: action }, true))}</div>`
            : nothing}</div>` : nothing}</details>`
        : nothing;

    return html`<div class="comments__actions">${interactions}${reply}${menu}</div>`;
}

function moderationLabel(view: ThreadView, action: ModerationAction, menu = false): string {
    const { labels } = view;

    if (menu && action === 'spam') return labels.markSpam;

    return labels[MODERATION_LABELS[action] ?? action] ?? action;
}

function review(view: ThreadView, comment: CommentData): unknown {
    const moderation = moderationOf(view, comment);
    const reported = comment.state === 'reported' || Boolean(comment.state === 'approved' && moderation?.openReportCount);

    if (!REVIEWED_STATES.has(comment.state) && !reported) {
        return nothing;
    }

    return view.part('review', { ...partData(view, comment), moderation }, () => defaultReview(view, comment, moderation, reported));
}

function defaultReview(view: ThreadView, comment: CommentData, moderation: ModerationData | null, reported: boolean): TemplateResult {
    const { labels } = view;
    const iconName: IconName = reported ? 'flag' : comment.state === 'spam' ? 'spam' : comment.state === 'removed' ? 'removed' : 'clock';
    const label = moderation?.reviewLabel
        ?? (comment.state === 'removed' ? labels.removedNote : comment.state === 'pending' ? labels.awaitingApproval : '');
    const decisions = moderation?.actions ?? [];

    return html`<div class="comments__review" data-comments-state-badge=${comment.state}><p class="comments__review-label">${icon(iconName)}${label}</p>${decisions.length
        ? html`<div class="comments__moderation-actions" role="group" aria-label=${labels.moderation}>${decisions.map((action, index) => html`<button ${attributes(view.control(index === 0 ? 'primaryButton' : 'secondaryButton', { attributes: { type: 'button', 'data-comments-action': 'moderate', 'data-comments-moderation-action': action, 'data-comments-comment-uid': comment.uid, 'aria-busy': view.ui.busy.has(`moderate:${comment.uid}:${action}`) ? 'true' : null } }))}>${moderationLabel(view, action)}</button>`)}</div>`
        : nothing}</div>`;
}

function replies(view: ThreadView, comment: CommentData): unknown {
    const state = view.state.replies[comment.uid];
    const loaded = selectReplies(view.state, comment.uid);
    const count = state?.count ?? comment.replies.count;
    const next = state ? state.next : comment.replies.next;
    const more = Boolean(next) && count > loaded.filter((reply) => reply.state === 'approved' || reply.state === 'reported').length;

    if (!loaded.length && !more) {
        return nothing;
    }

    const listId = `${anchorOf(comment)}-replies`;
    const collapsed = view.ui.collapsed.has(comment.uid);
    const remaining = Math.max(0, count - loaded.filter((reply) => reply.state === 'approved' || reply.state === 'reported').length);

    return html`<div class="comments__replies" data-comments-replies data-comments-root-uid=${comment.uid}>${loaded.length
        ? html`<button ${attributes(view.control('quietButton', { identify: 'comments__toggle-replies', attributes: { type: 'button', 'data-comments-action': 'toggle-replies', 'data-comments-comment-uid': comment.uid, 'aria-expanded': collapsed ? 'false' : 'true', 'aria-controls': listId } }))}>${collapsed ? view.labels.showReplies : view.labels.hideReplies}</button><ol class="comments__replies-list" id=${listId} role="list" ?hidden=${collapsed}>${repeat(loaded, (reply) => reply.uid, (reply) => commentItem(view, reply))}</ol>`
        : nothing}${more
        ? html`<div class="comments__load-more" ?hidden=${collapsed}><button ${attributes(view.control('secondaryButton', { identify: 'comments__load-more-button', attributes: { type: 'button', 'data-comments-action': 'load-replies', 'data-comments-comment-uid': comment.uid, 'aria-controls': loaded.length ? listId : null, 'aria-busy': view.ui.busy.has(`replies:${comment.uid}`) || state?.loading ? 'true' : null } }))}>${view.labels.loadMoreReplies}${remaining ? ` (${remaining})` : ''}</button></div>`
        : nothing}</div>`;
}
