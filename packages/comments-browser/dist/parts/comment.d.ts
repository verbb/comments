import type { BadgeData, CommentData } from '@verbb/comments-core';
import { type TemplateResult } from 'lit-html';
import { type ThreadView } from '../view.js';
/** Replies indent this many levels, then share the last level's indent. */
export declare const MAX_VISUAL_DEPTH = 4;
/** One comment and its replies, as a list item. */
export declare function commentItem(view: ThreadView, comment: CommentData): unknown;
/** The avatar for a comment's author, or a guest's. */
export declare function avatar(view: Pick<ThreadView, 'control'>, person: {
    avatarUrl: string | null;
    initials: string;
    avatarTone: number;
} | null, size?: number): unknown;
export declare function badgeTemplate(badge: BadgeData): TemplateResult;
