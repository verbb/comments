import type { CommentData, CommentViewerData, EditFileData, FieldValueData, FormFieldData, UploadedFileData, PartName as CorePartName, ThemeControlName, ThreadState, VoteValue } from '@verbb/comments-core';
import type { html as litHtml } from 'lit-html';
import type { AttributeMap } from './directives.js';
import type { Labels } from './labels.js';
import type { ControlSpec } from './theme-config.js';
/**
 * The parts a site can override, each one public API. Deliberately fewer than the elements in the
 * markup reference: everything else is reached with stable class names, tokens and controls.
 */
export declare const PART_NAMES: readonly ["header", "empty", "showButton", "loading", "notice", "comment", "avatar", "authorName", "badge", "meta", "content", "fields", "actions", "review", "placeholder", "join", "postingAs", "formFields"];
export type PartName = CorePartName;
/** Where the thread is: waiting to be reached, waiting for Show comments, loading, drawn or failed. */
export type ThreadPhase = 'waiting' | 'button' | 'loading' | 'ready' | 'error';
/** What the page itself adds to the store's state: what's open, pressed, busy or being said. */
export type UiState = {
    /** Root comments whose replies are collapsed with Hide replies. */
    collapsed: ReadonlySet<string>;
    /** Comments whose date shows the full date after a tap. */
    fullDates: ReadonlySet<string>;
    /** Moderator bar's Hide held. */
    hideHeld: boolean;
    /** The comment a link points at, outlined for a few seconds. */
    highlight: string | null;
    /** The toast at the foot of the thread. */
    toast: {
        message: string;
        kind: 'success' | 'error';
        retry: boolean;
    } | null;
    /** The live region's text. */
    live: string;
    /** The comment the report dialog is about. */
    reportUid: string | null;
    /** Controls waiting on the server, by key, such as `load-more` or `replies:<uid>`. */
    busy: ReadonlySet<string>;
    /** Votes shown before the server answers, by comment. */
    pendingVotes: ReadonlyMap<string, VoteValue | null>;
    /** The reply or edit form that's open, if any. */
    openForm: {
        mode: 'reply' | 'edit';
        uid: string;
    } | null;
    /** The report dialog's error, shown above its fields. */
    reportError: string | null;
    /** A guest has opened Comment as a guest, so their form is drawn. */
    joinOpen: boolean;
    /** Comments whose ··· menu has been opened, whose items are drawn from then on. */
    menus: ReadonlySet<string>;
    /** Folded comments a reader has opened with Show comment, for this page view. */
    unfolded: ReadonlySet<string>;
};
/** The actions a part override can call for one comment, the same ones the default parts trigger. */
export type CommentActions = {
    vote(vote: VoteValue | null): Promise<void>;
    reply(): void;
    edit(): void;
    /** Deletes the person's own comment, after asking them. */
    delete(): Promise<void>;
    report(): void;
    copyLink(): Promise<void>;
    moderate(action: string): Promise<void>;
    toggleReplies(): void;
    loadReplies(): Promise<void>;
};
/** Everything a part (or a part override) can read. */
export type PartContext = {
    labels: Labels;
    actions: CommentActions | Record<string, never>;
    /** The resolved attributes for one of the ten controls, to use on an override's own elements. */
    controlAttributes(name: ThemeControlName, spec?: ControlSpec): AttributeMap;
    /** lit-html's template tag, so an override's template escapes what it inserts. */
    html: typeof litHtml;
    /** Comments' own markup for this part, to wrap or fall back to. */
    defaultRender(): unknown;
    comment?: CommentData;
    /** For a `comment` override: the comment's reply or edit form, which it must draw for Reply and Edit to work. */
    form?: () => unknown;
    /** For a `comment` override: the comment's replies, with Hide replies and Load more replies. */
    replies?: () => unknown;
    viewer?: ThreadState['viewer'];
    conversation?: ThreadState['conversation'];
    permissions?: CommentViewerData | null;
    [key: string]: unknown;
};
/**
 * An override drawn by a framework component, such as a React or Vue adapter's. The renderer draws
 * an empty host element in the part's place and reports it with the controller's `subscribeHosts()`,
 * and the adapter renders `component` into it. The renderer never looks at `component` itself.
 */
export type ComponentOverride<C = unknown> = {
    readonly component: C;
};
export type PartOverride = string | ((context: PartContext) => unknown) | ComponentOverride;
/**
 * For a part whose surroundings Comments keeps when a component draws it, such as the whole
 * comment's list item: what goes around the component's host, and the default for what goes inside.
 */
export type PartFrame = {
    around(host: unknown): unknown;
    inside(): unknown;
};
/** Where a component host is: a part, or a custom field's input or display. */
export type ComponentHostKind = 'part' | 'input' | 'display';
/**
 * What a component host's component is given: what a function override would be, with
 * `renderDefaultInto()` in place of `defaultRender()`'s template, which only lit-html can draw.
 */
export type HostContext = (PartContext | FieldInputContext | FieldDisplayContext) & {
    /** Draws Comments' own markup for this part into an element the component owns. Call it again to bring it up to date. */
    renderDefaultInto(into: Element): void;
};
/** An empty element the renderer drew for a component override, reported to the adapter that renders into it. */
export interface ComponentHost<C = unknown> {
    /** Stable for as long as what it draws exists, such as `comment:<uid>:badge:0`; unique within one thread. */
    readonly key: string;
    readonly kind: ComponentHostKind;
    /** The part's name, or the custom field's handle. */
    readonly name: string;
    /** The element to render into, the same one for as long as the key is reported. */
    readonly element: HTMLElement;
    /** The override's `component`, as given. */
    readonly component: C;
    /** As of the draw that last drew this host. */
    readonly context: HostContext;
}
/** What a custom field's display override is given, beside `labels`, `html` and `defaultRender()`. */
export type FieldDisplayContext = PartContext & {
    /** The comment's entry in `fields`: its handle, label, type, display, raw value and formatted text. */
    field: FieldValueData;
    comment: CommentData;
    value: unknown;
    text: string;
};
/** What a custom field's input override is given, beside `labels`, `html`, `controlAttributes()` and `defaultRender()`. */
export type FieldInputContext = PartContext & {
    /** The field's description from `viewer` (or `edit`), with its label, options and attributes. */
    field: FormFieldData;
    /** The value so far: the edited comment's, or what `setValue()` last set. */
    value: unknown;
    /** Sets the value the form posts for this field, in the shape the data API takes. */
    setValue(value: unknown): void;
    /** The field's error, if the last post was refused because of it. */
    error: string | null;
    /** For the override's own control: an ID the label points at, the input name, and the IDs that describe it. */
    id: string;
    name: string;
    labelId: string;
    describedBy: string | null;
    mode: 'create' | 'reply' | 'edit';
    /** An Assets field only: the edited comment's files (`value` holds their IDs). */
    files?: EditFileData[];
    /** An Assets field only: uploads a file to the field, answering with its upload ID to `setValue()`. */
    upload?: (file: File) => Promise<UploadedFileData>;
};
/** A custom field's override by handle: how it's filled in on the form, and how it's shown on a comment. */
export type FieldOverride = {
    input?: string | ((context: FieldInputContext) => unknown) | ComponentOverride;
    display?: string | ((context: FieldDisplayContext) => unknown) | ComponentOverride;
};
/** What every part template is given. Plain data and functions, so the same templates draw to a string. */
export type ThreadView = {
    mode: 'dom' | 'string';
    instanceId: string;
    phase: ThreadPhase;
    /** The count the header shows, from the page until the thread arrives. */
    count: number;
    state: ThreadState;
    ui: UiState;
    labels: Labels;
    control(name: ThemeControlName, spec?: ControlSpec): AttributeMap;
    /**
     * A part, through the site's override if it gave one. `frame` places a component override's
     * host, for a part whose surroundings Comments keeps, such as the whole comment's list item.
     */
    part(name: PartName, data: Record<string, unknown>, fallback: () => unknown, frame?: PartFrame): unknown;
    /** A comment form: the live element in the page, or its template when drawing to a string. */
    form(key: string): unknown;
    actions(uid: string): CommentActions | Record<string, never>;
    /** The custom fields one form asks for. */
    formFields(mode: 'create' | 'reply' | 'edit'): FormFieldData[];
    /**
     * Draws the report dialog. In the browser it's only given once its chunk has arrived, the first
     * time someone opens a comment's menu or reports, so readers who never report don't download it.
     */
    reportDialog?: (view: ThreadView) => unknown;
    /** A custom field's display (or, for the forms, input) override by handle, or `fallback` without one. */
    field(kind: 'input' | 'display', handle: string, data: Record<string, unknown>, fallback: () => unknown): unknown;
    /** When drawing to a string: the handles with an input override. */
    fieldInputs?: Set<string>;
    /**
     * How many comments not drawn before may be drawn in this pass, the ones already drawn, and
     * whether any were left for later, so a long thread draws its first comments at once and the
     * rest in idle time; without it, everything is drawn. `adding` marks a pass that only adds
     * comments, where nothing else has changed, so a comment drawn before with all its replies stays
     * exactly as it is.
     */
    budget?: {
        left: number;
        drawn: Set<string>;
        more: boolean;
        adding?: boolean;
    };
};
export declare const emptyUi: () => UiState;
/** The comment's anchor, `comment-1446`, from its permalink, so links and `:target` keep working. */
export declare function anchorOf(comment: CommentData): string;
/** The number in a comment's anchor, as today's events report it, or null. */
export declare function idOf(comment: CommentData | undefined): string | null;
