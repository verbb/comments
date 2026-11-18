import { commentForm, type CommentForm, type CommentFormInput, type CommentFormState } from '@verbb/comments-browser/form';
import type { CreateResponse, ThreadStore, UpdateResponse } from '@verbb/comments-core';
import { useCallback, useEffect, useLayoutEffect, useRef, useState, useSyncExternalStore, type FormEvent } from 'react';
import { useCommentsSettings } from './settings.js';

const useBrowserLayoutEffect = typeof document === 'undefined' ? useEffect : useLayoutEffect;

export type { CommentFormInput };

export type UseCommentFormOptions = {
    /** The thread to post to: what `useCommentsThread()` returns, or core's store. */
    thread: { store: ThreadStore | null } | ThreadStore | null;
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
export function useCommentForm(options: UseCommentFormOptions): UseCommentFormResult {
    const settings = useCommentsSettings();
    const store = !options.thread ? null : 'getState' in options.thread ? options.thread : options.thread.store;
    const mode = options.mode ?? 'create';
    const parentUid = options.parentUid ?? null;
    const commentUid = options.commentUid ?? null;
    const nonce = options.nonce ?? settings.nonce;
    const [form, setForm] = useState<HTMLFormElement | null>(null);
    const [controller, setController] = useState<CommentForm | null>(null);
    const latest = useRef<CommentForm | null>(null);
    const target = useRef({ mode, parentUid, commentUid });

    // From the last committed render, never one React threw away.
    useBrowserLayoutEffect(() => {
        target.current = { mode, parentUid, commentUid };
        latest.current = controller;
        controller?.set(target.current);
    });

    // The form and its Protect, made once the form and the thread are both in the page.
    useEffect(() => {
        if (!store) return;

        const made = commentForm(form, store, { ...target.current, nonce });
        latest.current = made;
        setController(made);

        return () => {
            made.destroy();
            if (latest.current === made) latest.current = null;
            setController((previous) => (previous === made ? null : previous));
        };
    }, [form, store, nonce]);

    const state = useSyncExternalStore(controller ? controller.subscribe : noSubscription, controller ? controller.getState : idle, idle);

    const submit = useCallback((input?: CommentFormInput) => {
        const current = latest.current;

        if (!current) return Promise.reject(new Error('Comments: submit() was called before the thread mounted in the browser.'));

        return current.submit(input);
    }, []);

    const handleSubmit = useCallback((event?: FormEvent<HTMLFormElement> | Event) => {
        event?.preventDefault();
        return submit();
    }, [submit]);

    const reset = useCallback(() => latest.current?.reset(), []);

    return { ref: setForm, submit, handleSubmit, fieldErrors: state.fieldErrors, error: state.error, posting: state.posting, reset };
}

const IDLE: CommentFormState = { fieldErrors: {}, error: null, posting: false };
const idle = () => IDLE;
const noSubscription = () => () => undefined;
