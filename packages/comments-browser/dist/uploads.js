import { isCommentsRequestError } from '@verbb/comments-core';
import { format } from './labels.js';
import { safeUrl } from './parts/fields.js';
import { fileTray, formatSize, uploadLabel } from './parts/tray.js';
let keys = 0;
/**
 * The files in a form's Assets fields: checked against each field's types, size and number before
 * uploading, uploaded through `comments/api/upload` as they're chosen, removed or retried, and
 * posted as their upload IDs. An edit starts with the files the comment has.
 */
export class Uploads {
    constructor(form, fields, values) {
        this.form = form;
        this.fields = fields;
        this.items = {};
        this.picked = new Map();
        // Said together a moment later, so several files finishing at once are all read out.
        this.said = [];
        this.template = fileTray;
        for (const field of fields) {
            if (field.type !== 'file')
                continue;
            this.items[field.handle] = (Array.isArray(values[field.handle]) ? values[field.handle] : []).map((file) => ({
                key: `u${++keys}`,
                id: file.id,
                filename: file.filename,
                kind: file.kind,
                size: null,
                preview: file.kind === 'image' ? safeUrl(file.url) : null,
                state: 'done',
                error: null,
            }));
        }
    }
    /** Whether a file is still on its way, which posting would lose. */
    busy() {
        return Object.values(this.items).some((items) => items.some((item) => item.state === 'uploading'));
    }
    /** The first field with a file that didn't upload, which posting would leave out. */
    failed() {
        return Object.keys(this.items).find((handle) => this.items[handle].some((item) => item.state === 'failed'));
    }
    say(message) {
        this.said.push(message);
        clearTimeout(this.sayTimer);
        this.sayTimer = setTimeout(() => {
            this.form.host.announce(this.said.join(' '));
            this.said = [];
        }, 150);
    }
    focusRemove(handle, key) {
        this.form.element.querySelector(`[data-comments-field="${CSS.escape(handle)}"] .comments__upload-remove[data-comments-upload="${CSS.escape(key)}"]`)?.focus();
    }
    /** Add files, Remove and Try again. */
    act(button) {
        const handle = button.dataset.commentsUploadField ?? '';
        const key = button.dataset.commentsUpload ?? '';
        const action = button.dataset.commentsUploadAction;
        if (action === 'add') {
            this.form.element.querySelector(`input[data-comments-upload-input="${CSS.escape(handle)}"]`)?.click();
        }
        else if (action === 'remove') {
            this.remove(handle, key);
        }
        else if (action === 'retry' && this.picked.has(key)) {
            const field = this.fields.find((candidate) => candidate.handle === handle);
            const others = (this.items[handle] ?? []).filter((item) => item.key !== key && item.state !== 'failed').length;
            // A failed file counts again once it's retried, so it can't take the field past its limit.
            if (field?.maxFiles !== null && field?.maxFiles !== undefined && others >= field.maxFiles) {
                const message = format(uploadLabel(this.form.view(), 'tooManyFiles'), { count: field.maxFiles });
                this.form.setError(handle, message);
                this.form.render();
                this.form.host.announce(message);
                return;
            }
            this.set(handle, key, { state: 'uploading', error: null });
            this.form.render();
            // Try again is gone while it uploads, so the keyboard goes to the file's Remove.
            this.focusRemove(handle, key);
            void this.upload(handle, key);
        }
    }
    /** Files chosen in a field's picker, which is then emptied so the same file can be chosen again. */
    choose(input) {
        const field = this.fields.find((candidate) => candidate.handle === input.dataset.commentsUploadInput);
        if (field)
            this.add(field, Array.from(input.files ?? []));
        input.value = '';
    }
    /** Checks files against the field before uploading them, so the writer learns at once. */
    add(field, files) {
        const view = this.form.view();
        const items = this.items[field.handle] ?? [];
        const accept = (field.accept ?? []).map((extension) => extension.toLowerCase());
        let room = (field.maxFiles ?? Infinity) - items.filter((item) => item.state !== 'failed').length;
        const refused = [];
        const added = [];
        for (const file of files) {
            const extension = file.name.includes('.') ? file.name.slice(file.name.lastIndexOf('.')).toLowerCase() : '';
            if (accept.length && !accept.includes(extension)) {
                refused.push(format(uploadLabel(view, 'fileTypeNotAllowed'), { name: file.name, types: accept.join(', ') }));
            }
            else if (field.maxSize && file.size > field.maxSize) {
                refused.push(format(uploadLabel(view, 'fileTooLarge'), { name: file.name, size: formatSize(field.maxSize) }));
            }
            else if (room <= 0) {
                refused.push(format(uploadLabel(view, 'tooManyFiles'), { count: field.maxFiles ?? 0 }));
                break;
            }
            else {
                room--;
                const image = file.type.startsWith('image/');
                const item = { key: `u${++keys}`, id: null, filename: file.name, kind: image ? 'image' : 'file', size: file.size, preview: image ? URL.createObjectURL(file) : null, state: 'uploading', error: null };
                this.picked.set(item.key, { file });
                added.push(item);
            }
        }
        this.items = { ...this.items, [field.handle]: [...items, ...added] };
        this.form.setError(field.handle, refused[0] ?? null);
        this.form.render();
        // A field that's now full hides Add files, so the keyboard goes to the last new file's Remove.
        const last = added[added.length - 1];
        if (last && room <= 0 && (document.activeElement === document.body || document.activeElement?.closest('.comments__upload-add')))
            this.focusRemove(field.handle, last.key);
        if (refused.length)
            this.form.host.announce(refused.join(' '));
        added.forEach((item) => void this.upload(field.handle, item.key));
    }
    set(handle, key, change) {
        let updated;
        this.items = { ...this.items, [handle]: (this.items[handle] ?? []).map((item) => (item.key === key ? (updated = { ...item, ...change }) : item)) };
        return updated;
    }
    /**
     * Uploads one file: for a new comment with its thread (and the comment it replies to), for an
     * edit with the comment. A guest's upload takes a fresh protection preparation, which it uses
     * up, as a comment does.
     */
    async upload(handle, key) {
        const picked = this.picked.get(key);
        if (!picked)
            return;
        const abort = new AbortController();
        picked.abort = abort;
        let message;
        try {
            const file = await this.form.send(handle, picked.file, abort.signal);
            if (!this.picked.has(key))
                return;
            this.picked.delete(key);
            const item = this.set(handle, key, { id: file.id, size: file.size, state: 'done', error: null });
            message = format(uploadLabel(this.form.view(), 'uploaded'), { name: item?.filename ?? '' });
        }
        catch (error) {
            if (!this.picked.has(key) || abort.signal.aborted)
                return;
            const reason = isCommentsRequestError(error) ? error.message : this.form.host.labels().error;
            const item = this.set(handle, key, { state: 'failed', error: reason });
            message = `${format(uploadLabel(this.form.view(), 'uploadFailed'), { name: item?.filename ?? '' })} ${reason}`;
        }
        this.form.render();
        this.say(message);
    }
    /** Takes a file out of its field. One uploading stops; an uploaded one just isn't sent. */
    remove(handle, key) {
        const items = this.items[handle] ?? [];
        const index = items.findIndex((item) => item.key === key);
        const item = items[index];
        if (!item)
            return;
        this.drop(item);
        this.items = { ...this.items, [handle]: items.filter((candidate) => candidate !== item) };
        this.form.setError(handle, null);
        this.form.render();
        // Focus stays in the tray: on the next file's Remove, the previous one's, or Add files.
        const next = items[index + 1] ?? items[index - 1];
        const field = this.form.element.querySelector(`[data-comments-field="${CSS.escape(handle)}"]`);
        field?.querySelector(next ? `.comments__upload-remove[data-comments-upload="${next.key}"]` : '.comments__upload-add')?.focus();
        this.form.host.announce(format(uploadLabel(this.form.view(), 'fileRemoved'), { name: item.filename }));
    }
    drop(item) {
        this.picked.get(item.key)?.abort?.abort();
        this.picked.delete(item.key);
        if (item.preview?.startsWith('blob:'))
            URL.revokeObjectURL(item.preview);
    }
    /** Empties every field, after a post or when the form goes. */
    clear() {
        Object.values(this.items).flat().forEach((item) => this.drop(item));
        this.items = {};
        clearTimeout(this.sayTimer);
        this.said = [];
    }
}
