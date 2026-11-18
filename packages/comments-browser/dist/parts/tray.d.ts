import type { FormFieldData } from '@verbb/comments-core';
import { type TemplateResult } from 'lit-html';
import { type FormView } from './form.js';
/**
 * An Assets field's file tray on the comment form. It's a chunk of its own, loaded when a form has
 * an Assets field, so forms without one don't carry it.
 */
/** One file in an Assets field's tray. */
export type UploadItem = {
    key: string;
    /** The upload's ID once the server has it, which the post sends. */
    id: number | null;
    filename: string;
    kind: string;
    size: number | null;
    /** A preview of an image: the file's own address, or a local one while it uploads. */
    preview: string | null;
    state: 'done' | 'uploading' | 'failed';
    error: string | null;
};
/** What the form's field template has worked out for the tray. */
export type TrayParts = {
    inputId: string;
    labelId: string;
    describedBy: string | null;
    star: unknown;
    instructions: unknown;
};
/** The words the tray needs, used until the data's `labels` carry them. */
export declare const UPLOAD_LABELS: Record<string, string>;
/** A tray word from the data's labels, or the renderer's own. */
export declare function uploadLabel(view: Pick<FormView, 'labels'>, key: string): string;
/** A file size as people read it: KB below a megabyte, MB with one decimal above. */
export declare function formatSize(bytes: number): string;
/**
 * An Assets field: its files in a tray like Editor's uploads, each with remove, and retry when it
 * failed, then **Add files**, which opens the browser's file picker. The limits are said under it.
 */
export declare function fileTray(view: FormView, field: FormFieldData, parts: TrayParts): TemplateResult;
