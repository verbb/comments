import { type CommentsTheme, type LoadMode } from '@verbb/comments-browser';
import type { SortOrder, ThemeConfig } from '@verbb/comments-core';
import { type ReactNode } from 'react';
import { type CommentsEventProps } from './events.js';
import type { CommentsFieldOverrides, CommentsPartOverrides } from './overrides.js';
export type CommentsThreadProps = CommentsEventProps & {
    /** The element the comments belong to. Give this or `ownerUri`. */
    ownerId?: number;
    /** The owner's URI, for headless sites that route by URI. */
    ownerUri?: string;
    siteId?: number | null;
    /** The data API's URL. Defaults to the provider's, then the page's own origin. */
    endpoint?: string;
    /**
     * When the thread loads: as it nears view (`visible`, the default), on **Show comments** (`button`),
     * or at once (`eager`). Read when the thread starts.
     */
    loadMode?: LoadMode;
    /** A new sort re-sorts a loaded thread in place, keeping what's typed and where the reader is. */
    sort?: SortOrder;
    /** Root comments per page, 1 to 50. A new limit starts the thread again, since its pages change. */
    limit?: number;
    /** `light`, `dark`, `auto` or `none`. Defaults to the provider's, then `light`. */
    theme?: CommentsTheme;
    /** A selector, such as `html.dark`, that switches the thread to dark while it matches `<html>`, and to light when it stops. */
    darkSelector?: string;
    /** Classes and attributes for the ten controls, merged per control over the provider's. */
    themeConfig?: ThemeConfig;
    /** Part overrides: React components, `templatePart()` functions or custom element names. Define components outside your own, or memoise them, so they aren't new on every render. */
    parts?: CommentsPartOverrides;
    /** Custom field overrides by handle, `{ rating: { input, display } }`. */
    fields?: CommentsFieldOverrides;
    /** Wording to use instead of the site's translations. */
    labels?: Record<string, string>;
    /** The comment count to show before the thread loads, such as from `fetchCommentsCount()`. Read when the thread starts; the thread's own data shows the count from then on. */
    count?: number;
    /** The mount point's ID. Defaults to `comments-<ownerId>-<siteId>`, as `craft.comments.render()` writes it. */
    id?: string;
    className?: string;
};
/**
 * A Comments thread. On the server it renders only the mount point (the reserved space, the
 * heading and the count), so hydration has nothing to disagree about; in the browser the renderer
 * draws the thread inside it. Changing `ownerId` or `ownerUri` starts a new thread, and giving the
 * component the owner as its `key` makes that explicit.
 */
export declare function CommentsThread(props: CommentsThreadProps): ReactNode;
