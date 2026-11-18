import { type CommentsTheme, type LoadMode } from '@verbb/comments-browser';
import type { SortOrder, ThemeConfig } from '@verbb/comments-core';
import { type PropType } from 'vue';
import type { CommentsFieldOverrides, CommentsPartOverrides } from './overrides.js';
/**
 * A Comments thread. On the server it renders only the mount point (the reserved space, the
 * heading and the count), so hydration has nothing to disagree about; in the browser the renderer
 * draws the thread inside it. Changing `owner-id` or `owner-uri` starts a new thread, and giving the
 * component the owner as its `key` makes that explicit.
 */
export declare const CommentsThread: import("vue").DefineComponent<import("vue").ExtractPropTypes<{
    /** The element the comments belong to. Give this or `ownerUri`. */
    readonly ownerId: {
        readonly type: NumberConstructor;
        readonly default: undefined;
    };
    /** The owner's URI, for headless sites that route by URI. */
    readonly ownerUri: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
    readonly siteId: {
        readonly type: PropType<number | null>;
        readonly default: undefined;
    };
    /** The data API's URL. Defaults to the plugin's setting, then the page's own origin. */
    readonly endpoint: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
    /**
     * When the thread loads: as it nears view (`visible`, the default), on **Show comments** (`button`),
     * or at once (`eager`). Read when the thread starts.
     */
    readonly loadMode: {
        readonly type: PropType<LoadMode>;
        readonly default: undefined;
    };
    /** A new sort re-sorts a loaded thread in place, keeping what's typed and where the reader is. */
    readonly sort: {
        readonly type: PropType<SortOrder>;
        readonly default: undefined;
    };
    /** Root comments per page, 1 to 50. A new limit starts the thread again, since its pages change. */
    readonly limit: {
        readonly type: NumberConstructor;
        readonly default: undefined;
    };
    /** `light`, `dark`, `auto` or `none`. Defaults to the plugin's, then `light`. */
    readonly theme: {
        readonly type: PropType<CommentsTheme>;
        readonly default: undefined;
    };
    /** A selector, such as `html.dark`, that switches the thread to dark while it matches `<html>`, and to light when it stops. */
    readonly darkSelector: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
    /** Classes and attributes for the ten controls, merged per control over the plugin's. */
    readonly themeConfig: {
        readonly type: PropType<ThemeConfig>;
        readonly default: undefined;
    };
    /** Part overrides: Vue components, `templatePart()` functions or custom element names. Define components outside the template's render, so they aren't new each time. */
    readonly parts: {
        readonly type: PropType<CommentsPartOverrides>;
        readonly default: undefined;
    };
    /** Custom field overrides by handle, `{ rating: { input, display } }`. */
    readonly fields: {
        readonly type: PropType<CommentsFieldOverrides>;
        readonly default: undefined;
    };
    /** Wording to use instead of the site's translations. */
    readonly labels: {
        readonly type: PropType<Record<string, string>>;
        readonly default: undefined;
    };
    /** The comment count to show before the thread loads, such as from `fetchCommentsCount()`. Read when the thread starts; the thread's own data shows the count from then on. */
    readonly count: {
        readonly type: NumberConstructor;
        readonly default: undefined;
    };
    /** The mount point's ID. Defaults to `comments-<ownerId>-<siteId>`, as `craft.comments.render()` writes it. */
    readonly id: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
}>, () => import("vue").VNode<import("vue").RendererNode, import("vue").RendererElement, {
    [key: string]: any;
}>[], {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, import("./events.js").CommentsThreadEmits, string, import("vue").PublicProps, Readonly<import("vue").ExtractPropTypes<{
    /** The element the comments belong to. Give this or `ownerUri`. */
    readonly ownerId: {
        readonly type: NumberConstructor;
        readonly default: undefined;
    };
    /** The owner's URI, for headless sites that route by URI. */
    readonly ownerUri: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
    readonly siteId: {
        readonly type: PropType<number | null>;
        readonly default: undefined;
    };
    /** The data API's URL. Defaults to the plugin's setting, then the page's own origin. */
    readonly endpoint: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
    /**
     * When the thread loads: as it nears view (`visible`, the default), on **Show comments** (`button`),
     * or at once (`eager`). Read when the thread starts.
     */
    readonly loadMode: {
        readonly type: PropType<LoadMode>;
        readonly default: undefined;
    };
    /** A new sort re-sorts a loaded thread in place, keeping what's typed and where the reader is. */
    readonly sort: {
        readonly type: PropType<SortOrder>;
        readonly default: undefined;
    };
    /** Root comments per page, 1 to 50. A new limit starts the thread again, since its pages change. */
    readonly limit: {
        readonly type: NumberConstructor;
        readonly default: undefined;
    };
    /** `light`, `dark`, `auto` or `none`. Defaults to the plugin's, then `light`. */
    readonly theme: {
        readonly type: PropType<CommentsTheme>;
        readonly default: undefined;
    };
    /** A selector, such as `html.dark`, that switches the thread to dark while it matches `<html>`, and to light when it stops. */
    readonly darkSelector: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
    /** Classes and attributes for the ten controls, merged per control over the plugin's. */
    readonly themeConfig: {
        readonly type: PropType<ThemeConfig>;
        readonly default: undefined;
    };
    /** Part overrides: Vue components, `templatePart()` functions or custom element names. Define components outside the template's render, so they aren't new each time. */
    readonly parts: {
        readonly type: PropType<CommentsPartOverrides>;
        readonly default: undefined;
    };
    /** Custom field overrides by handle, `{ rating: { input, display } }`. */
    readonly fields: {
        readonly type: PropType<CommentsFieldOverrides>;
        readonly default: undefined;
    };
    /** Wording to use instead of the site's translations. */
    readonly labels: {
        readonly type: PropType<Record<string, string>>;
        readonly default: undefined;
    };
    /** The comment count to show before the thread loads, such as from `fetchCommentsCount()`. Read when the thread starts; the thread's own data shows the count from then on. */
    readonly count: {
        readonly type: NumberConstructor;
        readonly default: undefined;
    };
    /** The mount point's ID. Defaults to `comments-<ownerId>-<siteId>`, as `craft.comments.render()` writes it. */
    readonly id: {
        readonly type: StringConstructor;
        readonly default: undefined;
    };
}>> & Readonly<{
    onMounted?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & Record<never, never>>) => any) | undefined;
    onDestroyed?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & Record<never, never>>) => any) | undefined;
    "onBefore-render"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        reason: "thread" | "page" | "replies" | "viewer" | "comment" | "write";
        data: import("@verbb/comments-core").ThreadState;
    }>) => any) | undefined;
    "onBefore-action"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        action: string;
        commentUid: string | null;
    }>) => any) | undefined;
    "onBefore-submit"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        form: HTMLFormElement;
        mode: import("@verbb/comments-browser").CommentsEditorMode;
        commentUid: string | null;
        formData: FormData;
    }>) => any) | undefined;
    "onRequest-start"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        reason: string;
    }>) => any) | undefined;
    "onRequest-end"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        reason: string;
    }>) => any) | undefined;
    "onRequest-error"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        message: string;
        reason?: string;
        category?: string;
        code?: string;
        fieldErrors?: Record<string, string[]>;
        recoverable?: boolean;
    }>) => any) | undefined;
    "onViewer-loaded"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        viewer: import("@verbb/comments-core").ViewerData | null;
    }>) => any) | undefined;
    "onRoots-loaded"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & Record<never, never>>) => any) | undefined;
    "onReplies-loaded"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        commentUid: string;
    }>) => any) | undefined;
    onSorted?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        sort: string;
    }>) => any) | undefined;
    "onEditor-opened"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        mode: string;
        commentUid: string | null;
    }>) => any) | undefined;
    "onEditor-closed"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        reason: string;
    }>) => any) | undefined;
    "onComment-created"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        commentUid: string;
        moderationState: "approved" | "pending";
    }>) => any) | undefined;
    "onComment-updated"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        commentUid: string;
    }>) => any) | undefined;
    "onComment-deleted"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        commentUid: string | null;
    }>) => any) | undefined;
    "onComment-moderated"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        commentUid: string | null;
        commentId: string | null;
        moderationAction: string | null;
    }>) => any) | undefined;
    "onVote-changed"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        commentUid: string | null;
        commentId: string | null;
        vote: import("@verbb/comments-core").VoteValue | null;
    }>) => any) | undefined;
    "onReport-changed"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        commentUid: string | null;
        commentId: string | null;
        reported: boolean;
    }>) => any) | undefined;
    "onFollow-changed"?: ((event: CustomEvent<import("@verbb/comments-browser").CommentsEventDetail & {
        following: boolean;
    }>) => any) | undefined;
}>, {
    readonly fields: CommentsFieldOverrides;
    readonly sort: SortOrder;
    readonly parts: Partial<Record<import("@verbb/comments-core").PartName, import("./overrides.js").CommentsPartOverride>>;
    readonly endpoint: string;
    readonly ownerId: number;
    readonly ownerUri: string;
    readonly siteId: number | null;
    readonly limit: number;
    readonly themeConfig: Partial<Record<import("@verbb/comments-core").ThemeControlName, import("@verbb/comments-core").ThemeControlConfig>>;
    readonly labels: Record<string, string>;
    readonly theme: CommentsTheme;
    readonly darkSelector: string;
    readonly loadMode: LoadMode;
    readonly id: string;
    readonly count: number;
}, {}, {}, {}, string, import("vue").ComponentProvideOptions, true, {}, any>;
