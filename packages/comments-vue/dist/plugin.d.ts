import type { CommentsTheme } from '@verbb/comments-browser';
import type { SignInProvider, ThemeConfig } from '@verbb/comments-core';
import { type InjectionKey, type MaybeRefOrGetter, type Plugin } from 'vue';
import type { CommentsFieldOverrides, CommentsPartOverrides } from './overrides.js';
/** Settings shared by every thread and composable in the app. */
export type CommentsProviderSettings = {
    /** The data API's URL, such as `https://cms.example.com/actions/comments/api`. Defaults to the page's own origin. */
    endpoint?: string;
    /** How the person is identified: `cookieSession()`, `memberToken()` or `guestSession()` from `@verbb/comments-core`. */
    auth?: SignInProvider;
    siteId?: number | null;
    /** Classes and attributes for the ten controls, merged per control under each thread's own. */
    themeConfig?: ThemeConfig;
    /** Part overrides for every thread: Vue components, `templatePart()` functions or custom element names. */
    parts?: CommentsPartOverrides;
    /** Custom field overrides by handle for every thread. */
    fields?: CommentsFieldOverrides;
    /** Wording to use instead of the site's translations. */
    labels?: Record<string, string>;
    theme?: CommentsTheme;
    /** A selector, such as `html.dark`, that switches every thread to dark while it matches `<html>`, and to light when it stops. */
    darkSelector?: string;
    /**
     * Loads Editor for rich authoring. Defaults to `() => import('@verbb/editor')`, which your
     * bundler splits into a chunk of its own, loaded only when someone starts writing.
     */
    loadEditor?: () => Promise<unknown>;
    /** A CSP nonce for the scripts and stylesheets a thread loads, such as Protect's challenges. */
    nonce?: string;
    /** A `fetch` to use instead of the global one. */
    fetch?: typeof fetch;
};
export declare const COMMENTS_SETTINGS: InjectionKey<CommentsProviderSettings>;
/**
 * `app.use(CommentsPlugin, { endpoint, auth, themeConfig, parts })` gives every thread and
 * composable the same settings. Pass a `reactive()` object, a ref or a getter to change them later,
 * such as a new `auth` when someone signs in.
 */
export declare const CommentsPlugin: Plugin<[MaybeRefOrGetter<CommentsProviderSettings>?]>;
/**
 * Settings for the components below this one, over the app's. A thread's own props still win. As
 * with the plugin, a `reactive()` object, a ref or a getter keeps them up to date.
 */
export declare function provideCommentsSettings(settings: MaybeRefOrGetter<CommentsProviderSettings>): void;
/** The settings from `CommentsPlugin` or `provideCommentsSettings()`, or none. */
export declare function useCommentsSettings(): CommentsProviderSettings;
/**
 * Editor from npm, split by the app's bundler into a chunk of its own. Importing it registers
 * nothing, so its `<verbb-editor>` element is registered here before the form swaps it in.
 */
export declare const loadEditorFromPackage: () => Promise<void>;
