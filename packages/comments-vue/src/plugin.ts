import type { CommentsTheme } from '@verbb/comments-browser';
import type { SignInProvider, ThemeConfig } from '@verbb/comments-core';
import { computed, inject, provide, toValue, type App, type InjectionKey, type MaybeRefOrGetter, type Plugin } from 'vue';
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

export const COMMENTS_SETTINGS: InjectionKey<CommentsProviderSettings> = Symbol('verbb.comments.settings');

/**
 * `app.use(CommentsPlugin, { endpoint, auth, themeConfig, parts })` gives every thread and
 * composable the same settings. Pass a `reactive()` object, a ref or a getter to change them later,
 * such as a new `auth` when someone signs in.
 */
export const CommentsPlugin: Plugin<[MaybeRefOrGetter<CommentsProviderSettings>?]> = {
    install(app: App, settings: MaybeRefOrGetter<CommentsProviderSettings> = {}) {
        app.provide(COMMENTS_SETTINGS, live(() => toValue(settings)));
    },
};

/**
 * Settings for the components below this one, over the app's. A thread's own props still win. As
 * with the plugin, a `reactive()` object, a ref or a getter keeps them up to date.
 */
export function provideCommentsSettings(settings: MaybeRefOrGetter<CommentsProviderSettings>): void {
    const parent = useCommentsSettings();
    provide(COMMENTS_SETTINGS, live(() => ({ ...parent, ...toValue(settings) })));
}

/**
 * Settings that are read afresh, and tracked, wherever they're used, so a thread's computed values
 * follow a change. Not deeply reactive, so components and sign-in providers stay as they were given.
 */
function live(source: () => CommentsProviderSettings): CommentsProviderSettings {
    const value = computed(source);

    return new Proxy({} as CommentsProviderSettings, {
        get: (_target, key) => (value.value as Record<PropertyKey, unknown>)[key],
        has: (_target, key) => key in value.value,
        ownKeys: () => Reflect.ownKeys(value.value),
        getOwnPropertyDescriptor: (_target, key) => (key in value.value ? { value: (value.value as Record<PropertyKey, unknown>)[key], enumerable: true, configurable: true } : undefined),
    });
}

/** The settings from `CommentsPlugin` or `provideCommentsSettings()`, or none. */
export function useCommentsSettings(): CommentsProviderSettings {
    return inject(COMMENTS_SETTINGS, {});
}

/**
 * Editor from npm, split by the app's bundler into a chunk of its own. Importing it registers
 * nothing, so its `<verbb-editor>` element is registered here before the form swaps it in.
 */
export const loadEditorFromPackage = async (): Promise<void> => {
    const editor = await import('@verbb/editor');
    editor.registerVerbbEditorElement();
};
