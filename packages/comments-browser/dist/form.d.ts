/**
 * Posting from a site's own form, for an interface it draws itself on core's thread store. React's
 * and Vue's `useCommentForm()` are built on this, and a site without a framework uses it directly.
 *
 * This is its own entry, `@verbb/comments-browser/form`, so it never loads with the thread's script.
 */
import { type ContentInput, type CreateResponse, type GuestInput, type ThreadStore, type UpdateResponse } from '@verbb/comments-core';
export type CommentFormMode = 'create' | 'reply' | 'edit';
export type CommentFormOptions = {
    /** A new comment, a reply or an edit. Defaults to `create`. */
    mode?: CommentFormMode;
    /** The comment being replied to, for `reply`. */
    parentUid?: string | null;
    /** The comment being edited, for `edit`. */
    commentUid?: string | null;
    /** A CSP nonce for Protect's challenge scripts. */
    nonce?: string;
};
/** What `submit()` posts. Anything left out is read from the form's own fields. */
export type CommentFormInput = {
    /** Plain text, or `{ format: 'rich', document }` with Editor's document. Read from a field named `content` when left out. */
    content?: string | ContentInput;
    /** A guest's name and email. Read from `guest[name]`, `guest[email]` and `guest[notifyReplies]` when left out. */
    guest?: GuestInput;
    /** Custom field values by handle, in the data API's shapes. Read from `fields[<handle>]` (or `fields[<handle>][]`) when left out. */
    fields?: Record<string, unknown>;
    /**
     * The write's ID, so the server answers a repeat with the first result rather than posting
     * twice. Trying the same comment again after a failure reuses one by itself, until it's posted
     * or what's being posted changes.
     */
    operationId?: string;
};
export type CommentFormState = {
    /** Messages by field, as the data API returns them: `content`, `guest.name`, `guest.email` or `fields.<handle>`. */
    fieldErrors: Record<string, string[]>;
    /** A message to show for the whole form, when posting was refused or failed. */
    error: string | null;
    posting: boolean;
};
export type CommentForm = {
    /** The form's errors and whether it's posting. A new object after each change. */
    getState(): CommentFormState;
    /** Called after every change to the state. Returns a function that unsubscribes. */
    subscribe(listener: (state: CommentFormState) => void): () => void;
    /** Posts the comment. Resolves with the data API's answer, or null when it was refused, with the reasons in the state. */
    submit(input?: CommentFormInput): Promise<CreateResponse | UpdateResponse | null>;
    /** A submit handler that stops the browser's own submission and calls `submit()`. */
    handleSubmit(event?: Event): Promise<CreateResponse | UpdateResponse | null>;
    /** Changes what the form posts, such as which comment it replies to. */
    set(options: Pick<CommentFormOptions, 'mode' | 'parentUid' | 'commentUid'>): void;
    /** Clears the errors. */
    reset(): void;
    /** Takes Protect off the form and stops listening to it. */
    destroy(): void;
};
/**
 * Posting from your own `<form>` to a thread store. Protect is mounted on the form when someone starts
 * writing in it and kept fresh while it's open, and its evidence goes with each post, so guests'
 * comments pass the site's spam protection. `form` can be null when `submit()` is always given what
 * to post, though without a form there's nowhere to mount Protect.
 */
export declare function commentForm(form: HTMLFormElement | null, store: ThreadStore, options?: CommentFormOptions): CommentForm;
