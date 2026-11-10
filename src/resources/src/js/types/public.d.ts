/**
 * Types for Comments' front-end runtime: `window.Comments`, each thread's controller, and the
 * `comments:*` browser events with their `detail`.
 *
 * The build ships this file beside the runtime as `comments-frontend.d.ts`, so a site that imports
 * `comments-frontend.js` into its own bundle gets these types with it. The runtime imports them too,
 * so the build fails if it stops matching what's published here.
 */

/** Which form is being written in: a new comment, a reply or an edit. */
export type CommentsEditorMode = 'create' | 'reply' | 'edit';

/** The orders a thread can be shown in. */
export type CommentsSort = 'newest' | 'oldest' | 'top';

/** Errors to show on a comment form, in the same shape the server returns them. */
export interface CommentsFormErrors {
    /** Messages keyed by input name, such as `fields[comment]`. Each field shows its first message. */
    fieldErrors?: Record<string, string[]>;
    /** Shown at the top of the form when none of the field errors has a field to go beside. */
    message?: string;
}

/** One thread on the page, from `Comments.get(root)` or any event's `detail.controller`. */
export interface CommentsController {
    readonly root: HTMLElement;
    readonly conversationUid: string;
    /** Fetches the header and list again. `reason` is passed on to the request events. */
    refresh(options?: { reason?: string; sort?: CommentsSort | (string & {}) }): void;
    /** Opens the form for a new comment, or a reply or edit form under the comment with `commentUid`. */
    openEditor(options: { mode: CommentsEditorMode; commentUid?: string }): void;
    /** Closes the open reply or edit form. */
    closeEditor(options?: { reason?: string }): void;
    /** Shows errors on a form the way the runtime shows the server's, and moves focus to the first. */
    showFormErrors(form: HTMLFormElement, errors: CommentsFormErrors): void;
    /** Removes errors shown on a form, whether they came from the server or `showFormErrors()`. */
    clearFormErrors(form: HTMLFormElement): void;
    /** Stops this thread. */
    destroy(): void;
}

/** `window.Comments`, and the module's `Comments` and default export. */
export interface CommentsRuntime {
    /** Starts the threads inside `scope`, or the whole document, and returns their controllers. */
    mount(scope?: ParentNode): CommentsController[];
    /** Stops the threads inside `scope`, or the whole document. */
    destroy(scope?: ParentNode): void;
    /** The controller for a thread's root element, if it's started. */
    get(root: Element): CommentsController | undefined;
}

/** What every `comments:*` event's `detail` has. */
export interface CommentsEventDetail {
    contractVersion: number;
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
    'comments:action-before': {
        /** The clicked control's `data-comments-action`, such as `reply` or `remove`. */
        action: string;
    };
    'comments:submit-before': {
        form: HTMLFormElement;
        mode: CommentsEditorMode;
        /** The comment being replied to or edited, or `null` for a new comment. */
        commentUid: string | null;
        /** The values about to be posted. Change the form's own fields to send something different. */
        formData: FormData;
    };
    'comments:request-start': {
        operationId: string | null;
        reason?: string;
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
    'comments:viewer-hydrated': Record<never, never>;
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
        operationId: string | null;
        /** The new comment's UID, or `null` while it isn't public, such as when it waits for moderation. */
        resource: string | null;
    };
    'comments:comment-updated': {
        operationId: string | null;
        resource: string | null;
    };
    'comments:comment-removed': {
        commentUid: string | null;
    };
    'comments:comment-moderated': {
        commentId: string | null;
        moderationAction: string | null;
    };
    'comments:interaction-changed': {
        commentId: string | null;
        /** `upvote` or `downvote`. A like is an `upvote`. */
        type: string | null;
    };
    'comments:report-submitted': {
        commentId: string | null;
    };
    'comments:subscription-changed': {
        subscribed: boolean;
    };
}

export type CommentsEventName = keyof CommentsEventDetailMap;

/** Every `comments:*` event, as the `CustomEvent` a listener receives. */
export type CommentsEventMap = {
    [K in CommentsEventName]: CustomEvent<CommentsEventDetail & CommentsEventDetailMap[K]>;
};

export declare const Comments: CommentsRuntime;
export default Comments;

declare global {
    interface Window {
        Comments: CommentsRuntime;
    }

    // The events bubble from each thread's root, so they're typed wherever a listener can hear them.
    interface HTMLElementEventMap extends CommentsEventMap {}
    interface DocumentEventMap extends CommentsEventMap {}
    interface WindowEventMap extends CommentsEventMap {}
}
