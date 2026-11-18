import type { EditFileData, FormFieldData, GifData, UploadedFileData, ViewerData } from '@verbb/comments-core';
import { type TemplateResult } from 'lit-html';
import { type Labels } from '../labels.js';
import type { ThemeControlName } from '@verbb/comments-core';
import type { ControlSpec } from '../theme-config.js';
import type { AttributeMap } from '../directives.js';
import type { TrayParts, UploadItem } from './tray.js';
export type FormMode = 'create' | 'reply' | 'edit';
/** Everything the comment form's templates draw from. */
export type FormView = {
    mode: FormMode;
    /** Prefix for the form's element IDs, such as `comments-109-1-reply-1446`. */
    idPrefix: string;
    labels: Labels;
    control(name: ThemeControlName, spec?: ControlSpec): AttributeMap;
    part(name: 'postingAs' | 'formFields', data: Record<string, unknown>, fallback: () => unknown): unknown;
    /** A custom field's input override by handle, or `fallback` without one. */
    field(kind: 'input', handle: string, data: Record<string, unknown>, fallback: () => unknown): unknown;
    /** Whether the site draws this field's input itself. */
    overridden(handle: string): boolean;
    viewer: ViewerData | null;
    /** Whether the guest's name and email are asked for. */
    guest: {
        show: boolean;
        required: boolean;
        name: string | null;
        email: string | null;
        offerReplyEmails: boolean;
    };
    fields: FormFieldData[];
    /** Values set by field input overrides, by handle. */
    values: Record<string, unknown>;
    setValue(handle: string, value: unknown): void;
    /** Each Assets field's files: those the comment has, those uploaded, and those uploading or failed. */
    uploads: Record<string, UploadItem[]>;
    /** Draws an Assets field's file tray, once its chunk has arrived. */
    fileTray?: (view: FormView, field: FormFieldData, parts: TrayParts) => unknown;
    /** Whether the tray's chunk couldn't load, so its fields say so with Try again. */
    trayFailed?: boolean;
    /** An edited comment's files in an Assets field, for an input override. */
    fieldFiles?: (handle: string) => EditFileData[];
    /** Uploads a file to an Assets field, for an input override that draws its own picker. */
    upload?: (handle: string, file: File) => Promise<UploadedFileData>;
    /** A member's new comment starts as one line. */
    collapsible: boolean;
    collapsed: boolean;
    replyTo: string | null;
    placeholder: string;
    submitLabel: string;
    limit: number;
    length: number;
    draftState: 'saved' | 'faded' | null;
    notice: {
        message: string;
        action: string | null;
    } | null;
    posting: boolean;
    /** Why Post can't go yet, or an empty string. */
    blocker: string;
    /** Error messages keyed by `content`, `guest.name`, `guest.email`, `fields.<handle>`, and `form` for the rest. */
    errors: Record<string, string>;
    editorMounted: boolean;
    shortcut: string;
    /** Whether the form offers GIFs, which the site turns on with GIPHY. */
    gifs: boolean;
    /** The GIF going with the comment, chosen here or already on an edited comment. */
    gif: GifData | null;
    /** Whether the GIF picker is open. */
    gifOpen: boolean;
    /** The picker's panel, once its chunk has arrived. */
    gifPanel?: unknown;
    /** Whether the picker's chunk couldn't load. */
    gifFailed?: boolean;
    /** The parts that move into Editor's frame when it takes over: live elements in the page, or templates for a string. */
    slots: {
        postingAs: unknown;
        guestFields: unknown;
        fieldsAbove: unknown;
        formFields: unknown;
        cancel: unknown;
        editor: unknown;
        gifButton: unknown;
        gifArea: unknown;
    };
};
/** The comment form element's classes, the same in the browser and drawn to a string. */
export declare function formClass(mode: FormMode): string;
export declare function fieldError(view: FormView, key: string): unknown;
/**
 * The comment form: the writer's avatar beside the plugin's own editor frame, drawn to match
 * Editor's so that a rich form can swap Editor in without anything moving. The form element
 * itself, and the parts that move into Editor, belong to the script; this draws what's between.
 */
export declare function formTemplate(view: FormView): TemplateResult;
/** "Posting as Aroha", which moves into Editor's header once it's running. */
export declare function postingAsTemplate(view: FormView): unknown;
/** A guest's name and address above the text, in Editor's header once it's running. */
export declare function guestFieldsTemplate(view: FormView): TemplateResult;
/** Where a field goes in the form: above the text, or below it, from its place in the Comment field layout. */
export declare function fieldPosition(field: FormFieldData): 'above' | 'below';
/**
 * The custom fields this person may fill in, from the `viewer` read's field descriptions (or, for
 * an edit, the `edit` read's): every built-in input, an Assets field's file tray, and a site's
 * input override by handle. Drawn twice, for the fields above the text and those below it, so an
 * override of the `formFields` part is given each group with its `position`.
 */
export declare function formFieldsTemplate(view: FormView, position?: 'above' | 'below'): unknown;
