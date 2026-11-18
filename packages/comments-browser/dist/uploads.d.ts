import type { FormFieldData, UploadedFileData } from '@verbb/comments-core';
import type { CommentsEditorMode } from './events.js';
import type { FormHost } from './form-controller.js';
import type { FormView } from './parts/form.js';
import { fileTray, type UploadItem } from './parts/tray.js';
/** What the tray needs from the form it's in. */
export type TrayForm = {
    host: FormHost;
    mode: CommentsEditorMode;
    uid: string | null;
    element: HTMLFormElement;
    view(): FormView;
    render(): void;
    setError(handle: string, message: string | null): void;
    /** Uploads a file to a field; a guest's go one at a time, each with its own preparation. */
    send(handle: string, file: File, signal?: AbortSignal): Promise<UploadedFileData>;
};
/**
 * The files in a form's Assets fields: checked against each field's types, size and number before
 * uploading, uploaded through `comments/api/upload` as they're chosen, removed or retried, and
 * posted as their upload IDs. An edit starts with the files the comment has.
 */
export declare class Uploads {
    private readonly form;
    private readonly fields;
    items: Record<string, UploadItem[]>;
    private readonly picked;
    private said;
    private sayTimer;
    constructor(form: TrayForm, fields: FormFieldData[], values: Record<string, unknown>);
    readonly template: typeof fileTray;
    /** Whether a file is still on its way, which posting would lose. */
    busy(): boolean;
    /** The first field with a file that didn't upload, which posting would leave out. */
    failed(): string | undefined;
    private say;
    private focusRemove;
    /** Add files, Remove and Try again. */
    act(button: HTMLElement): void;
    /** Files chosen in a field's picker, which is then emptied so the same file can be chosen again. */
    choose(input: HTMLInputElement): void;
    /** Checks files against the field before uploading them, so the writer learns at once. */
    private add;
    private set;
    /**
     * Uploads one file: for a new comment with its thread (and the comment it replies to), for an
     * edit with the comment. A guest's upload takes a fresh protection preparation, which it uses
     * up, as a comment does.
     */
    private upload;
    /** Takes a file out of its field. One uploading stops; an uploaded one just isn't sent. */
    private remove;
    private drop;
    /** Empties every field, after a post or when the form goes. */
    clear(): void;
}
