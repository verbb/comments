import { type CommentFormInput } from '@verbb/comments-browser/form';
import type { CreateResponse, ThreadStore, UpdateResponse } from '@verbb/comments-core';
import { type FormEvent } from 'react';
export type { CommentFormInput };
export type UseCommentFormOptions = {
    /** The thread to post to: what `useCommentsThread()` returns, or core's store. */
    thread: {
        store: ThreadStore | null;
    } | ThreadStore | null;
    /** A new comment, a reply or an edit. Defaults to `create`. */
    mode?: 'create' | 'reply' | 'edit';
    /** The comment being replied to, for `reply`. */
    parentUid?: string | null;
    /** The comment being edited, for `edit`. */
    commentUid?: string | null;
    /** A CSP nonce for Protect's challenge scripts. Defaults to the provider's. */
    nonce?: string;
};
export type UseCommentFormResult = {
    /** Put on your `<form>`, so Protect can add its fields and run its check there. */
    ref: (form: HTMLFormElement | null) => void;
    /** Posts the comment. Resolves with the data API's answer, or null when it was refused, with the reasons in `fieldErrors` and `error`. */
    submit(input?: CommentFormInput): Promise<CreateResponse | UpdateResponse | null>;
    /** An `onSubmit` handler that stops the browser's own submission and calls `submit()`. */
    handleSubmit(event?: FormEvent<HTMLFormElement> | Event): Promise<CreateResponse | UpdateResponse | null>;
    /** Messages by field, as the data API returns them: `content`, `guest.name`, `guest.email` or `fields.<handle>`. */
    fieldErrors: Record<string, string[]>;
    /** A message to show for the whole form, when posting was refused or failed. */
    error: string | null;
    posting: boolean;
    /** Clears the errors. */
    reset(): void;
};
/**
 * Posting from your own form, for a custom interface built on `useCommentsThread()`. Protect is
 * mounted on the form when someone starts writing in it and kept fresh while it's open, and its
 * evidence goes with each post, so guests' comments pass the site's spam protection.
 */
export declare function useCommentForm(options: UseCommentFormOptions): UseCommentFormResult;
