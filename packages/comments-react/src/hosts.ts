import type { ComponentHost, MountOptions } from '@verbb/comments-browser';
import { Component, createElement, useSyncExternalStore, type ComponentType, type ErrorInfo, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { isComponent, isTemplatePart, templateOf, type CommentsFieldOverrides, type CommentsPartOverrides } from './overrides.js';

/**
 * The thread's component hosts, kept for React. The renderer draws an empty host element where a
 * component override goes and reports every host after each draw; each one becomes a portal, so the
 * component stays in the app's tree with its context, state and router, and follows its host when
 * the renderer redraws or removes it.
 */
export interface HostRegistry {
    /** Takes the renderer's latest list, as `onHosts` gives it. */
    set(hosts: readonly ComponentHost[]): void;
    clear(): void;
    /** A short ID for an override's identity, so swapping one component for another counts as a change. */
    idOf(value: unknown): string;
    /** The portals, one per host. */
    useHosts(): ReactNode;
}

const NO_HOSTS: readonly ComponentHost[] = Object.freeze([]);

export function createHostRegistry(): HostRegistry {
    let hosts = NO_HOSTS;
    const listeners = new Set<() => void>();
    const ids = new WeakMap<object, string>();
    let next = 0;
    const subscribe = (listener: () => void) => {
        listeners.add(listener);
        return () => listeners.delete(listener);
    };
    const snapshot = () => hosts;
    const serverSnapshot = () => NO_HOSTS;

    return {
        set(list) {
            if (list === hosts) return;

            hosts = list.length ? list : NO_HOSTS;
            listeners.forEach((listener) => listener());
        },
        clear() {
            this.set(NO_HOSTS);
        },
        idOf(value) {
            if (!value || (typeof value !== 'object' && typeof value !== 'function')) return String(value);

            let id = ids.get(value);
            if (!id) ids.set(value, id = `#${++next}`);

            return id;
        },
        useHosts() {
            const current = useSyncExternalStore(subscribe, snapshot, serverSnapshot);

            return current.map((host) => createPortal(
                createElement(OverrideBoundary, { host }, createElement(host.component as ComponentType<Record<string, unknown>>, host.context as Record<string, unknown>)),
                host.element,
                host.key,
            ));
        },
    };
}

/**
 * Keeps an override that throws to its own part: the error is logged and Comments' own markup is
 * drawn in its place, rather than the error unmounting the app around the thread. A portal's
 * errors otherwise reach the nearest boundary above `<CommentsThread>`, or the root.
 */
class OverrideBoundary extends Component<{ host: ComponentHost; children?: ReactNode }, { failed: boolean }> {
    override state = { failed: false };

    static getDerivedStateFromError(): { failed: boolean } {
        return { failed: true };
    }

    override componentDidCatch(error: unknown, info: ErrorInfo): void {
        console.error(`Comments: the "${this.props.host.name}" override threw, so Comments' own markup is drawn instead.`, error, info.componentStack);
        this.drawDefault();
    }

    override componentDidUpdate(previous: { host: ComponentHost }): void {
        if (this.state.failed && previous.host !== this.props.host) this.drawDefault();
    }

    override render(): ReactNode {
        return this.state.failed ? null : this.props.children;
    }

    private drawDefault(): void {
        try {
            this.props.host.context.renderDefaultInto(this.props.host.element);
        } catch (error) {
            console.error('Comments: the default markup couldn\'t be drawn either.', error);
        }
    }
}

type RendererOverrides = Pick<MountOptions, 'parts' | 'fields'>;

/**
 * The thread's overrides as the renderer takes them: a component becomes `{ component }`, which the
 * renderer draws as a host, a `templatePart()` function goes as the function itself, and a custom
 * element's name as it is.
 */
export function rendererOverrides(parts: CommentsPartOverrides, fields: CommentsFieldOverrides): RendererOverrides {
    const convert = (value: unknown): unknown => {
        if (typeof value === 'string' || value === undefined || value === null) return value ?? undefined;
        if (isTemplatePart(value)) return templateOf(value);
        if (typeof value === 'object' && 'component' in (value as object)) return value;
        if (isComponent(value)) return { component: value };

        console.warn('Comments: an override must be a React component, a templatePart() function or a custom element\'s name.', value);
        return undefined;
    };

    return {
        parts: Object.fromEntries(Object.entries(parts).map(([name, value]) => [name, convert(value)]).filter(([, value]) => value !== undefined)) as RendererOverrides['parts'],
        fields: Object.fromEntries(Object.entries(fields).map(([handle, field]) => [handle, {
            ...(field.input !== undefined ? { input: convert(field.input) } : {}),
            ...(field.display !== undefined ? { display: convert(field.display) } : {}),
        }])) as RendererOverrides['fields'],
    };
}

/** A value that names the overrides' identities, so a different component or function counts as a change. */
export function overridesKey(parts: CommentsPartOverrides, fields: CommentsFieldOverrides, registry: HostRegistry): string {
    const id = (value: unknown) => (typeof value === 'string' ? JSON.stringify(value) : registry.idOf(isTemplatePart(value) ? templateOf(value) : value));

    return [
        ...Object.keys(parts).sort().map((name) => `${name}=${id(parts[name as keyof CommentsPartOverrides])}`),
        ...Object.keys(fields).sort().map((handle) => `${handle}=${id(fields[handle]?.input)}/${id(fields[handle]?.display)}`),
    ].join(';');
}
