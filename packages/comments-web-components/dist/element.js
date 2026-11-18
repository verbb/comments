import { mountThread } from '@verbb/comments-browser/renderer';
/** The tag `registerCommentsWebComponents()` defines. */
export const COMMENTS_THREAD_TAG = 'verbb-comments';
const THEMES = ['light', 'dark', 'auto', 'none'];
const LOAD_MODES = ['visible', 'button', 'eager'];
// Attributes that say which thread to draw, or where from, so changing one draws another thread.
const REMOUNT = ['owner-id', 'owner-uri', 'site-id', 'endpoint'];
// Properties a site may set before the element is defined, which would otherwise hide the setters.
const PROPERTIES = ['ownerId', 'ownerUri', 'siteId', 'endpoint', 'loadMode', 'sort', 'theme', 'themeConfig', 'parts', 'fields', 'labels', 'loadEditor', 'auth'];
// What the thread adds to its element that would make the plugin's own script take a stopped
// element for one of the server's mount points.
const MOUNT_POINT_ATTRIBUTES = ['data-comments-root', 'data-comments-api-version'];
/**
 * Editor from npm, for a rich comment form, split into its own chunk by the app's bundler and
 * loaded only when someone starts writing. Importing Editor defines nothing, so its element is
 * registered here. Its stylesheet is the app's to import, as `@verbb/editor/styles`.
 */
const loadEditor = async () => {
    const editor = await import('@verbb/editor');
    editor.registerVerbbEditorElement();
};
// The element's base class, from the page. In Node, such as a server render importing the package,
// there's no HTMLElement, so the class can be imported but never constructed.
const Base = (typeof HTMLElement === 'undefined' ? class {
} : HTMLElement);
/**
 * `<verbb-comments>`, one Comments thread in the light DOM, so the page's own CSS and Comments'
 * stylesheet apply as they do to the plugin's mount point. It mounts the thread through
 * `@verbb/comments-browser/renderer` when it's added to the page and stops it when it's taken
 * away, keeping it through a move to another place on the page. Browser events bubble from the
 * element itself.
 *
 * - `owner-id` or `owner-uri` (one is needed), `site-id` and `endpoint` say which thread to draw.
 *   Changing any of them draws that thread instead.
 * - `sort` and `theme` change the drawn thread in place.
 * - `load-mode` (`visible`, `button` or `eager`), or the `loadMode` property, is read when the
 *   thread starts; call `controller.load()` to load a waiting thread later.
 * - The `themeConfig`, `parts`, `fields`, `labels` and `loadEditor` properties redraw the thread
 *   when set. `auth` is read when the thread starts, so setting it starts the thread again.
 * - Turbo Drive's cache gets the element empty, and `turbo:load` starts a thread that's still on
 *   the page, as the plugin's own script does with its threads. A morphing page swap that takes
 *   the drawn thread away is drawn again by the same controller.
 */
export class CommentsThreadElement extends Base {
    static observedAttributes = [...REMOUNT, 'load-mode', 'sort', 'theme'];
    #controller = null;
    #options = {};
    #queued = false;
    // While this element mounts its own thread, so `comments:mounted` listeners already see it as `controller`.
    #mounting = false;
    // Mount point attributes the thread added, to take off again when it stops.
    #added = [];
    #onDestroyed = (event) => {
        if (event.target !== this)
            return;
        // Stopped by something else, such as `Comments.destroy()`, so this element lets it go too,
        // and stops looking like a mount point the plugin's own script should start.
        this.#controller = null;
        for (const name of this.#added)
            this.removeAttribute(name);
    };
    #onMounted = (event) => {
        // Only the thread this element is mounting; another copy's never becomes its controller.
        if (this.#mounting && event.target === this && event.detail.controller)
            this.#controller = event.detail.controller;
    };
    #onBeforeCache = () => {
        // Turbo Drive is about to keep a copy of the page, which should hold the empty element, so
        // the copy it restores later starts its thread afresh.
        this.#stop();
    };
    #onLoad = () => {
        // Still on the page after a visit, such as one that didn't go ahead, so it starts again,
        // as the plugin's own script starts its threads.
        if (this.isConnected && !this.#controller)
            this.#mount();
    };
    /** The thread's controller, once the element is on the page with `owner-id` or `owner-uri`. */
    get controller() {
        return this.#controller;
    }
    get ownerId() {
        return this.#number('owner-id') ?? null;
    }
    set ownerId(value) {
        this.#reflect('owner-id', value);
    }
    get ownerUri() {
        return this.getAttribute('owner-uri');
    }
    set ownerUri(value) {
        this.#reflect('owner-uri', value);
    }
    get siteId() {
        return this.#number('site-id') ?? null;
    }
    set siteId(value) {
        this.#reflect('site-id', value);
    }
    get endpoint() {
        return this.getAttribute('endpoint');
    }
    set endpoint(value) {
        this.#reflect('endpoint', value);
    }
    get loadMode() {
        const value = this.getAttribute('load-mode');
        return value && LOAD_MODES.includes(value) ? value : null;
    }
    set loadMode(value) {
        this.#reflect('load-mode', value);
    }
    get sort() {
        return this.getAttribute('sort');
    }
    set sort(value) {
        this.#reflect('sort', value);
    }
    get theme() {
        const value = this.getAttribute('theme');
        return value && THEMES.includes(value) ? value : null;
    }
    set theme(value) {
        this.#reflect('theme', value);
    }
    /** Classes and attributes for the ten controls, which win over `Comments.configure()`'s. */
    get themeConfig() {
        return this.#options.themeConfig;
    }
    set themeConfig(value) {
        this.#set('themeConfig', value);
    }
    /** Part overrides: functions, custom element names, or `{ component }` for an adapter's component. */
    get parts() {
        return this.#options.parts;
    }
    set parts(value) {
        this.#set('parts', value);
    }
    /** Custom field overrides by handle, `{ rating: { input, display } }`. */
    get fields() {
        return this.#options.fields;
    }
    set fields(value) {
        this.#set('fields', value);
    }
    /** Wording to use instead of the site's translations. */
    get labels() {
        return this.#options.labels;
    }
    set labels(value) {
        this.#set('labels', value);
    }
    /** Loads Editor for rich authoring. Defaults to importing `@verbb/editor`. */
    get loadEditor() {
        return this.#options.loadEditor;
    }
    set loadEditor(value) {
        this.#set('loadEditor', value);
    }
    /** How the person is identified, such as `memberToken()` from `@verbb/comments-core` on another site. */
    get auth() {
        return this.#options.auth;
    }
    set auth(value) {
        this.#options = { ...this.#options, auth: value };
        if (this.#controller)
            this.#remount();
    }
    connectedCallback() {
        // A property set before the element was defined is an own property hiding the setter.
        for (const name of PROPERTIES) {
            if (Object.hasOwn(this, name)) {
                const value = this[name];
                delete this[name];
                this[name] = value;
            }
        }
        this.addEventListener('comments:destroyed', this.#onDestroyed);
        this.addEventListener('comments:mounted', this.#onMounted);
        document.addEventListener('turbo:before-cache', this.#onBeforeCache);
        document.addEventListener('turbo:load', this.#onLoad);
        // Moved on the page: the thread, and anything typed in it, stays as it is.
        if (!this.#controller)
            this.#mount();
    }
    disconnectedCallback() {
        document.removeEventListener('turbo:before-cache', this.#onBeforeCache);
        document.removeEventListener('turbo:load', this.#onLoad);
        // A move takes the element off the page and puts it back in the same task, so the thread
        // only stops if it's still off the page once the task is done.
        queueMicrotask(() => {
            if (!this.isConnected) {
                this.#stop();
                this.removeEventListener('comments:destroyed', this.#onDestroyed);
                this.removeEventListener('comments:mounted', this.#onMounted);
            }
        });
    }
    attributeChangedCallback(name, previous, value) {
        if (previous === value || !this.isConnected)
            return;
        const controller = this.#controller;
        // Not started yet, perhaps for want of an owner: try again with this attribute.
        if (!controller) {
            this.#remount();
            return;
        }
        if (name === 'theme') {
            controller.setTheme(this.theme ?? 'light');
        }
        else if (name === 'sort') {
            // A thread that has loaded is sorted in place; one still waiting starts again, sorted.
            if (this.dataset.commentsPhase === 'ready') {
                if (value)
                    controller.refresh({ sort: value });
            }
            else {
                this.#remount();
            }
        }
        else if (REMOUNT.includes(name)) {
            this.#remount();
        }
    }
    /** Starts the thread again from the element's attributes, once, however many changed together. */
    #remount() {
        if (this.#queued)
            return;
        this.#queued = true;
        queueMicrotask(() => {
            if (!this.#queued)
                return;
            this.#stop();
            if (this.isConnected)
                this.#mount();
        });
    }
    #mount() {
        // Whatever was queued is answered by mounting with the attributes as they are now.
        this.#queued = false;
        const ownerId = this.#number('owner-id');
        const ownerUri = this.getAttribute('owner-uri') || undefined;
        // Waits for an owner, which a framework may set just after adding the element.
        if (ownerId === undefined && !ownerUri)
            return;
        this.#added = MOUNT_POINT_ATTRIBUTES.filter((name) => !this.hasAttribute(name));
        this.#mounting = true;
        try {
            this.#controller = this.#start(ownerId, ownerUri);
        }
        finally {
            this.#mounting = false;
        }
    }
    #start(ownerId, ownerUri) {
        return mountThread(this, {
            ...this.#options,
            loadEditor: this.#options.loadEditor ?? loadEditor,
            ownerId,
            ownerUri,
            siteId: this.#number('site-id'),
            endpoint: this.getAttribute('endpoint') || undefined,
            // An attribute is markup, so Editor's module isn't trusted from its origin on that
            // account; Editor comes from npm here anyway, unless the site gives its own loader.
            endpointFromMarkup: true,
            loadMode: this.loadMode ?? undefined,
            sort: (this.getAttribute('sort') || undefined),
            theme: this.theme ?? undefined,
        });
    }
    #stop() {
        const controller = this.#controller;
        this.#controller = null;
        controller?.destroy();
        for (const name of this.#added)
            this.removeAttribute(name);
        this.#added = [];
    }
    /** Keeps an option and gives it to the thread, which redraws, or to the next thread to start. */
    #set(name, value) {
        this.#options = { ...this.#options, [name]: value };
        this.#controller?.setOptions({ [name]: name === 'loadEditor' ? value ?? loadEditor : value });
    }
    #number(name) {
        const value = this.getAttribute(name);
        return value === null || value.trim() === '' || Number.isNaN(Number(value)) ? undefined : Number(value);
    }
    #reflect(name, value) {
        if (value === null || value === undefined || value === '')
            this.removeAttribute(name);
        else
            this.setAttribute(name, String(value));
    }
}
