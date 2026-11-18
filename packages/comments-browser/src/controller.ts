import {
    createCommentsClient,
    createThreadStore,
    initialThreadState,
    isCommentsRequestError,
    selectReplies,
    selectRoots,
    type CommentData,
    type CommentsClient,
    type EditResponse,
    type SortOrder,
    type ThreadState,
    type ThreadStore,
    type VoteValue,
} from '@verbb/comments-core';
import { html, nothing, render } from 'lit-html';
import { customElementPart } from './directives.js';
import { lookUpComment, permalinkBase } from './deep-link.js';
import { importAgain, isShown, prefersReducedMotion } from './dom.js';
import { darkState, deepLink } from './page.js';
import { allowScriptOrigin } from './trusted.js';
import { dispatchCommentsEvent, type CommentsEventDetailMap, type CommentsEventName } from './events.js';
import type { FormController, FormHost } from './form-controller.js';
import { formPlaceholder } from './parts/thread.js';
import { resolveLabels, type Labels } from './labels.js';
import { threadTemplate } from './parts/thread.js';
import { commentItem } from './parts/comment.js';
import { controlAttributes, mergeThemeConfig, type ControlName, type ControlSpec, type ResolvedThemeConfig } from './theme-config.js';
import type { CommentsController, CommentsFormErrors, CommentsSettings, CommentsTheme, LoadMode, MountOptions, ThreadOptions } from './types.js';
import { anchorOf, emptyUi, idOf, type CommentActions, type ComponentHost, type ComponentHostKind, type ComponentOverride, type HostContext, type PartContext, type PartFrame, type PartName, type ThreadPhase, type ThreadView, type UiState } from './view.js';

/** A thread's options, with what the renderer itself passes. */
export type ControllerOptions = MountOptions & {
    /** The server's mount point, kept by the loader that drew Show comments in its place. */
    original?: Node[];
    /** Show comments was pressed before the renderer arrived. */
    showNow?: boolean;
    /** The endpoint came from the page's markup, such as an element's attribute, rather than from code. */
    endpointFromMarkup?: boolean;
};

/** Settings read from the mount point's attributes, which `craft.comments.render()` writes. */
type MountPointSettings = {
    endpoint: string;
    ownerId?: number;
    siteId: number | null;
    /** The thread's own load mode, or undefined when the mount point doesn't give one. */
    loadMode?: LoadMode;
    theme: CommentsTheme;
    sort?: SortOrder;
    limit?: number;
    themeConfig?: Record<string, unknown>;
    labels: Record<string, string>;
    count: number;
    /** Custom field overrides from `render()`'s config, which can only name custom elements. */
    fields: Record<string, { input?: string; display?: string }>;
};

const LOAD_MARGIN = '600px 0px';
const HIGHLIGHT_MS = 4000;
const TOAST_MS = 6000;
// How long the live region stays empty before a message, so the same words twice are read again.
const LIVE_GAP_MS = 50;
// Comments a pass may draw that weren't drawn before: the first frame's share, then each idle
// batch's, so a long thread shows at once and fills in without a long task.
const FIRST_BATCH = 45;
const IDLE_BATCH = 30;
// The options a thread's own setOptions() can replace after it's mounted.
const THREAD_OPTIONS = ['themeConfig', 'parts', 'fields', 'labels', 'loadEditor'] as const;
const NO_HOSTS: readonly ComponentHost[] = Object.freeze([]);

// What a part that isn't about one comment gets as its actions, the same object every time.
const NO_ACTIONS: Record<string, never> = Object.freeze({});

/** Whether two options are the same, comparing plain objects and lists a few levels deep. */
function sameOption(a: unknown, b: unknown, depth = 3): boolean {
    if (a === b) return true;
    if (depth === 0 || !a || !b || typeof a !== 'object' || typeof b !== 'object' || Array.isArray(a) !== Array.isArray(b)) return false;

    const left = Object.keys(a);
    const right = Object.keys(b);
    return left.length === right.length && left.every((key) => sameOption((a as Record<string, unknown>)[key], (b as Record<string, unknown>)[key], depth - 1));
}

/**
 * Whether a host's data is unchanged since it was last reported: the same values, key by key,
 * with lists compared item by item and functions (made afresh on each draw) left out.
 */
function sameData(a: Record<string, unknown>, b: Record<string, unknown>): boolean {
    const keys = Object.keys(a);

    if (keys.length !== Object.keys(b).length) return false;

    return keys.every((key) => {
        const left = a[key];
        const right = b[key];

        if (left === right || (typeof left === 'function' && typeof right === 'function')) return true;

        return Array.isArray(left) && Array.isArray(right) && left.length === right.length && left.every((item, index) => item === right[index]);
    });
}

/** A copy of an option's plain objects and lists, a few levels deep, to tell later whether it changed in place. */
function copyOption(value: unknown, depth = 3): unknown {
    if (depth === 0 || !value || typeof value !== 'object') return value;

    return Array.isArray(value)
        ? value.map((item) => copyOption(item, depth - 1))
        : Object.fromEntries(Object.entries(value).map(([key, item]) => [key, copyOption(item, depth - 1)]));
}

/** A readable, ID-safe piece of an owner's URI, such as `blog-hello-world`. */
function slug(value: string | undefined): string | undefined {
    const text = value?.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
    return text || undefined;
}

/** An element ID nothing on the page has yet: `base`, or `base-2`, `base-3` and so on. */
function uniqueId(base: string): string {
    let id = base;

    for (let n = 2; document.getElementById(id); n++) id = `${base}-${n}`;

    return id;
}

/** Draws into an element, starting it afresh if something else has replaced what lit-html drew there. */
function drawInto(value: unknown, into: HTMLElement): void {
    try {
        render(value, into);
    } catch {
        delete (into as { _$litPart$?: unknown })._$litPart$;
        into.replaceChildren();
        render(value, into);
    }
}

/** Whether an override is a framework component, drawn into a host element by an adapter. */
function isComponent(override: unknown): override is ComponentOverride {
    return typeof override === 'object' && override !== null && 'component' in override;
}

function readMountPoint(root: HTMLElement): MountPointSettings {
    const data = root.dataset;
    const parse = (value: string | undefined): Record<string, unknown> => {
        try {
            const parsed: unknown = JSON.parse(value ?? '{}');
            return parsed && typeof parsed === 'object' ? parsed as Record<string, unknown> : {};
        } catch {
            return {};
        }
    };
    const config = parse(data.commentsConfig);
    const loadMode = (['visible', 'button', 'eager'] as const).find((value) => value === data.commentsLoadMode);
    const theme = (['light', 'dark', 'auto', 'none'] as const).find((value) => value === data.commentsTheme) ?? 'light';
    const number = (value: unknown) => (value === undefined || value === null || value === '' || Number.isNaN(Number(value)) ? undefined : Number(value));

    return {
        endpoint: data.commentsEndpoint || new URL('/actions/comments/api', window.location.href).toString(),
        ownerId: number(data.commentsOwnerId),
        siteId: number(data.commentsSiteId) ?? null,
        loadMode,
        theme,
        sort: typeof config.sort === 'string' ? config.sort as SortOrder : undefined,
        limit: number(config.limit),
        themeConfig: config.themeConfig && typeof config.themeConfig === 'object' ? config.themeConfig as Record<string, unknown> : undefined,
        labels: parse(data.commentsLabels) as Record<string, string>,
        count: number(data.commentsCount) ?? 0,
        fields: elementNames(config.fields),
    };
}

/** Field overrides from the page, kept only where each is a custom element's name. */
function elementNames(value: unknown): MountPointSettings['fields'] {
    const fields: MountPointSettings['fields'] = {};
    const name = (tag: unknown) => (typeof tag === 'string' && /^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/.test(tag) ? tag : undefined);

    for (const [handle, override] of Object.entries(value && typeof value === 'object' ? value : {})) {
        if (override && typeof override === 'object') fields[handle] = { input: name((override as Record<string, unknown>).input), display: name((override as Record<string, unknown>).display) };
    }

    return fields;
}

/**
 * One thread: its client and store, the page state the store doesn't keep (what's open, pressed,
 * busy or being said), the forms, and every interaction, delegated from the thread's element by
 * `data-comments-action`. It draws with lit-html from the store's state, so a vote, a reply or an
 * error changes only what changed and never takes the keyboard or someone's text with it.
 */
export class ThreadController implements CommentsController, FormHost {
    readonly root: HTMLElement;
    readonly client: CommentsClient;
    readonly store: ThreadStore;
    readonly instanceId: string;
    private readonly mountPoint: MountPointSettings;
    private readonly original: Node[];
    private readonly abort = new AbortController();
    private readonly forms = new Map<string, FormController>();
    // The comment forms and the editor live in their own chunk, loaded when someone heads for one,
    // or when a thread shows a signed-in member the editor at the top.
    private formModule: typeof import('./form-controller.js') | null = null;
    private reportDialogPart: ((view: ThreadView) => unknown) | null = null;
    private reportLoading: Promise<void> | null = null;
    private formLoading: Promise<typeof import('./form-controller.js')> | null = null;
    private formsFailed = false;
    private readonly afterRender: Array<() => void> = [];
    private readonly stopStore: Array<() => void> = [];
    private ui: UiState = emptyUi();
    private phase: ThreadPhase;
    private themeConfig: ResolvedThemeConfig = {};
    private scheduled = false;
    private pendingReason: CommentsEventDetailMap['comments:before-render']['reason'] | null = null;
    private loading: Promise<void> | null = null;
    private observer: IntersectionObserver | null = null;
    private toastTimer: ReturnType<typeof setTimeout> | undefined;
    private liveTimer: ReturnType<typeof setTimeout> | undefined;
    private opening: string | null = null;
    private highlightTimer: ReturnType<typeof setTimeout> | undefined;
    private destroyed = false;
    private revealed: string | null = null;

    private morphWatch: MutationObserver | null = null;
    // Comments drawn so far, which stay drawn, and whether idle time still has more to draw.
    private readonly drawn = new Set<string>();
    private idleQueued = false;
    private drewThisTask = false;
    private warmed = false;
    private lastDrawn: { state: ThreadState; ui: UiState; phase: ThreadPhase } | null = null;
    // The thread's own options, a copy, so setOptions() can replace some of them later.
    private readonly options: ControllerOptions;
    // Component hosts by key with the data each was last reported with, the list as last
    // reported, and who's listening.
    private readonly hosts = new Map<string, ComponentHost>();
    private readonly hostData = new Map<string, Record<string, unknown>>();
    private hostList: readonly ComponentHost[] = NO_HOSTS;
    private readonly hostListeners = new Set<(hosts: readonly ComponentHost[]) => void>();
    private hostsChanged = false;
    private hostsQueued = false;
    // Each host's latest default, the elements components asked to have it drawn in, and the hosts
    // drawn since the last report, whose defaults are drawn again.
    private readonly hostDefaults = new Map<string, () => unknown>();
    private readonly defaultTargets = new Map<string, HTMLElement>();
    private readonly touched = new Set<string>();
    private readonly controlFor = (control: ControlName, spec?: ControlSpec) => this.control(control, spec);
    private labelCache: { sources: unknown[]; labels: Labels } | null = null;
    // Copies of the thread's own options as they were set, so ones changed in place still count as changed.
    private readonly optionCopies = new Map<string, unknown>();
    // The top-level elements the last draw left in the root, which nothing else puts there.
    private ownNodes: Element[] = [];
    // The elements beside the thread at the last draw that it didn't draw, such as a site's own note.
    private siteNodes: Element[] = [];
    // The last element in the thread that had focus, to give it back after a morph.
    private lastFocus: HTMLElement | null = null;
    private focusLost: HTMLElement | null = null;
    // The theme the thread is in, put back if a morphing page swap resets the root's attributes.
    private theme = 'light';

    /**
     * `onReplaced` is called when a page swap reuses the root for another thread (another owner,
     * site or endpoint in its attributes), so it's started again for that one.
     */
    constructor(root: HTMLElement, private readonly settings: () => CommentsSettings, options: ControllerOptions = {}, private readonly onReplaced?: () => void) {
        this.root = root;
        this.options = { ...options };
        for (const key of THREAD_OPTIONS) this.optionCopies.set(key, copyOption(options[key]));
        // From the first draw, which happens in this constructor, so an adapter's first report isn't missed.
        if (options.onHosts) this.hostListeners.add((hosts) => options.onHosts?.(hosts, this));
        this.mountPoint = readMountPoint(root);
        // Unique on the page, so two threads for the same owner, or by URI, never share element IDs.
        this.instanceId = root.id || uniqueId(`comments-${options.ownerId ?? (options.ownerUri ? slug(options.ownerUri) : this.mountPoint.ownerId) ?? 'thread'}`);
        // The server's mount point, to put back when the thread stops; the loader keeps it when it
        // has already drawn Show comments in its place.
        this.original = options.original ?? Array.from(root.childNodes, (node) => node.cloneNode(true));

        // Any element can be a thread through mountThread(), so it gets what the stylesheets and the
        // script look for, as the server's mount point has.
        root.id ||= this.instanceId;
        root.toggleAttribute('data-comments-root', true);
        root.dataset.commentsApiVersion ||= '1';
        root.classList.add('comments');
        this.resolveThemeConfig();

        const page = settings();
        this.client = createCommentsClient({
            endpoint: options.endpoint ?? this.mountPoint.endpoint,
            siteId: options.siteId ?? this.mountPoint.siteId,
            auth: options.auth ?? page.auth,
            fetch: options.fetch ?? page.fetch,
        });
        this.store = createThreadStore(this.client, {
            ownerId: options.ownerId ?? (options.ownerUri ? undefined : this.mountPoint.ownerId),
            ownerUri: options.ownerUri,
            siteId: options.siteId ?? this.mountPoint.siteId,
            sort: options.sort ?? this.mountPoint.sort,
            limit: options.limit ?? this.mountPoint.limit,
        });

        const theme = options.theme ?? page.theme;
        if (theme) root.dataset.commentsTheme = theme;
        root.dataset.commentsTheme ||= 'light';

        // A site's own dark-mode toggle decides from the start, for a thread mounted at any time.
        const dark = darkState(page.darkSelector);
        if (dark !== null && root.dataset.commentsTheme !== 'none') root.dataset.commentsTheme = dark ? 'dark' : 'light';
        this.theme = root.dataset.commentsTheme;

        // Where Editor's module may come from: the page's own origin, this script's, and an endpoint
        // given in code. One read from the page's markup (the mount point's or an element's
        // attribute) only counts on the page's own origin, so markup alone can't name a script elsewhere.
        allowScriptOrigin(globalThis.location?.href ?? '');
        allowScriptOrigin(import.meta.url);
        if (options.endpoint && !options.endpointFromMarkup) allowScriptOrigin(this.client.endpoint);

        this.phase = (this.loadMode === 'button' || options.showNow) && !deepLink() ? 'button' : 'waiting';

        // A drawn root that's mounted again, after Turbo restored it from its cache, starts clean.
        delete (root as { _$litPart$?: unknown })._$litPart$;
        root.replaceChildren();
        root.dataset.commentsJs = '1';

        this.listen();
        this.flush();
        this.watchForMorphs();
        this.start();
    }

    /**
     * Notices when something other than the renderer replaces the thread's contents, such as a
     * morphing page swap (Turbo 8's morph refresh, Datastar or idiomorph), and draws it again.
     */
    private watchForMorphs(): void {
        if (typeof MutationObserver === 'undefined') return;

        this.morphWatch = new MutationObserver(() => {
            if (this.destroyed) return;

            // Taken, the thread is drawn again; otherwise anything added beside it is the site's.
            if (this.taken()) this.schedule();
            else this.siteNodes = Array.from(this.root.children).filter((child) => !this.ownNodes.includes(child));
        });
        this.morphWatch.observe(this.root, { childList: true });
    }

    /** Whether the drawn thread's own elements have been taken out from under it. */
    private taken(): boolean {
        // Only the renderer's own elements count: an element a site adds beside them is left alone.
        return this.lastDrawn !== null && this.ownNodes.some((node) => node.parentNode !== this.root);
    }

    /**
     * For a root another thread takes over: the site's elements beside this thread, to carry over,
     * and copies of the rest that isn't the renderer's, the new thread's own mount point markup.
     */
    handOver(): { site: Element[]; original: Node[] } {
        const site = this.siteNodes.filter((node) => node.parentNode === this.root);
        // Not lit-html's markers, nor anything of the renderer's, or a copy of it, which only it marks with regions.
        const original = Array.from(this.root.childNodes, (node) => (site.includes(node as Element) || this.ownNodes.includes(node as Element) || node.nodeType === Node.COMMENT_NODE || (node instanceof Element && node.matches('[data-comments-region]')) ? [] : [node.cloneNode(true)])).flat();
        return { site, original };
    }

    /** Whether the root's attributes now say another thread, when the thread was mounted from them. */
    private replaced(): boolean {
        const now = readMountPoint(this.root);
        const owner = this.options.ownerId === undefined && !this.options.ownerUri && (now.ownerId !== this.mountPoint.ownerId || now.siteId !== this.mountPoint.siteId);
        return owner || (!this.options.endpoint && now.endpoint !== this.mountPoint.endpoint);
    }

    /**
     * Draws a thread whose contents were taken again from scratch, with the same controller, its
     * data, options and listeners, its forms (and what's typed in them), and its component hosts'
     * elements, so an adapter's portals carry on. The root's own attributes are put back too, in
     * case the swap reset them to the server's.
     */
    private redraw(): Element[] {
        delete (this.root as { _$litPart$?: unknown })._$litPart$;
        // Only what was beside the thread at its last draw, such as a site's own note, stays, after
        // the thread. What the swap put in (the server's mount point, or a copy of the renderer's
        // own elements), the renderer's leftovers and lit-html's markers go.
        const kept = this.siteNodes.filter((node) => node.parentNode === this.root);
        this.root.replaceChildren();
        this.root.id ||= this.instanceId;
        this.root.toggleAttribute('data-comments-root', true);
        this.root.dataset.commentsApiVersion ||= '1';
        this.root.classList.add('comments');
        this.root.dataset.commentsTheme = this.theme;
        this.root.dataset.commentsJs = '1';
        this.lastDrawn = null;
        this.ownNodes = [];
        // Drawn again from the first batch, with the rest in idle time, as a fresh load is; the
        // component hosts of comments waiting for a batch keep their elements meanwhile.
        this.drawn.clear();
        this.keepDrawn(this.ui.openForm?.uid);
        this.morphWatch?.takeRecords();
        return kept;
    }

    /** Keeps a comment, and those it replies to, drawn from the first pass, such as one with an open form. */
    private keepDrawn(uid: string | null | undefined): void {
        const comments = this.store.getState().comments;

        for (let current = uid ? comments[uid] : undefined; current; current = current.parentUid ? comments[current.parentUid] : undefined) {
            this.drawn.add(current.uid);
        }
    }

    // The controller
    // -------------------------------------------------------------------------

    get conversationUid(): string {
        return this.store.getState().conversation?.uid ?? '';
    }

    get sort(): SortOrder {
        return this.store.getState().sort;
    }

    /**
     * The thread's own load mode, from its mount options or the mount point `render()` drew, wins over
     * `Comments.configure()`'s, which applies to threads that don't set one.
     */
    private get loadMode(): LoadMode {
        return this.options.loadMode ?? this.mountPoint.loadMode ?? this.settings().loadMode ?? 'visible';
    }

    load(): Promise<void> {
        if (this.loading) return this.loading;

        this.observer?.disconnect();
        this.phase = 'loading';
        this.schedule();

        this.warmUp();
        this.loading = this.store.load().then(() => {
            // Usually drawn already, in the same pass as the data arrived, so a second draw isn't needed.
            if (this.phase !== 'ready' || this.root.dataset.commentsPhase !== 'ready') {
                this.phase = 'ready';
                this.schedule();
            }

            return this.reveal();
        }, (error: unknown) => {
            this.loading = null;
            this.phase = this.loadMode === 'button' ? 'button' : 'error';
            // The loading line becomes the error and Try again, or the button stays to press again.
            this.toast(isCommentsRequestError(error) ? error.message : this.labels().error, 'error');
            throw error;
        });

        return this.loading;
    }

    refresh(options: { reason?: string; sort?: string } = {}): void {
        if (options.sort && options.sort !== this.sort) {
            void this.setSort(options.sort as SortOrder);
        } else if (this.phase === 'ready') {
            this.store.load().catch(() => undefined);
        } else {
            this.load().catch(() => undefined);
        }
    }

    openEditor(options: { mode: 'create' | 'reply' | 'edit'; commentUid?: string }): void {
        if (options.mode === 'create') {
            this.forms.get('create')?.focus();
        } else if (options.commentUid) {
            // Opened from the site's own code, which mustn't reload the page.
            void this.openForm(options.mode, options.commentUid, false);
        }
    }

    closeEditor(options: { reason?: string } = {}): void {
        const open = this.ui.openForm;
        const form = open ? this.forms.get(`${open.mode}:${open.uid}`) : undefined;

        if (form) this.close(form, options.reason ?? 'api');
    }

    showFormErrors(form: HTMLFormElement, errors: CommentsFormErrors): void {
        this.formFor(form)?.showErrors(errors);
    }

    clearFormErrors(form: HTMLFormElement): void {
        this.formFor(form)?.clearErrors();
    }

    setTheme(theme: CommentsTheme): void {
        this.theme = theme;
        this.root.dataset.commentsTheme = theme;
        this.root.querySelectorAll('verbb-editor').forEach((editor) => editor.setAttribute('theme', this.editorTheme()));
    }

    setOptions(next: ThreadOptions): void {
        let changed = false;

        // An adapter may pass equal options made afresh on every render, which change nothing. They're
        // compared with a copy taken when they were set, so options changed in place still count, and
        // the very same object given again always does, since it may have been changed in place.
        for (const key of THREAD_OPTIONS) {
            if (key in next && (next[key] === this.options[key] || !sameOption(this.optionCopies.get(key), next[key]))) {
                (this.options as Record<string, unknown>)[key] = next[key];
                this.optionCopies.set(key, copyOption(next[key]));
                changed = true;
            }
        }

        if (changed) this.reconfigure();
    }

    // Bound, so they can be handed on as they are, such as to React's useSyncExternalStore().
    readonly getHosts = (): readonly ComponentHost[] => this.hostList;

    readonly subscribeHosts = (listener: (hosts: readonly ComponentHost[]) => void): (() => void) => {
        const own = (hosts: readonly ComponentHost[]) => listener(hosts);
        this.hostListeners.add(own);
        return () => this.hostListeners.delete(own);
    };

    /** Settings changed on the page, through `Comments.configure()`. */
    reconfigure(): void {
        this.resolveThemeConfig();
        this.forms.forEach((form) => form.render());
        this.schedule();
    }

    /** `putBack` is false when the root is about to become another thread's, whose markup it holds. */
    destroy(putBack = true): void {
        if (this.destroyed) return;

        this.destroyed = true;
        // Adapters let go of their hosts before the mount point is put back, and hear nothing more.
        if (this.hostList.length) this.reportTo(NO_HOSTS);
        this.hosts.clear();
        this.hostData.clear();
        this.hostDefaults.clear();
        this.defaultTargets.clear();
        this.hostListeners.clear();
        this.morphWatch?.disconnect();
        this.abort.abort();
        this.observer?.disconnect();
        clearTimeout(this.toastTimer);
        clearTimeout(this.highlightTimer);
        clearTimeout(this.liveTimer);
        this.stopStore.forEach((stop) => stop());
        this.forms.forEach((form) => form.destroy());
        this.forms.clear();
        this.actionCache.clear();
        this.store.destroy();
        // A morphing swap may have taken what lit-html drew, which it can't clear then.
        try {
            render(nothing, this.root);
        } catch {
            // Replaced below either way.
        }
        delete (this.root as { _$litPart$?: unknown })._$litPart$;
        // The mount point as the server drew it, so a page cached by Turbo shows the heading.
        if (putBack) this.root.replaceChildren(...this.original);
        delete this.root.dataset.commentsJs;
        delete this.root.dataset.commentsPhase;
        this.dispatch('comments:destroyed', {});
    }

    private formFor(element: HTMLFormElement): FormController | undefined {
        return Array.from(this.forms.values()).find((form) => form.element === element);
    }

    // The form host
    // -------------------------------------------------------------------------

    /** The thread's wording, the same object until one of its sources changes. */
    labels(): Labels {
        const sources = [this.mountPoint.labels, this.store.getState().labels, this.settings().labels, this.options.labels];

        if (!this.labelCache || sources.some((source, index) => source !== this.labelCache!.sources[index])) {
            const labels = resolveLabels(...sources as Parameters<typeof resolveLabels>);
            // The same wording from new objects keeps the same labels, so hosts aren't told again.
            this.labelCache = { sources, labels: this.labelCache && sameOption(this.labelCache.labels, labels, 1) ? this.labelCache.labels : labels };
        }

        return this.labelCache.labels;
    }

    control(name: ControlName, spec?: ControlSpec) {
        return controlAttributes(this.themeConfig, name, spec);
    }

    editorTheme(): string {
        const theme = this.root.dataset.commentsTheme;

        if (theme === 'dark' || theme === 'none') return theme;
        if (theme === 'auto' && typeof matchMedia === 'function') return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

        return 'light';
    }

    nonce(): string | undefined {
        return this.options.nonce ?? this.settings().nonce;
    }

    loader(): (() => Promise<unknown>) | undefined {
        return this.options.loadEditor ?? this.settings().loadEditor;
    }

    announce(message: string): void {
        // Emptied first and filled in a later task, so the same words twice are still read out.
        clearTimeout(this.liveTimer);
        this.ui = { ...this.ui, live: '' };
        this.schedule();
        this.liveTimer = setTimeout(() => {
            this.ui = { ...this.ui, live: message };
            this.schedule();
        }, LIVE_GAP_MS);
    }

    toast(message: string, kind: 'success' | 'error', retry = false): void {
        clearTimeout(this.toastTimer);
        this.ui = { ...this.ui, toast: { message, kind, retry } };
        this.announce(message);

        // Something that went wrong stays until the next thing happens.
        if (kind === 'success') {
            this.toastTimer = setTimeout(() => {
                this.ui = { ...this.ui, toast: null };
                this.schedule();
            }, TOAST_MS);
        }
    }

    dispatch<K extends CommentsEventName>(name: K, detail: CommentsEventDetailMap[K], options: { cancelable?: boolean; source?: 'api' | 'user' } = {}): boolean {
        return dispatchCommentsEvent(this.root, name, detail, { controller: this, ...options });
    }

    /**
     * A part through the site's override, or `fallback` without one. `frame` places a component's
     * host where Comments keeps the part's surroundings, and `scope` keys the host to a form.
     */
    part(name: PartName, data: Record<string, unknown>, fallback: () => unknown, frame?: PartFrame, scope?: string): unknown {
        const override = this.options.parts?.[name] ?? this.settings().parts?.[name];

        if (isComponent(override)) {
            const comment = data.comment as CommentData | undefined;
            // Badges are told apart by their place among the comment's badges.
            const badge = name === 'badge' && comment ? `:${(comment.author?.badges ?? []).indexOf(data.badge as never)}` : '';
            return this.host(override, 'part', name, `${scope ?? (comment ? `comment:${comment.uid}` : 'thread')}:${name}${badge}`, data, fallback, frame);
        }

        return this.drawOverride(override, `"${name}" part`, data, fallback);
    }

    /** A custom field's input or display override by handle, or `fallback` without one. */
    field(kind: 'input' | 'display', handle: string, data: Record<string, unknown>, fallback: () => unknown, scope?: string): unknown {
        const override = this.fieldOverride(kind, handle);

        if (isComponent(override)) {
            const comment = data.comment as CommentData | undefined;
            return this.host(override, kind, handle, `${scope ?? (comment ? `comment:${comment.uid}` : 'thread')}:${kind}:${handle}`, data, fallback);
        }

        return this.drawOverride(override, `"${handle}" field's ${kind}`, data, fallback);
    }

    /** Whether a custom field's input or display is drawn by the site's own override. */
    fieldOverride(kind: 'input' | 'display', handle: string): unknown {
        return this.options.fields?.[handle]?.[kind] ?? this.settings().fields?.[handle]?.[kind] ?? this.mountPoint.fields[handle]?.[kind];
    }

    private drawOverride(override: unknown, what: string, data: Record<string, unknown>, fallback: () => unknown): unknown {
        if (!override) {
            return fallback();
        }

        const context: PartContext = {
            labels: this.labels(),
            actions: NO_ACTIONS,
            ...data,
            controlAttributes: this.controlFor,
            html,
            defaultRender: fallback,
        };

        if (typeof override === 'string') {
            return customElementPart(override, context);
        }

        try {
            return (override as (context: PartContext) => unknown)(context);
        } catch (error) {
            console.error(`Comments: the ${what} override failed, so the default was drawn.`, error);
            return fallback();
        }
    }

    /**
     * A component override's host: an empty element, the same one for its key on every draw, so
     * lit-html leaves it (and whatever an adapter rendered into it) alone as the thread changes.
     * It's reported to the adapter after the draw, with what a function override would be given,
     * and the host object (and its context) stays the same until its component or data changes,
     * so a draw that changes nothing for a host doesn't render its component again.
     */
    private host(override: ComponentOverride, kind: ComponentHostKind, name: string, key: string, data: Record<string, unknown>, fallback: () => unknown, frame?: PartFrame): unknown {
        const previous = this.hosts.get(key);
        let element = previous?.element;

        if (!element) {
            element = document.createElement('verbb-comments-host');
            element.dataset.commentsHost = key;
            element.dataset.commentsHostKind = kind;
            element.dataset.commentsHostName = name;
        }

        // Comments draws a framed comment's list item, form and replies itself, around the
        // component, so its default is only what goes inside, and it gets no form() or replies().
        const values = frame ? { ...data } : data;
        if (frame) {
            delete values.form;
            delete values.replies;
        }

        const labels = this.labels();
        this.hostDefaults.set(key, frame ? frame.inside : fallback);
        this.touched.add(key);

        if (!previous || previous.component !== override.component || previous.context.labels !== labels || !sameData(this.hostData.get(key)!, values)) {
            const context = {
                labels,
                actions: NO_ACTIONS,
                ...values,
                controlAttributes: this.controlFor,
                html,
                // Always the latest default, so a host kept from an earlier draw never draws an old one.
                defaultRender: () => this.hostDefaults.get(key)?.(),
                renderDefaultInto: (into: Element) => {
                    this.defaultTargets.set(key, into as HTMLElement);
                    this.drawDefault(key);
                },
            } as HostContext;

            this.hosts.set(key, Object.freeze({ key, kind, name, element, component: override.component, context }));
            this.hostData.set(key, values);
            this.hostsChanged = true;
        }

        this.hostsDrawn();
        return frame ? frame.around(element) : element;
    }

    /** Draws a host's default into the element its component gave `renderDefaultInto()`. */
    private drawDefault(key: string): void {
        const into = this.defaultTargets.get(key);
        const fallback = this.hostDefaults.get(key);

        if (!into || !fallback || this.destroyed) return;

        try {
            drawInto(fallback(), into);
        } catch (error) {
            console.error(`Comments: the default for the "${key}" host couldn't be drawn.`, error);
        }
    }

    /**
     * After a draw, once it's done: defaults a component asked for are drawn again for the hosts
     * that were drawn, so they follow the thread; hosts no longer in the thread are let go; and if
     * any host was added, changed or let go, every listener hears the whole list once, however
     * many hosts the draw drew. Nothing is reported when nothing changed.
     */
    hostsDrawn(): void {
        if (this.hostsQueued || (!this.hosts.size && !this.hostsChanged)) return;

        this.hostsQueued = true;
        queueMicrotask(() => {
            if (this.destroyed) {
                this.hostsQueued = false;
                return;
            }

            // A default can hold hosts of its own, whose defaults are drawn in the next pass.
            for (let pass = 0; this.touched.size && pass < 8; pass++) {
                const keys = [...this.touched];
                this.touched.clear();
                keys.forEach((key) => this.drawDefault(key));
            }

            this.touched.clear();
            this.keeps = this.hostsToKeep();

            for (const [key] of this.hosts) {
                if (!this.keeps.has(key)) {
                    this.hosts.delete(key);
                    this.hostData.delete(key);
                    this.hostDefaults.delete(key);
                    this.defaultTargets.delete(key);
                    this.hostsChanged = true;
                }
            }

            this.hostsQueued = false;

            if (!this.hostsChanged) return;

            this.hostsChanged = false;
            this.reportTo(Object.freeze([...this.hosts.values()]));
        });
    }

    private keeps = new Set<string>();

    /**
     * The hosts to keep, found as a fixed point. A host stays if its element is in the thread; or
     * if it belongs to a comment still in the thread's data that isn't drawn right now, such as one
     * waiting for its batch after a re-sort or a morph, so its element (and the adapter's portal)
     * goes back in unchanged; or if it's inside the element another staying host gave
     * `renderDefaultInto()`. A host's own element as its own target doesn't keep it.
     */
    private hostsToKeep(): Set<string> {
        const comments = this.store.getState().comments;
        const keep = new Set<string>();

        for (const [key, host] of this.hosts) {
            const uid = /^comment:(.+?):(?:[^:]+)(?::.*)?$/.exec(key)?.[1];
            const waiting = uid && comments[uid] && !this.drawn.has(uid);

            if (this.root.contains(host.element) || waiting) keep.add(key);
        }

        for (let grew = true; grew;) {
            grew = false;

            for (const [key, host] of this.hosts) {
                if (keep.has(key)) continue;

                for (const [owner, target] of this.defaultTargets) {
                    if (owner !== key && keep.has(owner) && target.contains(host.element)) {
                        keep.add(key);
                        grew = true;
                        break;
                    }
                }
            }
        }

        return keep;
    }

    private reportTo(hosts: readonly ComponentHost[]): void {
        this.hostList = hosts;
        this.hostListeners.forEach((listener) => {
            try {
                listener(hosts);
            } catch (error) {
                console.error('Comments: a component host listener failed.', error);
            }
        });
    }

    /**
     * Focuses what `find` finds, now or within a moment, for a component that draws its control
     * after the form does. A later try only takes focus if it's still in the thread.
     */
    focusSoon(find: () => HTMLElement | null, tries = 6): void {
        const target = find();

        if (target) {
            const controls = 'button, a[href], input, select, textarea, summary, [tabindex]';
            // A group marked invalid, such as a component's radiogroup, may not take focus itself,
            // so its first control does.
            if (target.matches(controls)) target.focus();

            if (document.activeElement !== target) {
                Array.from(target.querySelectorAll<HTMLElement>(controls))
                    .find((element) => element.tabIndex >= 0 && !element.matches(':disabled') && !element.closest('[hidden], [inert]'))?.focus();
            }

            return;
        }

        if (tries > 0) {
            setTimeout(() => {
                const active = document.activeElement;
                if (!this.destroyed && (!active || active === document.body || this.root.contains(active))) this.focusSoon(find, tries - 1);
            }, 30);
        }
    }

    close(form: FormController, reason: string): void {
        if (form.mode === 'create') {
            this.cancelNewComment(form);
            return;
        }

        if (form.isDirty() && !window.confirm(this.labels().discardConfirm)) {
            return;
        }

        const opener = form.uid ? this.root.querySelector<HTMLElement>(form.mode === 'reply'
            ? `[data-comments-action="reply"][data-comments-comment-uid="${CSS.escape(form.uid)}"]`
            : `.comments__menu-toggle[data-comments-comment-uid="${CSS.escape(form.uid)}"]`) : null;

        this.forms.delete(form.key);
        form.destroy();
        this.ui = { ...this.ui, openForm: null };
        this.flush();
        opener?.focus();
        this.dispatch('comments:editor-closed', { reason });
    }

    posted(form: FormController, uid: string | null): void {
        if (form.mode !== 'create') {
            this.forms.delete(form.key);
            form.destroy();
            this.ui = { ...this.ui, openForm: null };
        } else {
            // A guest's form goes back behind the join card.
            const join = form.element.closest<HTMLDetailsElement>('details.comments__join-guest');
            if (join) join.open = false;
        }

        this.schedule(() => {
            // To what they just wrote, or back to where they were if it isn't drawn.
            if (!this.focusComment(uid)) {
                if (form.mode === 'create') form.focus();
                else this.focusComment(form.uid);
            }
        });
    }

    /** Cancel on the editor at the top clears it. A member's goes back to one line, and a guest's back to the join card. */
    private cancelNewComment(form: FormController): void {
        if (form.isDirty() && !window.confirm(this.labels().discardConfirm)) {
            return;
        }

        form.reset();
        const join = form.element.closest<HTMLDetailsElement>('details.comments__join-guest');

        if (join) {
            join.open = false;
            join.querySelector<HTMLElement>(':scope > summary')?.focus();
        } else {
            // Moving focus out of the form, rather than only blurring it, is what closes it.
            const heading = this.heading();

            if (heading) heading.focus();
            else (document.activeElement as HTMLElement | null)?.blur();
        }
    }

    // Drawing
    // -------------------------------------------------------------------------

    private resolveThemeConfig(): void {
        this.themeConfig = mergeThemeConfig([this.mountPoint.themeConfig, this.settings().themeConfig, this.options.themeConfig]);
    }

    /**
     * Draws on the next microtask, so several changes at once draw once. Once the thread has drawn
     * in a task, later changes in that task, such as a loaded thread's busy flags clearing a few
     * microtasks after its data, wait and draw together just before the frame.
     */
    private schedule(after?: () => void): void {
        if (after) this.afterRender.push(after);
        if (this.scheduled || this.destroyed) return;

        this.scheduled = true;

        if (!this.drewThisTask) {
            queueMicrotask(() => this.flush());
            return;
        }

        const run = () => {
            if (this.scheduled) this.flush();
        };

        if (typeof requestAnimationFrame === 'function') requestAnimationFrame(run);
        // A page in a background tab gets no frames, and still draws.
        setTimeout(run, 100);
    }

    /**
     * While the thread's data is on its way, draws a made-up comment and reply once off the page, so
     * the browser has prepared the comment's templates before the real comments arrive and the first
     * draw only fills them in. Skipped when a site overrides parts, which shouldn't get made-up data.
     */
    private warmUp(): void {
        if (this.warmed || Object.keys({ ...this.settings().parts, ...this.options.parts }).length) return;

        this.warmed = true;
        const run = () => {
            if (this.destroyed || this.store.getState().status !== 'loading') return;

            const comment = (uid: string, parentUid: string | null, replies: string[]): CommentData => ({
                uid, parentUid, depth: parentUid ? 1 : 0, state: 'approved', permalink: '', createdAt: '', editedAt: null,
                content: { format: 'plain', html: null, text: ' ', gifUrl: null, gifWidth: null, gifHeight: null },
                author: { name: ' ', initials: ' ', avatarUrl: null, avatarTone: 1, isGuest: false, badges: [], score: null },
                display: { timeAgo: ' ', fullDate: ' ' }, fields: [], interaction: { upvotes: 0, downvotes: 0 }, folded: null, custom: {},
                replies: { count: replies.length, items: [], next: null },
            }) as unknown as CommentData;
            const uid = '\u0000warm';
            const reply = `${uid}-reply`;
            const allowed = { canReply: true, canEdit: false, canDelete: false, canVote: true, canDownvote: false, canReport: true, vote: null, reported: false, isAuthor: false, moderation: null, custom: {} };
            const state = {
                ...initialThreadState(this.store.getState().sort),
                status: 'ready',
                comments: { [uid]: comment(uid, null, [reply]), [reply]: comment(reply, uid, []) },
                roots: [uid],
                replies: { [uid]: { uids: [reply], count: 1, next: null, loading: false } },
                permissions: { [uid]: allowed, [reply]: allowed },
            } as unknown as ThreadState;

            try {
                const view = this.view();
                // The thread's own labels arrive with its data, so any not known yet read as a space.
                const labels = new Proxy(view.labels, { get: (target, name) => (target as Record<string | symbol, string | undefined>)[name] ?? ' ' });
                render(html`<ol>${commentItem({ ...view, labels, state, phase: 'ready', budget: undefined }, state.comments[uid])}</ol>`, document.createElement('div'));
            } catch {
                // Only a head start; the real draw doesn't depend on it.
            }

            this.actionCache.delete(uid);
            this.actionCache.delete(reply);
        };
        const page = globalThis as { requestIdleCallback?: (callback: () => void, options?: { timeout: number }) => number };

        if (page.requestIdleCallback) page.requestIdleCallback(run, { timeout: 100 });
        else setTimeout(run, 0);
    }

    /** Runs a task once the thread is drawn: now if it is, or after the draw that's waiting. */
    private afterDraw(task: () => void): void {
        if (this.scheduled) this.afterRender.push(task);
        else task();
    }

    /**
     * Draws the thread. A long thread draws its first batch of comments now and the rest in idle
     * batches; a draw while batches are still waiting, such as several store changes as a thread
     * loads, adds no comments of its own, so the first frame holds only the first batch.
     */
    private flush(share = this.idleQueued ? 0 : FIRST_BATCH, adding = false): void {
        this.scheduled = false;

        if (this.destroyed) return;

        // Something took the drawn thread's elements, such as a morphing page swap, whether or not
        // its mutation observer has said so yet, so lit-html can't patch them: start the drawing afresh.
        const taken = this.taken();

        if (taken && this.onReplaced && this.replaced()) {
            this.onReplaced();
            return;
        }

        const kept = taken ? this.redraw() : [];
        // Elements beside the thread that it didn't draw, such as a site's own note, which a draw leaves where they are.
        const foreign = new Set(Array.from(this.root.children).filter((child) => !this.ownNodes.includes(child)));
        const hadFocus = this.root.contains(document.activeElement);

        const state = this.store.getState();

        // Drawn in the same pass the data arrives in, rather than a pass later.
        if (this.phase === 'loading' && state.status === 'ready') {
            this.phase = 'ready';
        }

        if (this.pendingReason) {
            const reason = this.pendingReason;
            this.pendingReason = null;

            if (!this.dispatch('comments:before-render', { reason, data: state }, { cancelable: true })) {
                // Nothing was drawn, so nothing waiting to happen after drawing can.
                this.afterRender.length = 0;
                return;
            }
        }

        if (!this.drewThisTask) {
            this.drewThisTask = true;
            setTimeout(() => { this.drewThisTask = false; }, 0);
        }

        this.root.dataset.commentsPhase = this.phase;
        const view = this.view();
        // Only adding comments when nothing has changed since the last draw, so the comments
        // already drawn needn't be drawn again and each batch costs the same however long the thread.
        const unchanged = adding && view.state === this.lastDrawn?.state && this.ui === this.lastDrawn.ui && this.phase === this.lastDrawn.phase;
        view.budget = { left: share, drawn: this.drawn, more: false, adding: unchanged };
        this.lastDrawn = { state: view.state, ui: this.ui, phase: this.phase };
        render(threadTemplate(view), this.root);
        this.root.append(...kept);
        this.siteNodes = [...foreign, ...kept];
        this.ownNodes = Array.from(this.root.children).filter((child) => !this.siteNodes.includes(child));

        // Focus lost with the elements a morph took, or with a comment a re-sort holds for a later
        // batch, goes back to the same element once it's back, unless something else has it by then.
        const active = document.activeElement;
        const blurred = !active || active === document.body;
        if ((taken || (hadFocus && blurred)) && this.lastFocus) this.focusLost = this.lastFocus;
        if (this.focusLost && !blurred) this.focusLost = null;
        if (this.focusLost && this.root.contains(this.focusLost)) {
            this.focusLost.focus({ preventScroll: true });
            this.focusLost = null;
        }

        if (view.budget.more) this.drawLater();
        this.forms.forEach((form) => {
            if (form.element.isConnected) form.render();
        });

        // Forms drawn into places that are gone, such as a reply under a comment that was removed, or
        // under a `comment` part override that doesn't draw its form().
        for (const [key, form] of this.forms) {
            if (key !== 'create' && !form.element.isConnected) {
                if (form.uid && this.store.getState().comments[form.uid] && (this.options.parts?.comment ?? this.settings().parts?.comment)) {
                    console.warn(`Comments: the ${form.mode} form for comment ${form.uid} has nowhere to go. A comment part override must draw form() where the form belongs.`);
                }

                form.destroy();
                this.forms.delete(key);
                if (this.ui.openForm && `${this.ui.openForm.mode}:${this.ui.openForm.uid}` === key) this.ui = { ...this.ui, openForm: null };
            }
        }

        this.hostsDrawn();
        this.afterRender.splice(0).forEach((task) => task());
    }

    /** Draws the next batch of a long thread when the browser is idle. */
    private drawLater(): void {
        if (this.idleQueued) return;

        this.idleQueued = true;
        const next = () => {
            this.idleQueued = false;
            // A draw that's waiting for other changes draws everything, so this batch only adds.
            if (!this.destroyed) this.flush(IDLE_BATCH, !this.scheduled);
        };
        const page = globalThis as { requestIdleCallback?: (callback: () => void, options?: { timeout: number }) => number };

        if (page.requestIdleCallback) page.requestIdleCallback(next, { timeout: 200 });
        else setTimeout(next, 16);
    }

    /** Draws a comment and the comments it's a reply to now, such as one a link points at or one to focus. */
    private drawNow(uid: string | null | undefined): void {
        const comments = this.store.getState().comments;
        let current = uid ? comments[uid] : undefined;
        let added = false;

        while (current) {
            if (!this.drawn.has(current.uid)) {
                this.drawn.add(current.uid);
                added = true;
            }

            current = current.parentUid ? comments[current.parentUid] : undefined;
        }

        if (added) this.flush();
    }

    private view(): ThreadView {
        const state = this.store.getState();

        return {
            mode: 'dom',
            instanceId: this.instanceId,
            phase: this.phase,
            count: state.conversation?.commentCount ?? this.mountPoint.count,
            state,
            ui: this.ui,
            labels: this.labels(),
            control: (name, spec) => this.control(name, spec),
            part: (name, data, fallback, frame) => this.part(name, data, fallback, frame),
            field: (kind, handle, data, fallback) => this.field(kind, handle, data, fallback),
            form: (key) => this.formElement(key),
            actions: (uid) => this.actionsFor(uid),
            formFields: (mode) => (mode === 'reply' ? state.viewerEditor?.fields.reply : state.viewerEditor?.fields.comment) ?? [],
            reportDialog: this.reportDialogPart ?? undefined,
        };
    }

    /** The report dialog's chunk, imported once from the script's own origin and then drawn. */
    private loadReportDialog(): Promise<void> {
        this.reportLoading ??= import('./parts/report.js').then(({ reportDialog }) => {
            this.reportDialogPart = reportDialog;
            if (!this.destroyed) this.flush();
        });

        return this.reportLoading;
    }

    /**
     * The form module, imported once from the script's own origin. `again` is someone asking for a
     * form after it failed, with Try again, Reply or Edit, which may reload the page (see `importAgain()`).
     */
    private loadForms(again = false): Promise<typeof import('./form-controller.js')> {
        this.formLoading ??= (again && this.formsFailed ? importAgain(() => import('./form-controller.js')) : import('./form-controller.js')).then((module) => {
            if (this.destroyed) return module;

            // Someone already in the stand-in box carries on in the real one, with what they typed.
            const standIn = this.root.querySelector<HTMLTextAreaElement>('[data-comments-form-placeholder] textarea');
            const focused = Boolean(standIn && document.activeElement === standIn);
            const typed = standIn?.value ?? '';
            this.formModule = module;
            this.formsFailed = false;
            this.schedule(() => {
                const form = this.forms.get('create');
                if (typed) form?.carry(typed);
                if (focused) form?.focus();
            });
            return module;
        }, (error: unknown) => {
            this.formLoading = null;
            this.formsFailed = true;
            this.schedule();
            throw error;
        });

        return this.formLoading;
    }

    private formElement(key: string): unknown {
        let form = this.forms.get(key);

        if (!form && key === 'create') {
            if (!this.formModule) {
                // Drawn the size of the collapsed editor, so nothing moves when the real one arrives.
                // One that failed waits for someone to head for it, or press Try again.
                if (!this.formsFailed) this.loadForms().catch(() => undefined);
                return formPlaceholder(this.view(), this.formsFailed);
            }

            form = new this.formModule.FormController(this, 'create', null);
            this.forms.set(key, form);
        }

        return form ? form.element : nothing;
    }

    private readonly actionCache = new Map<string, CommentActions>();

    /** The actions a part override can call for one comment, made once per comment. */
    private actionsFor(uid: string): CommentActions {
        let cached = this.actionCache.get(uid);

        if (!cached) {
            cached = this.makeActions(uid);
            this.actionCache.set(uid, cached);
        }

        return cached;
    }

    private makeActions(uid: string): CommentActions {
        return {
            vote: (vote) => this.vote(uid, vote),
            reply: () => void this.openForm('reply', uid),
            edit: () => void this.openForm('edit', uid),
            delete: () => this.delete(uid),
            report: () => this.openReport(uid),
            copyLink: () => this.copyLink(uid),
            moderate: (action) => this.moderate(uid, action),
            toggleReplies: () => this.toggleReplies(uid),
            loadReplies: () => this.loadReplies(uid),
        };
    }

    // Starting
    // -------------------------------------------------------------------------

    private start(): void {
        this.dispatch('comments:mounted', {});

        // Show comments was pressed before the renderer arrived, so it's pressed now.
        if (this.options.showNow && this.phase === 'button') {
            void this.showComments();
            return;
        }

        // A link to a comment loads the thread straight away, whatever the load mode.
        if (deepLink() || this.loadMode === 'eager') {
            this.load().catch(() => undefined);
            return;
        }

        if (this.loadMode === 'visible') {
            if (typeof IntersectionObserver === 'undefined') {
                this.load().catch(() => undefined);
                return;
            }

            this.observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    this.load().catch(() => undefined);
                }
            }, { rootMargin: LOAD_MARGIN });
            this.observer.observe(this.root);
        }
    }

    private listen(): void {
        const signal = this.abort.signal;
        const root = this.root;

        root.addEventListener('click', (event) => this.onClick(event), { signal });
        root.addEventListener('submit', (event) => this.onSubmit(event), { signal });
        root.addEventListener('keydown', (event) => this.onMenuKeydown(event), { signal });
        root.addEventListener('toggle', (event) => this.onToggle(event), { signal, capture: true });
        root.addEventListener('focusin', (event) => {
            if (event.target instanceof HTMLElement) this.lastFocus = event.target;
        }, { signal });

        for (const type of ['focusin', 'pointerdown', 'pointerover']) {
            root.addEventListener(type, (event) => this.onIntent(event), { signal });
        }

        document.addEventListener('click', (event) => {
            this.closeMenus(event.target instanceof Element ? event.target.closest('.comments__menu') : null);
        }, { signal });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') this.closeMenus();
        }, { signal });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.forms.forEach((form) => form.refreshProtection());
        }, { signal });

        // Only a link to a comment loads a waiting thread. Other in-page links, such as a table of
        // contents or a skip link, leave it as it is.
        window.addEventListener('hashchange', () => {
            if (!deepLink()) return;

            this.revealed = null;

            if (this.phase === 'ready') void this.reveal();
            else this.load().catch(() => undefined);
        }, { signal });


        let seen = this.store.getState();
        this.stopStore.push(this.store.subscribe((next) => {
            const before = seen;
            seen = next;

            // The store's own busy flags aren't drawn (the thread marks its controls busy itself), so
            // a change to them alone, such as a loaded thread's flag clearing, needs no draw.
            if ((Object.keys(next) as (keyof ThreadState)[]).every((key) => key === 'busy' || next[key] === before[key])) return;

            this.schedule();
        }));
        this.stopStore.push(this.store.on('comments:request-start', ({ reason }) => this.dispatch('comments:request-start', { reason })));
        this.stopStore.push(this.store.on('comments:request-end', ({ reason }) => this.dispatch('comments:request-end', { reason })));
        this.stopStore.push(this.store.on('comments:request-error', ({ reason, error }) => this.dispatch('comments:request-error', isCommentsRequestError(error)
            ? { reason, message: error.message, category: error.category, code: error.code, fieldErrors: error.fieldErrors, recoverable: error.recoverable }
            : { reason, message: this.labels().error })));
        this.stopStore.push(this.store.on('comments:roots-loaded', (detail) => {
            this.pendingReason = detail.cursor ? 'page' : 'thread';

            // A thread loaded afresh, such as in another order, draws its first comments again first
            // and the rest in idle time, keeping an open form's comment drawn. Component hosts of the
            // comments waiting for a batch keep their elements, so no portal is remounted.
            if (!detail.cursor) {
                this.drawn.clear();
                this.keepDrawn(this.ui.openForm?.uid);
            }
            this.dispatch('comments:roots-loaded', {});
        }));
        this.stopStore.push(this.store.on('comments:replies-loaded', (detail) => {
            this.pendingReason = 'replies';
            this.dispatch('comments:replies-loaded', { commentUid: detail.commentUid });
        }));
        for (const name of ['comments:comment-created', 'comments:comment-updated', 'comments:comment-deleted', 'comments:comment-moderated', 'comments:vote-changed', 'comments:report-changed', 'comments:follow-changed'] as const) {
            this.stopStore.push(this.store.on(name, () => {
                this.pendingReason ??= 'write';
            }));
        }

        this.stopStore.push(this.store.on('comments:viewer-loaded', (detail) => {
            this.pendingReason ??= 'viewer';
            this.dispatch('comments:viewer-loaded', { viewer: detail.viewer });
        }));
    }

    // Interaction
    // -------------------------------------------------------------------------

    private onClick(event: MouseEvent): void {
        const target = event.target instanceof Element ? event.target : null;
        const control = target?.closest<HTMLElement>('[data-comments-action]');

        if (!control || !this.root.contains(control)) return;

        // Comment text is the commenter's, so anything in it that looks like a control isn't one,
        // whoever draws it.
        if (control.closest('.comments__content, verbb-comments-host[data-comments-host-name="content"]')) return;

        const action = control.dataset.commentsAction ?? '';
        const uid = control.dataset.commentsCommentUid ?? null;

        // Clicks the form handles itself.
        if (action === 'discard-draft') return;

        if (action === 'follow') return;

        if (!this.dispatch('comments:before-action', { action, commentUid: uid }, { cancelable: true, source: 'user' })) {
            event.preventDefault();
            return;
        }

        // Choosing anything in a comment's menu closes it.
        control.closest('.comments__menu')?.removeAttribute('open');

        switch (action) {
            case 'show-comments':
                event.preventDefault();
                void this.showComments();
                break;
            case 'retry':
                event.preventDefault();
                this.ui = { ...this.ui, toast: null };
                this.refresh({ reason: 'retry' });
                break;
            case 'retry-forms':
                event.preventDefault();
                this.loadForms(true).then((module) => module.prefetchEditor(this)).catch(() => undefined);
                break;
            case 'sort':
                event.preventDefault();
                void this.setSort(control.dataset.commentsSort as SortOrder);
                break;
            case 'load-more':
                event.preventDefault();
                void this.loadMore();
                break;
            case 'load-replies':
                event.preventDefault();
                if (uid) void this.loadReplies(uid);
                break;
            case 'toggle-replies':
                event.preventDefault();
                if (uid) this.toggleReplies(uid);
                break;
            case 'unfold':
                event.preventDefault();
                if (uid) this.unfold(uid);
                break;
            case 'reply':
            case 'edit':
                event.preventDefault();
                if (uid) void this.openForm(action, uid);
                break;
            case 'cancel-editor': {
                event.preventDefault();
                const form = this.formFor(control.closest('form') as HTMLFormElement);
                if (form) this.close(form, 'user-cancel');
                break;
            }
            case 'vote': {
                event.preventDefault();
                const vote = control.dataset.commentsVote as VoteValue;
                if (uid) void this.vote(uid, control.getAttribute('aria-pressed') === 'true' ? null : vote);
                break;
            }
            case 'delete':
                event.preventDefault();
                if (uid) void this.delete(uid);
                break;
            case 'report':
                event.preventDefault();
                if (uid) void this.openReport(uid);
                break;
            case 'cancel-report':
                event.preventDefault();
                this.dialog()?.close();
                break;
            case 'moderate':
                event.preventDefault();
                if (uid) void this.moderate(uid, control.dataset.commentsModerationAction ?? '');
                break;
            case 'copy-link':
                event.preventDefault();
                if (uid) void this.copyLink(uid);
                break;
            case 'toggle-date':
                // Tapping the time switches between "3 days ago" and the full date, which works on touch screens.
                event.preventDefault();
                if (uid) this.toggleSet('fullDates', uid);
                break;
            case 'toggle-held':
                event.preventDefault();
                this.ui = { ...this.ui, hideHeld: !this.ui.hideHeld };
                this.schedule();
                break;
            default:
                break;
        }
    }

    private onSubmit(event: SubmitEvent): void {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;

        if (form.dataset.commentsForm === 'report') {
            event.preventDefault();
            void this.submitReport(form);
        } else if (form.dataset.commentsForm === 'follow') {
            event.preventDefault();
            void this.follow(form);
        }
    }

    /** Someone heading for a rich editor, Reply or Edit, so Editor starts loading before it's needed. */
    private onIntent(event: Event): void {
        const target = event.target instanceof Element ? event.target : null;

        if (!target) return;

        const formElement = target.closest<HTMLFormElement>('form[data-comments-form]');
        const form = formElement ? this.formFor(formElement) : undefined;

        if (form) {
            // Only the text itself swaps, because moving a guest's details into Editor's frame would
            // take the keyboard out of whichever field they're filling in.
            form.intent(event.type === 'focusin' && target === form.box);
            return;
        }

        if (target.closest('[data-comments-form-placeholder] textarea') || (event.type !== 'focusin' && target.closest('[data-comments-action="reply"], [data-comments-action="edit"], .comments__join-guest > summary, .comments__menu-toggle'))) {
            this.headForForms();
        }
    }

    /** Someone heading for a form: the form chunk, and Editor for rich authoring, start loading. */
    private headForForms(): void {
        // A forms chunk that failed is tried again only when someone asks for a form, with Try
        // again, Reply or Edit, as that may reload the page. Never on a draw, or opening a menu.
        if (this.formsFailed) return;
        this.loadForms().then((module) => module.prefetchEditor(this)).catch(() => undefined);
    }

    /** The guest card's details opening. */
    private onToggle(event: Event): void {
        const details = event.target;

        if (details instanceof HTMLDetailsElement && details.matches('.comments__menu') && details.open) {
            this.buildMenu(details);
            return;
        }

        if (details instanceof HTMLDetailsElement && details.matches('.comments__join-guest') && details.open && !this.ui.joinOpen) {
            // A guest's form is drawn once they ask for it, which is when its chunk is needed.
            this.ui = { ...this.ui, joinOpen: true };
            this.schedule();
            this.headForForms();
        }
    }

    private toggleSet(name: 'collapsed' | 'fullDates', uid: string): void {
        const next = new Set(this.ui[name]);

        if (next.has(uid)) next.delete(uid);
        else next.add(uid);

        this.ui = { ...this.ui, [name]: next };
        this.schedule();
    }

    private busy(key: string, on: boolean): void {
        const next = new Set(this.ui.busy);

        if (on) next.add(key);
        else next.delete(key);

        this.ui = { ...this.ui, busy: next };
        this.schedule();
    }

    /** Runs an action with a control marked busy, showing what went wrong in the toast. */
    private async attempt<T>(key: string | null, run: () => Promise<T>): Promise<T | undefined> {
        if (key) this.busy(key, true);

        try {
            return await run();
        } catch (error) {
            this.toast(isCommentsRequestError(error) ? error.message : this.labels().error, 'error');
            return undefined;
        } finally {
            if (key) this.busy(key, false);
        }
    }

    private heading(): HTMLElement | null {
        return this.root.querySelector<HTMLElement>('.comments__heading');
    }

    private article(uid: string | null | undefined): HTMLElement | null {
        return uid ? this.root.querySelector<HTMLElement>(`[data-comments-comment][data-comments-comment-uid="${CSS.escape(uid)}"] > article`) : null;
    }

    private focusComment(uid: string | null | undefined): boolean {
        this.drawNow(uid);
        const article = this.article(uid);
        article?.focus();
        return Boolean(article);
    }

    /** `loadMode` `button`: loads the thread, which replaces the button, then moves to the first comment or the heading. */
    private async showComments(): Promise<void> {
        this.busy('show', true);
        const loading = this.load();

        // The loader's Show comments, which had the keyboard, is gone: the heading takes it until the
        // thread arrives, rather than the page.
        this.afterDraw(() => {
            if (!document.activeElement || document.activeElement === document.body) this.heading()?.focus();
        });

        try {
            await loading;
        } catch {
            this.busy('show', false);
            return;
        }

        // The button is gone, so letting go of its busy state needs no draw of its own.
        const busy = new Set(this.ui.busy);
        busy.delete('show');
        this.ui = { ...this.ui, busy };

        this.afterDraw(() => {
            if (this.revealed) return;

            const first = this.root.querySelector<HTMLElement>('[data-comments-region="list"] [data-comments-comment] > article');
            (first ?? this.heading())?.focus();
        });
    }

    private async setSort(sort: SortOrder): Promise<void> {
        if (!sort || sort === this.sort) return;

        await this.attempt(`sort:${sort}`, () => this.store.setSort(sort));
        this.dispatch('comments:sorted', { sort });
    }

    private async loadMore(): Promise<void> {
        const shown = this.store.getState().roots.length;

        await this.attempt('load-more', () => this.store.loadMore());

        // The first new comment, drawn now if a long thread is still filling in.
        this.schedule(() => this.focusComment(this.store.getState().roots[shown]));
    }

    private async loadReplies(uid: string): Promise<void> {
        const shown = selectReplies(this.store.getState(), uid).length;

        await this.attempt(`replies:${uid}`, () => this.store.loadReplies(uid));

        this.schedule(() => this.focusComment(selectReplies(this.store.getState(), uid)[shown]?.uid));
    }

    private toggleReplies(uid: string): void {
        this.toggleSet('collapsed', uid);
    }

    /** Opens a folded comment for this page view, and takes focus to it, since its button is gone. */
    private unfold(uid: string): void {
        if (this.ui.unfolded.has(uid)) return;

        this.ui = { ...this.ui, unfolded: new Set([...this.ui.unfolded, uid]) };
        this.flush();
        this.root.querySelector<HTMLElement>(`[data-comments-comment-uid="${CSS.escape(uid)}"] > article`)?.focus();
    }

    /**
     * Opens a reply or edit form under its comment. There's one at a time, and opening another
     * can't quietly lose what's in this one. An edit reads the comment's own content first.
     * `pressed` is someone pressing Reply or Edit, which may reload the page if the forms failed.
     */
    private async openForm(mode: 'reply' | 'edit', uid: string, pressed = true): Promise<void> {
        // A second press while the first is still reading the comment changes nothing.
        if (this.opening === `${mode}:${uid}`) return;

        this.opening = `${mode}:${uid}`;

        try {
            await this.openFormNow(mode, uid, pressed);
        } finally {
            this.opening = null;
        }
    }

    private async openFormNow(mode: 'reply' | 'edit', uid: string, pressed: boolean): Promise<void> {
        const open = this.ui.openForm;
        const current = open ? this.forms.get(`${open.mode}:${open.uid}`) : undefined;

        if (open && open.mode === mode && open.uid === uid && current) {
            current.focus();
            return;
        }

        if (current?.isDirty() && !window.confirm(this.labels().discardConfirm)) {
            return;
        }

        let edit: EditResponse | null = null;

        if (mode === 'edit') {
            edit = (await this.attempt(`menu:${uid}`, () => this.client.edit(uid))) ?? null;

            if (!edit || this.destroyed) return;
        }

        // Reply (or the menu, for Edit) shows it's busy while the forms chunk arrives.
        const busyKey = `${mode === 'reply' ? 'reply' : 'menu'}:${uid}`;
        if (!this.formModule) this.busy(busyKey, true);
        const module = await this.loadForms(pressed).catch(() => null);
        if (this.ui.busy.has(busyKey)) this.busy(busyKey, false);

        if (this.destroyed) return;

        if (!module) {
            this.toast(this.labels().formFailed, 'error');
            return;
        }

        if (current) {
            this.forms.delete(current.key);
            current.destroy();
        }

        const form = new module.FormController(this, mode, uid, edit);
        this.forms.set(form.key, form);
        this.ui = { ...this.ui, openForm: { mode, uid }, collapsed: withoutKey(this.ui.collapsed, uid) };
        this.flush();
        form.focus();
        form.intent(false);

        // A rich form says it's open once Editor is in it, which opening a reply or an edit loads. A plain
        // one is ready now. Either way it's said once, however often Editor is asked for after.
        if (form.rich && !form.hasEditor) {
            void form.upgrade();
        } else {
            form.announceOpen();
        }
    }

    private async vote(uid: string, vote: VoteValue | null): Promise<void> {
        const pending = new Map(this.ui.pendingVotes);
        pending.set(uid, vote);
        this.ui = { ...this.ui, pendingVotes: pending };
        // Drawn now rather than on the next microtask, so the press answers in the same frame.
        this.flush();

        await this.attempt(null, async () => {
            await this.store.vote(uid, vote);
            this.dispatch('comments:vote-changed', { commentUid: uid, commentId: idOf(this.store.getState().comments[uid]), vote });
        });

        const after = new Map(this.ui.pendingVotes);
        if (after.get(uid) === vote) after.delete(uid);
        this.ui = { ...this.ui, pendingVotes: after };
        this.schedule();
    }

    private async delete(uid: string): Promise<void> {
        if (!window.confirm(this.labels().deleteConfirm)) return;

        // Removing it takes focus with it, so the keyboard goes to whatever's now in its place.
        const item = this.article(uid)?.parentElement;
        const neighbour = (item?.nextElementSibling ?? item?.previousElementSibling ?? item?.parentElement?.closest('[data-comments-comment]')) as HTMLElement | null;
        const response = await this.attempt(`menu:${uid}`, () => this.store.delete(uid));

        if (!response) return;

        if (response.announcement) this.toast(response.announcement, 'success');

        this.schedule(() => {
            if (!this.focusComment(neighbour?.dataset.commentsCommentUid)) this.heading()?.focus();
        });
        this.dispatch('comments:comment-deleted', { commentUid: uid });
    }

    private async moderate(uid: string, action: string): Promise<void> {
        const comment = this.store.getState().comments[uid];
        const response = await this.attempt(`moderate:${uid}:${action}`, () => this.store.moderate(uid, action as never));

        if (!response) return;

        if (response.announcement) this.toast(response.announcement, 'success');

        this.schedule(() => {
            if (!this.focusComment(uid)) this.heading()?.focus();
        });
        this.dispatch('comments:comment-moderated', { commentUid: uid, commentId: idOf(comment), moderationAction: action });
    }

    private async copyLink(uid: string): Promise<void> {
        const comment = this.store.getState().comments[uid];
        const link = comment?.permalink ? new URL(comment.permalink, window.location.href).toString() : '';

        if (!link) return;

        try {
            await navigator.clipboard.writeText(link);
            this.toast(this.labels().linkCopied, 'success');
        } catch {
            this.toast(this.labels().error, 'error');
        }
    }

    private dialog(): HTMLDialogElement | null {
        return this.root.querySelector<HTMLDialogElement>('dialog[data-comments-report-dialog]');
    }

    private async openReport(uid: string): Promise<void> {
        try {
            await this.loadReportDialog();
        } catch {
            this.reportLoading = null;
            this.toast(this.labels().error, 'error');
            return;
        }

        if (this.destroyed) return;

        this.ui = { ...this.ui, reportUid: uid, reportError: null };
        this.flush();

        const dialog = this.dialog();
        const form = dialog?.querySelector<HTMLFormElement>('form');

        if (!dialog || !form) return;

        form.reset();

        if (!dialog.dataset.commentsBound) {
            dialog.dataset.commentsBound = '1';
            dialog.addEventListener('close', () => {
                // Back to the comment's own menu button, rather than an item in a menu that's closed.
                const reported = this.ui.reportUid;
                this.root.querySelector<HTMLElement>(`.comments__menu-toggle[data-comments-comment-uid="${CSS.escape(reported ?? '')}"]`)?.focus();
            }, { signal: this.abort.signal });
        }

        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    }

    private async submitReport(form: HTMLFormElement): Promise<void> {
        const uid = this.ui.reportUid;
        const data = new FormData(form);
        const reason = String(data.get('reason') ?? '');

        if (!uid) return;

        if (!reason) {
            form.querySelector<HTMLInputElement>('input[name="reason"]')?.reportValidity();
            return;
        }

        this.busy('report', true);

        try {
            const response = await this.store.report(uid, { reason, details: String(data.get('details') ?? '') || null });
            this.dialog()?.close();
            this.toast(response.announcement ?? this.labels().reportSent, 'success');
            this.dispatch('comments:report-changed', { commentUid: uid, commentId: idOf(this.store.getState().comments[uid]), reported: true });
        } catch (error) {
            this.ui = { ...this.ui, reportError: isCommentsRequestError(error) ? error.message : this.labels().error };
            this.schedule(() => form.querySelector<HTMLElement>('.comments__form-error')?.focus());
        } finally {
            this.busy('report', false);
        }
    }

    private async follow(form: HTMLFormElement): Promise<void> {
        const following = !this.store.getState().viewer?.follow.following;
        const email = String(new FormData(form).get('email') ?? '') || undefined;

        if (form.querySelector('input[name="email"]') && !form.reportValidity()) return;

        const response = await this.attempt('follow', () => this.store.follow(following, email));

        if (!response) return;

        if (response.announcement) this.toast(response.announcement, 'success');

        this.dispatch('comments:follow-changed', { following: response.viewer.follow.following });
    }

    // Deep links
    // -------------------------------------------------------------------------

    /**
     * A link to one comment: found among what's loaded, or looked up once for the page and loaded
     * into this thread if it's this thread's, then outlined, scrolled to and focused. One that isn't
     * public, such as the visitor's own waiting comment, is asked of this thread's `viewer` read,
     * which only finds comments on its own owner.
     */
    private async reveal(): Promise<void> {
        const link = deepLink();

        if (!link || this.revealed === link.anchor) return;

        this.revealed = link.anchor;
        const ref = link.uid ? { uid: link.uid } : { id: Number(link.anchor.slice('comment-'.length)) };
        const find = () => selectAll(this.store).find((comment) => anchorOf(comment) === link.anchor || comment.uid === link.uid);
        let found = find();

        if (!found) {
            const response = await lookUpComment(this.client, ref).catch(() => undefined);

            if (response === undefined || (response && !this.owns(response.comment.permalink))) return;

            this.pendingReason = 'comment';
            await this.store.loadComment(ref, response).catch(() => undefined);
            found = find();
        }

        if (!found) return;

        const uid = found.uid;
        clearTimeout(this.highlightTimer);
        this.ui = { ...this.ui, highlight: uid };
        this.schedule(() => {
            this.drawNow(uid);
            const article = this.article(uid);

            if (!article) return;

            article.parentElement?.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
            article.focus({ preventScroll: true });
        });

        this.highlightTimer = setTimeout(() => {
            this.ui = { ...this.ui, highlight: null };
            this.schedule();
        }, HIGHLIGHT_MS);
    }

    /**
     * Whether a comment from a lookup belongs to this thread: its permalink is on the same address
     * as this thread's own comments, or this is the only thread on the page.
     */
    private owns(permalink: string | null): boolean {
        const base = permalinkBase(permalink);
        const own = Object.values(this.store.getState().comments).find((comment) => comment.permalink)?.permalink;

        if (base && own) return base === permalinkBase(own);

        return document.querySelectorAll('[data-comments-root][data-comments-js]').length <= 1;
    }

    // The ··· menu
    // -------------------------------------------------------------------------

    /** A menu's items, built the first time it opens, at once so the keyboard can move into them. */
    private buildMenu(menu: HTMLDetailsElement): void {
        const uid = menu.querySelector<HTMLElement>('.comments__menu-toggle')?.dataset.commentsCommentUid;

        if (!uid || this.ui.menus.has(uid)) return;

        this.ui = { ...this.ui, menus: new Set([...this.ui.menus, uid]) };
        this.flush();

        // Someone who can report may be about to, so the dialog's small chunk starts now.
        if (this.store.getState().permissions[uid]?.canReport) {
            this.loadReportDialog().catch(() => { this.reportLoading = null; });
        }
    }

    private visibleMenuItems(menu: Element): HTMLElement[] {
        return Array.from(menu.querySelectorAll<HTMLElement>('.comments__menu-item')).filter(isShown);
    }

    /**
     * The ··· menu works like a menu button: Enter, Space or an arrow opens it on an item, the
     * arrows, Home and End move through it, Escape closes it and returns to the button, and Tab
     * leaves it closed.
     */
    private onMenuKeydown(event: KeyboardEvent): void {
        const target = event.target instanceof HTMLElement ? event.target : null;
        const menu = target?.closest<HTMLDetailsElement>('.comments__menu');
        const toggle = menu?.querySelector<HTMLElement>('.comments__menu-toggle');

        if (!target || !menu || !toggle) return;

        if (target === toggle) {
            if (!['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) return;

            event.preventDefault();

            if (menu.open && (event.key === 'Enter' || event.key === ' ')) {
                menu.open = false;
                return;
            }

            this.closeMenus(menu);
            menu.open = true;
            this.buildMenu(menu);
            const items = this.visibleMenuItems(menu);
            (event.key === 'ArrowUp' ? items[items.length - 1] : items[0])?.focus();
            return;
        }

        const items = this.visibleMenuItems(menu);
        const index = items.indexOf(target);

        if (index === -1) return;

        const next: Record<string, HTMLElement | undefined> = {
            ArrowDown: items[(index + 1) % items.length],
            ArrowUp: items[(index - 1 + items.length) % items.length],
            Home: items[0],
            End: items[items.length - 1],
        };

        if (next[event.key]) {
            event.preventDefault();
            next[event.key]?.focus();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            menu.open = false;
            toggle.focus();
        } else if (event.key === 'Tab') {
            menu.open = false;
        }
    }

    private closeMenus(except?: Element | null): void {
        this.root.querySelectorAll('.comments__menu[open]').forEach((menu) => {
            if (menu !== except) menu.removeAttribute('open');
        });
    }
}

function selectAll(store: ThreadStore) {
    const state = store.getState();
    return [...selectRoots(state), ...Object.values(state.comments)];
}

function withoutKey(set: ReadonlySet<string>, key: string): ReadonlySet<string> {
    if (!set.has(key)) return set;

    const next = new Set(set);
    next.delete(key);
    return next;
}
