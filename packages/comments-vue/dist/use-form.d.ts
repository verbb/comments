import { type CommentFormInput } from '@verbb/comments-browser/form';
import type { CreateResponse, ThreadStore, UpdateResponse } from '@verbb/comments-core';
import { type MaybeRefOrGetter, type Ref, type ShallowRef } from 'vue';
export type { CommentFormInput };
type ThreadLike = {
    store: Readonly<ShallowRef<ThreadStore | null>> | ThreadStore | null;
} | ThreadStore | null;
export type UseCommentFormOptions = {
    /** The thread to post to: what `useCommentsThread()` returns, or core's store. */
    thread: MaybeRefOrGetter<ThreadLike>;
    /** A new comment, a reply or an edit. Defaults to `create`. */
    mode?: MaybeRefOrGetter<'create' | 'reply' | 'edit' | undefined>;
    /** The comment being replied to, for `reply`. */
    parentUid?: MaybeRefOrGetter<string | null | undefined>;
    /** The comment being edited, for `edit`. */
    commentUid?: MaybeRefOrGetter<string | null | undefined>;
    /** A CSP nonce for Protect's challenge scripts. Defaults to the plugin's. */
    nonce?: string;
};
export type UseCommentFormResult = {
    /** Bind to your `<form>` (`<form :ref="form.ref">`, or `ref="…"` with it in `<script setup>`), so Protect can add its fields and run its check there. */
    ref: Ref<HTMLFormElement | null>;
    /** Posts the comment. Resolves with the data API's answer, or null when it was refused, with the reasons in `fieldErrors` and `error`. */
    submit(input?: CommentFormInput): Promise<CreateResponse | UpdateResponse | null>;
    /** A `@submit` handler that stops the browser's own submission and calls `submit()`. */
    handleSubmit(event?: Event): Promise<CreateResponse | UpdateResponse | null>;
    /** Messages by field, as the data API returns them: `content`, `guest.name`, `guest.email` or `fields.<handle>`. */
    fieldErrors: Readonly<Ref<Record<string, string[]>>>;
    /** A message to show for the whole form, when posting was refused or failed. */
    error: Readonly<Ref<string | null>>;
    posting: Readonly<Ref<boolean>>;
    /** Clears the errors. */
    reset(): void;
};
/**
 * Posting from your own form, for a custom interface built on `useCommentsThread()`. Protect is
 * mounted on the form when someone starts writing in it and kept fresh while it's open, and its
 * evidence goes with each post, so guests' comments pass the site's spam protection.
 */
export declare function useCommentForm(options: UseCommentFormOptions): UseCommentFormResult;
