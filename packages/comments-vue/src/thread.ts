import { mergeThemeConfig, type CommentsController, type CommentsTheme, type LoadMode, type MountOptions, type ThreadOptions } from '@verbb/comments-browser';
import type { SortOrder, ThemeConfig } from '@verbb/comments-core';
import { computed, defineComponent, getCurrentInstance, h, mergeProps, ref, useId, watch, type PropType } from 'vue';
import { EVENT_EMITS, THREAD_EMITS } from './events.js';
import { createHostRegistry, overridesKey, rendererOverrides } from './hosts.js';
import { followDarkSelector, latestAuth, latestFetch, type LatestAuth } from './latest.js';
import { ADAPTER_ATTRIBUTE, mountPointHtml, reserveBucket, threadId, waitingLabels } from './mount-point.js';
import type { CommentsFieldOverrides, CommentsPartOverrides } from './overrides.js';
import { loadEditorFromPackage, useCommentsSettings } from './plugin.js';
import { stableKey } from './stable.js';

type Renderer = typeof import('@verbb/comments-browser/renderer');

const threadProps = {
    /** The element the comments belong to. Give this or `ownerUri`. */
    ownerId: { type: Number, default: undefined },
    /** The owner's URI, for headless sites that route by URI. */
    ownerUri: { type: String, default: undefined },
    siteId: { type: Number as PropType<number | null>, default: undefined },
    /** The data API's URL. Defaults to the plugin's setting, then the page's own origin. */
    endpoint: { type: String, default: undefined },
    /**
     * When the thread loads: as it nears view (`visible`, the default), on **Show comments** (`button`),
     * or at once (`eager`). Read when the thread starts.
     */
    loadMode: { type: String as PropType<LoadMode>, default: undefined },
    /** A new sort re-sorts a loaded thread in place, keeping what's typed and where the reader is. */
    sort: { type: String as PropType<SortOrder>, default: undefined },
    /** Root comments per page, 1 to 50. A new limit starts the thread again, since its pages change. */
    limit: { type: Number, default: undefined },
    /** `light`, `dark`, `auto` or `none`. Defaults to the plugin's, then `light`. */
    theme: { type: String as PropType<CommentsTheme>, default: undefined },
    /** A selector, such as `html.dark`, that switches the thread to dark while it matches `<html>`, and to light when it stops. */
    darkSelector: { type: String, default: undefined },
    /** Classes and attributes for the ten controls, merged per control over the plugin's. */
    themeConfig: { type: Object as PropType<ThemeConfig>, default: undefined },
    /** Part overrides: Vue components, `templatePart()` functions or custom element names. Define components outside the template's render, so they aren't new each time. */
    parts: { type: Object as PropType<CommentsPartOverrides>, default: undefined },
    /** Custom field overrides by handle, `{ rating: { input, display } }`. */
    fields: { type: Object as PropType<CommentsFieldOverrides>, default: undefined },
    /** Wording to use instead of the site's translations. */
    labels: { type: Object as PropType<Record<string, string>>, default: undefined },
    /** The comment count to show before the thread loads, such as from `fetchCommentsCount()`. Read when the thread starts; the thread's own data shows the count from then on. */
    count: { type: Number, default: undefined },
    /** The mount point's ID. Defaults to `comments-<ownerId>-<siteId>`, as `craft.comments.render()` writes it. */
    id: { type: String, default: undefined },
} as const;

/**
 * A Comments thread. On the server it renders only the mount point (the reserved space, the
 * heading and the count), so hydration has nothing to disagree about; in the browser the renderer
 * draws the thread inside it. Changing `owner-id` or `owner-uri` starts a new thread, and giving the
 * component the owner as its `key` makes that explicit.
 */
export const CommentsThread = defineComponent({
    name: 'CommentsThread',
    props: threadProps,
    emits: THREAD_EMITS,
    // Attributes such as `class` go on the mount point, not the teleports beside it.
    inheritAttrs: false,
    setup(props, { emit, attrs }) {
        const settings = useCommentsSettings();
        const generated = useId();
        const registry = createHostRegistry(getCurrentInstance()?.appContext.mixins ?? []);
        const root = ref<HTMLElement | null>(null);
        // The renderer and the element, from which the thread's controller is found each time, so a
        // thread the renderer starts again (after a morphing page swap) is the one that's changed.
        let thread: { renderer: Renderer; element: HTMLElement } | null = null;
        let following: LatestAuth | null = null;
        const controller = (): CommentsController | undefined => (thread ? thread.renderer.getThread(thread.element) : undefined);
        let markup: { identity: string; html: string } | null = null;
        // The appearance the thread last drew with.
        let drawn: string | null = null;

        const siteId = computed(() => props.siteId ?? settings.siteId ?? null);
        const endpoint = computed(() => props.endpoint ?? settings.endpoint);
        const id = computed(() => props.id ?? threadId(props.ownerId, siteId.value, generated ?? ''));
        const theme = computed<CommentsTheme>(() => props.theme ?? settings.theme ?? 'light');
        const darkSelector = computed(() => props.darkSelector ?? settings.darkSelector);
        const labels = computed(() => (settings.labels || props.labels ? { ...settings.labels, ...props.labels } : undefined));
        const count = computed(() => Math.max(0, Math.floor(Number(props.count) || 0)));
        // A thread still waiting to load when its sort changes starts again, sorted, as
        // `<verbb-comments>` does.
        const restarts = ref(0);
        // What a thread is, its page size and how the person signs in. A change to any of these
        // starts a new thread; a new sign-in provider, fetch or nonce of the same kind is taken over.
        const identity = computed(() => stableKey({ ownerId: props.ownerId, ownerUri: props.ownerUri, siteId: siteId.value, endpoint: endpoint.value, limit: props.limit, id: id.value, profile: settings.auth?.profile ?? null, restarts: restarts.value }));
        // The load mode is read once, when each thread starts.
        let loadMode: { identity: string; value: LoadMode } | null = null;
        const startLoadMode = (): LoadMode => {
            if (loadMode?.identity !== identity.value) loadMode = { identity: identity.value, value: props.loadMode ?? 'visible' };
            return loadMode.value;
        };
        const parts = computed(() => ({ ...settings.parts, ...props.parts }));
        const fields = computed(() => mergeFields(settings.fields, props.fields));
        const appearanceKey = computed(() => [stableKey([settings.themeConfig, props.themeConfig, labels.value]), overridesKey(parts.value, fields.value, registry), registry.idOf(settings.loadEditor)].join('|'));
        const appearance = (): ThreadOptions => ({
            // Empty rather than left out, so taking a setting away takes it off the thread too.
            themeConfig: settings.themeConfig || props.themeConfig ? mergeThemeConfig([settings.themeConfig, props.themeConfig]) : {},
            labels: labels.value ?? {},
            loadEditor: settings.loadEditor ?? loadEditorFromPackage,
            ...rendererOverrides(parts.value, fields.value),
        });

        // The renderer starts in the browser once the mount point is in the page, and again on a new identity.
        watch([root, identity], ([element], _previous, onCleanup) => {
            if (!element) return;

            let disposed = false;
            // Listening from before the thread starts, so `@mounted` hears it.
            const listeners = EVENT_EMITS.map(([name, event]) => {
                const listener = (dispatched: Event) => (emit as (event: string, payload: Event) => void)(event, dispatched);
                element.addEventListener(name, listener);
                return () => element.removeEventListener(name, listener);
            });

            import('@verbb/comments-browser/renderer').then((module) => {
                if (disposed) return;

                const auth = settings.auth;
                following = auth ? latestAuth(() => settings.auth ?? auth) : null;
                thread = { renderer: module, element };
                drawn = appearanceKey.value;
                module.mountThread(element, {
                    endpoint: endpoint.value,
                    ownerId: props.ownerId,
                    ownerUri: props.ownerUri,
                    siteId: siteId.value,
                    sort: props.sort,
                    limit: props.limit,
                    loadMode: startLoadMode(),
                    theme: theme.value,
                    auth: following ?? undefined,
                    fetch: latestFetch(() => settings.fetch),
                    get nonce() {
                        return settings.nonce;
                    },
                    ...appearance(),
                    onHosts: (hosts) => {
                        if (!disposed) registry.set(hosts);
                    },
                } satisfies MountOptions);
                // A site's dark-mode toggle decides from the start, as it does for the plugin's threads.
                followDarkSelector(darkSelector.value, applyDark)();
            }).catch((error: unknown) => {
                if (!disposed) console.error('Comments: the renderer couldn\'t load.', error);
            });

            onCleanup(() => {
                // Stopped before listening ends, so `@destroyed` hears it, and before the teleports go.
                // Only a thread this component started: one another copy of Comments draws is its to stop.
                if (thread?.element === element && thread.renderer.getThread(element)) thread.renderer.destroyThreads(element);

                disposed = true;
                if (thread?.element === element) thread = null;
                registry.clear();
                listeners.forEach((stop) => stop());
            });
        }, { flush: 'post' });

        // A new theme switches in place, and new controls, parts, fields or labels redraw in place.
        // A new provider is followed at once, so its reset() on sign-out reaches the thread.
        watch(() => settings.auth, () => following?.sync(), { flush: 'sync' });

        // A new theme switches in place, falling back to the plugin's or light when the prop goes.
        watch(theme, (next) => controller()?.setTheme(next));

        const applyDark = (dark: boolean) => {
            if (theme.value !== 'none') controller()?.setTheme(dark ? 'dark' : 'light');
        };
        watch(darkSelector, (selector, _previous, onCleanup) => onCleanup(followDarkSelector(selector, applyDark)), { immediate: true });

        watch(appearanceKey, (key) => {
            const current = controller();

            if (!current || drawn === key) return;

            drawn = key;
            current.setOptions(appearance());
        });

        // A new sort re-sorts a loaded thread in place, keeping a half-written comment, the reader's
        // place and the components drawn in it. A thread still waiting to load starts again, sorted,
        // and so does one whose sort is taken away, to go back to the site's default. A new thread, for
        // a new owner or limit, starts with the sort it's given.
        watch([() => props.sort, identity], ([sort, key], [previousSort, previousKey]) => {
            if (key !== previousKey || sort === previousSort) return;

            const current = controller();

            if (current && sort && root.value?.dataset.commentsPhase === 'ready') {
                current.refresh({ sort });
            } else {
                restarts.value++;
            }
        });

        return () => {
            // The mount point's markup is fixed for each thread, so Vue never writes over a drawn thread.
            if (markup?.identity !== identity.value) {
                markup = { identity: identity.value, html: mountPointHtml({ id: id.value, count: count.value, labels: labels.value }) };
            }

            return [
                h('section', mergeProps(attrs, {
                    key: identity.value,
                    ref: root,
                    id: id.value,
                    class: ['comments', `comments--reserve-${reserveBucket(count.value)}`],
                    'aria-labelledby': `${id.value}-heading`,
                    'data-comments-root': '',
                    'data-comments-api-version': '1',
                    // The plugin's own script, if the page has it too, leaves this thread to the component.
                    'data-comments-auto-mount': 'false',
                    [ADAPTER_ATTRIBUTE]: 'vue',
                    'data-comments-endpoint': endpoint.value,
                    'data-comments-owner-id': props.ownerId,
                    'data-comments-site-id': siteId.value ?? undefined,
                    'data-comments-load-mode': startLoadMode(),
                    'data-comments-theme': theme.value,
                    'data-comments-count': count.value,
                    'data-comments-labels': waitingLabels(labels.value),
                    innerHTML: markup.html,
                })),
                ...registry.render(),
            ];
        };
    },
});

function mergeFields(...sources: Array<CommentsFieldOverrides | undefined>): CommentsFieldOverrides {
    const fields: CommentsFieldOverrides = {};

    for (const source of sources) {
        for (const [handle, override] of Object.entries(source ?? {})) {
            fields[handle] = { ...fields[handle], ...override };
        }
    }

    return fields;
}
