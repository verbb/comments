import type { ThreadState, ViewerData, VoteValue } from '@verbb/comments-core';
import type { CommentsController } from './types.js';
/** Which form is being written in: a new comment, a reply or an edit. */
export type CommentsEditorMode = 'create' | 'reply' | 'edit';
/** What every `comments:*` event's `detail` has. */
export interface CommentsEventDetail {
    /** The version of the JSON format the script reads, which changes only when that format does. */
    apiVersion: number;
    root: HTMLElement;
    instanceId: string;
    conversationUid: string;
    controller: CommentsController | undefined;
    /** `user` for the cancelable events a visitor causes, and `api` for everything else. */
    source: 'api' | 'user';
}
/** What each event adds to `CommentsEventDetail`. */
export interface CommentsEventDetailMap {
    'comments:mounted': Record<never, never>;
    'comments:destroyed': Record<never, never>;
    'comments:before-render': {
        /**
         * What just changed: `thread`, `page`, `replies` or `viewer` data arriving, a `comment` loaded
         * for a link, or a `write`, such as a new comment or a vote.
         */
        reason: 'thread' | 'page' | 'replies' | 'viewer' | 'comment' | 'write';
        /** The thread's state about to be drawn. Change comments or labels in place; cancel to skip drawing this update. */
        data: ThreadState;
    };
    'comments:before-action': {
        /** The control's `data-comments-action`, such as `reply` or `edit`. */
        action: string;
        commentUid: string | null;
    };
    'comments:before-submit': {
        form: HTMLFormElement;
        mode: CommentsEditorMode;
        /** The comment being replied to or edited, or `null` for a new comment. */
        commentUid: string | null;
        /** The values about to be posted. Change the form's own fields to send something different. */
        formData: FormData;
    };
    'comments:request-start': {
        /** What the request is for, such as `thread`, `vote` or `create`. */
        reason: string;
    };
    'comments:request-end': {
        reason: string;
    };
    'comments:request-error': {
        message: string;
        reason?: string;
        category?: string;
        code?: string;
        fieldErrors?: Record<string, string[]>;
        recoverable?: boolean;
    };
    'comments:viewer-loaded': {
        /** Who's reading and what they may do, as the thread now has it. */
        viewer: ViewerData | null;
    };
    'comments:roots-loaded': Record<never, never>;
    'comments:replies-loaded': {
        commentUid: string;
    };
    'comments:sorted': {
        sort: string;
    };
    'comments:editor-opened': {
        mode: string;
        commentUid: string | null;
    };
    'comments:editor-closed': {
        reason: string;
    };
    'comments:comment-created': {
        /** The new comment's UID. */
        commentUid: string;
        /** `pending` when it waits for a moderator, and only its author sees it. */
        moderationState: 'approved' | 'pending';
    };
    'comments:comment-updated': {
        commentUid: string;
    };
    'comments:comment-deleted': {
        commentUid: string | null;
    };
    'comments:comment-moderated': {
        commentUid: string | null;
        /** The number in the comment's anchor, `comment-1446`. */
        commentId: string | null;
        moderationAction: string | null;
    };
    'comments:vote-changed': {
        commentUid: string | null;
        commentId: string | null;
        /** `upvote`, `downvote`, or `null` when a vote was taken back. A like is an `upvote`. */
        vote: VoteValue | null;
    };
    'comments:report-changed': {
        commentUid: string | null;
        commentId: string | null;
        /** False when the visitor withdrew their report. */
        reported: boolean;
    };
    'comments:follow-changed': {
        following: boolean;
    };
}
export type CommentsEventName = keyof CommentsEventDetailMap;
/** Every `comments:*` event, as the `CustomEvent` a listener receives. */
export type CommentsEventMap = {
    [K in CommentsEventName]: CustomEvent<CommentsEventDetail & CommentsEventDetailMap[K]>;
};
type DispatchOptions = {
    controller?: CommentsController;
    source?: 'api' | 'user';
    cancelable?: boolean;
};
declare let legacyDispatcher: ((root: HTMLElement, name: CommentsEventName, detail: object) => void) | null;
export declare function setLegacyEventDispatcher(dispatcher: typeof legacyDispatcher): void;
/**
 * Dispatches a `comments:*` event from a thread's root, with the detail every event shares, and
 * returns false if a listener cancelled it.
 */
export declare function dispatchCommentsEvent<K extends CommentsEventName>(root: HTMLElement, name: K, detail: CommentsEventDetailMap[K], options?: DispatchOptions): boolean;
export {};
