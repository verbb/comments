import type { CommentsClient, EditResponse, UploadedFileData, ThemeControlName, ThreadStore } from '@verbb/comments-core';
import type { CommentsEditorMode, CommentsEventDetailMap, CommentsEventName } from './events.js';
import type { Labels } from './labels.js';
import type { AttributeMap } from './directives.js';
import type { ControlSpec } from './theme-config.js';
/** What a form needs from the thread it belongs to. */
export interface FormHost {
    readonly root: HTMLElement;
    readonly instanceId: string;
    readonly store: ThreadStore;
    readonly client: CommentsClient;
    labels(): Labels;
    control(name: ThemeControlName, spec?: ControlSpec): AttributeMap;
    /** A part through the site's override; `scope` keys a component override's host to this form. */
    part(name: 'postingAs' | 'formFields', data: Record<string, unknown>, fallback: () => unknown, frame?: undefined, scope?: string): unknown;
    /** A custom field's input override by handle, or `fallback` without one. */
    field(kind: 'input', handle: string, data: Record<string, unknown>, fallback: () => unknown, scope?: string): unknown;
    /** The form drew, so hosts it no longer draws can be let go. */
    hostsDrawn(): void;
    /** Moves focus to an element a field's override draws, waiting briefly for one that hasn't rendered yet. */
    focusSoon(find: () => HTMLElement | null): void;
    /** The site's override of a custom field's input, if it gave one. */
    fieldOverride(kind: 'input', handle: string): unknown;
    /** The theme Editor should draw in: `light`, `dark` or `none`. */
    editorTheme(): string;
    nonce(): string | undefined;
    loader(): (() => Promise<unknown>) | undefined;
    announce(message: string): void;
    toast(message: string, kind: 'success' | 'error'): void;
    dispatch<K extends CommentsEventName>(name: K, detail: CommentsEventDetailMap[K], options?: {
        cancelable?: boolean;
        source?: 'api' | 'user';
    }): boolean;
    /** A reply or edit form asks to close, such as after Cancel or posting. */
    close(form: FormController, reason: string): void;
    /** A post went through, so the thread can move focus to it. */
    posted(form: FormController, uid: string | null): void;
}
/**
 * One comment form: a new comment, a reply or an edit. It owns its `<form>` element, which the
 * thread places in the page, and draws the plugin's own editor frame into it: the plain box with
 * Post waiting for some text, ⌘↵, a character ring, a draft kept in this browser, and a member's
 * new comment collapsed to one line. A rich form swaps Editor in when someone starts writing,
 * moving the parts marked `data-comments-slot` into Editor's frame along with what was typed.
 * Protect is mounted once someone starts writing, and its evidence goes with the post.
 */
export declare class FormController {
    private readonly host;
    readonly mode: CommentsEditorMode;
    readonly uid: string | null;
    readonly element: HTMLFormElement;
    private readonly marker;
    private readonly slots;
    private editorElement;
    private errors;
    private posting;
    private awaitingEditor;
    private cancelAttributes;
    private draftState;
    private notice;
    private focusInside;
    private collapseTimer;
    private draftTimer;
    private fadeTimer;
    private protection;
    private mountedManifest;
    private stopProtection;
    private flow;
    private editing;
    private initialText;
    private destroyed;
    private announced;
    private values;
    private overridden;
    private tray;
    private trayFailed;
    private guestUploads;
    private gif;
    private gifChanged;
    private gifSent;
    private gifOpen;
    private gifFailed;
    private picker;
    private gifButtonAttributes;
    private readonly abort;
    constructor(host: FormHost, mode: CommentsEditorMode, uid: string | null, edit?: EditResponse | null);
    get key(): string;
    /** Whether Editor is in this form now. */
    get hasEditor(): boolean;
    /**
     * Fires `comments:editor-opened` for this form, once: when it's drawn and ready, and on a rich thread
     * once Editor is in it, or Editor couldn't load. Editor arriving later, such as for a post, doesn't
     * open the form again.
     */
    announceOpen(): void;
    /** Whether this form writes in Editor once someone starts. */
    get rich(): boolean;
    private get collapsible();
    private get draftKey();
    /** The plain box. */
    get box(): HTMLTextAreaElement | null;
    private text;
    private setText;
    /** What was typed into the stand-in before this form arrived, if nothing's here yet. */
    carry(text: string): void;
    /** Whether a file has been chosen or an override has set a value. */
    private hasFieldContent;
    /** Whether there's anything here its author would lose. */
    isDirty(): boolean;
    /** GIF search for this form, or null when the site hasn't turned it on or this person can't use it. */
    private gifConfig;
    /**
     * The custom fields this form asks for: a reply's or a new comment's, and for an edit exactly
     * those the comment was posted with, from the `edit` read (or its kind's, from an older server).
     */
    private fields;
    /**
     * Fills the custom fields with the values an edited comment was posted with: text, a choice,
     * several choices or a switch, whichever each field is.
     */
    private prefill;
    /**
     * An edited comment's values for the fields the site draws itself. An Assets field's are the
     * IDs of its files, which is what a post sends; the files themselves go to the override too.
     */
    private seedFields;
    /**
     * The file tray's chunk, for a form with an Assets field. Edits start with the comment's files.
     * If it can't load, the field says so with Try again, and an edit keeps the comment's files.
     * `again` is Try again, which keeps what's written (see `importFresh()`).
     */
    private loadTray;
    /**
     * Uploads a file to an Assets field: for a new comment with its thread (and the comment it
     * replies to), for an edit with the comment. A guest's uploads go one at a time, each with a fresh
     * protection preparation it uses up, so one never takes Protect from under another.
     */
    sendUpload(handle: string, file: File, signal?: AbortSignal): Promise<UploadedFileData>;
    /** What the tray needs from its form. */
    private trayForm;
    /** Puts Editor's text in the plain box, which is what the form holds as `content`. */
    private syncContent;
    /** Why Post can't go yet, or nothing if it can. */
    private blocker;
    private limit;
    private view;
    /** Draws the form and its movable parts again from the current state. */
    render(): void;
    /**
     * Follows input overrides given or taken away while the form is open, such as through
     * `setOptions()`. A field that gains one starts with the edited comment's value; one that loses
     * one goes back to its built-in input, filled with the override's last value (or the edited
     * comment's), so an edit never posts nothing over a saved value. Returns what to fill in.
     */
    private followOverrides;
    /** The box grows with what's written, as Editor's does, up to the same height. */
    private grow;
    focus(): void;
    private onInput;
    private onKeydown;
    private onFocusIn;
    /**
     * The form closes when focus moves to something else on the page. Focus that goes nowhere, as when
     * someone presses a field's instructions, switches window, or a password manager opens its own
     * menu over a field, leaves it open: a press outside the form closes it then.
     */
    private onFocusOut;
    private checkFocusSoon;
    private onClick;
    private onChange;
    private setFieldError;
    private clearFieldError;
    /** Someone heading for this form: Editor starts loading, and swaps in once they're in the box. */
    intent(focusedBox: boolean): void;
    private prefetch;
    /** Editor's options for this form: the comment's own when editing, else the viewer's. */
    private editorOptions;
    private setGif;
    /** Opens the picker, loading its chunk the first time, and shows what's trending. */
    private openGifs;
    /** Shows what's trending, or the last search, and moves to the search box. */
    private showPicker;
    private closeGifs;
    /**
     * The GIF as the data API takes it: a new comment sends one if it has one, and an edit only when
     * it changed, with `null` to take it off.
     */
    private gifInput;
    /** A rich form keeps no draft here: Editor keeps its own once it's running, and the two would disagree. */
    private keepsDraft;
    private scheduleDraft;
    private restoreDraft;
    private clearDraft;
    /**
     * Swaps the plain box for Editor, loading Editor first if it isn't here. Whatever was typed
     * comes along, and the caret too if the box had it. Without Editor, the plain box carries on.
     */
    upgrade(): Promise<void>;
    /** Once someone starts writing: a preparation for this form, kept fresh while it's open. */
    private startProtection;
    private mountProtection;
    /** Protect's evidence for the preparation the store hands this post. */
    private evidence;
    /** A hidden tab that becomes visible again may hold a stale preparation. */
    refreshProtection(): void;
    private refuse;
    private content;
    private onSubmit;
    /** Back to empty after a post, ready for the next. */
    reset(): void;
    /**
     * Shows errors beside their fields and takes the writer to the first. Keys are the data API's
     * (`content`, `guest.name`, `fields.<handle>`) or an input's `name`; anything without a field
     * here goes at the top of the form.
     */
    showErrors(errors: {
        fieldErrors?: Record<string, string[]>;
        message?: string;
    }): void;
    clearErrors(): void;
    private markEditorInvalid;
    destroy(): void;
}
/** The data API's key for an input's name or an error key: `content`, `guest.name`, `fields.<handle>`. */
export declare function errorKey(name: string): string | null;
/** Starts fetching Editor ahead of need, for a rich thread's visitor heading for a form. */
export declare function prefetchEditor(host: Pick<FormHost, 'store' | 'loader' | 'nonce'>): void;
/** Characters as the server counts them, so an emoji is one rather than two. */
export declare function characterCount(text: string): number;
