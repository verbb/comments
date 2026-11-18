import { Component, createElement, useSyncExternalStore } from 'react';
import { createPortal } from 'react-dom';
import { isComponent, isTemplatePart, templateOf } from './overrides.js';
const NO_HOSTS = Object.freeze([]);
export function createHostRegistry() {
    let hosts = NO_HOSTS;
    const listeners = new Set();
    const ids = new WeakMap();
    let next = 0;
    const subscribe = (listener) => {
        listeners.add(listener);
        return () => listeners.delete(listener);
    };
    const snapshot = () => hosts;
    const serverSnapshot = () => NO_HOSTS;
    return {
        set(list) {
            if (list === hosts)
                return;
            hosts = list.length ? list : NO_HOSTS;
            listeners.forEach((listener) => listener());
        },
        clear() {
            this.set(NO_HOSTS);
        },
        idOf(value) {
            if (!value || (typeof value !== 'object' && typeof value !== 'function'))
                return String(value);
            let id = ids.get(value);
            if (!id)
                ids.set(value, id = `#${++next}`);
            return id;
        },
        useHosts() {
            const current = useSyncExternalStore(subscribe, snapshot, serverSnapshot);
            return current.map((host) => createPortal(createElement(OverrideBoundary, { host }, createElement(host.component, host.context)), host.element, host.key));
        },
    };
}
/**
 * Keeps an override that throws to its own part: the error is logged and Comments' own markup is
 * drawn in its place, rather than the error unmounting the app around the thread. A portal's
 * errors otherwise reach the nearest boundary above `<CommentsThread>`, or the root.
 */
class OverrideBoundary extends Component {
    constructor() {
        super(...arguments);
        this.state = { failed: false };
    }
    static getDerivedStateFromError() {
        return { failed: true };
    }
    componentDidCatch(error, info) {
        console.error(`Comments: the "${this.props.host.name}" override threw, so Comments' own markup is drawn instead.`, error, info.componentStack);
        this.drawDefault();
    }
    componentDidUpdate(previous) {
        if (this.state.failed && previous.host !== this.props.host)
            this.drawDefault();
    }
    render() {
        return this.state.failed ? null : this.props.children;
    }
    drawDefault() {
        try {
            this.props.host.context.renderDefaultInto(this.props.host.element);
        }
        catch (error) {
            console.error('Comments: the default markup couldn\'t be drawn either.', error);
        }
    }
}
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
        if (typeof value === 'object' && 'component' in value)
            return value;
        if (isComponent(value))
            return { component: value };
        console.warn('Comments: an override must be a React component, a templatePart() function or a custom element\'s name.', value);
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
