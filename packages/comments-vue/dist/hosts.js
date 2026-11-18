import { camelize, h, onErrorCaptured, watch, shallowRef, Teleport, defineComponent } from 'vue';
import { isComponent, isTemplatePart, templateOf } from './overrides.js';
const NO_HOSTS = Object.freeze([]);
export function createHostRegistry(globalMixins = []) {
    const hosts = shallowRef(NO_HOSTS);
    // Bumped when an async component has loaded, so its props can be told apart.
    const loaded = shallowRef(0);
    const ids = new WeakMap();
    let next = 0;
    return {
        hosts,
        set(list) {
            hosts.value = list.length ? list : NO_HOSTS;
        },
        clear() {
            hosts.value = NO_HOSTS;
        },
        idOf(value) {
            if (!value || (typeof value !== 'object' && typeof value !== 'function'))
                return String(value);
            let id = ids.get(value);
            if (!id)
                ids.set(value, id = `#${++next}`);
            return id;
        },
        render() {
            void loaded.value;
            return hosts.value.map((host) => {
                const props = propsFor(host.component, host.context, globalMixins);
                const failed = failedLoads.get(host.component);
                if (props === PENDING && !failedLoads.has(host.component)) {
                    void host.component.__asyncLoader?.().then(() => { loaded.value++; }, (error) => {
                        // Rethrown inside the host's boundary below, which draws Comments' own markup and
                        // passes the error on to the app's handler, as a component's own error would.
                        failedLoads.set(host.component, error ?? new Error('Comments: an async override failed to load.'));
                        loaded.value++;
                    });
                }
                return h(Teleport, { to: host.element, key: host.key }, [
                    h(OverrideBoundary, { host }, () => (failed !== undefined ? [h(LoadFailure, { error: failed })] : props === PENDING ? [] : [h(host.component, props)])),
                ]);
            });
        },
    };
}
const PENDING = Symbol('pending');
// Async components whose loader failed, with the error, so they're drawn as Comments' default.
const failedLoads = new WeakMap();
/** Throws an async override's loading error where the host's boundary catches it. */
const LoadFailure = defineComponent({
    name: 'CommentsOverrideLoadFailure',
    props: { error: { type: null, required: true } },
    setup(props) {
        return () => {
            throw props.error;
        };
    },
});
const warned = new WeakSet();
/**
 * The context as props: only those the component declares, with its `extends`, its mixins and the
 * app's global mixins, so the rest of the context never falls through onto its element as
 * attributes. A functional component without declared props gets everything, since Vue passes
 * only classes, styles and listeners through from those; an async component waits until it has
 * loaded, so its own props are known.
 */
function propsFor(component, context, globalMixins) {
    const async = component;
    if (component && typeof component === 'object' && typeof async.__asyncLoader === 'function') {
        return async.__asyncResolved ? propsFor(async.__asyncResolved, context, globalMixins) : PENDING;
    }
    if (typeof component === 'function' && !component.props)
        return context;
    const names = new Set();
    const collect = (options, seen) => {
        if (!options || (typeof options !== 'object' && typeof options !== 'function') || seen.has(options))
            return;
        seen.add(options);
        const { props, extends: base, mixins } = options;
        (Array.isArray(props) ? props : Object.keys(props ?? {})).forEach((name) => names.add(camelize(name)));
        collect(base, seen);
        (mixins ?? []).forEach((mixin) => collect(mixin, seen));
    };
    const seen = new Set();
    collect(component, seen);
    globalMixins.forEach((mixin) => collect(mixin, seen));
    if (!names.size && component && typeof component === 'object' && !warned.has(component)) {
        warned.add(component);
        console.warn('Comments: an override component declares no props, so it gets none. Declare the ones it uses, such as defineProps([\'comment\']).');
    }
    return Object.fromEntries([...names].filter((name) => name in context).map((name) => [name, context[name]]));
}
/**
 * Keeps an override that throws to its own part: the error goes on to the app's error handler and
 * Comments' own markup is drawn in its place.
 */
const OverrideBoundary = defineComponent({
    name: 'CommentsOverrideBoundary',
    props: { host: { type: Object, required: true } },
    setup(props, { slots }) {
        const failed = shallowRef(false);
        const drawDefault = () => {
            try {
                props.host.context.renderDefaultInto(props.host.element);
            }
            catch (error) {
                console.error('Comments: the default markup couldn\'t be drawn either.', error);
            }
        };
        watch(() => props.host, () => {
            if (failed.value)
                drawDefault();
        });
        onErrorCaptured(() => {
            if (!failed.value)
                queueMicrotask(drawDefault);
            failed.value = true;
            // Passed on, so the app's own error handler still hears about it.
            return true;
        });
        return () => (failed.value ? [] : slots.default?.());
    },
});
/**
 * The thread's overrides as the renderer takes them: a component becomes `{ component }`, which the
 * renderer draws as a host, a `templatePart()` function goes as the function itself, and a custom
 * element's name as it is.
 */
export function rendererOverrides(parts, fields) {
    const convert = (value) => {
        if (typeof value === 'string' || value === undefined || value === null)
            return value ?? undefined;
        if (isTemplatePart(value))
            return templateOf(value);
        if (typeof value === 'object' && 'component' in value && !('setup' in value) && !('render' in value))
            return value;
        if (isComponent(value))
            return { component: value };
        console.warn('Comments: an override must be a Vue component, a templatePart() function or a custom element\'s name.', value);
        return undefined;
    };
    return {
        parts: Object.fromEntries(Object.entries(parts).map(([name, value]) => [name, convert(value)]).filter(([, value]) => value !== undefined)),
        fields: Object.fromEntries(Object.entries(fields).map(([handle, field]) => [handle, {
                ...(field.input !== undefined ? { input: convert(field.input) } : {}),
                ...(field.display !== undefined ? { display: convert(field.display) } : {}),
            }])),
    };
}
/** A value that names the overrides' identities, so a different component or function counts as a change. */
export function overridesKey(parts, fields, registry) {
    const id = (value) => (typeof value === 'string' ? JSON.stringify(value) : registry.idOf(isTemplatePart(value) ? templateOf(value) : value));
    return [
        ...Object.keys(parts).sort().map((name) => `${name}=${id(parts[name])}`),
        ...Object.keys(fields).sort().map((handle) => `${handle}=${id(fields[handle]?.input)}/${id(fields[handle]?.display)}`),
    ].join(';');
}
