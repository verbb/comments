/**
 * Posting from a site's own form, for an interface it draws itself on core's thread store. React's
 * and Vue's `useCommentForm()` are built on this, and a site without a framework uses it directly.
 *
 * This is its own entry, `@verbb/comments-browser/form`, so it never loads with the thread's script.
 */
import { createOperationId, isCommentsRequestError, } from '@verbb/comments-core';
import { mountProtection } from '@verbb/protect';
const EMPTY = { fieldErrors: {}, error: null, posting: false };
/**
 * Posting from your own `<form>` to a thread store. Protect is mounted on the form when someone starts
 * writing in it and kept fresh while it's open, and its evidence goes with each post, so guests'
 * comments pass the site's spam protection. `form` can be null when `submit()` is always given what
 * to post, though without a form there's nowhere to mount Protect.
 */
export function commentForm(form, store, options = {}) {
    let mode = options.mode ?? 'create';
    let parentUid = options.parentUid ?? null;
    let commentUid = options.commentUid ?? null;
    let state = EMPTY;
    let destroyed = false;
    // The operation being tried, kept across failed attempts at the same comment.
    let attempt = null;
    const listeners = new Set();
    // An edit is checked against its own comment, so a new one for a different comment needs its own Protect.
    const flowKey = () => (mode === 'edit' ? commentUid ?? undefined : undefined);
    let keeperKey = flowKey();
    let protection = form ? createProtectionKeeper(form, store, keeperKey, options.nonce) : null;
    // Protect is mounted once someone starts writing, as the thread's own forms do.
    const begin = () => protection?.start();
    form?.addEventListener('focusin', begin);
    form?.addEventListener('input', begin);
    const update = (next) => {
        state = { ...state, ...next };
        listeners.forEach((listener) => listener(state));
    };
    const submit = async (input = {}) => {
        if (destroyed)
            throw new Error('Comments: submit() was called on a form that was destroyed.');
        const data = form ? new FormData(form) : null;
        const content = toContent(input.content ?? data?.get('content') ?? '');
        const fields = input.fields ?? readFields(data);
        const guest = input.guest ?? readGuest(data);
        const keeper = protection;
        const evidence = (preparation) => keeper?.evidence(preparation) ?? Promise.reject(new Error('Comment verification is unavailable.'));
        const key = stableKey({ mode, parentUid, commentUid, content, fields, guest });
        if (attempt?.key !== key)
            attempt = { key, operationId: createOperationId() };
        const operationId = input.operationId ?? attempt.operationId;
        update({ posting: true, fieldErrors: {}, error: null });
        try {
            const response = mode === 'edit'
                ? await store.update(requireUid(commentUid, 'edit'), { content, fields }, { evidence, operationId })
                : await store.create({ parentUid: mode === 'reply' ? requireUid(parentUid, 'reply') : null, content, guest, fields }, { evidence, operationId });
            attempt = null;
            if (form)
                clearForm(form);
            return response;
        }
        catch (failure) {
            if (isCommentsRequestError(failure)) {
                update({ fieldErrors: failure.fieldErrors ?? {}, error: failure.message });
                return null;
            }
            throw failure;
        }
        finally {
            update({ posting: false });
        }
    };
    return {
        getState: () => state,
        subscribe(listener) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        submit,
        handleSubmit(event) {
            event?.preventDefault();
            return submit();
        },
        set(next) {
            if (next.mode !== undefined)
                mode = next.mode;
            if (next.parentUid !== undefined)
                parentUid = next.parentUid;
            if (next.commentUid !== undefined)
                commentUid = next.commentUid;
            if (form && !destroyed && flowKey() !== keeperKey) {
                protection?.destroy();
                keeperKey = flowKey();
                protection = createProtectionKeeper(form, store, keeperKey, options.nonce);
            }
        },
        reset() {
            if (state.error !== null || Object.keys(state.fieldErrors).length)
                update({ fieldErrors: {}, error: null });
        },
        destroy() {
            destroyed = true;
            form?.removeEventListener('focusin', begin);
            form?.removeEventListener('input', begin);
            protection?.destroy();
            protection = null;
            listeners.clear();
        },
    };
}
function requireUid(uid, mode) {
    if (!uid)
        throw new Error(`Comments: a ${mode} form needs ${mode === 'edit' ? 'commentUid' : 'parentUid'}.`);
    return uid;
}
function toContent(content) {
    return typeof content === 'string' ? { format: 'plain', text: content } : content;
}
/** `fields[rating]`, `fields[tags][]` and so on from the form, keyed by handle. */
function readFields(data) {
    const fields = {};
    for (const name of new Set(data?.keys() ?? [])) {
        const match = /^fields\[([\w-]+)\](\[\])?$/.exec(name);
        if (match)
            fields[match[1]] = match[2] ? data.getAll(name).map(String) : String(data.get(name));
    }
    return Object.keys(fields).length ? fields : undefined;
}
function readGuest(data) {
    if (!data?.has('guest[name]') && !data?.has('guest[email]'))
        return undefined;
    const notify = data.get('guest[notifyReplies]');
    return { name: String(data.get('guest[name]') ?? ''), email: String(data.get('guest[email]') ?? ''), notifyReplies: notify === '1' || notify === 'on' || notify === 'true' };
}
/**
 * Back to empty after a post, as the browser's own fields hold them. Protect's fields stay, and so
 * do a guest's name and email, for their next comment. A framework's bound value is the app's own
 * state, which the app empties when `submit()` resolves with an answer.
 */
function clearForm(form) {
    for (const element of Array.from(form.elements)) {
        if (element.closest('.protect-honeypot') || element.closest('[data-protect]'))
            continue;
        if (/^guest\[/.test(element.name ?? ''))
            continue;
        if (element instanceof HTMLInputElement && (element.type === 'checkbox' || element.type === 'radio'))
            element.checked = false;
        else if (element instanceof HTMLInputElement && element.type !== 'hidden' && element.type !== 'submit' && element.type !== 'button')
            element.value = '';
        else if (element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement)
            element.value = '';
    }
}
/** One form's Protect: a fresh preparation kept mounted on it while someone writes, and its evidence on posting. */
function createProtectionKeeper(form, store, uid, nonce) {
    let flow = null;
    let stop = null;
    let controller = null;
    let mounted = null;
    let destroyed = false;
    const mount = (preparation) => {
        if (destroyed || !preparation || preparation.manifest === mounted)
            return;
        controller?.destroy();
        controller = mountProtection(form, preparation.manifest, { nonce });
        mounted = preparation.manifest;
    };
    return {
        start() {
            if (flow || destroyed)
                return;
            try {
                flow = store.protection(uid);
            }
            catch {
                // The thread hasn't loaded, so its owner isn't known yet; the next keystroke tries again.
                return;
            }
            flow.activate();
            stop = flow.subscribe(mount);
            const current = flow.current();
            if (current)
                mount(current);
            else
                flow.ensure().then(mount).catch(() => undefined);
        },
        async evidence(preparation) {
            mount(preparation);
            if (!controller)
                throw new Error('Comment verification is unavailable.');
            return await controller.prepare();
        },
        destroy() {
            destroyed = true;
            stop?.();
            flow?.deactivate();
            controller?.destroy();
        },
    };
}
/** The same string for equal values whatever their key order, so a retry of the same comment keeps its operation. */
function stableKey(value) {
    if (value === undefined)
        return 'undefined';
    if (value === null || typeof value !== 'object')
        return JSON.stringify(value) ?? String(value);
    if (Array.isArray(value))
        return `[${value.map(stableKey).join(',')}]`;
    return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${stableKey(value[key])}`).join(',')}}`;
}
