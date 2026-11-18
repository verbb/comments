import type { CommentsController, CommentsTheme, FieldOverride, LoadMode, MountOptions, PartName, PartOverride, ThemeConfig } from '@verbb/comments-browser';
/** The tag `registerCommentsWebComponents()` defines. */
export declare const COMMENTS_THREAD_TAG = "verbb-comments";
declare const Base: typeof HTMLElement;
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
export declare class CommentsThreadElement extends Base {
    #private;
    static readonly observedAttributes: string[];
    /** The thread's controller, once the element is on the page with `owner-id` or `owner-uri`. */
    get controller(): CommentsController | null;
    get ownerId(): number | null;
    set ownerId(value: number | null);
    get ownerUri(): string | null;
    set ownerUri(value: string | null);
    get siteId(): number | null;
    set siteId(value: number | null);
    get endpoint(): string | null;
    set endpoint(value: string | null);
    get loadMode(): LoadMode | null;
    set loadMode(value: LoadMode | null);
    get sort(): string | null;
    set sort(value: string | null);
    get theme(): CommentsTheme | null;
    set theme(value: CommentsTheme | null);
    /** Classes and attributes for the ten controls, which win over `Comments.configure()`'s. */
    get themeConfig(): ThemeConfig | undefined;
    set themeConfig(value: ThemeConfig | undefined);
    /** Part overrides: functions, custom element names, or `{ component }` for an adapter's component. */
    get parts(): Partial<Record<PartName, PartOverride>> | undefined;
    set parts(value: Partial<Record<PartName, PartOverride>> | undefined);
    /** Custom field overrides by handle, `{ rating: { input, display } }`. */
    get fields(): Record<string, FieldOverride> | undefined;
    set fields(value: Record<string, FieldOverride> | undefined);
    /** Wording to use instead of the site's translations. */
    get labels(): Record<string, string> | undefined;
    set labels(value: Record<string, string> | undefined);
    /** Loads Editor for rich authoring. Defaults to importing `@verbb/editor`. */
    get loadEditor(): (() => Promise<unknown>) | undefined;
    set loadEditor(value: (() => Promise<unknown>) | undefined);
    /** How the person is identified, such as `memberToken()` from `@verbb/comments-core` on another site. */
    get auth(): MountOptions['auth'];
    set auth(value: MountOptions['auth']);
    connectedCallback(): void;
    disconnectedCallback(): void;
    attributeChangedCallback(name: string, previous: string | null, value: string | null): void;
}
declare global {
    interface HTMLElementTagNameMap {
        'verbb-comments': CommentsThreadElement;
    }
}
export {};
