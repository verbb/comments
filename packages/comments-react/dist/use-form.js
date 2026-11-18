import { commentForm } from '@verbb/comments-browser/form';
import { useCallback, useEffect, useLayoutEffect, useRef, useState, useSyncExternalStore } from 'react';
import { useCommentsSettings } from './settings.js';
const useBrowserLayoutEffect = typeof document === 'undefined' ? useEffect : useLayoutEffect;
/**
 * Posting from your own form, for a custom interface built on `useCommentsThread()`. Protect is
 * mounted on the form when someone starts writing in it and kept fresh while it's open, and its
 * evidence goes with each post, so guests' comments pass the site's spam protection.
 */
export function useCommentForm(options) {
    const settings = useCommentsSettings();
    const store = !options.thread ? null : 'getState' in options.thread ? options.thread : options.thread.store;
    const mode = options.mode ?? 'create';
    const parentUid = options.parentUid ?? null;
    const commentUid = options.commentUid ?? null;
    const nonce = options.nonce ?? settings.nonce;
    const [form, setForm] = useState(null);
    const [controller, setController] = useState(null);
    const latest = useRef(null);
    const target = useRef({ mode, parentUid, commentUid });
    // From the last committed render, never one React threw away.
    useBrowserLayoutEffect(() => {
        target.current = { mode, parentUid, commentUid };
        latest.current = controller;
        controller?.set(target.current);
    });
    // The form and its Protect, made once the form and the thread are both in the page.
    useEffect(() => {
        if (!store)
            return;
        const made = commentForm(form, store, { ...target.current, nonce });
        latest.current = made;
        setController(made);
        return () => {
            made.destroy();
            if (latest.current === made)
                latest.current = null;
            setController((previous) => (previous === made ? null : previous));
        };
    }, [form, store, nonce]);
    const state = useSyncExternalStore(controller ? controller.subscribe : noSubscription, controller ? controller.getState : idle, idle);
    const submit = useCallback((input) => {
        const current = latest.current;
        if (!current)
            return Promise.reject(new Error('Comments: submit() was called before the thread mounted in the browser.'));
        return current.submit(input);
    }, []);
    const handleSubmit = useCallback((event) => {
        event?.preventDefault();
        return submit();
    }, [submit]);
    const reset = useCallback(() => latest.current?.reset(), []);
    return { ref: setForm, submit, handleSubmit, fieldErrors: state.fieldErrors, error: state.error, posting: state.posting, reset };
}
const IDLE = { fieldErrors: {}, error: null, posting: false };
const idle = () => IDLE;
const noSubscription = () => () => undefined;
