import type { CommentsTheme } from '@verbb/comments-browser';
import type { SignInProvider, ThemeConfig } from '@verbb/comments-core';
import { type ReactNode } from 'react';
import type { CommentsFieldOverrides, CommentsPartOverrides } from './overrides.js';
/** Settings shared by every thread and hook below a `<CommentsProvider>`. */
export type CommentsProviderSettings = {
    /** The data API's URL, such as `https://cms.example.com/actions/comments/api`. Defaults to the page's own origin. */
    endpoint?: string;
    /** How the person is identified: `cookieSession()`, `memberToken()` or `guestSession()` from `@verbb/comments-core`. */
    auth?: SignInProvider;
    siteId?: number | null;
    /** Classes and attributes for the ten controls, merged per control under each thread's own. */
    themeConfig?: ThemeConfig;
    /** Part overrides for every thread: React components, `templatePart()` functions or custom element names. */
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
export type CommentsProviderProps = CommentsProviderSettings & {
    children?: ReactNode;
};
/** Gives every `<CommentsThread>` and hook inside it the same settings. A thread's own props win. */
export declare function CommentsProvider({ children, endpoint, auth, siteId, themeConfig, parts, fields, labels, theme, darkSelector, loadEditor, nonce, fetch }: CommentsProviderProps): import("react").FunctionComponentElement<import("react").ProviderProps<CommentsProviderSettings>>;
/** The settings from the nearest `<CommentsProvider>`, or none. */
export declare function useCommentsSettings(): CommentsProviderSettings;
/**
 * Editor from npm, split by the app's bundler into a chunk of its own. Importing it registers
 * nothing, so its `<verbb-editor>` element is registered here before the form swaps it in.
 */
export declare const loadEditorFromPackage: () => Promise<void>;
