import { type CommentsClient, type SortOrder, type ThreadStore } from '@verbb/comments-core';
import { type CommentsEventDetailMap, type CommentsEventName } from './events.js';
import type { FormController, FormHost } from './form-controller.js';
import { type Labels } from './labels.js';
import { type ControlName, type ControlSpec } from './theme-config.js';
import type { CommentsController, CommentsFormErrors, CommentsSettings, CommentsTheme, MountOptions, ThreadOptions } from './types.js';
import { type ComponentHost, type PartFrame, type PartName } from './view.js';
/** A thread's options, with what the renderer itself passes. */
export type ControllerOptions = MountOptions & {
    /** The server's mount point, kept by the loader that drew Show comments in its place. */
    original?: Node[];
    /** Show comments was pressed before the renderer arrived. */
    showNow?: boolean;
    /** The endpoint came from the page's markup, such as an element's attribute, rather than from code. */
    endpointFromMarkup?: boolean;
};
/**
 * One thread: its client and store, the page state the store doesn't keep (what's open, pressed,
 * busy or being said), the forms, and every interaction, delegated from the thread's element by
 * `data-comments-action`. It draws with lit-html from the store's state, so a vote, a reply or an
 * error changes only what changed and never takes the keyboard or someone's text with it.
 */
export declare class ThreadController implements CommentsController, FormHost {
    private readonly settings;
    private readonly onReplaced?;
    readonly root: HTMLElement;
    readonly client: CommentsClient;
    readonly store: ThreadStore;
    readonly instanceId: string;
    private readonly mountPoint;
    private readonly original;
    private readonly abort;
    private readonly forms;
    private formModule;
    private reportDialogPart;
    private reportLoading;
    private formLoading;
    private formsFailed;
    private readonly afterRender;
    private readonly stopStore;
    private ui;
    private phase;
    private themeConfig;
    private scheduled;
    private pendingReason;
    private loading;
    private observer;
    private toastTimer;
    private liveTimer;
    private opening;
    private highlightTimer;
    private destroyed;
    private revealed;
    private morphWatch;
    private readonly drawn;
    private idleQueued;
    private drewThisTask;
    private warmed;
    private lastDrawn;
    private readonly options;
    private readonly hosts;
    private readonly hostData;
    private hostList;
    private readonly hostListeners;
    private hostsChanged;
    private hostsQueued;
    private readonly hostDefaults;
    private readonly defaultTargets;
    private readonly touched;
    private readonly controlFor;
    private labelCache;
    private readonly optionCopies;
    private ownNodes;
    private siteNodes;
    private lastFocus;
    private focusLost;
    private theme;
    /**
     * `onReplaced` is called when a page swap reuses the root for another thread (another owner,
     * site or endpoint in its attributes), so it's started again for that one.
     */
    constructor(root: HTMLElement, settings: () => CommentsSettings, options?: ControllerOptions, onReplaced?: (() => void) | undefined);
    /**
     * Notices when something other than the renderer replaces the thread's contents, such as a
     * morphing page swap (Turbo 8's morph refresh, Datastar or idiomorph), and draws it again.
     */
    private watchForMorphs;
    /** Whether the drawn thread's own elements have been taken out from under it. */
    private taken;
    /**
     * For a root another thread takes over: the site's elements beside this thread, to carry over,
     * and copies of the rest that isn't the renderer's, the new thread's own mount point markup.
     */
    handOver(): {
        site: Element[];
        original: Node[];
    };
    /** Whether the root's attributes now say another thread, when the thread was mounted from them. */
    private replaced;
    /**
     * Draws a thread whose contents were taken again from scratch, with the same controller, its
     * data, options and listeners, its forms (and what's typed in them), and its component hosts'
     * elements, so an adapter's portals carry on. The root's own attributes are put back too, in
     * case the swap reset them to the server's.
     */
    private redraw;
    /** Keeps a comment, and those it replies to, drawn from the first pass, such as one with an open form. */
    private keepDrawn;
    get conversationUid(): string;
    get sort(): SortOrder;
    /**
     * The thread's own load mode, from its mount options or the mount point `render()` drew, wins over
     * `Comments.configure()`'s, which applies to threads that don't set one.
     */
    private get loadMode();
    load(): Promise<void>;
    refresh(options?: {
        reason?: string;
        sort?: string;
    }): void;
    openEditor(options: {
        mode: 'create' | 'reply' | 'edit';
        commentUid?: string;
    }): void;
    closeEditor(options?: {
        reason?: string;
    }): void;
    showFormErrors(form: HTMLFormElement, errors: CommentsFormErrors): void;
    clearFormErrors(form: HTMLFormElement): void;
    setTheme(theme: CommentsTheme): void;
    setOptions(next: ThreadOptions): void;
    readonly getHosts: () => readonly ComponentHost[];
    readonly subscribeHosts: (listener: (hosts: readonly ComponentHost[]) => void) => (() => void);
    /** Settings changed on the page, through `Comments.configure()`. */
    reconfigure(): void;
    /** `putBack` is false when the root is about to become another thread's, whose markup it holds. */
    destroy(putBack?: boolean): void;
    private formFor;
    /** The thread's wording, the same object until one of its sources changes. */
    labels(): Labels;
    control(name: ControlName, spec?: ControlSpec): import("./directives.js").AttributeMap;
    editorTheme(): string;
    nonce(): string | undefined;
    loader(): (() => Promise<unknown>) | undefined;
    announce(message: string): void;
    toast(message: string, kind: 'success' | 'error', retry?: boolean): void;
    dispatch<K extends CommentsEventName>(name: K, detail: CommentsEventDetailMap[K], options?: {
        cancelable?: boolean;
        source?: 'api' | 'user';
    }): boolean;
    /**
     * A part through the site's override, or `fallback` without one. `frame` places a component's
     * host where Comments keeps the part's surroundings, and `scope` keys the host to a form.
     */
    part(name: PartName, data: Record<string, unknown>, fallback: () => unknown, frame?: PartFrame, scope?: string): unknown;
    /** A custom field's input or display override by handle, or `fallback` without one. */
    field(kind: 'input' | 'display', handle: string, data: Record<string, unknown>, fallback: () => unknown, scope?: string): unknown;
    /** Whether a custom field's input or display is drawn by the site's own override. */
    fieldOverride(kind: 'input' | 'display', handle: string): unknown;
    private drawOverride;
    /**
     * A component override's host: an empty element, the same one for its key on every draw, so
     * lit-html leaves it (and whatever an adapter rendered into it) alone as the thread changes.
     * It's reported to the adapter after the draw, with what a function override would be given,
     * and the host object (and its context) stays the same until its component or data changes,
     * so a draw that changes nothing for a host doesn't render its component again.
     */
    private host;
    /** Draws a host's default into the element its component gave `renderDefaultInto()`. */
    private drawDefault;
    /**
     * After a draw, once it's done: defaults a component asked for are drawn again for the hosts
     * that were drawn, so they follow the thread; hosts no longer in the thread are let go; and if
     * any host was added, changed or let go, every listener hears the whole list once, however
     * many hosts the draw drew. Nothing is reported when nothing changed.
     */
    hostsDrawn(): void;
    private keeps;
    /**
     * The hosts to keep, found as a fixed point. A host stays if its element is in the thread; or
     * if it belongs to a comment still in the thread's data that isn't drawn right now, such as one
     * waiting for its batch after a re-sort or a morph, so its element (and the adapter's portal)
     * goes back in unchanged; or if it's inside the element another staying host gave
     * `renderDefaultInto()`. A host's own element as its own target doesn't keep it.
     */
    private hostsToKeep;
    private reportTo;
    /**
     * Focuses what `find` finds, now or within a moment, for a component that draws its control
     * after the form does. A later try only takes focus if it's still in the thread.
     */
    focusSoon(find: () => HTMLElement | null, tries?: number): void;
    close(form: FormController, reason: string): void;
    posted(form: FormController, uid: string | null): void;
    /** Cancel on the editor at the top clears it. A member's goes back to one line, and a guest's back to the join card. */
    private cancelNewComment;
    private resolveThemeConfig;
    /**
     * Draws on the next microtask, so several changes at once draw once. Once the thread has drawn
     * in a task, later changes in that task, such as a loaded thread's busy flags clearing a few
     * microtasks after its data, wait and draw together just before the frame.
     */
    private schedule;
    /**
     * While the thread's data is on its way, draws a made-up comment and reply once off the page, so
     * the browser has prepared the comment's templates before the real comments arrive and the first
     * draw only fills them in. Skipped when a site overrides parts, which shouldn't get made-up data.
     */
    private warmUp;
    /** Runs a task once the thread is drawn: now if it is, or after the draw that's waiting. */
    private afterDraw;
    /**
     * Draws the thread. A long thread draws its first batch of comments now and the rest in idle
     * batches; a draw while batches are still waiting, such as several store changes as a thread
     * loads, adds no comments of its own, so the first frame holds only the first batch.
     */
    private flush;
    /** Draws the next batch of a long thread when the browser is idle. */
    private drawLater;
    /** Draws a comment and the comments it's a reply to now, such as one a link points at or one to focus. */
    private drawNow;
    private view;
    /** The report dialog's chunk, imported once from the script's own origin and then drawn. */
    private loadReportDialog;
    /**
     * The form module, imported once from the script's own origin. `again` is someone asking for a
     * form after it failed, with Try again, Reply or Edit, which may reload the page (see `importAgain()`).
     */
    private loadForms;
    private formElement;
    private readonly actionCache;
    /** The actions a part override can call for one comment, made once per comment. */
    private actionsFor;
    private makeActions;
    private start;
    private listen;
    private onClick;
    private onSubmit;
    /** Someone heading for a rich editor, Reply or Edit, so Editor starts loading before it's needed. */
    private onIntent;
    /** Someone heading for a form: the form chunk, and Editor for rich authoring, start loading. */
    private headForForms;
    /** The guest card's details opening. */
    private onToggle;
    private toggleSet;
    private busy;
    /** Runs an action with a control marked busy, showing what went wrong in the toast. */
    private attempt;
    private heading;
    private article;
    private focusComment;
    /** `loadMode` `button`: loads the thread, which replaces the button, then moves to the first comment or the heading. */
    private showComments;
    private setSort;
    private loadMore;
    private loadReplies;
    private toggleReplies;
    /** Opens a folded comment for this page view, and takes focus to it, since its button is gone. */
    private unfold;
    /**
     * Opens a reply or edit form under its comment. There's one at a time, and opening another
     * can't quietly lose what's in this one. An edit reads the comment's own content first.
     * `pressed` is someone pressing Reply or Edit, which may reload the page if the forms failed.
     */
    private openForm;
    private openFormNow;
    private vote;
    private delete;
    private moderate;
    private copyLink;
    private dialog;
    private openReport;
    private submitReport;
    private follow;
    /**
     * A link to one comment: found among what's loaded, or looked up once for the page and loaded
     * into this thread if it's this thread's, then outlined, scrolled to and focused. One that isn't
     * public, such as the visitor's own waiting comment, is asked of this thread's `viewer` read,
     * which only finds comments on its own owner.
     */
    private reveal;
    /**
     * Whether a comment from a lookup belongs to this thread: its permalink is on the same address
     * as this thread's own comments, or this is the only thread on the page.
     */
    private owns;
    /** A menu's items, built the first time it opens, at once so the keyboard can move into them. */
    private buildMenu;
    private visibleMenuItems;
    /**
     * The ··· menu works like a menu button: Enter, Space or an arrow opens it on an item, the
     * arrows, Home and End move through it, Escape closes it and returns to the button, and Tab
     * leaves it closed.
     */
    private onMenuKeydown;
    private closeMenus;
}
