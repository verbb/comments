import { commentForm } from '@verbb/comments-browser/form';
import { computed, isRef, ref, shallowRef, toValue, watch, watchEffect } from 'vue';
import { useCommentsSettings } from './plugin.js';
/**
 * Posting from your own form, for a custom interface built on `useCommentsThread()`. Protect is
 * mounted on the form when someone starts writing in it and kept fresh while it's open, and its
 * evidence goes with each post, so guests' comments pass the site's spam protection.
 */
export function useCommentForm(options) {
    const settings = useCommentsSettings();
    const form = ref(null);
    const fieldErrors = shallowRef({});
    const error = ref(null);
    const posting = ref(false);
    const nonce = options.nonce ?? settings.nonce;
    const store = computed(() => {
        const thread = toValue(options.thread);
        if (!thread)
            return null;
        if ('getState' in thread)
            return thread;
        const inner = thread.store;
        return isRef(inner) ? inner.value : inner;
    });
    const target = computed(() => ({
        mode: toValue(options.mode) ?? 'create',
        parentUid: toValue(options.parentUid) ?? null,
        commentUid: toValue(options.commentUid) ?? null,
    }));
    let controller = null;
    // The form and its Protect, made once the form and the thread are both in the page.
    watch([form, store], ([element, thread], _previous, onCleanup) => {
        if (!thread)
            return;
        const made = commentForm(element, thread, { ...target.value, nonce });
        const stop = made.subscribe((state) => {
            fieldErrors.value = state.fieldErrors;
            error.value = state.error;
            posting.value = state.posting;
        });
        controller = made;
        onCleanup(() => {
            stop();
            made.destroy();
            if (controller === made)
                controller = null;
        });
    }, { immediate: true, flush: 'post' });
    watchEffect(() => controller?.set(target.value), { flush: 'post' });
    function submit(input) {
        if (!controller)
            return Promise.reject(new Error('Comments: submit() was called before the thread mounted in the browser.'));
        // Read now, in case a reply's parent changed since the last update.
        controller.set(target.value);
        return controller.submit(input);
    }
    return {
        ref: form,
        submit,
        handleSubmit(event) {
            event?.preventDefault();
            return submit();
        },
        fieldErrors,
        error,
        posting,
        reset() {
            controller?.reset();
        },
    };
}
