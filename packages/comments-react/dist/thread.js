import { mergeThemeConfig } from '@verbb/comments-browser';
import { createElement, Fragment, useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { EVENT_PROPS } from './events.js';
import { createHostRegistry, overridesKey, rendererOverrides } from './hosts.js';
import { followDarkSelector, latestAuth, latestFetch } from './latest.js';
import { ADAPTER_ATTRIBUTE, mountPointHtml, reserveBucket, threadId, waitingLabels } from './mount-point.js';
import { loadEditorFromPackage, useCommentsSettings } from './settings.js';
import { stableKey } from './stable.js';
// Runs before the browser paints, and quietly does nothing during a server render.
const useBrowserLayoutEffect = typeof document === 'undefined' ? useEffect : useLayoutEffect;
/**
 * A Comments thread. On the server it renders only the mount point (the reserved space, the
 * heading and the count), so hydration has nothing to disagree about; in the browser the renderer
 * draws the thread inside it. Changing `ownerId` or `ownerUri` starts a new thread, and giving the
 * component the owner as its `key` makes that explicit.
 */
export function CommentsThread(props) {
    const settings = useCommentsSettings();
    const generated = useId();
    const siteId = props.siteId ?? settings.siteId ?? null;
    const endpoint = props.endpoint ?? settings.endpoint;
    const id = props.id ?? threadId(props.ownerId, siteId, generated);
    const theme = props.theme ?? settings.theme ?? 'light';
    const darkSelector = props.darkSelector ?? settings.darkSelector;
    const labels = settings.labels || props.labels ? { ...settings.labels, ...props.labels } : undefined;
    const count = Math.max(0, Math.floor(Number(props.count) || 0));
    // A thread still waiting to load when its sort changes starts again, sorted, as
    // `<verbb-comments>` does.
    const [restarts, setRestarts] = useState(0);
    // What a thread is, its page size and how the person signs in. A change to any of these starts
    // a new thread; a new sign-in provider, fetch or nonce of the same kind is taken over instead.
    const identity = stableKey({ ownerId: props.ownerId, ownerUri: props.ownerUri, siteId, endpoint, limit: props.limit, id, profile: settings.auth?.profile ?? null, restarts });
    // The load mode is read once, when each thread starts.
    const loadMode = useMemo(() => props.loadMode ?? 'visible', [identity]);
    // The mount point's markup is fixed for each thread, so React never writes over a drawn thread.
    const markup = useMemo(() => mountPointHtml({ id, count, labels }), [identity]);
    const [registry] = useState(createHostRegistry);
    const parts = { ...settings.parts, ...props.parts };
    const fields = mergeFields(settings.fields, props.fields);
    const appearanceKey = [stableKey([settings.themeConfig, props.themeConfig, labels]), overridesKey(parts, fields, registry), registry.idOf(settings.loadEditor)].join('|');
    const appearance = useMemo(() => ({
        key: appearanceKey,
        value: {
            // Empty rather than left out, so taking a setting away takes it off the thread too.
            themeConfig: settings.themeConfig || props.themeConfig ? mergeThemeConfig([settings.themeConfig, props.themeConfig]) : {},
            labels: labels ?? {},
            loadEditor: settings.loadEditor ?? loadEditorFromPackage,
            ...rendererOverrides(parts, fields),
        },
    }), [appearanceKey]);
    // What the effects read, kept from the last render React committed (never one it threw away,
    // such as a transition that suspended), so the thread starts with what's on the page.
    const latest = useRef(null);
    const following = useRef(null);
    useBrowserLayoutEffect(() => {
        latest.current = {
            start: { endpoint, ownerId: props.ownerId, ownerUri: props.ownerUri, siteId, sort: props.sort, limit: props.limit, loadMode, theme },
            appearance,
            handlers: props,
            auth: settings.auth,
            fetch: settings.fetch,
            nonce: settings.nonce,
            theme,
            darkSelector,
        };
        // A new provider is followed at once, so its reset() on sign-out reaches the thread.
        following.current?.sync();
    });
    const root = useRef(null);
    // The renderer and the element, from which the thread's controller is found each time, so a
    // thread the renderer starts again (after a morphing page swap) is the one that's changed.
    const thread = useRef(null);
    const controller = () => (thread.current ? thread.current.renderer.getThread(thread.current.element) : undefined);
    useEffect(() => {
        const element = root.current;
        if (!element)
            return;
        let disposed = false;
        const read = () => latest.current;
        // Listening from before the thread starts, so `onMounted` hears it.
        const listeners = EVENT_PROPS.map(([name, prop]) => {
            const listener = (event) => read().handlers[prop]?.(event);
            element.addEventListener(name, listener);
            return () => element.removeEventListener(name, listener);
        });
        import('@verbb/comments-browser/renderer').then((renderer) => {
            if (disposed)
                return;
            const { start, appearance: current, auth } = read();
            following.current = auth ? latestAuth(() => read().auth ?? auth) : null;
            thread.current = { renderer, element };
            drawn.current = current.key;
            renderer.mountThread(element, {
                ...start,
                ...current.value,
                auth: following.current ?? undefined,
                fetch: latestFetch(() => read().fetch),
                get nonce() {
                    return read().nonce;
                },
                onHosts: (hosts) => {
                    if (!disposed)
                        registry.set(hosts);
                },
            });
            // A site's dark-mode toggle decides from the start, as it does for the plugin's threads.
            followDarkSelector(read().darkSelector, (dark) => applyDark(dark))();
        }).catch((error) => {
            if (!disposed)
                console.error('Comments: the renderer couldn\'t load.', error);
        });
        return () => {
            // Stopped before listening ends, so `onDestroyed` hears it, and before the portals go.
            // Only a thread this component started: one another copy of Comments draws is its to stop.
            if (thread.current?.element === element && thread.current.renderer.getThread(element))
                thread.current.renderer.destroyThreads(element);
            disposed = true;
            if (thread.current?.element === element)
                thread.current = null;
            registry.clear();
            listeners.forEach((stop) => stop());
        };
    }, [identity, registry]);
    const applyDark = (dark) => {
        if (latest.current?.theme !== 'none')
            controller()?.setTheme(dark ? 'dark' : 'light');
    };
    useEffect(() => followDarkSelector(darkSelector, applyDark), [darkSelector]);
    // A new theme switches in place, falling back to the provider's or light when the prop goes,
    // and new controls, parts, fields or labels redraw in place.
    const shownTheme = useRef(theme);
    useBrowserLayoutEffect(() => {
        if (shownTheme.current === theme)
            return;
        shownTheme.current = theme;
        controller()?.setTheme(theme);
    }, [theme]);
    const drawn = useRef(null);
    useBrowserLayoutEffect(() => {
        const current = controller();
        if (!current || drawn.current === appearanceKey)
            return;
        drawn.current = appearanceKey;
        current.setOptions(appearance.value);
    }, [appearanceKey]);
    // A new sort re-sorts a loaded thread in place, keeping a half-written comment, the reader's
    // place and the components drawn in it. A thread still waiting to load starts again, sorted, and
    // so does one whose sort is taken away, to go back to the site's default. A new thread, for a new
    // owner or limit, starts with the sort it's given.
    const sorted = useRef({ identity, sort: props.sort });
    useBrowserLayoutEffect(() => {
        const previous = sorted.current;
        sorted.current = { identity, sort: props.sort };
        if (previous.identity !== identity || previous.sort === props.sort)
            return;
        const current = controller();
        if (current && props.sort && root.current?.dataset.commentsPhase === 'ready') {
            current.refresh({ sort: props.sort });
        }
        else {
            setRestarts((count) => count + 1);
        }
    }, [identity, props.sort]);
    return createElement(Fragment, null, createElement('section', {
        key: identity,
        ref: root,
        id,
        className: ['comments', `comments--reserve-${reserveBucket(count)}`, props.className].filter(Boolean).join(' '),
        'aria-labelledby': `${id}-heading`,
        'data-comments-root': '',
        'data-comments-api-version': '1',
        // The plugin's own script, if the page has it too, leaves this thread to the component.
        'data-comments-auto-mount': 'false',
        [ADAPTER_ATTRIBUTE]: 'react',
        'data-comments-endpoint': endpoint,
        'data-comments-owner-id': props.ownerId,
        'data-comments-site-id': siteId ?? undefined,
        'data-comments-load-mode': loadMode,
        'data-comments-theme': theme,
        'data-comments-count': count,
        'data-comments-labels': waitingLabels(labels),
        dangerouslySetInnerHTML: { __html: markup },
    }), registry.useHosts());
}
function mergeFields(...sources) {
    const fields = {};
    for (const source of sources) {
        for (const [handle, override] of Object.entries(source ?? {})) {
            fields[handle] = { ...fields[handle], ...override };
        }
    }
    return fields;
}
