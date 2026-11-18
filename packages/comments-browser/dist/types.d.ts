import type { CommentsClient, SignInProvider, SortOrder, ThemeConfig, ThreadStore } from '@verbb/comments-core';
import type { ComponentHost, FieldOverride, PartOverride, PartName } from './view.js';
export type { FieldOverride };
export type LoadMode = 'visible' | 'button' | 'eager';
export type CommentsTheme = 'light' | 'dark' | 'auto' | 'none';
/** Errors to show on a comment form, in the shape the data API returns them. */
export interface CommentsFormErrors {
    /**
     * Messages keyed by field: `content`, `guest.name`, `guest.email` or `fields.<handle>`, or by
     * an input's `name`. Each field shows its first message.
     */
    fieldErrors?: Record<string, string[]>;
    /** Shown at the top of the form when none of the field errors has a field to go beside. */
    message?: string;
}
/** Settings for every thread on the page, from `Comments.configure()`, `startComments()` or `mountThread()`. */
export interface CommentsSettings {
    /** Classes and attributes for the ten controls. */
    themeConfig?: ThemeConfig;
    /**
     * Part overrides: a function returning a template, a string or a node, a custom element's name,
     * or `{ component }` for a framework component an adapter renders into a host element.
     */
    parts?: Partial<Record<PartName, PartOverride>>;
    /**
     * Custom field overrides by handle: `input` draws the field on the form and `display` on a
     * comment, each a function, a registered custom element's name or `{ component }`, such as
     * `{ rating: { input: stars, display: starsShown } }`.
     */
    fields?: Record<string, FieldOverride>;
    /** Wording to use instead of the site's translations. */
    labels?: Record<string, string>;
    theme?: CommentsTheme;
    /** A selector that, while it matches `<html>`, switches every thread to dark, and to light when it stops. */
    darkSelector?: string;
    loadMode?: LoadMode;
    /**
     * Loads Editor for rich authoring, resolving once `<verbb-editor>` is defined, such as
     * `() => import('@verbb/editor').then((editor) => editor.registerVerbbEditorElement())` in a
     * bundled app (importing Editor alone defines nothing).
     * By default Editor's own module is loaded from the URL the thread's data gives.
     */
    loadEditor?: () => Promise<unknown>;
    /** How the person is identified, from `@verbb/comments-core`. Defaults to Craft's session cookie. */
    auth?: SignInProvider;
    /** A CSP nonce for the scripts and stylesheets the thread loads: Editor's and Protect's challenges. */
    nonce?: string;
    fetch?: typeof fetch;
}
/** Options for one thread, which win over the page's settings. */
export interface MountOptions extends CommentsSettings {
    endpoint?: string;
    ownerId?: number;
    ownerUri?: string;
    siteId?: number | null;
    sort?: SortOrder;
    limit?: number;
    /**
     * Called with every component host after each draw that added, changed or removed one, from
     * the thread's first draw, as `controller.subscribeHosts()` would be.
     */
    onHosts?: (hosts: readonly ComponentHost[], controller: CommentsController) => void;
}
/** The options of a mounted thread that `controller.setOptions()` can replace. */
export type ThreadOptions = Pick<CommentsSettings, 'themeConfig' | 'parts' | 'fields' | 'labels' | 'loadEditor'>;
/** One thread on the page, from `Comments.get(root)` or any event's `detail.controller`. */
export interface CommentsController {
    readonly root: HTMLElement;
    /** The thread's element ID, such as `comments-109-1`, which every event's `detail.instanceId` gives. */
    readonly instanceId: string;
    readonly conversationUid: string;
    readonly sort: SortOrder;
    readonly store: ThreadStore;
    readonly client: CommentsClient;
    /** Loads the thread now, whatever its load mode. */
    load(): Promise<void>;
    /** Fetches the thread again, optionally in another order. */
    refresh(options?: {
        reason?: string;
        sort?: SortOrder | (string & {});
    }): void;
    /** Opens the form for a new comment, or a reply or edit form under the comment with `commentUid`. */
    openEditor(options: {
        mode: 'create' | 'reply' | 'edit';
        commentUid?: string;
    }): void;
    /** Closes the open reply or edit form. */
    closeEditor(options?: {
        reason?: string;
    }): void;
    /** Shows errors on a form the way the script shows the server's, and moves focus to the first. */
    showFormErrors(form: HTMLFormElement, errors: CommentsFormErrors): void;
    /** Removes errors shown on a form, whether they came from the server or `showFormErrors()`. */
    clearFormErrors(form: HTMLFormElement): void;
    setTheme(theme: CommentsTheme): void;
    /**
     * Replaces this thread's own `themeConfig`, `parts`, `fields`, `labels` or `loadEditor` (those
     * given; the rest stay), and redraws. Settings from `Comments.configure()` still apply beneath.
     */
    setOptions(options: ThreadOptions): void;
    /** The component hosts as of the last report: the same array until the next. Bound to the controller. */
    getHosts(): readonly ComponentHost[];
    /**
     * Calls `listener` with every component host after each draw that added, changed or removed
     * one, once however many changed, and with none when the thread stops. Returns a function that
     * unsubscribes. Bound to the controller, so it can be passed to `useSyncExternalStore()` as it is.
     */
    subscribeHosts(listener: (hosts: readonly ComponentHost[]) => void): () => void;
    /** Stops this thread. */
    destroy(): void;
}
/**
 * `window.Comments`, and the package's `Comments`. The renderer loads when the first thread needs
 * it, so starting threads is asynchronous.
 */
export interface CommentsApp {
    /** Starts the threads inside `scope`, or the whole document, and resolves with their controllers. */
    mount(scope?: ParentNode): Promise<CommentsController[]>;
    /** Stops the threads inside `scope`, or the whole document. */
    destroy(scope?: ParentNode): void;
    /** The controller for a thread's root element, once it's started. */
    get(root: Element): CommentsController | undefined;
    /** Settings for every thread, merged with any given before. Threads already drawn redraw. */
    configure(settings: CommentsSettings): void;
    /** Switches every thread's theme, as a site's own dark-mode toggle would. */
    setTheme(theme: CommentsTheme): void;
    /** Starts one thread on any element, with options that win over the page's settings. */
    mountThread(element: HTMLElement, options?: MountOptions): Promise<CommentsController>;
}
